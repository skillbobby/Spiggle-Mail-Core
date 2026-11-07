<?php

namespace App\Console\Commands;

use App\Jobs\SendLastChanceEmailJob;
use App\Models\Contact;
use App\Models\Newsletter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupInactiveContactsCommand extends Command
{
    protected $signature = 'mail:cleanup-inactive';

    protected $description = 'Identifies dormant subscribers, sends re-engagement last-chance emails, and transitions unresponsive contacts to dormant';

    public function handle(): int
    {
        $this->info('Starting inactive contact cleanup cycle...');
        $newsletters = Newsletter::where('list_cleanup_enabled', true)->get();

        $warnedCount = 0;
        $dormantedCount = 0;

        foreach ($newsletters as $newsletter) {
            $minIssues = max(1, (int) ($newsletter->cleanup_inactive_issues ?: 6));
            $minDays = max(1, (int) ($newsletter->cleanup_inactive_days ?: 180));
            $cutoffDate = now()->subDays($minDays);

            // 1. Identify contacts needing last-chance warning
            $candidates = Contact::where('newsletter_id', $newsletter->id)
                ->where('status', 'subscribed')
                ->whereNull('last_chance_at')
                ->where('ignored_issues_count', '>=', $minIssues)
                ->where(function ($query) use ($cutoffDate) {
                    $query->where('last_engaged_at', '<=', $cutoffDate)
                        ->orWhere(function ($q) use ($cutoffDate) {
                            $q->whereNull('last_engaged_at')
                                ->where('subscribed_at', '<=', $cutoffDate);
                        });
                })
                ->get();

            foreach ($candidates as $contact) {
                SendLastChanceEmailJob::dispatch($contact->id);
                $warnedCount++;
            }

            // 2. Transition contacts who haven't clicked keep after 14 days
            $dormantCutoff = now()->subDays(14);
            $expired = Contact::where('newsletter_id', $newsletter->id)
                ->where('status', 'subscribed')
                ->whereNotNull('last_chance_at')
                ->where('last_chance_at', '<=', $dormantCutoff)
                ->update(['status' => 'dormant']);

            $dormantedCount += $expired;
        }

        $this->info("Cleanup completed: {$warnedCount} warning emails queued, {$dormantedCount} contacts marked dormant.");
        Log::info("Inactive contacts cleanup: warned {$warnedCount}, dormanted {$dormantedCount}");

        return self::SUCCESS;
    }
}
