<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ghostwriter_support_drafts', function (Blueprint $table) {
            $table->string('detected_language', 10)->nullable()->after('mentioned_entities');
            $table->mediumText('mail_translation')->nullable()->after('detected_language');
            $table->mediumText('draft_translation')->nullable()->after('mail_translation');
            $table->mediumText('edited_draft_translation')->nullable()->after('draft_translation');
        });
    }
};
