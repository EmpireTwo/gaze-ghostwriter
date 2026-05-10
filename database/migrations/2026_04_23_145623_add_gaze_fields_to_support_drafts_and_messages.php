<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table): void {
            $table->json('gaze_warnings')->nullable()->after('draft_body');
            $table->unsignedSmallInteger('gaze_turn_count')->nullable()->after('gaze_warnings');
            $table->unsignedInteger('gaze_sanitized_chars')->nullable()->after('gaze_turn_count');
        });

        Schema::table('ghostwriter_support_mail_messages', function (Blueprint $table): void {
            $table->string('processing_status', 64)->nullable()->after('matches_support_address');
            $table->index('processing_status');
        });
    }
};
