<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Domain;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SherlockComprehensiveFeatureTest extends TestCase
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
            'name' => 'Sherlock Test Org',
            'status' => 'active',
        ]);

        $this->newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'The Dispatch Investigator',
            'slug' => 'dispatch-investigator',
            'from_name' => 'Sherlock Holmes',
            'from_email' => 'sherlock@spiggle-mail.local',
            'postal_address' => '221B Baker St, London UK',
            'double_opt_in' => false,
            'welcome_enabled' => false,
        ]);
    }

    public function test_domains_index_lists_registered_domains(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'domain' => 'bakerstreet.co.uk',
            'status' => 'verified',
            'dkim_tokens' => ['token1', 'token2', 'token3'],
            'mail_from_domain' => 'mail.bakerstreet.co.uk',
        ]);

        $response = $this->get('/domains');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Domains/Index', false)
            ->where('domain.domain', 'bakerstreet.co.uk')
            ->has('domainsList', 1)
        );
    }

    public function test_domains_store_registers_new_sending_domain(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $response = $this->post('/domains', [
            'domain' => 'news.investigations.org',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('domains', [
            'account_id' => $this->account->id,
            'domain' => 'news.investigations.org',
            'status' => 'pending',
        ]);
    }

    public function test_domains_verify_marks_domain_verified(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $domain = Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'domain' => 'test-verify.local',
            'status' => 'pending',
            'dkim_tokens' => ['tok1', 'tok2', 'tok3'],
        ]);

        $response = $this->post("/domains/{$domain->id}/verify");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals('verified', $domain->fresh()->status);
    }

    public function test_domains_destroy_removes_domain(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $domain = Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'domain' => 'to-remove.local',
            'status' => 'pending',
        ]);

        $response = $this->delete("/domains/{$domain->id}");
        $response->assertRedirect('/domains');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_campaign_test_send_dispatches_preview_email(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $response = $this->post('/campaigns/test-send', [
            'subject' => 'Secret Clue Revealed',
            'html' => '<p>The game is afoot.</p>',
            'recipient' => 'watson@bakerstreet.co.uk',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_mcp_send_test_email_tool(): void
    {
        $rawKey = 'spm_' . Str::random(32);
        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Sherlock MCP Key',
            'key_prefix' => substr($rawKey, 0, 8),
            'key_hash' => hash('sha256', $rawKey),
            'role' => 'full_access',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$rawKey}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => [
                'name' => 'send_test_email',
                'arguments' => [
                    'subject' => 'Autonomous Agent Issue',
                    'html' => '<p>Generated by Claude Agent.</p>',
                    'recipient' => 'editor@spiggle-mail.local',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('result.structuredContent.success', true);
    }

    public function test_llms_txt_and_full_endpoints_render_markdown(): void
    {
        $r1 = $this->get('/llms.txt');
        $r1->assertStatus(200);
        $r1->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $r1->assertSee('# SpiggleMail');

        $r2 = $this->get('/llms-full.txt');
        $r2->assertStatus(200);
        $r2->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $r2->assertSee('# SpiggleMail API & MCP Reference', false);
    }

    public function test_deliverability_index_returns_real_suppressions_and_allows_removal(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $suppression = Suppression::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'email_hash' => hash('sha256', 'spammer@badsource.com'),
            'reason' => 'bounce',
        ]);

        $response = $this->get('/deliverability');
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Deliverability/Index', false)
            ->has('suppressions', 1)
        );

        $deleteResponse = $this->delete("/deliverability/suppressions/{$suppression->id}");
        $deleteResponse->assertRedirect();
        $this->assertDatabaseMissing('suppressions', ['id' => $suppression->id]);
    }
}
