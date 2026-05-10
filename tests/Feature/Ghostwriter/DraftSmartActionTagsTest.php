<?php

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Models\GhostwriterSmartAction;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;

test('draft stores smart_action_tags and mentioned_entities from AI response', function () {
    config(['ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hier ist Ihre Rechnung.',
            'thematische_begruendung' => 'Rechnungsthema.',
            'stilistische_begruendung' => 'Sachlich.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => ['INVOICES'],
            'mentioned_entities' => [
                ['type' => 'release', 'query' => 'dolores sed'],
            ],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Rechnung',
        'body_text' => 'Wie steht es mit den Abrechnungen für mein Release dolores sed?',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft)->toBeInstanceOf(SupportDraft::class)
        ->and($draft->smart_action_tags)->toBe(['INVOICES'])
        ->and($draft->mentioned_entities)->toBe([
            ['type' => 'release', 'query' => 'dolores sed'],
        ]);
});

test('draft defaults to empty arrays when AI omits smart action fields', function () {
    config(['ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hallo, danke.',
            'thematische_begruendung' => 'Kurz.',
            'stilistische_begruendung' => 'Neutral.',
            'referenzierte_chunk_ids' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Hi',
        'body_text' => 'Hi Artistfy',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft->smart_action_tags)->toBe([])
        ->and($draft->mentioned_entities)->toBe([]);
});

test('smart action prompt instructions are injected when active actions exist', function () {
    GhostwriterSmartAction::query()->create([
        'marker' => 'INVOICES',
        'label' => 'Rechnungen',
        'prompt_hint' => 'Wenn es um Rechnungen geht',
        'route_template' => '/admin/billings/customer/{customerId}',
        'is_active' => true,
    ]);

    $block = GhostwriterSmartAction::buildPromptInstructions();

    expect($block)->toContain('INVOICES')
        ->and($block)->toContain('smart_action_tags');
});

test('route template resolves placeholders correctly', function () {
    $action = GhostwriterSmartAction::query()->create([
        'marker' => 'INVOICES',
        'label' => 'Rechnungen',
        'prompt_hint' => 'Hint',
        'route_template' => '/admin/billings/customer/{customerId}',
    ]);

    $resolved = $action->resolveRoute(['customerId' => 42]);

    expect($resolved)->toBe('/admin/billings/customer/42');
});
