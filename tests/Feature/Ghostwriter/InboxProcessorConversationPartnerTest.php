<?php

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterInboxProcessor;
use Empire2\GazeGhostwriter\Services\ImapInboundMailSync;
use Empire2\GazeGhostwriter\Support\ConversationPartnerCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;

beforeEach(function () {
    Cache::flush();

    config([
        'gaze-ghostwriter.enabled' => true,
        'gaze-ghostwriter.imap.host' => 'imap.test',
        'gaze-ghostwriter.imap.username' => 'test@test.com',
        'gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini',
    ]);
});

function fakeAiForInboxProcessor(int $times = 3): void
{
    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    $responses = array_fill(0, $times, [
        'draft_body' => 'Danke für Ihre Nachricht.',
        'thematische_begruendung' => 'Test.',
        'stilistische_begruendung' => 'Test.',
        'referenzierte_chunk_ids' => [],
    ]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, $responses);
}

function buildProcessor(): GhostwriterInboxProcessor
{
    $imapSync = Mockery::mock(ImapInboundMailSync::class);
    $imapSync->shouldReceive('sync')->andReturn(0);

    return new GhostwriterInboxProcessor($imapSync, app(DraftGeneratorService::class));
}

test('draft generation respects conversation partner filter', function () {
    fakeAiForInboxProcessor(1);

    $partnerMessage = SupportMailMessage::factory()->create([
        'from_email' => 'krishan.koenig@googlemail.com',
        'matches_support_address' => true,
    ]);

    $unrelatedMessage = SupportMailMessage::factory()->create([
        'from_email' => 'newsletter@example.com',
        'to_emails' => ['support@artistfy.com'],
        'matches_support_address' => true,
    ]);

    ConversationPartnerCache::put('krishan.koenig@googlemail.com');

    $result = buildProcessor()->run();

    expect($result->draftsCreated)->toBe(1);
    expect(SupportDraft::query()->where('support_mail_message_id', $partnerMessage->id)->exists())->toBeTrue();
    expect(SupportDraft::query()->where('support_mail_message_id', $unrelatedMessage->id)->exists())->toBeFalse();
});

test('draft generation without partner filter processes all support messages', function () {
    fakeAiForInboxProcessor(2);

    SupportMailMessage::factory()->create([
        'from_email' => 'alice@example.com',
        'matches_support_address' => true,
    ]);

    SupportMailMessage::factory()->create([
        'from_email' => 'bob@example.com',
        'matches_support_address' => true,
    ]);

    $result = buildProcessor()->run();

    expect($result->draftsCreated)->toBe(2);
});

test('partner in to_emails also gets drafts', function () {
    fakeAiForInboxProcessor(1);

    $outbound = SupportMailMessage::factory()->create([
        'from_email' => 'support@artistfy.com',
        'to_emails' => ['krishan.koenig@googlemail.com'],
        'matches_support_address' => true,
    ]);

    SupportMailMessage::factory()->create([
        'from_email' => 'stranger@example.com',
        'to_emails' => ['support@artistfy.com'],
        'matches_support_address' => true,
    ]);

    ConversationPartnerCache::put('krishan.koenig@googlemail.com');

    $result = buildProcessor()->run();

    expect($result->draftsCreated)->toBe(1);
    expect(SupportDraft::query()->where('support_mail_message_id', $outbound->id)->exists())->toBeTrue();
});
