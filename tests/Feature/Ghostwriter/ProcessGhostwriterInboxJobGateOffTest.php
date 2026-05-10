<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterInboxProcessor;
use Empire2\GazeGhostwriter\Services\ImapInboundMailSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;

beforeEach(function (): void {
    Cache::flush();

    config([
        'ghostwriter.enabled' => true,
        'ghostwriter.imap.host' => 'imap.test',
        'ghostwriter.imap.username' => 'test@test.com',
        'gaze_boundary.enabled' => false,
    ]);

    Log::spy();
});

it('catches GazeDisabledException per-message, marks processing_status=gaze_disabled, emits warning log, zero retries', function (): void {
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
        ->andThrow(new GazeDisabledException('Gaze boundary disabled and failClosed=true'));

    $processor = new GhostwriterInboxProcessor($imapSync, $generator);

    $result = $processor->run();

    expect($message->fresh()->processing_status)->toBe('gaze_disabled')
        ->and($result->draftsCreated)->toBe(0);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($name, $ctx) => $name === 'ghostwriter.gaze.disabled_defer'
            && $ctx['message_id'] === $message->id);
});
