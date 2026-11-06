<?php

namespace Tests\Feature;

use App\Jobs\SendCampaignBatchJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_batch_dispatches_with_rfc8058_headers_and_updates_audit_records(): void
    {
        // 1. Seed user and account
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
            'name' => 'Dev Publication Group',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = (string) Str::ulid();
        DB::table('newsletters')->insert([
            'id' => $newsletterId,
            'account_id' => $accountId,
            'name' => 'The Engineering Dispatch',
            'slug' => 'engineering-dispatch',
            'from_name' => 'Alex',
            'from_email' => 'alex@spiggle-mail.local',
            'postal_address' => '100 Main St, Austin TX',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Seed contacts
        $contact1Id = (string) Str::ulid();
        $contact2Id = (string) Str::ulid();

        DB::table('contacts')->insert([
            [
                'id' => $contact1Id,
                'newsletter_id' => $newsletterId,
                'email' => 'subscriber1@example.com',
                'status' => 'subscribed',
                'subscribed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $contact2Id,
                'newsletter_id' => $newsletterId,
                'email' => 'subscriber2@example.com',
                'status' => 'subscribed',
                'subscribed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // 3. Create campaign
        $campaignId = (string) Str::ulid();
        DB::table('campaigns')->insert([
            'id' => $campaignId,
            'newsletter_id' => $newsletterId,
            'subject' => 'Weekly Digest for {{ contact.name }}',
            'html' => '<h1>Hello {{ contact.name }}</h1><p>Read our issue!</p><a href="{{ unsubscribe_url }}">Unsubscribe</a>',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Dispatch job
        $job = new SendCampaignBatchJob($campaignId, 50);
        app()->call([$job, 'handle']);

        // 5. Assertions
        $campaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals('sent', $campaign->status);
        $this->assertEquals(2, $campaign->sent_count);

        $messages = DB::table('messages')->where('campaign_id', $campaignId)->get();
        $this->assertCount(2, $messages);
        foreach ($messages as $msg) {
            $this->assertEquals('sent', $msg->status);
            $this->assertStringStartsWith('mock-ses-', $msg->ses_message_id);
        }
    }
}
