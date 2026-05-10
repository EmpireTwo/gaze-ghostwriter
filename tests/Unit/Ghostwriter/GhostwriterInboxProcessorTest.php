<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterInboxProcessor;
use Empire2\GazeGhostwriter\Services\ImapInboundMailSync;

test('processor does not sync when ghostwriter disabled', function () {
    config(['gaze-ghostwriter.enabled' => false]);

    $imap = $this->createMock(ImapInboundMailSync::class);
    $imap->expects($this->never())->method('sync');

    $drafts = $this->createMock(DraftGeneratorService::class);
    $drafts->expects($this->never())->method('generateForMessage');

    $result = (new GhostwriterInboxProcessor($imap, $drafts))->run();

    expect($result->messagesImported)->toBe(0)
        ->and($result->draftsCreated)->toBe(0);
});

test('processor syncs when enabled and imap configured', function () {
    config([
        'gaze-ghostwriter.enabled' => true,
        'gaze-ghostwriter.imap.host' => 'imap.example.test',
        'gaze-ghostwriter.imap.username' => 'user@test.de',
    ]);

    $imap = $this->createMock(ImapInboundMailSync::class);
    $imap->expects($this->once())->method('sync')->willReturn(2);

    $drafts = $this->createMock(DraftGeneratorService::class);
    $drafts->expects($this->never())->method('generateForMessage');

    $result = (new GhostwriterInboxProcessor($imap, $drafts))->run();

    expect($result->messagesImported)->toBe(2)
        ->and($result->draftsCreated)->toBe(0);
});

test('processor skips sync when imap host or user missing', function () {
    config([
        'gaze-ghostwriter.enabled' => true,
        'gaze-ghostwriter.imap.host' => '',
        'gaze-ghostwriter.imap.username' => 'u',
    ]);

    $imap = $this->createMock(ImapInboundMailSync::class);
    $imap->expects($this->never())->method('sync');

    $drafts = $this->createMock(DraftGeneratorService::class);

    $result = (new GhostwriterInboxProcessor($imap, $drafts))->run();

    expect($result->messagesImported)->toBe(0);
});
