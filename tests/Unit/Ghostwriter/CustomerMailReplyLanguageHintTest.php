<?php

use Empire2\GazeGhostwriter\Support\CustomerMailReplyLanguageHint;

test('buildPromptDirective requires English for clear English support mail', function () {
    $text = CustomerMailReplyLanguageHint::buildPromptDirective(
        'Question about my release',
        'Hi, could you please help me understand how to postpone my release? Thank you.',
        'Deutsch',
    );

    expect($text)
        ->toContain('ausschließlich Englisch')
        ->toContain('Regenerierung');
});

test('buildPromptDirective requires German for clear German support mail', function () {
    $text = CustomerMailReplyLanguageHint::buildPromptDirective(
        'Release verschieben',
        'Kann ich mein Release noch verschieben? Vielen Dank.',
        'English',
    );

    expect($text)->toContain('ausschließlich Deutsch');
});

test('buildPromptDirective uses fallback label when customer text is empty', function () {
    $text = CustomerMailReplyLanguageHint::buildPromptDirective('', '', 'Deutsch');

    expect($text)->toContain('ausschließlich Deutsch');
    expect($text)->not->toContain('Englisch');
});
