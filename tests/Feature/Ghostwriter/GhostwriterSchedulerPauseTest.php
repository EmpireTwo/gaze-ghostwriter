<?php

declare(strict_types=1);

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Livewire\Admin\GhostwriterSettings;
use Empire2\GazeGhostwriter\Support\GhostwriterSchedulerPause;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ghostwriterAdminForSchedulerPauseTest(): User
{
    Role::findOrCreate('admin');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

beforeEach(function (): void {
    Cache::forget(GhostwriterSchedulerPause::KEY);
});

test('scheduler is not paused by default', function (): void {
    expect(GhostwriterSchedulerPause::isPaused())->toBeFalse();
});

test('pause sets the cache flag', function (): void {
    GhostwriterSchedulerPause::pause();

    expect(GhostwriterSchedulerPause::isPaused())->toBeTrue();
});

test('resume clears the cache flag', function (): void {
    GhostwriterSchedulerPause::pause();
    GhostwriterSchedulerPause::resume();

    expect(GhostwriterSchedulerPause::isPaused())->toBeFalse();
});

test('toggle flips the state and returns new value', function (): void {
    $nowPaused = GhostwriterSchedulerPause::toggle();
    expect($nowPaused)->toBeTrue();
    expect(GhostwriterSchedulerPause::isPaused())->toBeTrue();

    $nowPaused = GhostwriterSchedulerPause::toggle();
    expect($nowPaused)->toBeFalse();
    expect(GhostwriterSchedulerPause::isPaused())->toBeFalse();
});

test('admin can toggle scheduler pause via settings component', function (): void {
    $admin = ghostwriterAdminForSchedulerPauseTest();

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->assertSet('schedulerPaused', false)
        ->call('toggleSchedulerPause')
        ->assertSet('schedulerPaused', true);

    expect(GhostwriterSchedulerPause::isPaused())->toBeTrue();
});

test('admin can resume scheduler via settings component', function (): void {
    GhostwriterSchedulerPause::pause();
    $admin = ghostwriterAdminForSchedulerPauseTest();

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->assertSet('schedulerPaused', true)
        ->call('toggleSchedulerPause')
        ->assertSet('schedulerPaused', false);

    expect(GhostwriterSchedulerPause::isPaused())->toBeFalse();
});
