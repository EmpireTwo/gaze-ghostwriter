<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ghostwriter_user_data', function (Blueprint $table) {
            $table->text('additional_prompt')->nullable()->after('reply_signature');
        });
    }

    public function down(): void
    {
        Schema::table('ghostwriter_user_data', function (Blueprint $table) {
            $table->dropColumn('additional_prompt');
        });
    }
};
