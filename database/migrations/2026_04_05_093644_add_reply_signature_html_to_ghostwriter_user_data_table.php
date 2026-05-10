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
        Schema::table('ghostwriter_user_data', function (Blueprint $table) {
            $table->longText('reply_signature_html')->nullable()->after('reply_signature');
        });
    }

    public function down(): void
    {
        Schema::table('ghostwriter_user_data', function (Blueprint $table) {
            $table->dropColumn('reply_signature_html');
        });
    }
};
