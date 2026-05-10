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
            $table->dropColumn(['gaze_turn_count', 'gaze_sanitized_chars']);
        });

        Schema::table('ghostwriter_support_drafts', function (Blueprint $table): void {
            $table->longText('clean_prompt')->nullable()->after('gaze_warnings');
            $table->json('llm_raw_response')->nullable()->after('clean_prompt');
            $table->unsignedInteger('gaze_detections')->nullable()->after('llm_raw_response');
            $table->unsignedInteger('gaze_duration_ms')->nullable()->after('gaze_detections');
            $table->timestamp('gaze_ran_at')->nullable()->after('gaze_duration_ms');
        });
    }
};
