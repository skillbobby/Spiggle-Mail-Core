<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Domain;
use App\Models\Newsletter;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiErrorAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;
    private Newsletter $newsletter;
    private string $rawApiKey;
    private ApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'author@spiggle-mail.local']);
        $this->account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'name' => 'Author Group',
            'status' => 'active',
        ]);

        $this->newsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Tech Dispatch',
            'slug' => 'tech-dispatch',
            'from_name' => 'Tech Author',
            'from_email' => 'author@tech-dispatch.local',
            'postal_address' => '123 Tech Lane, San Francisco, CA 94107',
        ]);

        $this->rawApiKey = 'spm_' . Str::random(32);
        $this->apiKey = ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Audit Test Key',
            'key_prefix' => substr($this->rawApiKey, 0, 8),
            'key_hash' => hash('sha256', $this->rawApiKey),
            'role' => 'full_access',
        ]);
    }

    public function test_api_root_and_discovery_endpoints(): void
    {
        $response = $this->getJson('/api');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'operational')
            ->assertJsonStructure(['name', 'version', 'status', 'documentation', 'openapi', 'mcp_server', 'endpoints']);

        $v1Response = $this->getJson('/api/v1');
        $v1Response->assertStatus(200)
            ->assertJsonPath('version', '1.0.0');
    }

    public function test_openapi_specification_endpoint(): void
    {
        $response = $this->getJson('/api/v1/openapi.json');
        $response->assertStatus(200)
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonStructure([
                'openapi',
                'info' => ['title', 'version'],
                'paths' => [
                    '/contacts',
                    '/contacts/export.csv',
                    '/campaigns',
                    '/deliverability',
                ],
                'components' => ['securitySchemes', 'schemas'],
            ]);
    }

    public function test_contacts_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/contacts')->assertStatus(401);
        $this->postJson('/api/v1/contacts', ['email' => 'test@example.com'])->assertStatus(401);
        $this->get('/api/v1/contacts/export.csv')->assertStatus(401);
    }

    public function test_contacts_crud_and_account_isolation(): void
    {
        // Create contacts for this account
        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'email' => 'active.sub@example.com',
            'status' => 'subscribed',
        ]);

        // Create contact for another account to verify isolation
        $otherAccount = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'name' => 'Other Group',
            'status' => 'active',
        ]);
        $otherNewsletter = Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $otherAccount->id,
            'name' => 'Other Dispatch',
            'slug' => 'other-dispatch',
            'from_name' => 'Other Author',
            'from_email' => 'author@other-dispatch.local',
            'postal_address' => '456 Other Way, New York, NY 10001',
        ]);
        Contact::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $otherNewsletter->id,
            'email' => 'alien.sub@example.com',
            'status' => 'subscribed',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->getJson('/api/v1/contacts');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'active.sub@example.com');

        // Test POST contact
        $postResponse = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->postJson('/api/v1/contacts', [
                'email' => 'new.user@example.com',
            ]);

        $postResponse->assertStatus(201)
            ->assertJsonPath('data.email', 'new.user@example.com')
            ->assertJsonPath('data.newsletterId', $this->newsletter->id);
    }

    public function test_contacts_hygiene_and_suppression(): void
    {
        // Disposable domain rejected
        $disposable = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->postJson('/api/v1/contacts', ['email' => 'spam@mailinator.com']);
        $disposable->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed');

        // Suppressed email rejected
        $hash = hash_hmac('sha256', 'blocked@example.com', config('app.key'));
        Suppression::create(['email_hash' => $hash, 'reason' => 'bounce']);

        $suppressed = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->postJson('/api/v1/contacts', ['email' => 'blocked@example.com']);
        $suppressed->assertStatus(422)
            ->assertJsonPath('error.code', 'suppressed');
    }

    public function test_campaigns_endpoints_list_and_create(): void
    {
        Campaign::create([
            'id' => (string) Str::ulid(),
            'newsletter_id' => $this->newsletter->id,
            'subject' => 'Existing Campaign',
            'status' => 'sent',
            'sent_count' => 100,
        ]);

        $list = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->getJson('/api/v1/campaigns');
        $list->assertStatus(200)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.subject', 'Existing Campaign');

        // Create new draft
        $create = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->postJson('/api/v1/campaigns', [
                'subject' => 'REST Draft Issue',
                'markdown' => '## Content from API',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.subject', 'REST Draft Issue')
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('campaigns', [
            'subject' => 'REST Draft Issue',
            'newsletter_id' => $this->newsletter->id,
            'status' => 'draft',
        ]);
    }

    public function test_deliverability_metrics_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->getJson('/api/v1/deliverability');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'healthScore',
                    'healthStatus',
                    'metrics' => [
                        'activeSubscribers',
                        'suppressedCount',
                        'sentLast30Days',
                        'bounceRatePercent',
                        'complaintRatePercent',
                    ],
                    'circuitBreakers' => ['bounceThreshold', 'complaintThreshold', 'status'],
                ],
            ]);
    }

    public function test_mcp_get_and_post_endpoints(): void
    {
        // GET returns protocol and serverInfo without -32601 error
        $getResponse = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->getJson('/api/mcp');
        $getResponse->assertStatus(200)
            ->assertJsonPath('result.serverInfo.name', 'spiggle-mail');

        // POST initialize
        $postResponse = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->postJson('/api/mcp', [
                'jsonrpc' => '2.0',
                'id' => 'req-1',
                'method' => 'initialize',
                'params' => [],
            ]);
        $postResponse->assertStatus(200)
            ->assertJsonPath('result.serverInfo.name', 'spiggle-mail');
    }

    public function test_domain_multi_account_unique_constraint_safety(): void
    {
        // First account owns spiggle-mail.local
        Domain::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'domain' => 'spiggle-mail.local',
            'status' => 'verified',
        ]);

        // Second account visiting /domains must not crash with duplicate key exception
        $secondUser = User::factory()->create(['email' => 'second@spiggle-mail.local']);
        $secondAccount = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $secondUser->id,
            'name' => 'Second Group',
            'status' => 'active',
        ]);
        Newsletter::create([
            'id' => (string) Str::ulid(),
            'account_id' => $secondAccount->id,
            'name' => 'Second News',
            'slug' => 'second-news',
            'from_name' => 'Second',
            'from_email' => 'second@spiggle-mail.local',
            'postal_address' => '789 Second Blvd, Austin, TX 78701',
        ]);

        $this->actingAs($secondUser);
        session(['authenticated_user_id' => $secondUser->id]);

        $response = $this->get('/domains');
        $response->assertStatus(200);

        // Verify a distinct domain was provisioned for second account
        $this->assertTrue(Domain::where('account_id', $secondAccount->id)->exists());
    }

    public function test_api_keys_dashboard_creation_and_scoping(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $createResponse = $this->post('/api-keys', [
            'name' => 'Dashboard Created Key',
            'role' => 'full_access',
        ]);

        $createResponse->assertRedirect('/api-keys');
        $this->assertDatabaseHas('api_keys', [
            'account_id' => $this->account->id,
            'name' => 'Dashboard Created Key',
            'role' => 'full_access',
        ]);
    }
}
