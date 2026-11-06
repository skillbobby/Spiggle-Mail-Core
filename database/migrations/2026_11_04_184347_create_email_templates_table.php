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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
            $table->string('title');
            $table->string('category'); // e.g., 'Product Updates', 'Weekly Digest', 'Announcements', 'Welcome & Onboarding'
            $table->string('subject_default');
            $table->string('preview_default')->nullable();
            $table->longText('html_content');
            $table->boolean('is_system')->default(false); // System prebuilt vs user saved
            $table->timestamps();

            $table->index(['account_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
