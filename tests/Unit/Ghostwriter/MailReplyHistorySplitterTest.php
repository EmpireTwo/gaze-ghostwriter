<?php

use Empire2\GazeGhostwriter\Support\MailReplyHistorySplitter;

test('splits on English On wrote marker', function () {
    $body = "Thanks for your help with the order.\n\nOn Mon, 1 Jan 2024, Shop wrote:\n> Old message here\n> second line\n";

    $parts = MailReplyHistorySplitter::split($body);

    expect($parts['latest'])->toBe('Thanks for your help with the order.')
        ->and($parts['history'])->toContain('On Mon, 1 Jan 2024, Shop wrote:');
});

test('splits on German Am schrieb marker', function () {
    $body = "Hallo Team,\n\nbitte um Rückmeldung.\n\nAm 04.04.2024 um 10:00 schrieb Kunde:\n> Frühere Mail\n> noch eine Zeile\n> dritte\n";

    $parts = MailReplyHistorySplitter::split($body);

    expect($parts['latest'])->toContain('bitte um Rückmeldung')
        ->and($parts['history'])->toContain('schrieb Kunde:');
});

test('splits on Original Message separator', function () {
    $body = "My reply text is here.\n\n-----Original Message-----\nFrom: someone@example.com\nSubject: Re: Thing\n\nOld";

    $parts = MailReplyHistorySplitter::split($body);

    expect($parts['latest'])->toBe('My reply text is here.')
        ->and($parts['history'])->toContain('-----Original Message-----');
});

test('splits on block of quoted lines after blank line', function () {
    $body = "Neue Nachricht oben.\n\n> Zeile eins\n> Zeile zwei\n> Zeile drei\n> Zeile vier\n";

    $parts = MailReplyHistorySplitter::split($body);

    expect($parts['latest'])->toBe('Neue Nachricht oben.')
        ->and($parts['history'])->toContain('> Zeile eins');
});

test('returns null history when body has no recognizable thread', function () {
    $body = 'Nur ein kurzer Text ohne Zitat und ohne Historie.';

    $parts = MailReplyHistorySplitter::split($body);

    expect($parts['history'])->toBeNull()
        ->and($parts['latest'])->toBe($body);
});

test('returns null history when marker appears too early', function () {
    $body = "Hi\n\nOn Mon wrote:\n> x\n> y\n> z\n";

    $parts = MailReplyHistorySplitter::split($body);

    expect($parts['history'])->toBeNull();
});
