<?php

use Empire2\GazeGhostwriter\Services\GhostwriterImapDiagnostics;

test('diagnostics fails when host or username missing', function () {
    config([
        'gaze-ghostwriter.imap.host' => '',
        'gaze-ghostwriter.imap.username' => '',
    ]);

    $result = app(GhostwriterImapDiagnostics::class)->run();

    expect($result['ok'])->toBeFalse()
        ->and($result['folders'])->toBe([])
        ->and($result['headline'])->toBe('Konfiguration unvollständig');
});
