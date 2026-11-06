<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

final class SyncDisposableDomainsCommand extends Command
{
    protected $signature = 'deliverability:sync-disposable-domains {--force : Force refresh even if recently synced}';

    protected $description = 'Sync the latest continuous disposable and throwaway domain lists from community feeds';

    private const FEED_URL = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

    public function handle(): int
    {
        $this->info('Fetching latest disposable email domain blocklist...');

        try {
            $response = Http::timeout(15)->get(self::FEED_URL);
            if (! $response->successful()) {
                $this->error('Failed to download feed: HTTP '.$response->status());

                return self::FAILURE;
            }

            $lines = explode("\n", $response->body());
            $domains = [];
            foreach ($lines as $line) {
                $domain = strtolower(trim($line));
                if ($domain !== '' && ! str_starts_with($domain, '#')) {
                    $domains[] = ['domain' => substr($domain, 0, 190)];
                }
            }

            $count = count($domains);
            $this->info("Parsed {$count} disposable domains. Updating database...");

            DB::transaction(function () use ($domains) {
                // Bulk insert in chunks of 1000
                foreach (array_chunk($domains, 1000) as $chunk) {
                    DB::table('disposable_domains')->upsert($chunk, ['domain']);
                }
            });

            // Bust cached disposable set
            Cache::forget('disposable_domains_set');

            $this->info("Successfully synced {$count} disposable domains!");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Error syncing disposable domains: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
