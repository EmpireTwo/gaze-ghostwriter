<?php

declare(strict_types=1);

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Models\GhostwriterPromptHistory;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);
});

test('prompt history is recorded when draft is generated', function () {
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hallo, wir helfen gerne.',
            'thematische_begruendung' => 'Support-Thema.',
            'stilistische_begruendung' => 'Freundlich.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Hilfe benötigt',
        'body_text' => 'Ich brauche Unterstützung.',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft)->toBeInstanceOf(SupportDraft::class);

    $history = GhostwriterPromptHistory::query()->where('support_mail_message_id', $message->id)->first();

    expect($history)->not->toBeNull()
        ->and($history->support_draft_id)->toBe($draft->id)
        ->and($history->system_prompt)->not->toBeEmpty()
        ->and($history->user_prompt)->not->toBeEmpty()
        ->and($history->response_structured)->toBeArray()
        ->and($history->response_structured)->toHaveKey('draft_body')
        ->and($history->ai_model)->toBe('gpt-4o-mini')
        ->and($history->is_regeneration)->toBeFalse();
});

test('prompt history is recorded as regeneration when draft is regenerated', function () {
    Embeddings::fake([[[0.1, 0.2, 0.3]], [[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Erster Entwurf.',
            'thematische_begruendung' => 'Grund 1.',
            'stilistische_begruendung' => 'Stil 1.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
        ],
        [
            'draft_body' => 'Zweiter Entwurf.',
            'thematische_begruendung' => 'Grund 2.',
            'stilistische_begruendung' => 'Stil 2.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Frage',
        'body_text' => 'Wie geht das?',
    ]);

    $service = app(DraftGeneratorService::class);
    $draft = $service->generateForMessage($message);
    $service->regenerateFromDraft($draft);

    $entries = GhostwriterPromptHistory::query()
        ->where('support_mail_message_id', $message->id)
        ->orderBy('id')
        ->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->is_regeneration)->toBeFalse()
        ->and($entries[1]->is_regeneration)->toBeTrue();
});

test('prompt history page renders for admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('gaze-ghostwriter.prompt-history'))
        ->assertOk()
        ->assertSee('Prompt-History');
});

test('prompt history page shows entries with message details', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'from_email' => 'kunde@example.com',
        'from_name' => 'Max Muster',
        'subject' => 'Testbetreff',
    ]);

    GhostwriterPromptHistory::query()->create([
        'support_mail_message_id' => $message->id,
        'system_prompt' => 'Du bist ein hilfreicher Assistent.',
        'user_prompt' => 'Bitte antworte auf diese Mail.',
        'response_structured' => ['draft_body' => 'Antwort.'],
        'ai_model' => 'gpt-4o',
        'ai_provider' => 'openai',
        'duration_ms' => 1234,
        'is_regeneration' => false,
    ]);

    $this->actingAs($admin)
        ->get(route('gaze-ghostwriter.prompt-history'))
        ->assertOk()
        ->assertSee('Max Muster')
        ->assertSee('Testbetreff')
        ->assertSee('gpt-4o');
});
