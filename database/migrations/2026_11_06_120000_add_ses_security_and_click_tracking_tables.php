<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_clicks')) {
            Schema::create('message_clicks', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('campaign_id')->nullable()->index();
                $table->ulid('message_id')->index();
                $table->ulid('contact_id')->nullable();
                $table->string('url', 2048);
                $table->timestamp('created_at')->nullable();

                $table->index(['campaign_id', 'contact_id']);
            });
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'soft_bounce_count')) {
                $table->unsignedTinyInteger('soft_bounce_count')->default(0)->after('click_count');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'audience_limit')) {
                $table->unsignedInteger('audience_limit')->nullable()->after('total_recipients');
            }
            if (! Schema::hasColumn('campaigns', 'is_spread_send')) {
                $table->boolean('is_spread_send')->default(false)->after('ab_split_ratio');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_clicks');

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'soft_bounce_count')) {
                $table->dropColumn('soft_bounce_count');
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('campaigns', 'audience_limit')) {
                $cols[] = 'audience_limit';
            }
            if (Schema::hasColumn('campaigns', 'is_spread_send')) {
                $cols[] = 'is_spread_send';
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};
