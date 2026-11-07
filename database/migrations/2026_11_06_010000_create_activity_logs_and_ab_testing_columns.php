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
        // 1. Create activity_logs table
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->char('id', 26)->primary();
                $table->char('account_id', 26)->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->text('description');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('properties')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add A/B testing columns to campaigns table
        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                if (! Schema::hasColumn('campaigns', 'subject_b')) {
                    $table->string('subject_b', 255)->nullable()->after('subject');
                }
                if (! Schema::hasColumn('campaigns', 'ab_test_enabled')) {
                    $table->boolean('ab_test_enabled')->default(false)->after('subject_b');
                }
                if (! Schema::hasColumn('campaigns', 'ab_winner')) {
                    $table->string('ab_winner', 10)->nullable()->after('ab_test_enabled');
                }
                if (! Schema::hasColumn('campaigns', 'ab_split_ratio')) {
                    $table->unsignedInteger('ab_split_ratio')->default(50)->after('ab_winner');
                }
                if (! Schema::hasColumn('campaigns', 'variant_a_opens')) {
                    $table->unsignedInteger('variant_a_opens')->default(0)->after('open_count');
                }
                if (! Schema::hasColumn('campaigns', 'variant_b_opens')) {
                    $table->unsignedInteger('variant_b_opens')->default(0)->after('variant_a_opens');
                }
            });
        }

        // 3. Add ab_variant to messages table
        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                if (! Schema::hasColumn('messages', 'ab_variant')) {
                    $table->string('ab_variant', 10)->nullable()->after('status');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        if (Schema::hasTable('campaigns')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn([
                    'subject_b',
                    'ab_test_enabled',
                    'ab_winner',
                    'ab_split_ratio',
                    'variant_a_opens',
                    'variant_b_opens',
                ]);
            });
        }

        if (Schema::hasTable('messages')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->dropColumn(['ab_variant']);
            });
        }
    }
};
