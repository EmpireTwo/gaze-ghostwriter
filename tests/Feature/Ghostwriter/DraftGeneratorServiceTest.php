<?php

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Enums\AdditionalPromptScope;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Enums\MailChunkRole;
use Empire2\GazeGhostwriter\Models\GhostwriterAdditionalPrompt;
use Empire2\GazeGhostwriter\Models\GhostwriterPromptHistory;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailChunk;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;

test('creates draft using faked AI and embeddings', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]], [[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Sehr geehrte Kundin, wir helfen gerne.',
            'thematische_begruendung' => 'Passend zum Thema Release.',
            'stilistische_begruendung' => 'Freundlicher Support-Ton.',
            'referenzierte_chunk_ids' => [],
        ],
    ]);

    $historical = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    SupportMailChunk::query()->create([
        'support_mail_message_id' => $historical->id,
        'role' => MailChunkRole::INBOUND,
        'content' => 'Frühere Frage zu Release-Datum',
        'embedding' => [0.1, 0.2, 0.3],
    ]);

    $incoming = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Release verschieben',
        'body_text' => 'Kann ich mein Release noch verschieben?',
    ]);

    $service = app(DraftGeneratorService::class);
    $draft = $service->generateForMessage($incoming);

    expect($draft)->toBeInstanceOf(SupportDraft::class);
    expect($draft->status)->toBe(DraftStatus::PENDING_REVIEW);
    expect($draft->draft_body)->toContain('Sehr geehrte');
    expect($draft->rationale)->toHaveKey('thematische_begruendung');
});

test('regenerateFromDraft updates draft in-place keeping same ID', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]], [[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Erster Entwurf.',
            'thematische_begruendung' => 'Grund 1.',
            'stilistische_begruendung' => 'Stil 1.',
            'referenzierte_chunk_ids' => [],
        ],
        [
            'draft_body' => 'Verbesserter zweiter Entwurf.',
            'thematische_begruendung' => 'Grund 2.',
            'stilistische_begruendung' => 'Stil 2.',
            'referenzierte_chunk_ids' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Frage',
        'body_text' => 'Wie funktioniert das?',
    ]);

    $service = app(DraftGeneratorService::class);
    $original = $service->generateForMessage($message);
    $originalId = $original->id;

    $original->update(['edited_body' => 'Manuell bearbeitet.']);

    $result = $service->regenerateFromDraft($original);

    expect($result)->toBeInstanceOf(SupportDraft::class)
        ->and($result->id)->toBe($originalId)
        ->and($result->draft_body)->toBe('Verbesserter zweiter Entwurf.')
        ->and($result->edited_body)->toBeNull()
        ->and($result->status)->toBe(DraftStatus::PENDING_REVIEW);

    expect(SupportDraft::query()->where('status', DraftStatus::SUPERSEDED)->count())->toBe(0);
});

test('bare greeting inbound skips rag snippets so unrelated chunks do not steer the draft', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hallo, danke für deine Nachricht.',
            'thematische_begruendung' => 'Kurze Grußantwort.',
            'stilistische_begruendung' => 'Neutral und freundlich.',
            'referenzierte_chunk_ids' => [],
        ],
    ]);

    $historical = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    SupportMailChunk::query()->create([
        'support_mail_message_id' => $historical->id,
        'role' => MailChunkRole::INBOUND,
        'content' => 'Lassen Sie uns einen Termin finden um Ihre Ideen zu besprechen',
        'embedding' => [0.1, 0.2, 0.3],
    ]);

    $incoming = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Test',
        'body_text' => 'Hi',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($incoming);

    expect($draft)->toBeInstanceOf(SupportDraft::class)
        ->and($draft->rationale['retrieved_snippets'])->toBe([]);
});

test('system prompt contains numbered strict blocks for each additional prompt', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::GLOBAL,
        'label' => 'Rechnungs-Hinweis',
        'body' => 'Erwähne immer online-Rechnungen bei Finanzthemen.',
        'position' => 0,
    ]);

    GhostwriterAdditionalPrompt::query()->create([
        'scope' => AdditionalPromptScope::GLOBAL,
        'label' => 'Stil',
        'body' => 'Halte dich kurz.',
        'position' => 1,
    ]);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Testantwort.',
            'thematische_begruendung' => 'Test.',
            'stilistische_begruendung' => 'Test.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Rechnung',
        'body_text' => 'Wo finde ich meine Rechnung?',
    ]);

    app(DraftGeneratorService::class)->generateForMessage($message);

    $history = GhostwriterPromptHistory::query()->latest()->first();

    expect($history->system_prompt)
        ->toContain('VERBINDLICHE ZUSATZREGEL #1 (STRICT')
        ->toContain('Erwähne immer online-Rechnungen bei Finanzthemen.')
        ->toContain('VERBINDLICHE ZUSATZREGEL #2 (STRICT')
        ->toContain('Halte dich kurz.')
        ->toContain('ABSCHLIESSENDE PFLICHTPRÜFUNG')
        ->toContain('☐ Regel #1')
        ->toContain('☐ Regel #2')
        ->toContain('Wurde diese Anweisung im Entwurf umgesetzt?');
});

test('entity extraction prompt instructs songs to use release type', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Testantwort.',
            'thematische_begruendung' => 'Test.',
            'stilistische_begruendung' => 'Test.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [
                ['type' => 'release', 'query' => 'dolores sed'],
            ],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Release Status',
        'body_text' => 'Wie ist der Status meines Songs dolores sed?',
    ]);

    app(DraftGeneratorService::class)->generateForMessage($message);

    $history = GhostwriterPromptHistory::query()->latest()->first();

    expect($history->system_prompt)
        ->toContain('Songs, Singles und Alben sind immer type "release"');

    $draft = SupportDraft::query()->latest()->first();

    expect($draft->mentioned_entities)
        ->toHaveCount(1)
        ->and($draft->mentioned_entities[0]['type'])->toBe('release')
        ->and($draft->mentioned_entities[0]['query'])->toBe('dolores sed');
});
