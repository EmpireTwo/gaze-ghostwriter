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
            $table->timestamp('sent_at')->nullable()->after('rated_by_user_id');
            $table->string('sent_message_id', 998)->nullable()->after('sent_at');
            $table->foreignId('sent_by_user_id')->nullable()->after('sent_message_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->dropForeign(['sent_by_user_id']);
            $table->dropColumn(['sent_at', 'sent_message_id', 'sent_by_user_id']);
        });
    }
};
