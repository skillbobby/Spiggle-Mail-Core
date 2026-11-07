<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;

    private function createNewsletter(string $name, string $slug): Newsletter
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'API Admin',
            'email' => 'admin_'.Str::random(5).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'API Account '.Str::random(4),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Newsletter::create([
            'account_id' => $accountId,
            'name' => $name,
            'slug' => $slug,
            'from_name' => 'API Sender',
            'from_email' => 'sender@example.com',
            'postal_address' => '100 Main St',
        ]);
    }

    private function createTestApiKey(string $role = 'full_access', ?string $accountId = null): array
    {
        $rawToken = 'spm_'.Str::random(32);
        $key = ApiKey::create([
            'account_id' => $accountId,
            'name' => 'Automated Test Key',
            'key_prefix' => substr($rawToken, 0, 8),
            'key_hash' => hash('sha256', $rawToken),
            'role' => $role,
        ]);

        return [$rawToken, $key];
    }

    public function test_cannot_access_api_v1_without_token(): void
    {
        $response = $this->getJson('/api/v1/contacts');
        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'code' => 'unauthorized',
            ],
        ]);
    }

    public function test_can_list_contacts_with_valid_bearer_token(): void
    {
        $newsletter = $this->createNewsletter('API Newsletter', 'api-nl-'.Str::random(5));
        [$rawToken] = $this->createTestApiKey('full_access', $newsletter->account_id);

        Contact::create([
            'newsletter_id' => $newsletter->id,
            'email' => 'subscriber_'.Str::random(5).'@example.com',
            'status' => 'subscribed',
            'source' => 'api',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->getJson('/api/v1/contacts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['total', 'perPage', 'currentPage', 'lastPage'],
        ]);
    }

    public function test_can_create_contact_via_api(): void
    {
        $slug = 'api-create-'.Str::random(5);
        $newsletter = $this->createNewsletter('API Creation Test', $slug);
        [$rawToken] = $this->createTestApiKey('full_access', $newsletter->account_id);

        $newEmail = 'valid.dev.'.strtolower(Str::random(4)).'@gmail.com';

        $response = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->postJson('/api/v1/contacts', [
                'email' => $newEmail,
                'newsletter_slug' => $slug,
                'source' => 'custom_webhook',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.email', $newEmail);
        $response->assertJsonPath('data.source', 'custom_webhook');

        $this->assertDatabaseHas('contacts', [
            'newsletter_id' => $newsletter->id,
            'email' => $newEmail,
            'source' => 'custom_webhook',
        ]);
    }

    public function test_rejects_disposable_email_domains_via_api(): void
    {
        $newsletter = $this->createNewsletter('API Disposable Test', 'api-disp-'.Str::random(5));
        [$rawToken] = $this->createTestApiKey('full_access', $newsletter->account_id);

        $response = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->postJson('/api/v1/contacts', [
                'email' => 'throwaway@mailinator.com',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => [
                'code' => 'validation_failed',
            ],
        ]);
    }

    public function test_can_export_contacts_csv_with_bearer_token(): void
    {
        $newsletter = $this->createNewsletter('Export NL', 'export-nl-'.Str::random(5));
        [$rawToken] = $this->createTestApiKey('full_access', $newsletter->account_id);

        $uniqueEmail = 'csv.reader.'.Str::random(6).'@example.com';
        Contact::create([
            'newsletter_id' => $newsletter->id,
            'email' => $uniqueEmail,
            'status' => 'subscribed',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$rawToken}")
            ->get('/api/v1/contacts/export.csv');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('email,status,source', $response->streamedContent());
        $this->assertStringContainsString($uniqueEmail, $response->streamedContent());
    }
}
