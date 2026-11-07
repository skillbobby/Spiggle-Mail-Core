<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignReportTest extends TestCase
{
    use RefreshDatabase;

    private function createNewsletter(string $slug): Newsletter
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'user_'.Str::random(5).'@example.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $accountId = (string) Str::ulid();
        DB::table('accounts')->insert([
            'id' => $accountId,
            'user_id' => $userId,
            'name' => 'Account '.Str::random(4),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::findOrFail($userId);
        $this->actingAs($user);

        return Newsletter::create([
            'account_id' => $accountId,
            'name' => 'Tech Pulse',
            'slug' => $slug,
            'from_name' => 'Editor',
            'from_email' => 'editor@example.com',
            'postal_address' => '500 Tech Blvd',
        ]);
    }

    public function test_can_view_campaign_report_screen(): void
    {
        $newsletter = $this->createNewsletter('tech-pulse-'.Str::random(5));

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'The Next Era of Infrastructure',
            'preview_text' => 'Deep dive into performance',
            'archive_slug' => 'next-era-'.Str::random(4),
            'archive_pinned' => false,
            'archive_listed' => true,
            'format' => 'rich',
            'html' => '<p>Hello world of speed!</p>',
            'status' => 'sent',
            'total_recipients' => 10,
            'sent_count' => 10,
            'delivered_count' => 10,
            'open_count' => 5,
            'click_count' => 2,
            'bounce_count' => 0,
            'complaint_count' => 0,
        ]);

        $response = $this->get("/campaigns/{$campaign->id}");
        $response->assertStatus(200);
        $response->assertSee('The Next Era of Infrastructure');
    }

    public function test_can_pause_and_resume_campaign(): void
    {
        $newsletter = $this->createNewsletter('weekly-brief-'.Str::random(5));

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Vol. 42',
            'format' => 'rich',
            'html' => '<p>Issue 42</p>',
            'status' => 'sending',
            'total_recipients' => 50,
            'sent_count' => 10,
        ]);

        // Pause
        $pauseRes = $this->post("/campaigns/{$campaign->id}/pause");
        $pauseRes->assertRedirect();
        $this->assertEquals('paused', $campaign->fresh()->status);

        // Resume
        $resumeRes = $this->post("/campaigns/{$campaign->id}/resume");
        $resumeRes->assertRedirect();
        $this->assertContains($campaign->fresh()->status, ['sending', 'sent']);
    }

    public function test_can_duplicate_campaign(): void
    {
        $newsletter = $this->createNewsletter('weekly-brief-'.Str::random(5));

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Original Issue',
            'preview_text' => 'Original preview',
            'format' => 'rich',
            'html' => '<p>Original body</p>',
            'status' => 'sent',
            'total_recipients' => 10,
            'sent_count' => 10,
        ]);

        $dupRes = $this->post("/campaigns/{$campaign->id}/duplicate");
        $dupRes->assertRedirect();

        $cloned = Campaign::where('subject', 'like', '%Original Issue%')->where('id', '!=', $campaign->id)->first();
        $this->assertNotNull($cloned);
        $this->assertEquals('draft', $cloned->status);
        $this->assertEquals(0, $cloned->sent_count);
    }

    public function test_can_toggle_archive_pin(): void
    {
        $newsletter = $this->createNewsletter('weekly-brief-'.Str::random(5));

        $campaign = Campaign::create([
            'newsletter_id' => $newsletter->id,
            'subject' => 'Issue to Pin',
            'format' => 'rich',
            'html' => '<p>Issue text</p>',
            'status' => 'sent',
            'archive_pinned' => false,
        ]);

        $this->post("/campaigns/{$campaign->id}/toggle-pin");
        $this->assertTrue((bool) $campaign->fresh()->archive_pinned);

        $this->post("/campaigns/{$campaign->id}/toggle-pin");
        $this->assertFalse((bool) $campaign->fresh()->archive_pinned);
    }
}
