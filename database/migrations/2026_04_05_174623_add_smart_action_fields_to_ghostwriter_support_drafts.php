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
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->json('smart_action_tags')->nullable()->after('github_issue_url');
            $table->json('mentioned_entities')->nullable()->after('smart_action_tags');
        });
    }

    public function down(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->dropColumn(['smart_action_tags', 'mentioned_entities']);
        });
    }
};
