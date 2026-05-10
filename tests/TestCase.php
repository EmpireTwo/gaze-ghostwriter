<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests;

use Empire2\GazeGhostwriter\GazeGhostwriterServiceProvider;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Laravel\Ai\AiServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Livewire\LivewireServiceProvider;
use Naoray\GazeLaravel\EncryptedBlob;
use Naoray\GazeLaravel\Facades\Gaze;
use Naoray\GazeLaravel\GazeServiceProvider;
use Naoray\GazeLaravel\GazeSession;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AiServiceProvider::class,
            PermissionServiceProvider::class,
            PennantServiceProvider::class,
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
        // Default the Gaze boundary ON in tests; individual tests still
        // call `Gaze::fake(...)` to install a deterministic clean/restore
        // pair. Tests that need the boundary OFF flip the flag locally.
        $app['config']->set('gaze-ghostwriter.gaze_enabled', true);

        // Point the package at the in-package fixture User so tests have a
        // concrete Authenticatable to rely on.
        $app['config']->set('gaze-ghostwriter.user_model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);

        // Pennant: array driver in tests.
        $app['config']->set('pennant.default', 'array');

        // Spatie permission cache key — keep default; just ensure it's set.
        $app['config']->set('permission.cache.store', 'array');

        // The default route middleware ['web', 'auth'] does not gate
        // by role — but several tests assert that non-admin users get
        // 403. Mirror what a host would do and require an admin role.
        $app['config']->set('gaze-ghostwriter.middleware', ['web', 'auth', 'role:admin|super-admin']);

        // Tests assert that recorded GazeInvocation argv[0] equals
        // config('gaze.binary'); set a deterministic value.
        $app['config']->set('gaze.binary', 'gaze');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Register a fixture path for anonymous Blade components the
        // package's bundled views reference (e.g. <x-admin.pagination>).
        Blade::anonymousComponentPath(__DIR__.'/Fixtures/views', null);

        // Register the fixtures path for plain Blade view lookups so the
        // bundled Livewire pages can resolve their layout
        // (`components.layouts.app`).
        View::addLocation(__DIR__.'/Fixtures/views');

        // Spatie Permission ships a Role middleware but does not register a
        // global alias — hosts opt in. Tests rely on the `role:...` syntax.
        $this->app['router']->aliasMiddleware('role', RoleMiddleware::class);

        // Install a default Gaze fake so the GuardedAgentRunner has a
        // deterministic clean/restore pair when tests do not provide
        // their own. The default identity handlers are important: the
        // built-in FakeGaze::restore() returns the *original* prompt
        // text from the session blob, which corrupts LLM response
        // restoration in tests. Identity restore preserves the LLM
        // response as-is. Tests can call Gaze::fake(...) again to
        // override with their own clean/restore handlers.
        Gaze::fake(
            cleanHandler: fn (string $text): GazeSession => new GazeSession(
                cleanText: $text,
                ciphertext: EncryptedBlob::wrap('test-blob'),
                detections: 0,
            ),
            restoreHandler: fn (GazeSession $session, string $text): string => $text,
        );
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->createUsersTable();
        $this->createCustomersTable();
        $this->createNotificationsTable();
        $this->createPermissionTables();
        $this->loadMigrationsFrom(__DIR__.'/../vendor/laravel/pennant/database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();
        $this->seedDefaultRoles();
    }

    private function seedDefaultRoles(): void
    {
        foreach (['admin', 'super-admin'] as $name) {
            Role::findOrCreate($name);
        }
    }

    /**
     * Spatie ships its permission migrations as `.stub`. We mimic them in
     * code so the in-memory SQLite DB has the necessary tables.
     */
    private function createPermissionTables(): void
    {
        if (Schema::hasTable('roles')) {
            return;
        }

        Schema::create('permissions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    private function createNotificationsTable(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    private function createUsersTable(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    private function createCustomersTable(): void
    {
        if (Schema::hasTable('customers')) {
            return;
        }

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->timestamps();
        });
    }
}
