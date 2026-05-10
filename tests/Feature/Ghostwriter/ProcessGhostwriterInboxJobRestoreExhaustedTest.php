<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterInboxProcessor;
use Empire2\GazeGhostwriter\Services\ImapInboundMailSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Naoray\GazeLaravel\Exceptions\GazeUnknownTokenException;

beforeEach(function (): void {
    Cache::flush();

    config([
        'gaze-ghostwriter.enabled' => true,
        'gaze-ghostwriter.imap.host' => 'imap.test',
        'gaze-ghostwriter.imap.username' => 'test@test.com',
    ]);

    Log::spy();
});

it('catches GazeUnknownTokenException per-message, marks processing_status=gaze_restore_exhausted, emits error log, zero retries', function (): void {
    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'processing_status' => null,
    ]);

    /** @var ImapInboundMailSync&MockInterface $imapSync */
    $imapSync = Mockery::mock(ImapInboundMailSync::class);
    $imapSync->shouldReceive('sync')->andReturn(0);

    /** @var DraftGeneratorService&MockInterface $generator */
    $generator = Mockery::mock(DraftGeneratorService::class);
    $generator->shouldReceive('generateForMessage')
        ->andThrow(new GazeUnknownTokenException('unknown token', 3, hash('sha256', '')));

    $processor = new GhostwriterInboxProcessor($imapSync, $generator);

    $result = $processor->run();

    expect($message->fresh()->processing_status)->toBe('gaze_restore_exhausted')
        ->and($result->draftsCreated)->toBe(0);

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($name, $ctx) => $name === 'gaze-ghostwriter.gaze.restore_exhausted'
            && $ctx['message_id'] === $message->id);
});
