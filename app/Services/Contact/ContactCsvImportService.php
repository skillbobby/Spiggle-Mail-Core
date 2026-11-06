<?php

namespace App\Services\Contact;

use App\Models\Contact;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Services\Deliverability\EmailHygieneService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactCsvImportService
{
    private const COLUMN_ALIASES = [
        'email' => ['email', 'emailaddress', 'subscriberemail', 'subscriber'],
        'status' => ['status', 'subscriptionstatus', 'subscriberstatus', 'subscribertype'],
        'subscribed_at' => [
            'createdat', 'created', 'subscribedat', 'subscribedate', 'subscriptiondate',
            'datesubscribed', 'signupdate', 'startdate', 'creationdate', 'optintime', 'confirmtime',
        ],
        'unsubscribed_at' => ['unsubscribedat', 'unsubtime', 'unsubscriptiondate', 'canceledat', 'cancelledat'],
        'cleaned_at' => ['cleantime'],
    ];

    private const STATUS_ALIASES = [
        'active' => 'subscribed',
        'subscribed' => 'subscribed',
        'confirmed' => 'subscribed',
        'cold' => 'subscribed',
        'regular' => 'subscribed',
        'premium' => 'subscribed',
        'gifted' => 'subscribed',
        'trialed' => 'subscribed',
        'inactive' => 'unsubscribed',
        'unsubscribed' => 'unsubscribed',
        'cancelled' => 'unsubscribed',
        'canceled' => 'unsubscribed',
        'removed' => 'unsubscribed',
        'blocked' => 'unsubscribed',
        'bounced' => 'bounced',
        'invalid' => 'bounced',
        'hardbounce' => 'bounced',
        'cleaned' => 'bounced',
        'undeliverable' => 'bounced',
        'complained' => 'complained',
        'spam' => 'complained',
        'spamreport' => 'complained',
        'junk' => 'complained',
        'pending' => 'pending',
        'unconfirmed' => 'pending',
        'unactivated' => 'pending',
        'validating' => 'pending',
        'dormant' => 'dormant',
    ];

    public function import(Newsletter $newsletter, UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Could not open CSV file.']];
        }

        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Empty CSV file.']];
        }

        // Map column indices
        $mappedIndices = $this->resolveColumnMap($headers);
        if (!isset($mappedIndices['email'])) {
            fclose($handle);
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['No email column detected in CSV header.']];
        }

        $imported = 0;
        $skipped = 0;
        $batch = [];
        $batchSize = 250;

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            $rawEmail = $row[$mappedIndices['email']] ?? null;
            $email = strtolower(trim((string) $rawEmail));
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            // Syntax and Disposable domain check
            $hygieneReason = EmailHygieneService::checkEmail($email);
            if ($hygieneReason !== null) {
                $skipped++;
                continue;
            }

            // Check suppression
            $emailHash = hash_hmac('sha256', $email, config('app.key'));
            if (Suppression::where('email_hash', $emailHash)->exists()) {
                $skipped++;
                continue;
            }

            // Determine status
            $status = 'subscribed';
            if (isset($mappedIndices['status']) && !empty($row[$mappedIndices['status']])) {
                $rawStatus = strtolower(trim(preg_replace('/[^a-z0-9]/', '', $row[$mappedIndices['status']])));
                $status = self::STATUS_ALIASES[$rawStatus] ?? 'subscribed';
            } elseif (isset($mappedIndices['unsubscribed_at']) && !empty($row[$mappedIndices['unsubscribed_at']])) {
                $status = 'unsubscribed';
            } elseif (isset($mappedIndices['cleaned_at']) && !empty($row[$mappedIndices['cleaned_at']])) {
                $status = 'bounced';
            }

            // Subscribed date
            $subscribedAt = now();
            if (isset($mappedIndices['subscribed_at']) && !empty($row[$mappedIndices['subscribed_at']])) {
                try {
                    $parsed = strtotime($row[$mappedIndices['subscribed_at']]);
                    if ($parsed) {
                        $subscribedAt = date('Y-m-d H:i:s', $parsed);
                    }
                } catch (\Throwable) {
                    $subscribedAt = now();
                }
            }

            $batch[$email] = [
                'newsletter_id' => $newsletter->id,
                'email' => $email,
                'status' => $status,
                'source' => 'csv_import',
                'subscribed_at' => $subscribedAt,
            ];

            if (count($batch) >= $batchSize) {
                $imported += $this->flushBatch($newsletter, $batch);
                $batch = [];
            }
        }

        fclose($handle);

        if (!empty($batch)) {
            $imported += $this->flushBatch($newsletter, $batch);
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => [],
        ];
    }

    private function flushBatch(Newsletter $newsletter, array $batch): int
    {
        $emails = array_keys($batch);
        $existing = Contact::where('newsletter_id', $newsletter->id)
            ->whereIn('email', $emails)
            ->pluck('email')
            ->toArray();

        $existingLookup = array_flip($existing);
        $toInsert = [];
        $insertedCount = 0;

        foreach ($batch as $email => $data) {
            if (isset($existingLookup[$email])) {
                // Already on list, skip or ignore to avoid duplicating
                continue;
            }

            $toInsert[] = [
                'id' => (string) Str::ulid(),
                'newsletter_id' => $data['newsletter_id'],
                'email' => $data['email'],
                'status' => $data['status'],
                'source' => $data['source'],
                'subscribed_at' => $data['subscribed_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $insertedCount++;
        }

        if (!empty($toInsert)) {
            Contact::insert($toInsert);
        }

        return $insertedCount;
    }

    private function resolveColumnMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $header));
            foreach (self::COLUMN_ALIASES as $key => $aliases) {
                if (!isset($map[$key]) && in_array($normalized, $aliases, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }
        return $map;
    }
}
