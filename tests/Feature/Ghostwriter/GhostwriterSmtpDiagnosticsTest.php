<?php

use Empire2\GazeGhostwriter\Services\GhostwriterSmtpDiagnostics;

test('smtp diagnostics reports incomplete when host or from missing', function () {
    config([
        'ghostwriter.smtp.host' => 'mail.example.test',
        'ghostwriter.reply.from_address' => '',
    ]);

    $result = app(GhostwriterSmtpDiagnostics::class)->run();

    expect($result['ok'])->toBeFalse()
        ->and($result['headline'])->toBe('SMTP-Konfiguration unvollständig');
});

test('smtp diagnostics null driver skips real connection and succeeds', function () {
    config([
        'ghostwriter.smtp.driver' => 'null',
        'ghostwriter.smtp.host' => 'mail.example.test',
        'ghostwriter.reply.from_address' => 'support@example.test',
    ]);

    $result = app(GhostwriterSmtpDiagnostics::class)->run();

    expect($result['ok'])->toBeTrue()
        ->and($result['headline'])->toBe('SMTP (Null-Treiber)');
});
