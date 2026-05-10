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
        Schema::create('ghostwriter_prompt_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_mail_message_id')
                ->constrained('ghostwriter_support_mail_messages')
                ->cascadeOnDelete();
            $table->foreignId('support_draft_id')
                ->nullable()
                ->constrained('ghostwriter_support_drafts')
                ->nullOnDelete();
            $table->mediumText('system_prompt');
            $table->mediumText('user_prompt');
            $table->json('response_structured')->nullable();
            $table->string('ai_model')->nullable();
            $table->string('ai_provider')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('cache_read_input_tokens')->nullable();
            $table->unsignedInteger('cache_write_input_tokens')->nullable();
            $table->unsignedInteger('reasoning_tokens')->nullable();
            $table->boolean('is_regeneration')->default(false);
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghostwriter_prompt_history');
    }
};
