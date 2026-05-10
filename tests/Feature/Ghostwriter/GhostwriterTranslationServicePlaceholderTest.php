<?php

use Empire2\GazeGhostwriter\Agents\GhostwriterTranslatorAgent;
use Empire2\GazeGhostwriter\Services\GhostwriterTranslationService;
use Laravel\Ai\Ai;

beforeEach(function () {
    config(['ghostwriter.openai.chat_model' => 'gpt-4o-mini']);
});

test('translation service strips bracketed placeholders before calling the agent and restores them in the result', function () {
    Ai::fakeAgent(GhostwriterTranslatorAgent::class, [
        ['translated_text' => "Hola Ana,\nSaludos,\n__GWPH_0__"],
    ]);

    $result = app(GhostwriterTranslationService::class)
        ->translateFromGerman("Hallo Ana,\nGruesse,\n[Dein Vorname]", 'es');

    expect($result)->toBe("Hola Ana,\nSaludos,\n[Dein Vorname]");

    Ai::assertAgentWasPrompted(
        GhostwriterTranslatorAgent::class,
        fn ($prompt) => str_contains($prompt->prompt, '__GWPH_0__')
            && ! str_contains($prompt->prompt, '[Dein Vorname]'),
    );
});

test('translation service round-trips multiple placeholders even when the agent translates the surrounding bracket-shaped text', function () {
    // Worst case: the LLM "translated" [Dein Vorname] into [Tu Nombre] for any
    // un-protected bracket. We pass sentinels in, so the LLM never sees the
    // German tokens; the sentinels echo back unchanged and restore() rebuilds
    // the originals.
    Ai::fakeAgent(GhostwriterTranslatorAgent::class, [
        ['translated_text' => "Hola,\n__GWPH_0__\n\nUn cordial saludo,\n__GWPH_1__"],
    ]);

    $result = app(GhostwriterTranslationService::class)
        ->translateFromGerman(
            "Hallo,\n[Dein Vorname]\n\nMit freundlichen Gruessen,\n[Dein Name]",
            'es'
        );

    expect($result)->toBe("Hola,\n[Dein Vorname]\n\nUn cordial saludo,\n[Dein Name]");
});

test('translation service passes text without placeholders through unchanged', function () {
    Ai::fakeAgent(GhostwriterTranslatorAgent::class, [
        ['translated_text' => 'Hola, mundo.'],
    ]);

    $result = app(GhostwriterTranslationService::class)
        ->translateFromGerman('Hallo, Welt.', 'es');

    expect($result)->toBe('Hola, mundo.');

    Ai::assertAgentWasPrompted(
        GhostwriterTranslatorAgent::class,
        fn ($prompt) => $prompt->prompt === 'Hallo, Welt.',
    );
});
