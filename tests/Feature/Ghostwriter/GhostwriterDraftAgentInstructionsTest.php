<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;

test('ghostwriter draft agent instructions require proportional brevity for short mails', function () {
    $text = (string) (new GhostwriterDraftAgent('Deutsch'))->instructions();

    expect($text)
        ->toContain('OBERSTE REGEL — Antwortsprache')
        ->toContain('dominanten Hauptsprache')
        ->and($text)->toContain('Passe Länge und Tiefe')
        ->and($text)->toContain('Erfinde keine Details')
        ->and($text)->toContain('Kürze und Zurückhaltung');
});

test('ghostwriter draft agent instructions forbid signatures in draft body', function () {
    $text = (string) (new GhostwriterDraftAgent('Deutsch'))->instructions();

    expect($text)
        ->toContain('KEINE E-Mail-Signatur')
        ->and($text)->toContain('automatisch angehängt');
});

test('ghostwriter draft agent instructions contain strict prohibition and self-check', function () {
    $text = (string) (new GhostwriterDraftAgent('Deutsch'))->instructions();

    expect($text)
        ->toContain('WICHTIGE REGEL (STRICT)')
        ->toContain('keine direkten Zitate')
        ->toContain('[ORIGINAL_EMAIL]')
        ->toContain('Selbstprüfung vor Ausgabe');
});

test('additional instructions are wrapped with STRICT enforcement heading', function () {
    $additional = "Regel: Erwähne immer online-Rechnungen.\nZweite Regel: Prüfe Wiederholungen.";
    $text = (string) (new GhostwriterDraftAgent('Deutsch', additionalInstructions: $additional))->instructions();

    expect($text)
        ->toContain('Zusätzliche Anweisungen (STRICT — vollständig einhalten)')
        ->toContain($additional);
});

test('instructions without additional instructions omit strict section', function () {
    $text = (string) (new GhostwriterDraftAgent('Deutsch'))->instructions();

    expect($text)->not->toContain('Zusätzliche Anweisungen (STRICT');
});
