<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendCampaignBatchJob;
use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Message;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Models\User;
use App\Services\Campaign\ShortcodeParserService;
use App\Services\Deliverability\TrackingTokenService;
use App\Services\Deliverability\UnsubscribeHeaderService;
use App\Services\Email\AmazonSesEmailProvider;
use App\Services\Email\EmailProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SesReputationAndSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_visitors_are_blocked_from_all_private_routes(): void
    {
        $user = User::factory()->create();
        $account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'name' => 'Private Tenant',
            'status' => 'active',
        ]);
        Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Private Pub',
            'slug' => 'private-pub',
            'from_name' => 'Private',
            'from_email' => 'news@private.com',
            'postal_address' => '100 Main St',
        ]);

        $this->get('/dashboard')->assertRedirect('/signin');
        $this->get('/settings')->assertRedirect('/signin');
        $this->get('/account/export')->assertRedirect('/signin');
        $this->get('/campaigns')->assertRedirect('/signin');
        $this->get('/contacts')->assertRedirect('/signin');
        $this->get('/domains')->assertRedirect('/signin');
        $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(401);
    }

    public function test_cross_tenant_idor_access_is_blocked(): void
    {
        $tenantAUser = User::factory()->create(['email' => 'alice@tenanta.com']);
        $tenantAAccount = Account::create(['id' => (string) Str::ulid(), 'user_id' => $tenantAUser->id, 'name' => 'Tenant A', 'status' => 'active']);
        $tenantANewsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $tenantAAccount->id,
            'name' => 'Alice Dispatch',
            'slug' => 'alice-dispatch',
            'from_name' => 'Alice',
            'from_email' => 'alice@tenanta.com',
            'postal_address' => '1 Alice Way',
        ]);
        $tenantACampaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $tenantANewsletter->id,
            'subject' => 'Top Secret Roadmap',
            'status' => 'sent',
        ]);

        $tenantBUser = User::factory()->create(['email' => 'bob@tenantb.com']);
        $tenantBAccount = Account::create(['id' => (string) Str::ulid(), 'user_id' => $tenantBUser->id, 'name' => 'Tenant B', 'status' => 'active']);
        Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $tenantBAccount->id,
            'name' => 'Bob Dispatch',
            'slug' => 'bob-dispatch',
            'from_name' => 'Bob',
            'from_email' => 'bob@tenantb.com',
            'postal_address' => '2 Bob Way',
        ]);

        $this->actingAs($tenantBUser)
            ->get("/campaigns/{$tenantACampaign->id}")
            ->assertStatus(403);

        $this->actingAs($tenantBUser)
            ->get("/campaigns/{$tenantACampaign->id}/export.csv")
            ->assertStatus(403);

        // API key belonging to Tenant B cannot add contacts to Tenant A's newsletter
        $rawKeyB = 'spm_' . Str::random(32);
        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $tenantBAccount->id,
            'name' => 'Bob Key',
            'key_prefix' => substr($rawKeyB, 0, 8),
            'key_hash' => hash('sha256', $rawKeyB),
            'role' => 'full_access',
        ]);

        $this->withHeader('Authorization', "Bearer {$rawKeyB}")
            ->postJson('/api/v1/contacts', [
                'email' => 'intruder@example.com',
                'newsletter_id' => $tenantANewsletter->id,
            ])
            ->assertStatus(403);
    }

    public function test_rfc_8058_one_click_unsubscribe_works_without_csrf_and_records_suppression(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'name' => 'RFC Org', 'status' => 'active']);
        $newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'RFC Pub',
            'slug' => 'rfc-pub',
            'from_name' => 'Editor',
            'from_email' => 'editor@rfcpub.com',
            'postal_address' => '100 RFC St',
        ]);
        $contact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'email' => 'reader@gmail.com',
            'status' => 'subscribed',
        ]);

        $token = UnsubscribeHeaderService::createUnsubscribeToken($contact->id, $newsletter->slug);

        $response = $this->post("/api/v1/unsubscribe/{$token}", [
            'List-Unsubscribe' => 'One-Click',
        ]);

        $response->assertStatus(200);
        $this->assertSame('unsubscribed', $contact->fresh()->status);

        $expectedHash = hash_hmac('sha256', 'reader@gmail.com', (string) config('app.key'));
        $this->assertDatabaseHas('suppressions', [
            'account_id' => $account->id,
            'email_hash' => $expectedHash,
            'reason' => 'manual',
        ]);
    }

    public function test_sns_webhook_blocks_ssrf_enforces_signature_and_handles_soft_vs_hard_bounces_and_circuit_breaker(): void
    {
        // 1. Reject forged/unsigned SNS webhook when signature verification is enabled
        config(['services.ses.verify_sns_signature' => true]);

        $unsignedRes = $this->postJson('/api/webhooks/ses', [
            'Type' => 'Notification',
            'MessageId' => 'msg-unsigned-1',
            'Message' => json_encode(['eventType' => 'Bounce']),
        ]);
        $unsignedRes->assertStatus(403);

        // 2. Block SSRF SubscribeURL pointing to metadata/non-AWS host even if signature check disabled in test
        config(['services.ses.verify_sns_signature' => false]);

        $ssrfRes = $this->postJson('/api/webhooks/ses', [
            'Type' => 'SubscriptionConfirmation',
            'SubscribeURL' => 'http://169.254.169.254/latest/meta-data/iam/security-credentials/',
        ]);
        $ssrfRes->assertStatus(403);

        // 3. Soft (Transient) 3-strike rule vs Hard (Permanent) bounce + Campaign Circuit Breaker
        Http::fake([
            'https://tickitdex.com/*' => Http::response(['success' => true, 'ticket_id' => 'TDX-CB-1'], 200),
        ]);
        config([
            'services.tickitdex.enabled' => true,
            'services.tickitdex.token' => 'tdx-secret',
            'services.tickitdex.webhook_url' => 'https://tickitdex.com/support-chat/webhooks/tickets',
        ]);

        $user = User::factory()->create();
        $account = Account::create(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'name' => 'SES Guard Org', 'status' => 'active']);
        $newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'SES Guard Weekly',
            'slug' => 'ses-guard-weekly',
            'from_name' => 'Guard',
            'from_email' => 'news@sesguard.com',
            'postal_address' => '99 Guard Blvd',
        ]);

        $softContact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'email' => 'full-inbox@gmail.com',
            'status' => 'subscribed',
        ]);

        // Strike 1 & 2 Transient soft bounces keep contact subscribed
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/webhooks/ses', [
                'Type' => 'Notification',
                'MessageId' => "sns-soft-{$i}",
                'Message' => json_encode([
                    'eventType' => 'Bounce',
                    'bounce' => [
                        'bounceType' => 'Transient',
                        'bouncedRecipients' => [['emailAddress' => 'full-inbox@gmail.com']],
                    ],
                ]),
            ])->assertOk();
        }
        $this->assertSame('subscribed', $softContact->fresh()->status);
        $this->assertSame(2, (int) $softContact->fresh()->soft_bounce_count);

        // Strike 3 Transient soft bounce suppresses contact
        $this->postJson('/api/webhooks/ses', [
            'Type' => 'Notification',
            'MessageId' => 'sns-soft-3',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'bounce' => [
                    'bounceType' => 'Transient',
                    'bouncedRecipients' => [['emailAddress' => 'full-inbox@gmail.com']],
                ],
            ]),
        ])->assertOk();
        $this->assertSame('bounced', $softContact->fresh()->status);

        // Hard bounce spike (>= 5% and >= 10 bounces) trips circuit breaker and pauses Account
        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'subject' => 'Broadcast #99',
            'status' => 'sending',
            'sent_count' => 100,
            'bounce_count' => 9,
        ]);
        $hardContact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'email' => 'dead-mailbox@invalid-domain.org',
            'status' => 'subscribed',
        ]);
        Message::create([
            'id' => (string) Str::ulid(),
            'campaign_id' => $campaign->id,
            'contact_id' => $hardContact->id,
            'ses_message_id' => 'ses-msg-hard-001',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->postJson('/api/webhooks/ses', [
            'Type' => 'Notification',
            'MessageId' => 'sns-hard-spike-1',
            'Message' => json_encode([
                'eventType' => 'Bounce',
                'mail' => ['messageId' => 'ses-msg-hard-001'],
                'bounce' => [
                    'bounceType' => 'Permanent',
                    'bouncedRecipients' => [['emailAddress' => 'dead-mailbox@invalid-domain.org', 'diagnosticCode' => '550 No such user']],
                ],
            ]),
        ])->assertOk();

        // 10 bounces / 100 sent = 10.0% (>= 5% & >= 10 bounces) -> block threshold tripped!
        $this->assertSame(10, (int) $campaign->fresh()->bounce_count);
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertSame('paused', $account->fresh()->status);
        $this->assertSame('bounced', $hardContact->fresh()->status);
    }

    public function test_live_ses_blocks_unverified_sender_domains_and_never_injects_fake_contacts(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'name' => 'Live SES Check', 'status' => 'active']);
        $newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Unverified Pub',
            'slug' => 'unverified-pub',
            'from_name' => 'Sender',
            'from_email' => 'hello@unverified-domain.com',
            'postal_address' => '100 Real Ave',
        ]);

        // Bind live AmazonSesEmailProvider to test pre-send gate
        $this->app->bind(EmailProviderInterface::class, fn () => new AmazonSesEmailProvider('us-east-1', 'AKIA_TEST', 'SECRET_TEST'));

        $blockedRes = $this->actingAs($user)->post('/campaigns', [
            'subject' => 'Should Be Blocked',
            'html' => '<p>Body</p>',
        ]);
        $blockedRes->assertSessionHasErrors('subject');
        $this->assertSame(0, Campaign::count());

        // Now mark domain verified and switch back to MockEmailProvider to verify zero fake contacts are injected
        Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'domain' => 'unverified-domain.com',
            'status' => 'verified',
            'verified_at' => now(),
        ]);
        $this->app->bind(EmailProviderInterface::class, fn () => new \App\Services\Email\MockEmailProvider);

        $okRes = $this->actingAs($user)->post('/campaigns', [
            'subject' => 'Clean Empty Audience Send',
            'html' => '<p>Body</p>',
        ]);
        $okRes->assertRedirect();
        $this->assertSame(0, Contact::count(), 'Campaign dispatch must NEVER inject fake @example.org contacts.');
    }

    public function test_batch_job_injects_tracking_and_compliance_footer_skips_suppressions_and_tracks_real_clicks(): void
    {
        $user = User::factory()->create();
        $account = Account::create(['id' => (string) Str::ulid(), 'user_id' => $user->id, 'name' => 'Batch Org', 'status' => 'active']);
        $newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'name' => 'Batch News',
            'slug' => 'batch-news',
            'from_name' => 'Batch',
            'from_email' => 'news@batch.org',
            'postal_address' => '742 Evergreen Terrace',
        ]);

        $goodContact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'email' => 'good@reader.org',
            'status' => 'subscribed',
        ]);
        $suppressedContact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'email' => 'suppressed@reader.org',
            'status' => 'subscribed',
        ]);
        Suppression::create([
            'id' => (string) Str::ulid(),
            'account_id' => $account->id,
            'email_hash' => hash_hmac('sha256', 'suppressed@reader.org', (string) config('app.key')),
            'reason' => 'complaint',
        ]);

        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $newsletter->id,
            'subject' => 'Tracking Verification',
            'html' => '<p>Read our <a href="https://spiggle.dev/launch">Launch Guide</a>!</p><img src="/storage/uploads/2026/11/chart.png" />',
            'status' => 'sending',
        ]);

        (new SendCampaignBatchJob($campaign->id, 50))->handle(
            new \App\Services\Email\MockEmailProvider,
            app(ShortcodeParserService::class)
        );

        // Suppressed contact was skipped (never sent via SES) and marked unsubscribed
        $this->assertSame('unsubscribed', $suppressedContact->fresh()->status);
        $this->assertSame(1, Message::where('campaign_id', $campaign->id)->whereNotNull('ses_message_id')->count());
        $this->assertSame(1, (int) $campaign->fresh()->sent_count);

        $message = Message::where('campaign_id', $campaign->id)->where('contact_id', $goodContact->id)->firstOrFail();

        // Simulate real click via /c/{token}
        $clickToken = TrackingTokenService::createClickToken($message->id, 'https://spiggle.dev/launch');
        $clickRes = $this->get("/c/{$clickToken}");
        $clickRes->assertRedirect('https://spiggle.dev/launch');

        $this->assertSame(1, (int) $campaign->fresh()->click_count);
        $this->assertDatabaseHas('message_clicks', [
            'campaign_id' => $campaign->id,
            'message_id' => $message->id,
            'url' => 'https://spiggle.dev/launch',
        ]);
    }

    public function test_account_settings_are_encrypted_at_rest_and_ssrf_is_blocked(): void
    {
        $user = User::factory()->create(['email' => 'admin@spiggle-mail.local']);
        $account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'name' => 'Crypto Check',
            'status' => 'active',
            'settings' => [
                'aws_secret_access_key' => 'SUPER_SECRET_AWS_KEY_9999',
                'tickitdex' => ['token' => 'TDX_ULTRA_SECRET_TOKEN'],
            ],
        ]);

        $rawCiphertext = (string) DB::table('accounts')->where('id', $account->id)->value('settings');
        $this->assertStringNotContainsString('SUPER_SECRET_AWS_KEY_9999', $rawCiphertext);
        $this->assertStringNotContainsString('TDX_ULTRA_SECRET_TOKEN', $rawCiphertext);
        $this->assertSame('SUPER_SECRET_AWS_KEY_9999', $account->fresh()->settings['aws_secret_access_key']);

        // SSRF attempts on TickitDex test endpoint are rejected
        $ssrfRes = $this->actingAs($user)->postJson('/settings/tickitdex/test', [
            'webhookUrl' => 'https://127.0.0.1/internal-admin',
            'token' => 'TDX_ULTRA_SECRET_TOKEN',
        ]);
        $ssrfRes->assertStatus(422);
    }
}
