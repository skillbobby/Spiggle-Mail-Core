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
        Schema::table('newsletters', function (Blueprint $table) {
            $table->boolean('welcome_enabled')->default(false)->after('double_opt_in');
            $table->string('welcome_subject')->nullable()->after('welcome_enabled');
            $table->text('welcome_markdown')->nullable()->after('welcome_subject');
            $table->string('confirmation_subject')->nullable()->after('welcome_markdown');
            $table->text('confirmation_markdown')->nullable()->after('confirmation_subject');
            $table->unsignedInteger('cleanup_inactive_issues')->default(6)->after('list_cleanup_enabled');
            $table->unsignedInteger('cleanup_inactive_days')->default(180)->after('cleanup_inactive_issues');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('confirmation_token', 64)->nullable()->index()->after('source');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_token');
            $table->string('keep_token', 64)->nullable()->index()->after('confirmed_at');
            $table->timestamp('last_chance_at')->nullable()->after('keep_token');
            $table->unsignedInteger('ignored_issues_count')->default(0)->after('last_chance_at');
            $table->timestamp('last_engaged_at')->nullable()->after('ignored_issues_count');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('paused_by')->nullable()->after('pause_reason'); // admin, rates, campaign, slice, trap, ses, gmail
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('pause_code')->nullable()->after('pause_reason');
            $table->unsignedInteger('first_send_slice')->nullable()->after('audience_limit');
            $table->unsignedInteger('first_send_held')->nullable()->after('first_send_slice');
            $table->timestamp('first_send_check_at')->nullable()->after('first_send_held');
            $table->timestamp('first_send_checked_at')->nullable()->after('first_send_check_at');
            $table->timestamp('rates_accepted_at')->nullable()->after('first_send_checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'pause_code',
                'first_send_slice',
                'first_send_held',
                'first_send_check_at',
                'first_send_checked_at',
                'rates_accepted_at',
            ]);
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['paused_by']);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'confirmation_token',
                'confirmed_at',
                'keep_token',
                'last_chance_at',
                'ignored_issues_count',
                'last_engaged_at',
            ]);
        });

        Schema::table('newsletters', function (Blueprint $table) {
            $table->dropColumn([
                'welcome_enabled',
                'welcome_subject',
                'welcome_markdown',
                'confirmation_subject',
                'confirmation_markdown',
                'cleanup_inactive_issues',
                'cleanup_inactive_days',
            ]);
        });
    }
};
