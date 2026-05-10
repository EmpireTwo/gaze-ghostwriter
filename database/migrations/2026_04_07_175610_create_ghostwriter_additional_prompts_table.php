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
        Schema::create('ghostwriter_additional_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 10);
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('label', 150)->nullable();
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['scope', 'user_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghostwriter_additional_prompts');
    }
};
