<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Empire2\GazeGhostwriter\Livewire\Admin\GhostwriterSettings;
use Empire2\GazeGhostwriter\Models\GhostwriterUserData;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ghostwriterAdminForUserDataSettings(): User
{
    Role::findOrCreate("admin");

    $user = User::factory()->create();
    $user->assignRole("admin");

    return $user;
}

test('admin can save ghostwriter signing name on settings page', function () {
    $admin = ghostwriterAdminForUserDataSettings();

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->set('ghostwriterSigningNameInput', 'Markus von Artistfy')
        ->set('ghostwriterReplySignatureInput', '')
        ->call('saveGhostwriterSigningProfile')
        ->assertHasNoErrors();

    $row = GhostwriterUserData::query()->where('user_id', $admin->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->signing_name)->toBe('Markus von Artistfy')
        ->and($row->reply_signature)->toBeNull();
});

test('admin can save optional reply signature on settings page', function () {
    $admin = ghostwriterAdminForUserDataSettings();

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->set('ghostwriterSigningNameInput', '')
        ->set('ghostwriterReplySignatureInput', "Viele Grüße\nArtistfy")
        ->call('saveGhostwriterSigningProfile')
        ->assertHasNoErrors();

    $row = GhostwriterUserData::query()->where('user_id', $admin->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->reply_signature)->toBe("Viele Grüße\nArtistfy");
});

test('admin can save HTML signature on settings page', function () {
    $admin = ghostwriterAdminForUserDataSettings();
    $html = '<table><tr><td><strong>Artistfy</strong></td></tr></table>';

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->set('ghostwriterReplySignatureHtmlInput', $html)
        ->call('saveGhostwriterSigningProfile')
        ->assertHasNoErrors();

    $row = GhostwriterUserData::query()->where('user_id', $admin->id)->first();

    expect($row)->not->toBeNull()
        ->and($row->reply_signature_html)->toBe($html);
});

test('admin can save HTML signature and it sanitizes dangerous tags', function () {
    $admin = ghostwriterAdminForUserDataSettings();
    $html = '<div>Sig</div><script>alert("xss")</script>';

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->set('ghostwriterReplySignatureHtmlInput', $html)
        ->call('saveGhostwriterSigningProfile')
        ->assertHasNoErrors();

    $row = GhostwriterUserData::query()->where('user_id', $admin->id)->first();

    expect($row->reply_signature_html)->toBe('<div>Sig</div>');
});

test('admin can clear HTML signature', function () {
    $admin = ghostwriterAdminForUserDataSettings();
    GhostwriterUserData::query()->create([
        'user_id' => $admin->id,
        'reply_signature_html' => '<div>Old Sig</div>',
    ]);

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->call('clearHtmlSignature')
        ->assertSet('ghostwriterReplySignatureHtmlInput', '');

    $row = GhostwriterUserData::query()->where('user_id', $admin->id)->first();

    expect($row->reply_signature_html)->toBeNull();
});

test('settings page loads existing HTML signature', function () {
    $admin = ghostwriterAdminForUserDataSettings();
    GhostwriterUserData::query()->create([
        'user_id' => $admin->id,
        'reply_signature_html' => '<div><b>Loaded</b></div>',
    ]);

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->assertSet('ghostwriterReplySignatureHtmlInput', '<div><b>Loaded</b></div>');
});
