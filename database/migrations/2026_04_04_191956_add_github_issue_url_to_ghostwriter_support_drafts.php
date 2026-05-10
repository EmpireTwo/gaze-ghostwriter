<?php

declare(strict_types=1);

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
            $table->string('github_issue_url', 2048)->nullable()->after('sent_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->dropColumn('github_issue_url');
        });
    }
};
