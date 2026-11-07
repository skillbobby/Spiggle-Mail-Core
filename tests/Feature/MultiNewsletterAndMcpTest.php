<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MultiNewsletterAndMcpTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Newsletter $primaryNewsletter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'admin@spiggle-mail.local']);
        $this->account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'name' => 'Tech Media Group',
            'status' => 'active',
        ]);

        $this->primaryNewsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Main Engineering',
            'slug' => 'main-engineering',
            'from_name' => 'Lead Dev',
            'from_email' => 'dev@spiggle-mail.local',
            'postal_address' => '500 Tech Blvd, Austin, TX',
            'double_opt_in' => false,
            'welcome_enabled' => true,
        ]);
    }

    public function test_can_create_additional_newsletters_up_to_limit(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $response = $this->post('/newsletters', [
            'name' => 'AI Dispatch',
            'slug' => 'ai-dispatch',
            'from_name' => 'AI Lead',
            'from_email' => 'ai@spiggle-mail.local',
            'postal_address' => '500 Tech Blvd, Austin, TX',
            'description' => 'Weekly AI developments',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('newsletters', [
            'slug' => 'ai-dispatch',
            'name' => 'AI Dispatch',
            'account_id' => $this->account->id,
        ]);
        $this->assertEquals(2, Newsletter::where('account_id', $this->account->id)->count());
    }

    public function test_can_switch_active_newsletter(): void
    {
        $secondNewsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Product News',
            'slug' => 'product-news',
            'from_name' => 'PM',
            'from_email' => 'pm@spiggle-mail.local',
            'postal_address' => '500 Tech Blvd, Austin, TX',
        ]);

        $this->actingAs($this->user);

        $response = $this->post('/newsletters/select', [
            'newsletter_id' => $secondNewsletter->id,
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals($secondNewsletter->id, session('selected_newsletter_id'));
    }

    public function test_mcp_initialize_and_tools_list(): void
    {
        $rawKey = 'spm_'.Str::random(32);
        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'MCP Test Key',
            'key_prefix' => substr($rawKey, 0, 8),
            'key_hash' => hash('sha256', $rawKey),
            'role' => 'full_access',
        ]);

        // 1. Initialize
        $initResponse = $this->withHeader('Authorization', "Bearer {$rawKey}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
            ],
        ]);

        $initResponse->assertStatus(200);
        $initData = $initResponse->json();
        $this->assertEquals('2.0', $initData['jsonrpc']);
        $this->assertEquals('spiggle-mail', $initData['result']['serverInfo']['name']);

        // 2. Tools List
        $listResponse = $this->withHeader('Authorization', "Bearer {$rawKey}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]);

        $listResponse->assertStatus(200);
        $tools = $listResponse->json('result.tools');
        $this->assertIsArray($tools);
        $toolNames = array_column($tools, 'name');
        $this->assertContains('list_campaigns', $toolNames);
        $this->assertContains('create_campaign', $toolNames);
        $this->assertContains('list_contacts', $toolNames);
        $this->assertContains('get_deliverability_stats', $toolNames);
    }

    public function test_mcp_tool_execution(): void
    {
        $rawKey = 'spm_'.Str::random(32);
        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'MCP Exec Key',
            'key_prefix' => substr($rawKey, 0, 8),
            'key_hash' => hash('sha256', $rawKey),
            'role' => 'full_access',
        ]);

        // Add a campaign
        Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->primaryNewsletter->id,
            'subject' => 'MCP Broadcast',
            'status' => 'draft',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$rawKey}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'list_campaigns',
                'arguments' => ['limit' => 5],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($response->json('result.content.0.text'));
        $this->assertStringContainsString('MCP Broadcast', $response->json('result.content.0.text'));
    }

    public function test_public_rss_feed_and_sitemap(): void
    {
        Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->primaryNewsletter->id,
            'subject' => 'Issue 101: System Architecture',
            'html' => '<p>Deep dive into distributed systems.</p>',
            'status' => 'sent',
            'archive_listed' => true,
            'sent_at' => now(),
        ]);

        // 1. RSS Feed
        $feedResponse = $this->get("/archive/{$this->primaryNewsletter->slug}/feed.xml");
        $feedResponse->assertStatus(200);
        $feedResponse->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('Issue 101: System Architecture', $feedResponse->getContent());

        // 2. Sitemap
        $sitemapResponse = $this->get("/archive/{$this->primaryNewsletter->slug}/sitemap.xml");
        $sitemapResponse->assertStatus(200);
        $sitemapResponse->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('<urlset', $sitemapResponse->getContent());
    }
}
