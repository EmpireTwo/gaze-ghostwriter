<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class GhostwriterTranslatorAgent implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        private readonly string $sourceLanguage,
        private readonly string $targetLanguage,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<TXT
        Du bist ein präziser Übersetzer.
        Übersetze den folgenden Text aus dem {$this->sourceLanguage} ins {$this->targetLanguage}.
        Gib ausschließlich die Übersetzung im Feld "translated_text" zurück.
        Keine Erklärungen, keine Einleitung, keine Anmerkungen.
        Behalte die Formatierung (Absätze, Zeilenumbrüche) bei.
        Tokens der Form __GWPH_0__, __GWPH_1__ usw. sind technische Platzhalter.
        Übernimm sie unverändert (Schreibweise, Position, Reihenfolge) — niemals übersetzen oder umformatieren.
        TXT;
    }

    /**
     * @return list<Message>
     */
    public function messages(): iterable
    {
        return [];
    }

    public function tools(): iterable
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'translated_text' => $schema->string()->required(),
        ];
    }
}
