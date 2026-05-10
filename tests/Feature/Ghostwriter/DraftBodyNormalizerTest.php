<?php

declare(strict_types=1);

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Livewire\Admin\GhostwriterSettings;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Support\DraftBodyNormalizer;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function createDraftWithBody(string $body, ?string $editedBody = null): SupportDraft
{
    $message = SupportMailMessage::query()->create([
        'rfc_message_id' => 'test-'.Str::random(16),
        'from_email' => 'test@example.com',
        'to_emails' => ['support@artistfy.com'],
        'subject' => 'Test',
        'body_text' => 'Test body',
        'received_at' => now(),
        'matches_support_address' => true,
    ]);

    return SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => $body,
        'edited_body' => $editedBody,
        'rationale' => [],
        'status' => 'pending_review',
    ]);
}

test('normalizeText converts literal backslash-n to real newlines', function (): void {
    $input = 'Hallo,\n\nDanke für die Nachricht.\n\nBeste Grüße,\n[Dein Name]';

    $result = DraftBodyNormalizer::normalizeText($input);

    expect($result)->toBe("Hallo,\n\nDanke für die Nachricht.\n\nBeste Grüße,\n[Dein Name]");
});

test('normalizeText leaves real newlines untouched', function (): void {
    $input = "Hallo,\n\nDanke für die Nachricht.\n\nBeste Grüße";

    expect(DraftBodyNormalizer::normalizeText($input))->toBe($input);
});

test('normalizeText handles null and empty strings', function (): void {
    expect(DraftBodyNormalizer::normalizeText(null))->toBeNull();
    expect(DraftBodyNormalizer::normalizeText(''))->toBe('');
});

test('normalizeAll fixes drafts with literal newlines', function (): void {
    $draft = createDraftWithBody('Hi\n\nDanke\nLG');

    $result = DraftBodyNormalizer::normalizeAll();

    expect($result['normalized'])->toBe(1);
    expect($draft->fresh()->draft_body)->toBe("Hi\n\nDanke\nLG");
});

test('normalizeAll also normalizes edited_body', function (): void {
    $draft = createDraftWithBody(
        'Original\nText',
        'Bearbeitet\nVersion',
    );

    DraftBodyNormalizer::normalizeAll();

    $draft->refresh();
    expect($draft->draft_body)->toBe("Original\nText");
    expect($draft->edited_body)->toBe("Bearbeitet\nVersion");
});

test('normalizeAll skips already clean drafts', function (): void {
    createDraftWithBody("Schon sauber\n\nMit echten Newlines");

    $result = DraftBodyNormalizer::normalizeAll();

    expect($result['normalized'])->toBe(0);
    expect($result['skipped'])->toBe(1);
});

test('admin can trigger normalization via settings component', function (): void {
    Role::findOrCreate('admin');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    createDraftWithBody('Test\nText');

    Livewire::actingAs($admin)
        ->test(GhostwriterSettings::class)
        ->call('normalizeDraftBodies')
        ->assertHasNoErrors();

    expect(SupportDraft::query()->first()->draft_body)->toBe("Test\nText");
});
