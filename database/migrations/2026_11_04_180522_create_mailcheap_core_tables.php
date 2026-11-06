<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Accounts (Tenants)
        Schema::create('accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Personal');
            $table->string('status')->default('active'); // active, paused, blocked
            $table->string('pause_reason')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // 2. Newsletters (up to 3 per account)
        Schema::create('newsletters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('from_name');
            $table->string('from_email');
            $table->text('postal_address');
            $table->text('description')->nullable();
            $table->string('reply_to')->nullable();
            $table->boolean('double_opt_in')->default(false);
            $table->boolean('list_cleanup_enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // 3. Domains
        Schema::create('domains', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->string('status')->default('pending'); // pending, verified, failed
            $table->json('dkim_tokens')->nullable();
            $table->string('mail_from_domain')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // 4. Contacts / Subscribers
        Schema::create('contacts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('newsletter_id')->constrained('newsletters')->cascadeOnDelete();
            $table->string('email');
            $table->string('status')->default('subscribed'); // subscribed, unsubscribed, bounced, complained, dormant
            $table->string('source')->default('website');
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('last_opened_at')->nullable();
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique(['newsletter_id', 'email']);
            $table->index(['newsletter_id', 'status']);
        });

        // 5. Campaigns
        Schema::create('campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('newsletter_id')->constrained('newsletters')->cascadeOnDelete();
            $table->string('subject');
            $table->string('preview_text')->nullable();
            $table->string('format')->default('rich'); // rich, markdown, html
            $table->longText('html')->nullable();
            $table->longText('markdown')->nullable();
            $table->string('status')->default('draft'); // draft, scheduled, sending, paused, sent, canceled
            $table->string('pause_reason')->nullable();
            $table->unsignedInteger('audience_limit')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->unsignedInteger('bounce_count')->default(0);
            $table->unsignedInteger('complaint_count')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['newsletter_id', 'status']);
        });

        // 6. Messages (Audit trail for each sent email)
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->foreignUlid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('ses_message_id')->nullable()->index();
            $table->string('status')->default('sent'); // sent, delivered, bounced, complained
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->timestamp('complained_at')->nullable();
            $table->timestamps();
        });

        // 7. Suppressions (Account local + Server Global Hashed)
        Schema::create('suppressions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->string('email_hash', 64)->index();
            $table->string('reason'); // bounce, complaint, manual, spam_trap
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'email_hash']);
        });

        // 8. Contact Imports
        Schema::create('contact_imports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('newsletter_id')->constrained('newsletters')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('filename');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('dropped_count')->default(0);
            $table->json('dropped_reasons')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('newsletters');
        Schema::dropIfExists('accounts');
    }
};
