<?php

declare(strict_types=1);

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Livewire\Admin\SmartActionsManager;
use Empire2\GazeGhostwriter\Models\GhostwriterSmartAction;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function smartActionsAdmin(): User
{
    Role::findOrCreate('admin');

    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

test('admin can create a smart action', function () {
    Livewire::actingAs(smartActionsAdmin())
        ->test(SmartActionsManager::class)
        ->set('newMarker', 'INVOICES')
        ->set('newLabel', 'Rechnungen')
        ->set('newPromptHint', 'Wenn es um Rechnungen oder Zahlungen geht')
        ->set('newRouteTemplate', '/admin/billings/customer/{customerId}')
        ->call('addAction')
        ->assertHasNoErrors();

    $action = GhostwriterSmartAction::query()->where('marker', 'INVOICES')->first();

    expect($action)->not->toBeNull()
        ->and($action->label)->toBe('Rechnungen')
        ->and($action->is_active)->toBeTrue();
});

test('marker must be screaming snake case', function () {
    Livewire::actingAs(smartActionsAdmin())
        ->test(SmartActionsManager::class)
        ->set('newMarker', 'lowercase')
        ->set('newLabel', 'Test')
        ->set('newPromptHint', 'Test hint')
        ->set('newRouteTemplate', '/admin/test')
        ->call('addAction')
        ->assertHasErrors(['newMarker']);
});

test('marker must be unique', function () {
    GhostwriterSmartAction::query()->create([
        'marker' => 'INVOICES',
        'label' => 'Rechnungen',
        'prompt_hint' => 'Hint',
        'route_template' => '/admin/test',
    ]);

    Livewire::actingAs(smartActionsAdmin())
        ->test(SmartActionsManager::class)
        ->set('newMarker', 'INVOICES')
        ->set('newLabel', 'Duplicate')
        ->set('newPromptHint', 'Hint')
        ->set('newRouteTemplate', '/admin/test')
        ->call('addAction')
        ->assertHasErrors(['newMarker']);
});

test('admin can toggle active state', function () {
    $action = GhostwriterSmartAction::query()->create([
        'marker' => 'TEST',
        'label' => 'Test',
        'prompt_hint' => 'Hint',
        'route_template' => '/test',
        'is_active' => true,
    ]);

    Livewire::actingAs(smartActionsAdmin())
        ->test(SmartActionsManager::class)
        ->call('toggleActive', $action->id);

    expect($action->fresh()->is_active)->toBeFalse();
});

test('admin can delete a smart action', function () {
    $action = GhostwriterSmartAction::query()->create([
        'marker' => 'DELETE_ME',
        'label' => 'Delete',
        'prompt_hint' => 'Hint',
        'route_template' => '/test',
    ]);

    Livewire::actingAs(smartActionsAdmin())
        ->test(SmartActionsManager::class)
        ->call('deleteAction', $action->id);

    expect(GhostwriterSmartAction::query()->find($action->id))->toBeNull();
});

test('admin can edit a smart action', function () {
    $action = GhostwriterSmartAction::query()->create([
        'marker' => 'OLD_MARKER',
        'label' => 'Old',
        'prompt_hint' => 'Old hint',
        'route_template' => '/old',
    ]);

    Livewire::actingAs(smartActionsAdmin())
        ->test(SmartActionsManager::class)
        ->call('startEditing', $action->id)
        ->set('editMarker', 'NEW_MARKER')
        ->set('editLabel', 'New')
        ->set('editPromptHint', 'New hint')
        ->set('editRouteTemplate', '/new')
        ->call('saveEditing')
        ->assertHasNoErrors();

    $updated = $action->fresh();

    expect($updated->marker)->toBe('NEW_MARKER')
        ->and($updated->label)->toBe('New');
});

test('buildPromptInstructions returns null when no active actions exist', function () {
    expect(GhostwriterSmartAction::buildPromptInstructions())->toBeNull();
});

test('buildPromptInstructions generates block from active actions', function () {
    GhostwriterSmartAction::query()->create([
        'marker' => 'INVOICES',
        'label' => 'Rechnungen',
        'prompt_hint' => 'Wenn es um Rechnungen geht',
        'route_template' => '/admin/billings/customer/{customerId}',
        'is_active' => true,
    ]);

    GhostwriterSmartAction::query()->create([
        'marker' => 'INACTIVE',
        'label' => 'Disabled',
        'prompt_hint' => 'Should not appear',
        'route_template' => '/nope',
        'is_active' => false,
    ]);

    $block = GhostwriterSmartAction::buildPromptInstructions();

    expect($block)->toContain('INVOICES')
        ->and($block)->toContain('Wenn es um Rechnungen geht')
        ->and($block)->not->toContain('INACTIVE');
});
