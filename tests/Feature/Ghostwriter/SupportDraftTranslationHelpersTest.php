<?php

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;

test('needsTranslation returns true for non-german language', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hello',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
    ]);

    expect($draft->needsTranslation())->toBeTrue();
});

test('needsTranslation returns false for german language', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hallo',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'de',
    ]);

    expect($draft->needsTranslation())->toBeFalse();
});

test('needsTranslation returns false for null language', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hallo',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => null,
    ]);

    expect($draft->needsTranslation())->toBeFalse();
});

test('translationsReady returns true when both translations exist', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hello',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
        'mail_translation' => 'Hallo',
        'draft_translation' => 'Hallo Welt',
    ]);

    expect($draft->translationsReady())->toBeTrue();
});

test('translationsReady returns false when translations are missing', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hello',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
        'mail_translation' => null,
        'draft_translation' => null,
    ]);

    expect($draft->translationsReady())->toBeFalse();
});

test('resolvedDraftTranslation prefers edited over original', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hello',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
        'draft_translation' => 'Original-Uebersetzung',
        'edited_draft_translation' => 'Bearbeitete Uebersetzung',
    ]);

    expect($draft->resolvedDraftTranslation())->toBe('Bearbeitete Uebersetzung');
});

test('resolvedDraftTranslation falls back to draft_translation', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hello',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
        'draft_translation' => 'Original-Uebersetzung',
        'edited_draft_translation' => null,
    ]);

    expect($draft->resolvedDraftTranslation())->toBe('Original-Uebersetzung');
});

test('resolvedDraftTranslation returns null when no translation exists', function () {
    $message = SupportMailMessage::factory()->create();
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hello',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
        'draft_translation' => null,
    ]);

    expect($draft->resolvedDraftTranslation())->toBeNull();
});
