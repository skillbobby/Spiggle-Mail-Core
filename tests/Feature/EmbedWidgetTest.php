<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmbedWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_visitor_can_subscribe_via_embed_endpoint(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'demouser3@spiggle-mail.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Test Account 3',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = (string) Str::ulid();
        DB::table('newsletters')->insert([
            'id' => $newsletterId,
            'account_id' => $accountId,
            'name' => 'Tech Weekly',
            'slug' => 'tech-weekly',
            'from_name' => 'Editor',
            'from_email' => 'editor@tech-weekly.local',
            'postal_address' => '456 Oak St',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Submit embed subscription
        $response = $this->post('/embed/subscribe', [
            'newsletter_slug' => 'tech-weekly',
            'email' => 'new.fan@gmail.com',
        ]);

        $response->assertSessionHas('success');

        $contact = DB::table('contacts')->where('email', 'new.fan@gmail.com')->first();
        $this->assertNotNull($contact);
        $this->assertEquals('embed_widget', $contact->source);
        $this->assertEquals('subscribed', $contact->status);
    }

    public function test_embed_endpoint_rejects_disposable_email(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Demo User',
            'email' => 'demouser4@spiggle-mail.local',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Test Account 4',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newsletterId = (string) Str::ulid();
        DB::table('newsletters')->insert([
            'id' => $newsletterId,
            'account_id' => $accountId,
            'name' => 'Tech Weekly 2',
            'slug' => 'tech-weekly-2',
            'from_name' => 'Editor',
            'from_email' => 'editor@tech-weekly.local',
            'postal_address' => '456 Oak St',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Disposable domain test
        $response = $this->post('/embed/subscribe', [
            'newsletter_slug' => 'tech-weekly-2',
            'email' => 'spammer@mailinator.com',
        ]);

        $response->assertSessionHas('error');
        $contact = DB::table('contacts')->where('email', 'spammer@mailinator.com')->first();
        $this->assertNull($contact);
    }
}
