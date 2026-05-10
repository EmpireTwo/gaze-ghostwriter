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
        Schema::create('ghostwriter_smart_actions', function (Blueprint $table) {
            $table->id();
            $table->string('marker')->unique();
            $table->string('label');
            $table->text('prompt_hint');
            $table->string('route_template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ghostwriter_smart_actions');
    }
};
