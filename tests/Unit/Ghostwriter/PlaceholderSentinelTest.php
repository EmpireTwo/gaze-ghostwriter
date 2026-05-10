<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Support\PlaceholderSentinel;

test('protect replaces bracketed tokens with sentinels and restore brings them back', function () {
    $input = "Hallo Ana,\n\nDanke fuer deine Nachricht.\n\nGruesse,\n[Dein Vorname]";

    [$protected, $sentinels] = PlaceholderSentinel::protect($input);

    expect($protected)->not->toContain('[Dein Vorname]')
        ->and($protected)->toContain('__GWPH_0__')
        ->and($sentinels)->toBe([0 => '[Dein Vorname]']);

    $restored = PlaceholderSentinel::restore($protected, $sentinels);

    expect($restored)->toBe($input);
});

test('protect handles multiple distinct placeholders independently', function () {
    $input = '[Dein Name] / [Dein Vorname]';

    [$protected, $sentinels] = PlaceholderSentinel::protect($input);

    expect($protected)->toBe('__GWPH_0__ / __GWPH_1__')
        ->and($sentinels)->toBe([
            0 => '[Dein Name]',
            1 => '[Dein Vorname]',
        ]);

    expect(PlaceholderSentinel::restore($protected, $sentinels))->toBe($input);
});

test('protect assigns separate sentinels to repeated placeholders so each can be restored', function () {
    $input = '[Dein Name] und [Dein Name]';

    [$protected, $sentinels] = PlaceholderSentinel::protect($input);

    expect($protected)->toBe('__GWPH_0__ und __GWPH_1__')
        ->and(PlaceholderSentinel::restore($protected, $sentinels))->toBe($input);
});

test('protect leaves text without placeholders unchanged', function () {
    $input = 'Hallo Welt, hier sind keine Platzhalter.';

    [$protected, $sentinels] = PlaceholderSentinel::protect($input);

    expect($protected)->toBe($input)
        ->and($sentinels)->toBe([]);
});

test('restore is a no-op when sentinels list is empty', function () {
    expect(PlaceholderSentinel::restore('Hola, __GWPH_0__', []))->toBe('Hola, __GWPH_0__');
});

test('protect ignores tokens that span newlines or contain nested brackets', function () {
    $input = "[noch\noffen] und [foo[bar]]";

    [$protected, $sentinels] = PlaceholderSentinel::protect($input);

    expect($sentinels)->toBe([0 => '[bar]'])
        ->and($protected)->toBe("[noch\noffen] und [foo__GWPH_0__]");
});
