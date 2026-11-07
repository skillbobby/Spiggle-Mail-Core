<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Newsletter;
use App\Models\User;
use App\Services\Alerting\TickitDexWebhookService;
use App\Services\Deliverability\TrackingTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HorizonEnterpriseFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_export_downloads_complete_json_archive(): void
    {
        $user = User::factory()->create(['email' => 'admin@spiggle-mail.local']);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Acme Corporation',
            'status' => 'active',
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'The Acme Wire',
            'slug' => 'acme-wire',
            'from_name' => 'Acme Editor',
            'from_email' => 'editor@acme.com',
            'postal_address' => '456 Market St',
        ]);

        Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Issue 1',
            'format' => 'rich',
            'status' => 'sent',
            'sent_count' => 5,
        ]);

        Contact::create([
            'newsletter_id' => $newsletter->id,
            'email' => 'reader@example.com',
            'status' => 'subscribed',
        ]);

        ActivityLog::record(
            action: 'test.seeded',
            description: 'Seed event for export verification',
            accountId: $account->id,
            userId: $user->id
        );

        $response = $this->actingAs($user)
            ->withSession(['authenticated_user_id' => $user->id])
            ->get('/account/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $json = json_decode($response->streamedContent(), true);
        $this->assertSame('SpiggleMail', $json['app']);
        $this->assertSame('Acme Corporation', $json['account']['name']);
        $this->assertCount(1, $json['newsletters']);
        $this->assertCount(1, $json['campaigns']);
        $this->assertCount(1, $json['contacts']);
        $this->assertNotEmpty($json['activity_logs']);
    }

    public function test_activity_log_persists_and_is_presented_in_account_page(): void
    {
        $user = User::factory()->create(['email' => 'owner@spiggle-mail.local']);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Activity Test Org',
            'status' => 'active',
        ]);

        ActivityLog::record(
            action: 'domain.verified',
            description: 'Domain acme.org SPF and DKIM verified.',
            accountId: $account->id,
            userId: $user->id
        );

        $response = $this->actingAs($user)
            ->withSession(['authenticated_user_id' => $user->id])
            ->get('/account');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Account/Index', false)
            ->has('recentActivity', 1)
            ->where('recentActivity.0.action', 'domain.verified')
            ->where('recentActivity.0.description', 'Domain acme.org SPF and DKIM verified.')
        );
    }

    public function test_tickitdex_webhook_dispatch_and_artisan_command(): void
    {
        config([
            'services.tickitdex.enabled' => true,
            'services.tickitdex.token' => 'mock-tickitdex-token-xyz',
            'services.tickitdex.webhook_url' => 'https://tickitdex.com/support-chat/webhooks/tickets',
        ]);

        Http::fake([
            'https://tickitdex.com/support-chat/webhooks/tickets' => Http::response([
                'success' => true,
                'ticket_id' => '01HX_TICKET_999',
                'message' => 'Ticket created',
            ], 200),
        ]);

        // Test Service Directly
        $service = app(TickitDexWebhookService::class);
        $this->assertTrue($service->isEnabled());
        $this->assertTrue($service->isConfigured());

        $result = $service->createTicket([
            'title' => 'SES Bounce Spike Alarm',
            'message' => 'Recent bounce rate exceeded 5.2% on campaign #101.',
            'priority' => 'critical',
            'tags' => ['circuit-breaker', 'bounce-rate'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('01HX_TICKET_999', $result['ticket_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tickitdex.com/support-chat/webhooks/tickets'
                && $request->hasHeader('Authorization', 'Bearer mock-tickitdex-token-xyz')
                && $request['sending_app'] === 'spiggle-mail'
                && $request['ticket_category'] === 'deliverability'
                && $request['priority'] === 'critical';
        });

        // Test Artisan Command
        $this->artisan('alert:test-tickitdex', [
            '--title' => 'CLI Verification Test',
            '--priority' => 'warning',
        ])->assertExitCode(0);
    }

    public function test_ab_subject_line_split_testing_workflow(): void
    {
        $user = User::factory()->create(['email' => 'ab-test@spiggle-mail.local']);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Split Test Org',
            'status' => 'active',
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Growth Lab',
            'slug' => 'growth-lab',
            'from_name' => 'Lab Lead',
            'from_email' => 'lab@growth.org',
            'postal_address' => '789 Experiment Way',
        ]);

        // 1. Post campaign with A/B test enabled
        $response = $this->actingAs($user)
            ->withSession(['authenticated_user_id' => $user->id])
            ->post('/campaigns', [
                'subject' => 'Variant A: Unlock 10x Velocity',
                'subjectB' => 'Variant B: Double Your Shipping Speed',
                'abTestEnabled' => true,
                'previewText' => 'Testing preview snippet',
                'html' => '<p>Hello world A/B content</p>',
            ]);

        $response->assertRedirect();

        $campaign = Campaign::where('subject', 'Variant A: Unlock 10x Velocity')->firstOrFail();
        $this->assertTrue((bool) $campaign->ab_test_enabled);
        $this->assertSame('Variant B: Double Your Shipping Speed', $campaign->subject_b);

        // 2. Add recipients and message audit records
        $c1 = Contact::create(['newsletter_id' => $newsletter->id, 'email' => 'sub1@test.org', 'status' => 'subscribed']);
        $c2 = Contact::create(['newsletter_id' => $newsletter->id, 'email' => 'sub2@test.org', 'status' => 'subscribed']);

        $m1 = Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $c1->id,
            'status' => 'delivered',
            'ab_variant' => 'a',
            'sent_at' => now(),
        ]);

        $m2 = Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $c2->id,
            'status' => 'delivered',
            'ab_variant' => 'b',
            'sent_at' => now(),
        ]);

        // 3. Open Variant B
        $openTokenB = TrackingTokenService::createOpenToken($m2->id);
        $this->get("/o/{$openTokenB}")->assertOk();

        $fresh = $campaign->fresh();
        $this->assertSame(1, (int) $fresh->open_count);
        $this->assertSame(0, (int) $fresh->variant_a_opens);
        $this->assertSame(1, (int) $fresh->variant_b_opens);

        // 4. Report page reflects A/B metrics
        $reportRes = $this->actingAs($user)->get("/campaigns/{$campaign->id}");
        $reportRes->assertOk();
        $reportRes->assertInertia(fn ($page) => $page
            ->component('Campaigns/Report', false)
            ->where('campaign.abTestEnabled', true)
            ->where('campaign.subjectB', 'Variant B: Double Your Shipping Speed')
            ->where('campaign.variantAOpens', 0)
            ->where('campaign.variantBOpens', 1)
        );
    }

    public function test_settings_saves_and_persists_tickitdex_parameters(): void
    {
        $user = User::factory()->create(['email' => 'settings-admin@spiggle-mail.local']);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Settings Test Org',
            'status' => 'active',
            'settings' => [],
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Settings Dispatch',
            'slug' => 'settings-dispatch',
            'from_name' => 'Original Sender',
            'from_email' => 'original@settings.org',
            'postal_address' => '123 Settings Rd',
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'authenticated_user_id' => $user->id,
                'selected_newsletter_id' => $newsletter->id,
            ])
            ->post('/settings', [
                'defaultFromName' => 'Updated Sender',
                'defaultFromEmail' => 'updated@settings.org',
                'replyToEmail' => 'reply@settings.org',
                'postalAddress' => '456 Updated Ave',
                'doubleOptIn' => true,
                'sesDriver' => 'live',
                'sendRateLimit' => 35,
                'tickitdexEnabled' => true,
                'tickitdexWebhookUrl' => 'https://tickitdex.com/support-chat/webhooks/tickets',
                'tickitdexToken' => 'tdx_live_custom_token_456',
                'tickitdexCategory' => 'incident',
                'tickitdexCustomerEmail' => 'lead-ops@settings.org',
                'tickitdexMinPriority' => 'high',
            ]);

        $response->assertRedirect();

        $freshAccount = $account->fresh();
        $this->assertSame('live', $freshAccount->settings['ses_driver']);
        $this->assertSame(35, $freshAccount->settings['send_rate_limit']);

        $tickitdexSettings = $freshAccount->settings['tickitdex'];
        $this->assertTrue($tickitdexSettings['enabled']);
        $this->assertSame('https://tickitdex.com/support-chat/webhooks/tickets', $tickitdexSettings['webhook_url']);
        $this->assertSame('tdx_live_custom_token_456', $tickitdexSettings['token']);
        $this->assertSame('incident', $tickitdexSettings['default_category']);
        $this->assertSame('lead-ops@settings.org', $tickitdexSettings['customer_email']);
        $this->assertSame('high', $tickitdexSettings['min_priority']);

        // GET /settings passes these values to Inertia view
        $getRes = $this->actingAs($user)
            ->withSession([
                'authenticated_user_id' => $user->id,
                'selected_newsletter_id' => $newsletter->id,
            ])
            ->get('/settings');

        $getRes->assertOk();
        $getRes->assertInertia(fn ($page) => $page
            ->component('Settings/Index', false)
            ->where('settings.tickitdexEnabled', true)
            ->where('settings.tickitdexToken', '••••••••••••_456')
            ->where('settings.hasTickitdexToken', true)
            ->where('settings.tickitdexCategory', 'incident')
            ->where('settings.tickitdexMinPriority', 'high')
        );
    }

    public function test_settings_tickitdex_test_endpoint_dispatches_ping(): void
    {
        Http::fake([
            'https://tickitdex.com/support-chat/webhooks/tickets' => Http::response([
                'success' => true,
                'ticket_id' => '01HX_PING_TEST_888',
                'message' => 'Verification ticket created',
            ], 200),
        ]);

        $user = User::factory()->create(['email' => 'ping-admin@spiggle-mail.local']);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Ping Org',
            'status' => 'active',
            'settings' => [
                'tickitdex' => [
                    'enabled' => true,
                    'webhook_url' => 'https://tickitdex.com/support-chat/webhooks/tickets',
                    'token' => 'saved-token-123',
                ],
            ],
        ]);

        config(['services.tickitdex.token' => null]);

        $userNoToken = User::factory()->create(['email' => 'no-token@spiggle-mail.local']);
        $accountWithoutToken = Account::create([
            'user_id' => $userNoToken->id,
            'name' => 'Empty Token Org',
            'status' => 'active',
            'settings' => [],
        ]);

        $errRes = $this->actingAs($userNoToken)
            ->withSession(['authenticated_user_id' => $userNoToken->id])
            ->postJson('/settings/tickitdex/test', [
                'token' => '',
            ]);
        $errRes->assertStatus(422);

        // 2. Successful test dispatch with provided parameters
        $successRes = $this->actingAs($user)
            ->postJson('/settings/tickitdex/test', [
                'webhookUrl' => 'https://tickitdex.com/support-chat/webhooks/tickets',
                'token' => 'override-ping-token-777',
                'category' => 'deliverability',
            ]);

        $successRes->assertOk();
        $successRes->assertJson([
            'success' => true,
            'ticket_id' => '01HX_PING_TEST_888',
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://tickitdex.com/support-chat/webhooks/tickets'
                && $request->hasHeader('Authorization', 'Bearer override-ping-token-777')
                && $request['sending_app'] === 'spiggle-mail'
                && $request['title'] === 'SpiggleMail Dashboard Connection Test';
        });
    }
}
