<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ghostwriter_support_mail_messages', function (Blueprint $table): void {
            $table->string('channel', 16)->default('smtp')->after('id');
            $table->unsignedBigInteger('client_user_id')->nullable()->after('from_name');
            $table->json('client_context')->nullable()->after('client_user_id');
            $table->string('source_url', 2048)->nullable()->after('client_context');
            $table->string('topic', 64)->nullable()->after('source_url');

            $table->index('channel');
            $table->index('client_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('ghostwriter_support_mail_messages', function (Blueprint $table): void {
            $table->dropIndex(['channel']);
            $table->dropIndex(['client_user_id']);
            $table->dropColumn(['channel', 'client_user_id', 'client_context', 'source_url', 'topic']);
        });
    }
};
