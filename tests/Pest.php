<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class)->in('Feature', 'Unit');

/*
| Some package migrations create foreign-key constraints against a `users`
| table the host owns. The default Pest hook below provisions a minimal
| users table so the package migrations succeed under Testbench's in-memory
| SQLite database. Override this in individual tests if you need a richer
| users schema.
*/
uses()->beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique()->nullable();
            $table->timestamps();
        });
    }
})->in('Feature', 'Unit');
