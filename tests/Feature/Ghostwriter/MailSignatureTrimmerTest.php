<?php

use Empire2\GazeGhostwriter\Support\MailSignatureTrimmer;

test('mail signature trimmer cuts after dash dash delimiter line', function () {
    $body = "Hallo Support,\n\nich habe eine Frage.\n\n-- \nMax Mustermann\nTel. 0123\n";

    expect(MailSignatureTrimmer::trimForGithubIssue($body))->toBe("Hallo Support,\n\nich habe eine Frage.");
});

test('mail signature trimmer cuts after dash dash without space', function () {
    $body = "Kurze Nachricht hier.\n\n--\nFirma GmbH\n";

    expect(MailSignatureTrimmer::trimForGithubIssue($body))->toBe('Kurze Nachricht hier.');
});

test('mail signature trimmer cuts before sent from mobile footer', function () {
    $body = "Hi\n\nBitte helfen.\nSent from my iPhone";

    expect(MailSignatureTrimmer::trimForGithubIssue($body))->toBe("Hi\n\nBitte helfen.");
});

test('mail signature trimmer does not cut when delimiter is too early', function () {
    $body = "--\nshort";

    expect(MailSignatureTrimmer::trimForGithubIssue($body))->toBe($body);
});

test('mail signature trimmer picks earliest boundary when multiple markers exist', function () {
    $body = "This is enough leading text before markers.\n\n-- \nSig line\n\n----------\nMore junk";

    expect(MailSignatureTrimmer::trimForGithubIssue($body))->toBe('This is enough leading text before markers.');
});
