<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Prompts\PromptResolver;

test('resolves draft system prompt with locale variable', function () {
    $resolver = new PromptResolver;

    $result = $resolver->resolve('draft-system', ['localeLabel' => 'Deutsch']);

    expect($result)
        ->toContain('Ghostwriter 3000')
        ->toContain('OBERSTE REGEL — Antwortsprache')
        ->toContain('dominanten Hauptsprache')
        ->toContain('Passe Länge und Tiefe')
        ->toContain('Erfinde keine Details')
        ->not->toContain('{{ localeLabel }}');
});

test('resolves draft user prompt with all variables', function () {
    $resolver = new PromptResolver;

    $result = $resolver->resolve('draft-user', [
        'snippetsBlock' => 'test-snippets',
        'subject' => 'Betreff Test',
        'fromName' => 'Max',
        'fromEmail' => 'max@example.com',
        'bodyTextLatest' => 'Hallo Welt',
        'regenerateSection' => '',
        'bareGreetingHint' => '',
    ]);

    expect($result)
        ->toContain('test-snippets')
        ->toContain('[ORIGINAL_EMAIL]')
        ->toContain('Betreff: Betreff Test')
        ->toContain('Von: <max@example.com>')
        ->toContain('Hallo Welt')
        ->toContain('[/ORIGINAL_EMAIL]')
        ->toContain('als Support.')
        ->not->toContain('Max')
        ->toContain('referenzierte_chunk_ids');
});

test('throws for missing prompt file', function () {
    $resolver = new PromptResolver;

    $resolver->resolve('nonexistent-prompt');
})->throws(InvalidArgumentException::class, 'Prompt file not found');

test('uses override path when provided', function () {
    $tmpDir = sys_get_temp_dir().'/ghostwriter-prompts-test-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/custom.php', "<?php\nreturn 'Custom prompt for {{ name }}';");

    $resolver = new PromptResolver($tmpDir);
    $result = $resolver->resolve('custom', ['name' => 'Artistfy']);

    expect($result)->toBe('Custom prompt for Artistfy');

    unlink($tmpDir.'/custom.php');
    rmdir($tmpDir);
});

test('uses config path when ghostwriter prompts path is configured', function () {
    $tmpDir = sys_get_temp_dir().'/ghostwriter-prompts-config-test-'.uniqid();
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir.'/draft-system.php', "<?php\nreturn 'Overridden system prompt';");

    config(['gaze-ghostwriter.prompts.path' => $tmpDir]);

    $resolver = new PromptResolver;
    $result = $resolver->resolve('draft-system');

    expect($result)->toBe('Overridden system prompt');

    unlink($tmpDir.'/draft-system.php');
    rmdir($tmpDir);
});
