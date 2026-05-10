<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use App\Enums\Roles;
use Domain\Account\Models\User;
use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Enums\AdditionalPromptScope;
use Empire2\GazeGhostwriter\Livewire\Admin\PromptEditor;
use Empire2\GazeGhostwriter\Models\GhostwriterAdditionalPrompt;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function promptEditorAdmin(): User
{
    Role::findOrCreate(Roles::ADMIN->value);

    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    return $user;
}

test('prompt editor page renders core prompt and rule sections', function () {
    $admin = promptEditorAdmin();

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->assertSee('Ghostwriter 3000')
        ->assertSee('Core-Prompt (nicht änderbar)')
        ->assertSee('Globale Zusatzregeln')
        ->assertSee('Persönliche Zusatzregeln')
        ->assertSee('Vorschau');
});

test('admin can add and save a global prompt rule', function () {
    $admin = promptEditorAdmin();

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->call('addGlobalPrompt')
        ->set('globalPrompts.0.label', 'Rechnungs-Hinweis')
        ->set('globalPrompts.0.body', 'Erwähne immer online-Rechnungen.')
        ->call('saveGlobalPrompt', 0)
        ->assertHasNoErrors();

    $record = GhostwriterAdditionalPrompt::query()->first();

    expect($record)->not->toBeNull()
        ->and($record->scope)->toBe(AdditionalPromptScope::GLOBAL)
        ->and($record->user_id)->toBeNull()
        ->and($record->label)->toBe('Rechnungs-Hinweis')
        ->and($record->body)->toBe('Erwähne immer online-Rechnungen.');
});

test('admin can remove a global prompt rule', function () {
    $admin = promptEditorAdmin();

    $record = GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::GLOBAL,
        'label' => 'Test',
        'body' => 'Testregel',
        'position' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->call('removeGlobalPrompt', 0)
        ->assertHasNoErrors();

    expect(GhostwriterAdditionalPrompt::query()->find($record->id))->toBeNull();
});

test('admin can add and save a personal prompt rule', function () {
    $admin = promptEditorAdmin();

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->call('addUserPrompt')
        ->set('userPrompts.0.label', 'Grußformel')
        ->set('userPrompts.0.body', 'Unterschreibe mit Viele Grüße.')
        ->call('saveUserPrompt', 0)
        ->assertHasNoErrors();

    $record = GhostwriterAdditionalPrompt::query()
        ->where('scope', AdditionalPromptScope::USER)
        ->where('user_id', $admin->id)
        ->first();

    expect($record)->not->toBeNull()
        ->and($record->label)->toBe('Grußformel')
        ->and($record->body)->toBe('Unterschreibe mit Viele Grüße.');
});

test('admin can remove a personal prompt rule', function () {
    $admin = promptEditorAdmin();

    $record = GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::USER,
        'user_id' => $admin->id,
        'label' => 'Test',
        'body' => 'Testregel',
        'position' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->call('removeUserPrompt', 0)
        ->assertHasNoErrors();

    expect(GhostwriterAdditionalPrompt::query()->find($record->id))->toBeNull();
});

test('preview shows numbered strict blocks for all rules', function () {
    $admin = promptEditorAdmin();

    GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::GLOBAL,
        'label' => 'Regel A',
        'body' => 'Globale Anweisung eins.',
        'position' => 0,
    ]);

    GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::USER,
        'user_id' => $admin->id,
        'label' => 'Regel B',
        'body' => 'Persönliche Anweisung.',
        'position' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->assertSee('VERBINDLICHE ZUSATZREGEL #1')
        ->assertSee('Globale Anweisung eins.')
        ->assertSee('VERBINDLICHE ZUSATZREGEL #2')
        ->assertSee('Persönliche Anweisung.')
        ->assertSee('ABSCHLIESSENDE PFLICHTPRÜFUNG')
        ->assertSee('Wurde diese Anweisung im Entwurf umgesetzt?');
});

test('draft agent appends additional instructions to core prompt', function () {
    $agent = new GhostwriterDraftAgent(
        localeLabel: 'Deutsch',
        additionalInstructions: 'Antworte in der Du-Form.',
    );

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Ghostwriter 3000')
        ->toContain('Zusätzliche Anweisungen (STRICT')
        ->toContain('Antworte in der Du-Form.');
});

test('draft agent without additional instructions returns only core prompt', function () {
    $agent = new GhostwriterDraftAgent(localeLabel: 'Deutsch');

    $instructions = (string) $agent->instructions();

    expect($instructions)
        ->toContain('Ghostwriter 3000')
        ->not->toContain('Zusätzliche Anweisungen');
});

test('saving a rule with empty body fails validation', function () {
    $admin = promptEditorAdmin();

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->call('addGlobalPrompt')
        ->set('globalPrompts.0.body', '')
        ->call('saveGlobalPrompt', 0)
        ->assertHasErrors('globalPrompts.0.body');
});

test('reordering global prompts updates positions', function () {
    $admin = promptEditorAdmin();

    $first = GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::GLOBAL,
        'label' => 'First',
        'body' => 'Erste Regel.',
        'position' => 0,
    ]);

    $second = GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::GLOBAL,
        'label' => 'Second',
        'body' => 'Zweite Regel.',
        'position' => 1,
    ]);

    Livewire::actingAs($admin)
        ->test(PromptEditor::class)
        ->call('moveGlobalPrompt', 0, 'down');

    expect($first->refresh()->position)->toBe(1)
        ->and($second->refresh()->position)->toBe(0);
});
