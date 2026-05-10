<?php

use Empire2\GazeGhostwriter\Services\GhostwriterImapDiagnostics;
use Tests\TestCase;

uses(TestCase::class);

test('diagnostics fails when host or username missing', function () {
    config([
        'ghostwriter.imap.host' => '',
        'ghostwriter.imap.username' => '',
    ]);

    $result = app(GhostwriterImapDiagnostics::class)->run();

    expect($result['ok'])->toBeFalse()
        ->and($result['folders'])->toBe([])
        ->and($result['headline'])->toBe('Konfiguration unvollständig');
});
