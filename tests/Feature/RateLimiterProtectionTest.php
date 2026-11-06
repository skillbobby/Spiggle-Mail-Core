<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignBatchJob;
use App\Services\Deliverability\RateLimiterAndProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimiterProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluates_safety_triggers_pause_on_high_bounce_rate(): void
    {
        // 5 bounces out of 100 sent = 5.0% bounce rate (> 2% threshold)
        $safety = RateLimiterAndProtectionService::evaluateSafety(100, 5, 0);
        $this->assertEquals('pause', $safety['action']);
        $this->assertEquals('bounce', $safety['metric']);
    }

    public function test_evaluates_safety_triggers_block_on_excessive_complaints(): void
    {
        // 3 complaints out of 500 sent = 0.6% complaint rate (> 0.3% block threshold)
        $safety = RateLimiterAndProtectionService::evaluateSafety(500, 0, 3);
        $this->assertEquals('block', $safety['action']);
        $this->assertEquals('complaint', $safety['metric']);
    }

    public function test_campaign_batch_job_auto_pauses_when_bounce_threshold_exceeded(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Alex Developer',
            'email' => 'alex@spiggle-mail.local',
            'password' => bcrypt('secret123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Dev Group',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = (string) Str::ulid();
        DB::table('newsletters')->insert([
            'id' => $newsletterId,
            'account_id' => $accountId,
            'name' => 'The Dispatch',
            'slug' => 'the-dispatch',
            'from_name' => 'Alex',
            'from_email' => 'alex@spiggle-mail.local',
            'postal_address' => '100 Main St',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignId = (string) Str::ulid();
        // Seed campaign with pre-existing bounce data exceeding limit
        DB::table('campaigns')->insert([
            'id' => $campaignId,
            'newsletter_id' => $newsletterId,
            'subject' => 'Unsafe Issue',
            'html' => '<p>Dangerous</p>',
            'status' => 'sending',
            'sent_count' => 100,
            'bounce_count' => 8, // 8% bounce
            'complaint_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new SendCampaignBatchJob($campaignId, 50);
        app()->call([$job, 'handle']);

        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('paused', $campaign->status);
        $this->assertStringContainsString('Auto-paused', $campaign->pause_reason);
    }
}
