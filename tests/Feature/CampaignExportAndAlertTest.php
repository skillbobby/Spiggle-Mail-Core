<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignExportAndAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_email_api_detects_common_domain_typos(): void
    {
        $res1 = $this->getJson('/api/v1/check-email?email=alex@gmial.com');
        $res1->assertOk()
            ->assertJson([
                'has_typo' => true,
                'suggestion' => 'alex@gmail.com',
                'reason' => 'typo',
            ]);

        $res2 = $this->getJson('/api/v1/check-email?email=sarah@yaho.com');
        $res2->assertOk()
            ->assertJson([
                'has_typo' => true,
                'suggestion' => 'sarah@yahoo.com',
                'reason' => 'typo',
            ]);

        $res3 = $this->getJson('/api/v1/check-email?email=valid@gmail.com');
        $res3->assertOk()
            ->assertJson([
                'has_typo' => false,
                'suggestion' => null,
            ]);
    }

    public function test_campaign_csv_export_streams_audit_logs(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Acme Inc',
            'status' => 'active',
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Tech Weekly',
            'slug' => 'tech-weekly',
            'from_name' => 'Editor',
            'from_email' => 'editor@example.com',
            'postal_address' => '123 Main St',
        ]);

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Issue #42 Launch',
            'format' => 'rich',
            'status' => 'sent',
            'sent_count' => 2,
            'delivered_count' => 2,
            'open_count' => 1,
            'click_count' => 1,
        ]);

        $contact1 = Contact::create([
            'newsletter_id' => $newsletter->id,
            'email' => 'subscriber1@example.com',
            'status' => 'active',
        ]);

        $contact2 = Contact::create([
            'newsletter_id' => $newsletter->id,
            'email' => 'subscriber2@example.com',
            'status' => 'active',
        ]);

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact1->id,
            'ses_message_id' => 'ses-001',
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
            'opened_at' => now(),
            'clicked_at' => now(),
        ]);

        Message::create([
            'campaign_id' => $campaign->id,
            'contact_id' => $contact2->id,
            'ses_message_id' => 'ses-002',
            'status' => 'delivered',
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}/export.csv");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('Recipient Email', $content);
        $this->assertStringContainsString('Message ID', $content);
        $this->assertStringContainsString('Sent At', $content);
        $this->assertStringContainsString('subscriber1@example.com', $content);
        $this->assertStringContainsString('subscriber2@example.com', $content);
        $this->assertStringContainsString('ses-001', $content);
        $this->assertStringContainsString('ses-002', $content);
    }

    public function test_campaign_report_extracts_links_and_click_analytics(): void
    {
        $user = User::factory()->create();

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Acme Inc',
            'status' => 'active',
        ]);

        $newsletter = Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Weekly Digest',
            'slug' => 'weekly-digest',
            'from_name' => 'Author',
            'from_email' => 'author@example.com',
            'postal_address' => '123 Main St',
        ]);

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Deep Dive Article',
            'format' => 'rich',
            'status' => 'sent',
            'sent_count' => 10,
            'delivered_count' => 10,
            'click_count' => 14,
            'html' => '<p>Check out our <a href="https://example.com/product">New Product</a> and <a href="https://example.com/blog">Blog Post</a>.</p>',
        ]);

        $response = $this->actingAs($user)->get("/campaigns/{$campaign->id}");
        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->component('Campaigns/Report', false)
            ->has('clickedLinks', 2)
            ->where('clickedLinks.0.url', 'https://example.com/product')
            ->where('clickedLinks.0.text', 'New Product')
            ->where('clickedLinks.1.url', 'https://example.com/blog')
            ->where('clickedLinks.1.text', 'Blog Post')
        );
    }

    public function test_account_alert_is_shared_when_account_circuit_breaker_trips(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@spiggle-mail.local',
        ]);

        $account = Account::create([
            'user_id' => $user->id,
            'name' => 'Test Pub',
            'status' => 'paused',
            'pause_reason' => 'Bounce rate exceeded 5.0% on recent campaign.',
            'paused_by' => 'ses_bounce_spike',
            'paused_at' => now(),
        ]);

        Newsletter::create([
            'account_id' => $account->id,
            'name' => 'Alert Publication',
            'slug' => 'alert-pub',
            'from_name' => 'Admin',
            'from_email' => 'admin@example.com',
            'postal_address' => '123 Main St',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['authenticated_user_id' => $user->id])
            ->get('/dashboard');

        $response->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->has('accountAlert')
            ->where('accountAlert.type', 'warning')
            ->where('accountAlert.title', 'Automated Circuit Breaker Active')
            ->where('accountAlert.pausedBy', 'ses_bounce_spike')
            ->where('accountAlert.message', 'Bounce rate exceeded 5.0% on recent campaign.')
        );
    }
}
