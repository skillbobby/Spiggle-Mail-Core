<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private string $rawApiKey;

    private ApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->user = User::factory()->create(['email' => 'publisher@spiggle-mail.local']);
        $this->account = Account::create([
            'id' => (string) Str::ulid(),
            'user_id' => $this->user->id,
            'name' => 'Publisher Account',
            'status' => 'active',
        ]);

        $this->rawApiKey = 'spm_'.Str::random(32);
        $this->apiKey = ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Upload Test Key',
            'key_prefix' => substr($this->rawApiKey, 0, 8),
            'key_hash' => hash('sha256', $this->rawApiKey),
            'role' => 'full_access',
        ]);
    }

    public function test_multipart_image_upload_success(): void
    {
        $this->actingAs($this->user);
        session(['authenticated_user_id' => $this->user->id]);

        $file = UploadedFile::fake()->image('weekly-chart.png', 800, 600)->size(500);

        $response = $this->postJson('/uploads', [
            'image' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'url',
                'fullUrl',
                'fileName',
                'originalName',
                'size',
                'mimeType',
                'markdown',
                'html',
            ]);

        $responseData = $response->json();
        $this->assertStringStartsWith('/storage/uploads/', $responseData['url']);
        $this->assertStringContainsString('weekly-chart', $responseData['fileName']);
        $this->assertStringContainsString('![weekly chart]', $responseData['markdown']);
        $this->assertStringContainsString('<img src="/storage/uploads/', $responseData['html']);

        // Verify storage on disk
        $relativeStoragePath = str_replace('/storage/', '', $responseData['url']);
        Storage::disk('public')->assertExists($relativeStoragePath);
    }

    public function test_api_bearer_token_upload_success(): void
    {
        $file = UploadedFile::fake()->image('architecture.webp', 1200, 800)->size(300);

        $response = $this->withHeader('Authorization', "Bearer {$this->rawApiKey}")
            ->postJson('/api/v1/uploads', [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('mimeType', 'image/webp');
    }

    public function test_file_exceeding_size_limit_is_rejected(): void
    {
        $this->actingAs($this->user);

        // 5MB file exceeds 4MB limit
        $file = UploadedFile::fake()->create('giant-photo.jpg', 5120, 'image/jpeg');

        $response = $this->postJson('/uploads', [
            'image' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'file_too_large');
    }

    public function test_non_image_mime_type_is_rejected(): void
    {
        $this->actingAs($this->user);

        $file = UploadedFile::fake()->create('malicious.php', 10, 'text/x-php');

        $response = $this->postJson('/uploads', [
            'image' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_mime_type');
    }

    public function test_read_only_api_key_cannot_upload(): void
    {
        $readOnlySecret = 'spm_readonly_'.Str::random(24);
        ApiKey::create([
            'id' => (string) Str::ulid(),
            'account_id' => $this->account->id,
            'name' => 'Read Only Key',
            'key_prefix' => 'spm_read',
            'key_hash' => hash('sha256', $readOnlySecret),
            'role' => 'read_only',
        ]);

        $file = UploadedFile::fake()->image('test.png');

        $response = $this->withHeader('Authorization', "Bearer {$readOnlySecret}")
            ->postJson('/api/v1/uploads', [
                'image' => $file,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
}
