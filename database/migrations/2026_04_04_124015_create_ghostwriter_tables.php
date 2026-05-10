<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ghostwriter_support_mail_messages', function (Blueprint $table) {
            $table->id();
            $table->string('rfc_message_id')->unique();
            $table->unsignedInteger('imap_uid')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_emails');
            $table->json('cc_emails')->nullable();
            $table->string('subject')->nullable();
            $table->text('body_text');
            $table->text('body_html')->nullable();
            $table->timestamp('received_at');
            $table->boolean('matches_support_address')->default(false);
            $table->timestamps();
        });

        Schema::create('ghostwriter_support_mail_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_mail_message_id')
                ->constrained('ghostwriter_support_mail_messages')
                ->cascadeOnDelete();
            $table->string('role', 32);
            $table->text('content');
            $table->json('embedding')->nullable();
            $table->timestamps();
        });

        Schema::create('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_mail_message_id')
                ->constrained('ghostwriter_support_mail_messages')
                ->cascadeOnDelete();
            $table->text('draft_body');
            $table->json('rationale');
            $table->string('status', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghostwriter_support_drafts');
        Schema::dropIfExists('ghostwriter_support_mail_chunks');
        Schema::dropIfExists('ghostwriter_support_mail_messages');
    }
};
