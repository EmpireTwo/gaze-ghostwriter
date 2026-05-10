<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Empire2\GazeGhostwriter\Enums\MailChunkRole;
use Empire2\GazeGhostwriter\Models\SupportMailChunk;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\ChunkEmbeddingService;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;
use Naoray\GazeLaravel\Facades\Gaze;

test('chunk embedding sends sanitized text through Gaze::clean before the embedding provider', function () {
    config(['gaze-ghostwriter.gaze_enabled' => true]);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $chunk = SupportMailChunk::query()->create([
        'support_mail_message_id' => $message->id,
        'role' => MailChunkRole::INBOUND,
        'content' => 'Mein Name ist Alice und meine Mail ist alice@example.com',
    ]);

    app(ChunkEmbeddingService::class)->embedChunk($chunk);

    // Boundary proof: Gaze::clean() must be called with the chunk text
    // BEFORE it reaches the embedding provider. The default test fake's
    // identity restore handler means cleanText == original, so the call
    // is recorded with the original text.
    Gaze::assertCleaned('Mein Name ist Alice und meine Mail ist alice@example.com');

    $chunk->refresh();
    expect($chunk->embedding)->toBe([0.1, 0.2, 0.3]);
});

test('chunk embedding skips entirely when gaze boundary is off', function () {
    config(['gaze-ghostwriter.gaze_enabled' => false]);

    // preventStrayGenerations would throw if any unexpected embedding fires;
    // here we expect ZERO embedding calls so the empty fake stays unused.
    Embeddings::fake([])->preventStrayEmbeddings();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
    ]);

    $chunk = SupportMailChunk::query()->create([
        'support_mail_message_id' => $message->id,
        'role' => MailChunkRole::INBOUND,
        'content' => 'Sensible Daten dürfen nicht raus.',
    ]);

    app(ChunkEmbeddingService::class)->embedChunk($chunk);

    Gaze::assertNothingCleaned();

    $chunk->refresh();
    expect($chunk->embedding)->toBeNull();
});

test('draft query embedding sends sanitized text through Gaze::clean', function () {
    config([
        'gaze-ghostwriter.gaze_enabled' => true,
        'gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini',
    ]);

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Antwort.',
            'thematische_begruendung' => 'X.',
            'stilistische_begruendung' => 'Y.',
            'referenzierte_chunk_ids' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Release-Datum',
        'body_text' => 'Wann erscheint mein Release?',
    ]);

    app(DraftGeneratorService::class)->generateForMessage($message);

    // The query text is the trimmed subject + "\n\n" + body. Gaze::clean()
    // must see it before Embeddings::generate() is invoked.
    $expectedQuery = trim('Release-Datum'."\n\n".'Wann erscheint mein Release?');
    Gaze::assertCleaned($expectedQuery);
});

test('draft generator skips embedding entirely when gaze boundary is off', function () {
    config([
        'gaze-ghostwriter.gaze_enabled' => false,
        'gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini',
    ]);

    // No embedding call may fire; if it does, preventStrayEmbeddings throws.
    Embeddings::fake([])->preventStrayEmbeddings();

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Subject',
        'body_text' => 'Body',
    ]);

    // Boundary off → GuardedAgentRunner throws GazeDisabledException, but
    // BEFORE the agent fires the embedding step has already run (or, in our
    // fix, been SKIPPED). We assert the embedding was skipped by relying on
    // preventStrayEmbeddings — if our fix wrongly let the embedding through,
    // the fake would throw RuntimeException('Attempted embedding generation
    // without a fake response.') which is NOT a GazeDisabledException and
    // the toThrow matcher below would fail.
    expect(fn () => app(DraftGeneratorService::class)->generateForMessage($message))
        ->toThrow(GazeDisabledException::class);

    Gaze::assertNothingCleaned();
});
