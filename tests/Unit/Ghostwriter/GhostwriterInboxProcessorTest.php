<?php

use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterInboxProcessor;
use Empire2\GazeGhostwriter\Services\ImapInboundMailSync;
use Tests\TestCase;

uses(TestCase::class);

test('processor does not sync when ghostwriter disabled', function () {
    config(['ghostwriter.enabled' => false]);

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
        'ghostwriter.enabled' => true,
        'ghostwriter.imap.host' => 'imap.example.test',
        'ghostwriter.imap.username' => 'user@test.de',
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
        'ghostwriter.enabled' => true,
        'ghostwriter.imap.host' => '',
        'ghostwriter.imap.username' => 'u',
    ]);

    $imap = $this->createMock(ImapInboundMailSync::class);
    $imap->expects($this->never())->method('sync');

    $drafts = $this->createMock(DraftGeneratorService::class);

    $result = (new GhostwriterInboxProcessor($imap, $drafts))->run();

    expect($result->messagesImported)->toBe(0);
});
