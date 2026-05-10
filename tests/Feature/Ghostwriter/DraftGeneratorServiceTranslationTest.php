<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Jobs\GenerateDraftTranslationsJob;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;

test('dispatches translation job for english mail', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);
    Queue::fake();
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hi, we can help.',
            'thematische_begruendung' => 'Test.',
            'stilistische_begruendung' => 'Test.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
            'detected_language' => 'en',
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Help needed',
        'body_text' => 'I need help with my account.',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft)->toBeInstanceOf(SupportDraft::class)
        ->and($draft->detected_language)->toBe('en');

    Queue::assertPushed(GenerateDraftTranslationsJob::class, fn ($job) => $job->draftId === $draft->id);
});

test('does not dispatch translation job for german mail', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);
    Queue::fake();
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hallo, wir helfen gerne.',
            'thematische_begruendung' => 'Test.',
            'stilistische_begruendung' => 'Test.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
            'detected_language' => 'de',
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Hilfe',
        'body_text' => 'Ich brauche Hilfe mit meinem Konto.',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft->detected_language)->toBe('de');

    Queue::assertNotPushed(GenerateDraftTranslationsJob::class);
});

test('persists detected_language on draft creation', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);
    Queue::fake();
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Bonjour, nous pouvons vous aider.',
            'thematische_begruendung' => 'Test.',
            'stilistische_begruendung' => 'Test.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
            'detected_language' => 'fr',
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Aide necessaire',
        'body_text' => 'Bonjour, je besoin aide.',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft->detected_language)->toBe('fr');
});

test('clears translation fields on regeneration', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);
    Queue::fake();
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Help',
        'body_text' => 'I need help.',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'We can help.',
        'rationale' => [],
        'status' => DraftStatus::PENDING_REVIEW,
        'detected_language' => 'en',
        'draft_translation' => 'Wir koennen helfen.',
        'edited_draft_translation' => 'Bearbeitete Uebersetzung.',
    ]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'We are happy to help you.',
            'thematische_begruendung' => 'Test.',
            'stilistische_begruendung' => 'Test.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
            'detected_language' => 'en',
        ],
    ]);

    app(DraftGeneratorService::class)->regenerateFromDraft($draft);
    $draft->refresh();

    expect($draft->draft_translation)->toBeNull()
        ->and($draft->edited_draft_translation)->toBeNull()
        ->and($draft->detected_language)->toBe('en');

    Queue::assertPushed(GenerateDraftTranslationsJob::class);
});
