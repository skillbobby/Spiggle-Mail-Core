<?php

namespace App\Console\Commands;

use App\Jobs\SendCampaignBatchJob;
use App\Models\Campaign;
use Illuminate\Console\Command;

class SendScheduledCampaignsCommand extends Command
{
    protected $signature = 'campaigns:send-scheduled';

    protected $description = 'Dispatches scheduled campaigns and releases held warm-up/pilot slices whose evaluation window has arrived';

    public function handle(): int
    {
        $dueCampaigns = Campaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        $resumableSlices = Campaign::where('status', 'sending')
            ->whereNotNull('first_send_check_at')
            ->whereNull('first_send_checked_at')
            ->where('first_send_check_at', '<=', now())
            ->get();

        if ($dueCampaigns->isEmpty() && $resumableSlices->isEmpty()) {
            $this->info('No scheduled campaigns or held pilot slices due for dispatch.');

            return self::SUCCESS;
        }

        foreach ($dueCampaigns as $campaign) {
            $this->info("Dispatching scheduled campaign: {$campaign->id} ({$campaign->subject})");

            $campaign->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            SendCampaignBatchJob::dispatch($campaign->id, 50);
        }

        foreach ($resumableSlices as $campaign) {
            $this->info("Releasing held pilot slice for campaign: {$campaign->id} ({$campaign->subject})");
            SendCampaignBatchJob::dispatch($campaign->id, 50);
        }

        return self::SUCCESS;
    }
}
