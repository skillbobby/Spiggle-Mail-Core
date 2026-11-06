<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'id' => (string) Str::ulid(),
                'title' => 'Weekly Curated Digest',
                'category' => 'Weekly Digest',
                'subject_default' => 'Issue #{{ current_year }} — Key ideas & curated reads',
                'preview_default' => 'Here are our top insights and stories this week.',
                'html_content' => <<<HTML
<h2>Hello {{ contact.name }},</h2>
<p>Welcome to this week's edition of <strong>{{ newsletter.name }}</strong>. Here are the top three stories and technical discoveries we curated for you:</p>
<hr style="border:0; border-top:1px solid #e4e4e7; margin:24px 0;" />
<h3>1. Scalable Queue Architectures</h3>
<p>Modern token-bucket queues allow predictable rate limiting without starving burst workflows. Understanding Redis streams vs traditional message brokers.</p>
<h3>2. Deep-Dive into RFC 8058</h3>
<p>One-click unsubscribe headers protect your sender domain reputation. Google and Yahoo mandate this for bulk senders in 2024 and beyond.</p>
<p>What did you think of today's issue? Hit reply and let us know!</p>
HTML,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'title' => 'Product Feature Announcement',
                'category' => 'Product Updates',
                'subject_default' => 'Introducing our new feature on {{ newsletter.name }}',
                'preview_default' => 'We just shipped something we think you will love.',
                'html_content' => <<<HTML
<h2>Hi {{ contact.name }},</h2>
<p>We're thrilled to announce a major update to {{ newsletter.name }}. Over the past month, our team has been focused on performance and deliverability.</p>
<div style="background:#f4f4f5; padding:20px; border-radius:8px; margin:20px 0;">
  <h4 style="margin-top:0;">What's New:</h4>
  <ul>
    <li>High-speed CSV imports with automated typo cleanup.</li>
    <li>Zero-delay DNS health inspector for SPF, DKIM, and DMARC.</li>
    <li>Amazon SES v2 integration with auto-pause safety limits.</li>
  </ul>
</div>
<p>Try it out now and share your feedback with us!</p>
HTML,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::ulid(),
                'title' => 'Subscriber Welcome & Orientation',
                'category' => 'Welcome & Onboarding',
                'subject_default' => 'Welcome to {{ newsletter.name }}!',
                'preview_default' => 'Here is what you can expect in your inbox.',
                'html_content' => <<<HTML
<h2>Welcome, {{ contact.name }}!</h2>
<p>Thanks for subscribing to <strong>{{ newsletter.name }}</strong>. We're excited to have you join our reading community.</p>
<p>Every week, we send concise, actionable dispatches directly to <code>{{ contact.email }}</code>.</p>
<p><strong>A quick favor:</strong> To make sure our emails never end up in your spam or promotions tab, please reply to this email with a quick "Hello" or move it to your Primary inbox.</p>
<p>Talk soon,<br/>The Team</p>
HTML,
                'is_system' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('email_templates')->upsert($templates, ['id']);
    }
}
