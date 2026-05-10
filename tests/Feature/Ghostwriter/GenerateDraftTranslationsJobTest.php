<?php

use Empire2\GazeGhostwriter\Agents\GhostwriterTranslatorAgent;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Jobs\GenerateDraftTranslationsJob;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\GhostwriterTranslationService;
use Laravel\Ai\Ai;

test('generates translations for non-german draft', function () {
    config(['ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Ai::fakeAgent(GhostwriterTranslatorAgent::class, [
        ['translated_text' => 'Uebersetzung der Mail'],
        ['translated_text' => 'Uebersetzung des Entwurfs'],
    ]);

    $message = SupportMailMessage::factory()->create([
        'body_text' => 'Hi, I need help with my account.',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hi, we can help you with that.',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
    ]);

    (new GenerateDraftTranslationsJob($draft->id))->handle(app(GhostwriterTranslationService::class));

    $draft->refresh();

    expect($draft->mail_translation)->toBe('Uebersetzung der Mail')
        ->and($draft->draft_translation)->toBe('Uebersetzung des Entwurfs');
});

test('skips translation for german draft', function () {
    $message = SupportMailMessage::factory()->create();

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Hallo, wir helfen gerne.',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'de',
    ]);

    (new GenerateDraftTranslationsJob($draft->id))->handle(app(GhostwriterTranslationService::class));

    $draft->refresh();

    expect($draft->mail_translation)->toBeNull()
        ->and($draft->draft_translation)->toBeNull();
});

test('skips job when draft not found', function () {
    $job = new GenerateDraftTranslationsJob(999999);
    $job->handle(app(GhostwriterTranslationService::class));

    expect(true)->toBeTrue();
});
