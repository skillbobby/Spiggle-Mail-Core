<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function createNewsletter(string $slug, ?string $description = null): Newsletter
    {
        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'name' => 'Archive Author',
            'email' => 'author_' . Str::random(5) . '@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        \Illuminate\Support\Facades\DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Archive Account ' . Str::random(4),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Newsletter::create([
            'account_id' => $accountId,
            'name' => 'Engineering Journal',
            'slug' => $slug,
            'from_name' => 'Lead Architect',
            'from_email' => 'architect@example.com',
            'postal_address' => '42 Matrix Way',
            'description' => $description ?? 'Dispatches from high scale systems.',
        ]);
    }

    public function test_can_view_public_archive_index(): void
    {
        $slug = 'engineering-journal-' . strtolower(Str::random(4));
        $newsletter = $this->createNewsletter($slug);

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Architecture Lessons in 2026',
            'preview_text' => 'What changed and what stayed the same',
            'archive_slug' => 'architecture-lessons-2026',
            'archive_pinned' => true,
            'archive_listed' => true,
            'format' => 'rich',
            'html' => '<h1>Scaling to Millions</h1>',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $response = $this->get("/archive/{$slug}");
        $response->assertStatus(200);
        $response->assertSee('Engineering Journal');
        $response->assertSee('Architecture Lessons in 2026');
    }

    public function test_can_view_individual_public_archive_issue_and_increments_views(): void
    {
        $slug = 'dev-dispatch-' . strtolower(Str::random(4));
        $newsletter = $this->createNewsletter($slug);

        $postSlug = 'zero-downtime-deployments-' . strtolower(Str::random(4));
        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Zero Downtime Deployments at Scale',
            'preview_text' => 'Blue/green strategies with zero dropped connections',
            'archive_slug' => $postSlug,
            'archive_pinned' => false,
            'archive_listed' => true,
            'format' => 'rich',
            'html' => '<p>The secret is graceful drain timeouts.</p>',
            'status' => 'sent',
            'web_views' => 4,
            'sent_at' => now(),
        ]);

        $response = $this->get("/archive/{$slug}/p/{$postSlug}");
        $response->assertStatus(200);
        $response->assertSee('Zero Downtime Deployments at Scale');
        $response->assertSee('The secret is graceful drain timeouts.');

        // Verify web_views incremented
        $this->assertEquals(5, $campaign->fresh()->web_views);
    }
}
