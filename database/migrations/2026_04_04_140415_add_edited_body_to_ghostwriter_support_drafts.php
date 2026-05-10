<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->longText('edited_body')->nullable()->after('draft_body');
        });
    }

    public function down(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->dropColumn('edited_body');
        });
    }
};
