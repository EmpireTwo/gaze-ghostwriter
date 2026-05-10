<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->unsignedTinyInteger('user_rating')->nullable()->after('status');
            $table->text('rating_comment')->nullable()->after('user_rating');
            $table->timestamp('rated_at')->nullable()->after('rating_comment');
            $table->foreignId('rated_by_user_id')->nullable()->after('rated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->dropForeign(['rated_by_user_id']);
            $table->dropColumn([
                'user_rating',
                'rating_comment',
                'rated_at',
                'rated_by_user_id',
            ]);
        });
    }
};
