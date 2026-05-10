<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftShow;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ghostwriterAdmin(): User
{
    Role::findOrCreate("admin");

    $user = User::factory()->create();
    $user->assignRole("admin");

    return $user;
}

test('regenerate updates draft in-place keeping same ID', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Verbesserter Entwurf nach Regenerierung.',
            'thematische_begruendung' => 'Besser passend.',
            'stilistische_begruendung' => 'Klarer.',
            'referenzierte_chunk_ids' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Test',
        'body_text' => 'Hilfe bitte',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Alter Entwurf',
        'edited_body' => 'Manuell bearbeitet.',
        'rationale' => [
            'thematische_begruendung' => 'x',
            'stilistische_begruendung' => 'y',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::PENDING_REVIEW,
    ]);

    $originalId = $draft->id;

    $service = app(DraftGeneratorService::class);
    $result = $service->regenerateFromDraft($draft);

    expect($result)->toBeInstanceOf(SupportDraft::class)
        ->and($result->id)->toBe($originalId)
        ->and($result->status)->toBe(DraftStatus::PENDING_REVIEW)
        ->and($result->draft_body)->toContain('Verbesserter')
        ->and($result->edited_body)->toBeNull();

    expect(SupportDraft::query()->where('status', DraftStatus::SUPERSEDED)->count())->toBe(0);
});

test('draft show saves star rating and optional comment', function () {
    $admin = ghostwriterAdmin();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Text',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::PENDING_REVIEW,
    ]);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('ratingComment', 'Zu knapp formuliert.')
        ->call('rate', 3);

    $draft->refresh();

    expect($draft->user_rating)->toBe(3);
    expect($draft->rating_comment)->toBe('Zu knapp formuliert.');
    expect($draft->rated_by_user_id)->toBe($admin->id);
    expect($draft->rated_at)->not->toBeNull();
});

test('draft show saves star rating on sent draft', function () {
    $admin = ghostwriterAdmin();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Text',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::SENT,
        'sent_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('ratingComment', 'Gute Antwort.')
        ->call('rate', 4);

    $draft->refresh();

    expect($draft->user_rating)->toBe(4);
    expect($draft->rating_comment)->toBe('Gute Antwort.');
});

test('draft show saves star rating on dismissed draft', function () {
    $admin = ghostwriterAdmin();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Text',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::DISMISSED,
    ]);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->call('rate', 2);

    $draft->refresh();

    expect($draft->user_rating)->toBe(2);
});

test('reopen changes dismissed draft back to pending_review', function () {
    $admin = ghostwriterAdmin();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Text',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::DISMISSED,
    ]);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->call('reopen');

    $draft->refresh();

    expect($draft->status)->toBe(DraftStatus::PENDING_REVIEW);
});

test('reopen does nothing for non-dismissed drafts', function () {
    $admin = ghostwriterAdmin();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Text',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::SENT,
        'sent_at' => now(),
    ]);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->call('reopen');

    $draft->refresh();

    expect($draft->status)->toBe(DraftStatus::SENT);
});

test('draft show saves edited body and normalizes when identical to ai draft', function () {
    $admin = ghostwriterAdmin();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'KI sagt Hallo.',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::PENDING_REVIEW,
    ]);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('editableBody', 'Manuell angepasst.')
        ->call('saveEditedBody');

    $draft->refresh();
    expect(trim((string) $draft->edited_body))->toBe('Manuell angepasst.');
    expect($draft->resolvedReplyBody())->toBe('Manuell angepasst.');

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('editableBody', 'KI sagt Hallo.')
        ->call('saveEditedBody');

    $draft->refresh();
    expect($draft->edited_body)->toBeNull();
    expect($draft->resolvedReplyBody())->toBe('KI sagt Hallo.');
});
