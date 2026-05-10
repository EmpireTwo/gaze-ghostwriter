<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use App\Enums\Roles;
use Domain\Account\Models\User;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftShow;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Models\GhostwriterUserData;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function ghostwriterAdminForSendReply(): User
{
    Role::findOrCreate(Roles::ADMIN->value);

    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    return $user;
}

beforeEach(function () {
    config([
        'ghostwriter.smtp.driver' => 'null',
        'ghostwriter.smtp.host' => '127.0.0.1',
        'ghostwriter.smtp.port' => 1025,
        'ghostwriter.smtp.encryption' => 'none',
        'ghostwriter.reply.from_address' => 'support@example.test',
        'ghostwriter.reply.from_name' => 'Support',
    ]);
});

test('draft show send reply marks draft sent with null mail transport', function () {
    $admin = ghostwriterAdminForSendReply();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'from_email' => 'customer@example.test',
        'subject' => 'Hallo',
        'body_text' => 'Body',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Antworttext',
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
        ->call('sendReply');

    $draft->refresh();

    expect($draft->status)->toBe(DraftStatus::SENT)
        ->and($draft->sent_at)->not->toBeNull()
        ->and($draft->sent_by_user_id)->toBe($admin->id)
        ->and($draft->sent_message_id)->not->toBeNull();
});

test('drafts index modal send reply marks draft sent', function () {
    $admin = ghostwriterAdminForSendReply();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'from_email' => 'kunde@example.test',
        'subject' => 'Frage',
        'body_text' => 'Text',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hier die Antwort',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::ACCEPTED,
    ]);

    Livewire::actingAs($admin)
        ->test(DraftsIndex::class)
        ->set('draftModalOpen', true)
        ->set('modalDraftId', $draft->id)
        ->call('sendReply');

    $draft->refresh();

    expect($draft->status)->toBe(DraftStatus::SENT)
        ->and($draft->sent_at)->not->toBeNull();
});

test('send reply includes HTML part when user has HTML signature', function () {
    $admin = ghostwriterAdminForSendReply();
    GhostwriterUserData::query()->create([
        'user_id' => $admin->id,
        'reply_signature' => "Best,\nArtistfy",
        'reply_signature_html' => '<table><td><strong>Artistfy</strong></td></table>',
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'from_email' => 'customer@example.test',
        'subject' => 'Test',
        'body_text' => 'Frage',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Antworttext',
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
        ->call('sendReply');

    $draft->refresh();

    expect($draft->status)->toBe(DraftStatus::SENT);
});

test('send reply does not include HTML part when no HTML signature', function () {
    $admin = ghostwriterAdminForSendReply();
    GhostwriterUserData::query()->create([
        'user_id' => $admin->id,
        'reply_signature' => "Best,\nArtistfy",
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'from_email' => 'customer@example.test',
        'subject' => 'Test',
        'body_text' => 'Frage',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Antworttext',
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
        ->call('sendReply');

    $draft->refresh();

    expect($draft->status)->toBe(DraftStatus::SENT);
});
