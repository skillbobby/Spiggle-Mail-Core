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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('archive_slug', 255)->nullable()->after('preview_text')->index();
            $table->boolean('archive_pinned')->default(false)->after('archive_slug');
            $table->boolean('archive_listed')->default(true)->after('archive_pinned');
            $table->unsignedInteger('web_views')->default(0)->after('complaint_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['archive_slug']);
            $table->dropColumn(['archive_slug', 'archive_pinned', 'archive_listed', 'web_views']);
        });
    }
};
