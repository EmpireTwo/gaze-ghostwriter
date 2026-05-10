<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Agents\GhostwriterTranslatorAgent;
use Empire2\GazeGhostwriter\Ai\Contracts\GuardedAgentRunnerContract;
use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Support\PlaceholderSentinel;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Agent;
use Naoray\GazeLaravel\Exceptions\GazeUnknownTokenException;
use Throwable;

class GhostwriterTranslationService
{
    private const LANGUAGE_LABELS = [
        'de' => 'Deutsche',
        'en' => 'Englische',
        'fr' => 'Französische',
        'pt' => 'Portugiesische',
        'es' => 'Spanische',
        'it' => 'Italienische',
        'nl' => 'Niederländische',
        'pl' => 'Polnische',
        'tr' => 'Türkische',
        'ru' => 'Russische',
    ];

    public function __construct(
        private readonly GuardedAgentRunnerContract $runner,
    ) {}

    public function translateToGerman(string $text, string $sourceLanguage, SupportDraft|SupportMailMessage|null $source = null): ?string
    {
        $sourceLabel = self::LANGUAGE_LABELS[$sourceLanguage] ?? $sourceLanguage;

        return $this->translate($text, $sourceLabel, 'Deutsche');
    }

    public function translateFromGerman(string $germanText, string $targetLanguage, SupportDraft|SupportMailMessage|null $source = null): ?string
    {
        $targetLabel = self::LANGUAGE_LABELS[$targetLanguage] ?? $targetLanguage;

        return $this->translate($germanText, 'Deutsche', $targetLabel);
    }

    public function generateTranslationsForDraft(SupportDraft $draft): void
    {
        if (! $draft->needsTranslation()) {
            return;
        }

        $language = (string) $draft->detected_language;
        $message = $draft->message;

        $mailBody = trim((string) $message->body_text);
        $draftBody = trim((string) $draft->draft_body);

        $mailTranslation = null;
        if ($mailBody !== '') {
            $mailTranslation = $this->translateToGerman($mailBody, $language, $draft);
        }

        $draftTranslation = null;
        if ($draftBody !== '') {
            $draftTranslation = $this->translateToGerman($draftBody, $language, $draft);
        }

        $draft->update([
            'mail_translation' => $mailTranslation,
            'draft_translation' => $draftTranslation,
        ]);
    }

    private function translate(string $text, string $sourceLabel, string $targetLabel): ?string
    {
        [$protectedText, $sentinels] = PlaceholderSentinel::protect($text);

        /** @var class-string<Agent> $agentClass */
        $agentClass = config('gaze-ghostwriter.agents.translator', GhostwriterTranslatorAgent::class);

        $agent = new $agentClass($sourceLabel, $targetLabel);

        $aiProvider = (string) config('ai.default');
        $aiModel = (string) config('gaze-ghostwriter.openai.chat_model');

        try {
            $response = $this->runner->run(
                agent: $agent,
                message: $protectedText,
                options: [
                    'provider' => $aiProvider,
                    'model' => $aiModel,
                ],
            );
        } catch (GazeDisabledException|GazeUnknownTokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::warning('Ghostwriter translation failed', [
                'source' => $sourceLabel,
                'target' => $targetLabel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        /** @var array<string, mixed>|null $structured */
        $structured = $response->structured;
        if ($structured === null) {
            return null;
        }

        $translated = $structured['translated_text'] ?? null;

        if (! is_string($translated) || trim($translated) === '') {
            return null;
        }

        return PlaceholderSentinel::restore($translated, $sentinels);
    }
}
