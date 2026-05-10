<?php

declare(strict_types=1);

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Prompts\PromptResolver;
use Empire2\GazeGhostwriter\Services\DraftPromptComposer;

beforeEach(function () {
    $this->composer = new DraftPromptComposer(new PromptResolver);
});

test('composes user prompt with message details and no rag', function () {
    $message = SupportMailMessage::factory()->create([
        'subject' => 'Release verschieben',
        'from_name' => 'Anna',
        'from_email' => 'anna@example.com',
        'body_text' => 'Kann ich mein Release noch verschieben?',
    ]);

    $result = $this->composer->compose($message, [], false, null);

    expect($result)
        ->toContain('[ORIGINAL_EMAIL]')
        ->toContain('[/ORIGINAL_EMAIL]')
        ->toContain('ausschließlich Deutsch')
        ->toContain('Die Antwortsprache des Entwurfs richtet sich nach dem Kundeninhalt')
        ->toContain('Betreff: Release verschieben')
        ->toContain('Von: <anna@example.com>')
        ->toContain('Kann ich mein Release noch verschieben?')
        ->toContain('als Support.')
        ->not->toContain('Anna')
        ->toContain('keine passenden historischen Einträge');
});

test('composes user prompt with rag snippets', function () {
    $message = SupportMailMessage::factory()->create([
        'subject' => 'Frage',
        'from_name' => 'Test',
        'from_email' => 'test@example.com',
        'body_text' => 'Wie funktioniert das?',
    ]);

    $snippets = [
        ['chunk_id' => 42, 'score' => 0.9123, 'excerpt' => 'Frühere Antwort zu Release'],
    ];

    $result = $this->composer->compose($message, $snippets, false, null);

    expect($result)
        ->toContain('42 | 0.9123 | Frühere Antwort zu Release')
        ->toContain('Referenz-Snippets (je Zeile:');
});

test('composes user prompt with withheld rag for bare greetings', function () {
    $message = SupportMailMessage::factory()->create([
        'subject' => 'Hi',
        'from_name' => 'Test',
        'from_email' => 'test@example.com',
        'body_text' => 'Hi',
    ]);

    $result = $this->composer->compose($message, [], true, null);

    expect($result)
        ->toContain('absichtlich nicht einbezogen')
        ->toContain('Hinweis für den Entwurf');
});

test('composes user prompt with regeneration section', function () {
    $message = SupportMailMessage::factory()->create([
        'subject' => 'Frage',
        'from_name' => 'Test',
        'from_email' => 'test@example.com',
        'body_text' => 'Was geht?',
    ]);

    $previousDraft = 'Ein früherer Entwurf, der nicht gut war.';

    $result = $this->composer->compose($message, [], false, $previousDraft);

    expect($result)
        ->toContain('WICHTIG bei Regenerierung — Sprache')
        ->toContain('nicht nach dem früheren Entwurf')
        ->toContain('früherer Entwurf, der ersetzt werden soll')
        ->toContain($previousDraft);
});

test('composed prompt requires English when customer mail is English even with German previous draft', function () {
    $message = SupportMailMessage::factory()->create([
        'subject' => 'Billing question',
        'from_name' => 'Sam',
        'from_email' => 'sam@example.com',
        'body_text' => 'I would like to know when my invoice will be sent. Thanks.',
    ]);

    $previousDraft = 'Hallo! Gerne helfen wir dir bei deiner Frage weiter.';

    $result = $this->composer->compose($message, [], false, $previousDraft);

    expect($result)
        ->toContain('ausschließlich Englisch')
        ->toContain('WICHTIG bei Regenerierung — Sprache')
        ->toContain($previousDraft);
});

test('formats snippets block returns withheld message for bare greeting', function () {
    $result = $this->composer->formatSnippetsBlock([], true);

    expect($result)->toContain('absichtlich nicht einbezogen');
});

test('formats snippets block returns empty message when no snippets', function () {
    $result = $this->composer->formatSnippetsBlock([], false);

    expect($result)->toContain('keine passenden historischen Einträge');
});

test('formats regenerate section returns empty string when no previous draft', function () {
    expect($this->composer->formatRegenerateSection(null))->toBe('')
        ->and($this->composer->formatRegenerateSection(''))->toBe('')
        ->and($this->composer->formatRegenerateSection('   '))->toBe('');
});

test('formats bare greeting hint returns empty string when not withheld', function () {
    expect($this->composer->formatBareGreetingHint(false))->toBe('');
});

test('splitAndCleanBody strips sender signature and separates reply history', function () {
    $body = "Hallo, hier meine Frage.\n\n--\nMax Mustermann\nFirma GmbH\n\nOn Mon 1. Jan 2026 at 10:00, Support wrote:\n> Hallo Max\n> Danke für deine Nachricht.\n> Grüße, Support";

    $result = $this->composer->splitAndCleanBody($body);

    expect($result['latest'])
        ->toContain('Hallo, hier meine Frage')
        ->not->toContain('Max Mustermann')
        ->not->toContain('Firma GmbH');
});

test('splitAndCleanBody returns only latest when no quoted reply', function () {
    $body = 'Nur eine einfache Frage.';

    $result = $this->composer->splitAndCleanBody($body);

    expect($result['latest'])->toBe('Nur eine einfache Frage.');
});

test('compose sends cleaned body without history to prompt template', function () {
    $body = "Meine Frage\n\n--\nSender Signatur\n\nOn Mon wrote:\n> Old reply content here\n> And more content here\n> Third line of history";

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Test',
        'from_name' => 'Krishan',
        'from_email' => 'krishan@example.com',
        'body_text' => $body,
    ]);

    $result = $this->composer->compose($message, [], false, null);

    expect($result)
        ->toContain('[ORIGINAL_EMAIL]')
        ->toContain('Meine Frage')
        ->toContain('als Support.')
        ->not->toContain('Krishan')
        ->not->toContain('Sender Signatur')
        ->not->toContain('Old reply content here');
});
