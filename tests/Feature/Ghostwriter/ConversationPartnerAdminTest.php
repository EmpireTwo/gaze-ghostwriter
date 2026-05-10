<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use App\Enums\Roles;
use Domain\Account\Models\User;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Livewire\Admin\GhostwriterSettings;
use Empire2\GazeGhostwriter\Support\ConversationPartnerCache;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Cache::flush();
});

function ghostwriterAdminForConversationFilter(): User
{
    Role::findOrCreate(Roles::ADMIN->value);

    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    return $user;
}

test('admin saves conversation partner email to cache normalized', function () {
    config(['ghostwriter.imap.only_conversation_with_email' => '']);

    Livewire::actingAs(ghostwriterAdminForConversationFilter())
        ->test(GhostwriterSettings::class)
        ->set('conversationPartnerEmailInput', 'Kunde@Example.com')
        ->call('saveConversationPartnerFilter');

    expect(ConversationPartnerCache::get())->toBe('kunde@example.com');
    expect(ConversationPartnerCache::hasAdminOverride())->toBeTrue();
});

test('admin clear removes cache override', function () {
    ConversationPartnerCache::put('a@b.c');

    Livewire::actingAs(ghostwriterAdminForConversationFilter())
        ->test(GhostwriterSettings::class)
        ->call('clearAdminConversationPartnerFilter');

    expect(ConversationPartnerCache::get())->toBeNull();
});

test('effective prefers admin cache over env', function () {
    config(['ghostwriter.imap.only_conversation_with_email' => 'env@example.com']);
    ConversationPartnerCache::put('cache@example.com');

    expect(ConversationPartnerCache::effective())->toBe('cache@example.com');
});

test('run inbox sync does nothing harmful when ghostwriter disabled', function () {
    config(['ghostwriter.enabled' => false]);

    Livewire::actingAs(ghostwriterAdminForConversationFilter())
        ->test(DraftsIndex::class)
        ->call('runInboxSync')
        ->assertHasNoErrors();
});

test('mail connection test stores imap and smtp diagnostics on settings page', function () {
    config([
        'ghostwriter.imap.host' => '',
        'ghostwriter.imap.username' => '',
        'ghostwriter.smtp.host' => '',
        'ghostwriter.reply.from_address' => '',
    ]);

    Livewire::actingAs(ghostwriterAdminForConversationFilter())
        ->test(GhostwriterSettings::class)
        ->call('testMailConnections')
        ->assertSet('imapDiagnosticsResult.headline', 'Konfiguration unvollständig')
        ->assertSet('imapDiagnosticsResult.ok', false)
        ->assertSet('smtpDiagnosticsResult.headline', 'SMTP-Konfiguration unvollständig')
        ->assertSet('smtpDiagnosticsResult.ok', false);
});

test('admin can open ghostwriter settings page', function () {
    actingAs(ghostwriterAdminForConversationFilter())
        ->get(route('gaze-ghostwriter.settings'))
        ->assertOk();
});

test('admin save with empty input clears cache override', function () {
    ConversationPartnerCache::put('old@example.com');

    Livewire::actingAs(ghostwriterAdminForConversationFilter())
        ->test(GhostwriterSettings::class)
        ->set('conversationPartnerEmailInput', '')
        ->call('saveConversationPartnerFilter');

    expect(ConversationPartnerCache::get())->toBeNull();
});
