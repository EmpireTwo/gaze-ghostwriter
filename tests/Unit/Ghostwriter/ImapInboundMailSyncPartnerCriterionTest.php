<?php

use Empire2\GazeGhostwriter\Services\ImapInboundMailSync;

test('partner IMAP criterion uses nested binary OR form', function () {
    $sync = app(ImapInboundMailSync::class);

    $method = new ReflectionMethod(ImapInboundMailSync::class, 'imapPartnerTripleOrCriteria');
    $method->setAccessible(true);

    expect($method->invoke($sync, 'client@example.com'))
        ->toBe('OR OR FROM "client@example.com" TO "client@example.com" CC "client@example.com"');
});

test('partner IMAP criterion escapes backslash and double quote in address', function () {
    $sync = app(ImapInboundMailSync::class);

    $method = new ReflectionMethod(ImapInboundMailSync::class, 'imapPartnerTripleOrCriteria');
    $method->setAccessible(true);

    expect($method->invoke($sync, 'a\\b"c@example.com'))
        ->toBe('OR OR FROM "a\\\\b\\"c@example.com" TO "a\\\\b\\"c@example.com" CC "a\\\\b\\"c@example.com"');
});
