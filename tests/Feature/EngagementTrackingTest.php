<?php

namespace Tests\Feature;

use App\Services\Deliverability\TrackingTokenService;
use App\Services\Deliverability\UnsubscribeHeaderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngagementTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_tracking_pixel_increments_open_count_and_returns_gif(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'demouser@spiggle-mail.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Test Account',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = (string) Str::ulid();
        DB::table('newsletters')->insert([
            'id' => $newsletterId,
            'account_id' => $accountId,
            'name' => 'Weekly Reader',
            'slug' => 'weekly-reader',
            'from_name' => 'Reader Team',
            'from_email' => 'team@spiggle-mail.local',
            'postal_address' => '123 Fake St',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contactId = (string) Str::ulid();
        DB::table('contacts')->insert([
            'id' => $contactId,
            'newsletter_id' => $newsletterId,
            'email' => 'reader@example.com',
            'status' => 'subscribed',
            'open_count' => 0,
            'subscribed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignId = (string) Str::ulid();
        DB::table('campaigns')->insert([
            'id' => $campaignId,
            'newsletter_id' => $newsletterId,
            'subject' => 'Issue 1',
            'open_count' => 0,
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messageId = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $messageId,
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Generate open token
        $token = TrackingTokenService::createOpenToken($messageId);

        // Request open pixel
        $response = $this->get("/o/{$token}");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');

        // Check updated counts
        $updatedCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(1, $updatedCampaign->open_count);

        $updatedContact = DB::table('contacts')->where('id', $contactId)->first();
        $this->assertEquals(1, $updatedContact->open_count);
    }

    public function test_click_tracking_redirects_and_increments_click_count(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'demouser2@spiggle-mail.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Test Account 2',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = (string) Str::ulid();
        DB::table('newsletters')->insert([
            'id' => $newsletterId,
            'account_id' => $accountId,
            'name' => 'Weekly Reader 2',
            'slug' => 'weekly-reader-2',
            'from_name' => 'Reader Team',
            'from_email' => 'team2@spiggle-mail.local',
            'postal_address' => '123 Fake St',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contactId = (string) Str::ulid();
        DB::table('contacts')->insert([
            'id' => $contactId,
            'newsletter_id' => $newsletterId,
            'email' => 'reader2@example.com',
            'status' => 'subscribed',
            'click_count' => 0,
            'subscribed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $campaignId = (string) Str::ulid();
        DB::table('campaigns')->insert([
            'id' => $campaignId,
            'newsletter_id' => $newsletterId,
            'subject' => 'Issue 2',
            'click_count' => 0,
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $messageId = (string) Str::ulid();
        DB::table('messages')->insert([
            'id' => $messageId,
            'campaign_id' => $campaignId,
            'contact_id' => $contactId,
            'status' => 'sent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $targetUrl = 'https://google.com';
        $token = TrackingTokenService::createClickToken($messageId, $targetUrl);

        $response = $this->get("/c/{$token}");
        $response->assertRedirect($targetUrl);

        $updatedCampaign = DB::table('campaigns')->where('id', $campaignId)->first();
        $this->assertEquals(1, $updatedCampaign->click_count);

        $updatedContact = DB::table('contacts')->where('id', $contactId)->first();
        $this->assertEquals(1, $updatedContact->click_count);
    }
}
