<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Models\User;
use App\Jobs\SendWelcomeEmailJob;
use App\Jobs\SendConfirmationEmailJob;
use App\Jobs\SendCampaignBatchJob;
use App\Services\Email\EmailProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

class AutomationsAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Newsletter $newsletter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'admin@spiggle-mail.local']);
        $this->account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'name' => 'Primary Tenant',
            'status' => 'active',
        ]);

        $this->newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Tech Weekly',
            'slug' => 'tech-weekly',
            'from_name' => 'Editor',
            'from_email' => 'editor@spiggle-mail.local',
            'postal_address' => '123 Media Ave, San Francisco, CA',
            'double_opt_in' => true,
            'welcome_enabled' => true,
            'welcome_subject' => 'Welcome to Tech Weekly!',
            'welcome_markdown' => 'Hello and welcome to our newsletter.',
            'list_cleanup_enabled' => true,
            'cleanup_inactive_issues' => 6,
            'cleanup_inactive_days' => 180,
        ]);
    }

    public function test_double_opt_in_creates_pending_contact_and_sends_confirmation(): void
    {
        // 1. Subscribe via widget
        $response = $this->post('/embed/subscribe', [
            'newsletter_slug' => $this->newsletter->slug,
            'email' => 'reader@example.com',
        ]);

        $response->assertSessionHas('success');

        $contact = Contact::where('email', 'reader@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('pending', $contact->status);
        $this->assertNotNull($contact->confirmation_token);
        $this->assertNull($contact->confirmed_at);

        // 2. Click confirmation link
        $confirmResponse = $this->get("/confirm?token={$contact->confirmation_token}");
        $confirmResponse->assertStatus(200);

        $contact->refresh();
        $this->assertEquals('subscribed', $contact->status);
        $this->assertNotNull($contact->confirmed_at);
        $this->assertNull($contact->confirmation_token);
    }

    public function test_welcome_email_job_dispatches_when_welcome_is_enabled(): void
    {
        $contact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'new.member@example.com',
            'status' => 'subscribed',
            'confirmed_at' => now(),
        ]);

        $emailProvider = app(EmailProviderInterface::class);
        $job = new SendWelcomeEmailJob($contact->id);
        $job->handle($emailProvider);

        $this->assertDatabaseHas('messages', [
            'contact_id' => $contact->id,
            'status' => 'sent',
        ]);
    }

    public function test_inactive_contact_sunsetting_and_keep_reengagement(): void
    {
        // 1. Create dormant candidate: 6 ignored issues and 200 days inactive
        $contact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'dormant.user@example.com',
            'status' => 'subscribed',
            'ignored_issues_count' => 6,
            'last_engaged_at' => now()->subDays(200),
            'subscribed_at' => now()->subDays(250),
        ]);

        // 2. Run cleanup command
        Artisan::call('mail:cleanup-inactive');

        $contact->refresh();
        $this->assertNotNull($contact->last_chance_at);
        $this->assertNotNull($contact->keep_token);
        $this->assertEquals('subscribed', $contact->status);

        // 3. User clicks re-engagement link (/keep?token=...)
        $keepResponse = $this->get("/keep?token={$contact->keep_token}");
        $keepResponse->assertStatus(200);

        $contact->refresh();
        $this->assertEquals('subscribed', $contact->status);
        $this->assertEquals(0, $contact->ignored_issues_count);
        $this->assertNull($contact->last_chance_at);
        $this->assertNull($contact->keep_token);
    }

    public function test_unresponsive_contact_is_marked_dormant_after_14_days(): void
    {
        $contact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'unresponsive@example.com',
            'status' => 'subscribed',
            'ignored_issues_count' => 6,
            'last_engaged_at' => now()->subDays(200),
            'last_chance_at' => now()->subDays(15), // sent 15 days ago
        ]);

        Artisan::call('mail:cleanup-inactive');

        $contact->refresh();
        $this->assertEquals('dormant', $contact->status);
    }

    public function test_inbound_ses_bounce_webhook_records_suppression_and_updates_contact(): void
    {
        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'bad.address@example.com',
            'status' => 'subscribed',
        ]);

        $snsPayload = [
            'Type' => 'Notification',
            'Message' => json_encode([
                'notificationType' => 'Bounce',
                'bounce' => [
                    'bounceSubType' => 'General',
                    'bouncedRecipients' => [
                        ['emailAddress' => 'bad.address@example.com']
                    ],
                ],
            ]),
        ];

        $response = $this->postJson('/api/webhooks/ses', $snsPayload);
        $response->assertStatus(200);

        $contact = Contact::where('email', 'bad.address@example.com')->first();
        $this->assertEquals('bounced', $contact->status);

        $hash = hash_hmac('sha256', 'bad.address@example.com', config('app.key'));
        $this->assertTrue(Suppression::where('email_hash', $hash)->exists());
    }

    public function test_inbound_postmark_spam_complaint_webhook(): void
    {
        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'complainer@example.com',
            'status' => 'subscribed',
        ]);

        $response = $this->postJson('/api/webhooks/postmark', [
            'RecordType' => 'SpamComplaint',
            'Email' => 'complainer@example.com',
        ]);
        $response->assertStatus(200);

        $contact = Contact::where('email', 'complainer@example.com')->first();
        $this->assertEquals('complained', $contact->status);

        $hash = hash_hmac('sha256', 'complainer@example.com', config('app.key'));
        $this->assertTrue(Suppression::where('email_hash', $hash)->exists());
    }

    public function test_circuit_breaker_halts_send_if_account_is_paused(): void
    {
        $this->account->update(['status' => 'paused', 'pause_reason' => 'Administrative lock']);

        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Issue 1',
            'html' => '<p>Hello</p>',
            'status' => 'draft',
        ]);

        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'user1@example.com',
            'status' => 'subscribed',
        ]);

        $job = new SendCampaignBatchJob($campaign->id, 50);
        $job->handle(app(EmailProviderInterface::class), app(\App\Services\Campaign\ShortcodeParserService::class));

        $campaign->refresh();
        $this->assertEquals('paused', $campaign->status);
        $this->assertStringContainsString('Account is paused', $campaign->pause_reason);
    }

    public function test_first_send_safety_slicing_holds_cold_list(): void
    {
        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Cold List First Issue',
            'html' => '<p>Welcome new readers</p>',
            'status' => 'draft',
        ]);

        // Create 100 never-mailed contacts
        for ($i = 1; $i <= 100; $i++) {
            Contact::create([
                'id' => (string) Str::ulid(),
                'newsletter_id' => $this->newsletter->id,
                'email' => "cold.contact.{$i}@example.com",
                'status' => 'subscribed',
                'last_sent_at' => null, // Never mailed before
            ]);
        }

        $job = new SendCampaignBatchJob($campaign->id, 50);
        $job->handle(app(EmailProviderInterface::class), app(\App\Services\Campaign\ShortcodeParserService::class));

        $campaign->refresh();

        // 1. Verify slice values were computed and stored
        $this->assertEquals(50, $campaign->first_send_slice);
        $this->assertEquals(50, $campaign->first_send_held);
        $this->assertNotNull($campaign->first_send_check_at);
        $this->assertNull($campaign->first_send_checked_at);

        // 2. Verify only 50 were sent in the pilot slice
        $this->assertEquals(50, $campaign->sent_count);
    }
}
