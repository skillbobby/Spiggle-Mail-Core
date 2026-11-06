<?php

namespace App\Jobs;

use App\Services\Deliverability\EmailHygieneService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImportContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $importId;

    public function __construct(string $importId)
    {
        $this->importId = $importId;
    }

    public function handle(): void
    {
        $import = DB::table('contact_imports')->where('id', $this->importId)->first();
        if (!$import) {
            Log::error("Contact import record {$this->importId} not found");
            return;
        }

        DB::table('contact_imports')->where('id', $this->importId)->update([
            'status' => 'processing',
        ]);

        $filePath = storage_path('app/imports/' . $import->filename);
        if (!file_exists($filePath)) {
            DB::table('contact_imports')->where('id', $this->importId)->update([
                'status' => 'failed',
            ]);
            Log::error("Import file {$filePath} does not exist");
            return;
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            DB::table('contact_imports')->where('id', $this->importId)->update([
                'status' => 'failed',
            ]);
            return;
        }

        $header = fgetcsv($handle);
        $emailColIndex = 0;
        if ($header) {
            foreach ($header as $idx => $col) {
                if (stripos($col, 'email') !== false) {
                    $emailColIndex = $idx;
                    break;
                }
            }
        }

        $importedCount = 0;
        $droppedCount = 0;
        $droppedReasons = [];
        $totalRows = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $totalRows++;
            $rawEmail = trim($row[$emailColIndex] ?? '');
            if (empty($rawEmail)) {
                $droppedCount++;
                continue;
            }

            // Hygiene check
            $dropReason = EmailHygieneService::checkEmail($rawEmail);
            if ($dropReason !== null) {
                $droppedCount++;
                $droppedReasons[$dropReason] = ($droppedReasons[$dropReason] ?? 0) + 1;
                continue;
            }

            $cleanEmail = strtolower($rawEmail);

            // Global/account suppression list check
            $emailHash = hash_hmac('sha256', $cleanEmail, (string) config('app.key'));
            if (DB::table('suppressions')->where('email_hash', $emailHash)->exists()) {
                $droppedCount++;
                $droppedReasons['suppressed'] = ($droppedReasons['suppressed'] ?? 0) + 1;
                continue;
            }

            // Check if already in contacts for this newsletter
            $exists = DB::table('contacts')
                ->where('newsletter_id', $import->newsletter_id)
                ->where('email', $cleanEmail)
                ->exists();

            if ($exists) {
                $droppedCount++;
                $droppedReasons['duplicate'] = ($droppedReasons['duplicate'] ?? 0) + 1;
                continue;
            }

            // Insert clean subscriber
            DB::table('contacts')->insert([
                'id' => (string) Str::ulid(),
                'newsletter_id' => $import->newsletter_id,
                'email' => $cleanEmail,
                'status' => 'subscribed',
                'source' => 'csv_import',
                'subscribed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $importedCount++;
        }

        fclose($handle);

        DB::table('contact_imports')->where('id', $this->importId)->update([
            'status' => 'completed',
            'total_rows' => $totalRows,
            'imported_count' => $importedCount,
            'dropped_count' => $droppedCount,
            'dropped_reasons' => json_encode($droppedReasons),
        ]);

        Log::info("Completed import {$this->importId}: {$importedCount} imported, {$droppedCount} dropped.");
    }
}
