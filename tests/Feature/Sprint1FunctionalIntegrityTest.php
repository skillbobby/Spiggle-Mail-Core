<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Sprint1FunctionalIntegrityTest extends TestCase
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
            'name' => 'Sprint 1 Workspace',
            'status' => 'active',
        ]);

        $this->newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'The Architect Dispatch',
            'slug' => 'architect-dispatch',
            'from_name' => 'Lead Architect',
            'from_email' => 'architect@spiggle-mail.local',
            'postal_address' => '700 Tech Way, Austin TX',
            'double_opt_in' => false,
            'welcome_enabled' => false,
        ]);
    }

    public function test_contacts_index_returns_paginated_real_subscribers(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'subscriber1@example.com',
            'status' => 'subscribed',
        ]);

        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'subscriber2@example.com',
            'status' => 'dormant',
        ]);

        $response = $this->get('/contacts');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Contacts/Index', false)
            ->where('totalContacts', 2)
            ->where('statusCounts.subscribed', 1)
            ->where('statusCounts.dormant', 1)
            ->has('contacts', 2)
            ->where('contacts.0.email', fn ($email) => in_array($email, ['subscriber1@example.com', 'subscriber2@example.com']))
        );
    }

    public function test_contacts_store_adds_single_subscriber(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $response = $this->post('/contacts', [
            'email' => 'new.developer@techcorp.io',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'newsletter_id' => $this->newsletter->id,
            'email' => 'new.developer@techcorp.io',
            'status' => 'subscribed',
        ]);
    }

    public function test_contacts_import_parses_csv_and_imports_subscribers(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $csvContent = "Email Address,Subscription Status,Signup Date\n"
            ."alice@company.org,active,2026-01-15\n"
            ."bob@agency.dev,unsubscribed,2026-02-10\n"
            ."carol@domain.net,subscribed,2026-03-01\n";

        $file = UploadedFile::fake()->createWithContent('subscribers.csv', $csvContent);

        $response = $this->post('/contacts/import', [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'newsletter_id' => $this->newsletter->id,
            'email' => 'alice@company.org',
            'status' => 'subscribed',
        ]);

        $this->assertDatabaseHas('contacts', [
            'newsletter_id' => $this->newsletter->id,
            'email' => 'bob@agency.dev',
            'status' => 'unsubscribed',
        ]);

        $this->assertDatabaseHas('contacts', [
            'newsletter_id' => $this->newsletter->id,
            'email' => 'carol@domain.net',
            'status' => 'subscribed',
        ]);
    }

    public function test_contacts_destroy_removes_subscriber(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $contact = Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'todelete@example.com',
            'status' => 'subscribed',
        ]);

        $response = $this->delete("/contacts/{$contact->id}");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_campaigns_index_returns_campaigns_list(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Sprint 1 Launch Announcement',
            'status' => 'draft',
        ]);

        $response = $this->get('/campaigns');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Campaigns/Index', false)
            ->where('statusCounts.draft', 1)
            ->has('campaigns', 1)
            ->where('campaigns.0.subject', 'Sprint 1 Launch Announcement')
        );
    }

    public function test_campaigns_scheduling_holds_future_campaign_in_scheduled_status(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $futureDate = now()->addDays(2)->format('Y-m-d H:i');

        $response = $this->post('/campaigns', [
            'subject' => 'Future Scheduled Dispatch',
            'previewText' => 'Look out for this',
            'html' => '<p>Coming soon</p>',
            'scheduledAt' => $futureDate,
        ]);

        $response->assertRedirect('/campaigns');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('campaigns', [
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Future Scheduled Dispatch',
            'status' => 'scheduled',
        ]);
    }

    public function test_campaigns_send_scheduled_command_dispatches_due_campaign(): void
    {
        Queue::fake();

        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Due Campaign For Dispatch',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->artisan('campaigns:send-scheduled')
            ->assertExitCode(0);

        $this->assertEquals('sending', $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->sent_at);
    }

    public function test_campaigns_destroy_deletes_draft_campaign(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $campaign = Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Unwanted Draft',
            'status' => 'draft',
        ]);

        $response = $this->delete("/campaigns/{$campaign->id}");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_settings_post_persists_newsletter_and_account_data(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $response = $this->post('/settings', [
            'defaultFromName' => 'Updated Publisher Name',
            'defaultFromEmail' => 'publisher@updateddomain.org',
            'replyToEmail' => 'reply@updateddomain.org',
            'postalAddress' => '999 New Street, Seattle WA',
            'doubleOptIn' => true,
            'sesDriver' => 'live',
            'sendRateLimit' => 45,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->newsletter->refresh();
        $this->assertEquals('Updated Publisher Name', $this->newsletter->from_name);
        $this->assertEquals('publisher@updateddomain.org', $this->newsletter->from_email);
        $this->assertEquals('reply@updateddomain.org', $this->newsletter->reply_to);
        $this->assertEquals('999 New Street, Seattle WA', $this->newsletter->postal_address);
        $this->assertTrue((bool) $this->newsletter->double_opt_in);

        $this->account->refresh();
        $this->assertEquals('live', $this->account->settings['ses_driver'] ?? null);
        $this->assertEquals(45, $this->account->settings['send_rate_limit'] ?? null);
    }
}
