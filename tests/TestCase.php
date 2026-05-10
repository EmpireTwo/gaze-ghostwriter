<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests;

use Empire2\GazeGhostwriter\GazeGhostwriterServiceProvider;
use Livewire\LivewireServiceProvider;
use Naoray\GazeLaravel\GazeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            GazeServiceProvider::class,
            LivewireServiceProvider::class,
            GazeGhostwriterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('gaze-ghostwriter.enabled', true);
        $app['config']->set('gaze-ghostwriter.gaze_enabled', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        // The host's `users` table is required by the package's user-FK migrations.
        // Tests that need the relationships should also create a stub users table —
        // see `tests/Pest.php` for a default that covers most cases.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
