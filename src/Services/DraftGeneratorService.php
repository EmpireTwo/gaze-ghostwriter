<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Carbon\CarbonInterface;
use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Ai\Contracts\GuardedAgentRunnerContract;
use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Jobs\GenerateDraftTranslationsJob;
use Empire2\GazeGhostwriter\Models\GhostwriterAdditionalPrompt;
use Empire2\GazeGhostwriter\Models\GhostwriterPromptHistory;
use Empire2\GazeGhostwriter\Models\GhostwriterSmartAction;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailChunk;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Prompts\PromptResolver;
use Empire2\GazeGhostwriter\Support\CosineSimilarity;
use Empire2\GazeGhostwriter\Support\SupportMailBareGreetingDetector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Embeddings;
use Naoray\GazeLaravel\Exceptions\GazeUnknownTokenException;
use Throwable;

class DraftGeneratorService
{
    public function __construct(
        private readonly DraftPromptComposer $promptComposer,
        private readonly GuardedAgentRunnerContract $runner,
    ) {}

    public function generateForMessage(SupportMailMessage $message): ?SupportDraft
    {
        if (! $message->matches_support_address) {
            return null;
        }

        if ($message->drafts()->exists()) {
            return null;
        }

        $payload = $this->buildDraftPayload($message, previousDraftBody: null);
        if ($payload === null) {
            return null;
        }

        $draft = SupportDraft::query()->create([
            'support_mail_message_id' => $message->id,
            'draft_body' => $payload['draft_body'],
            'rationale' => $payload['rationale'],
            'status' => DraftStatus::PENDING_REVIEW,
            'smart_action_tags' => $payload['smart_action_tags'],
            'mentioned_entities' => $payload['mentioned_entities'],
            'detected_language' => $payload['detected_language'],
            'gaze_warnings' => $payload['gaze_warnings'],
            'clean_prompt' => $payload['clean_prompt'],
            'llm_raw_response' => $payload['llm_raw_response'],
            'gaze_detections' => $payload['gaze_detections'],
            'gaze_duration_ms' => $payload['gaze_duration_ms'],
            'gaze_ran_at' => $payload['gaze_ran_at'],
            'gaze_invocations' => $payload['gaze_invocations'],
        ]);

        Log::info('ghostwriter.gaze.run', [
            'source_type' => $message::class,
            'support_mail_message_id' => $message->id,
            'detections' => $payload['gaze_detections'],
            'duration_ms' => $payload['gaze_duration_ms'],
            'warning_count' => count($payload['gaze_warnings']),
        ]);

        $this->recordPromptHistory($payload, $message, $draft, isRegeneration: false);

        if ($draft->needsTranslation()) {
            GenerateDraftTranslationsJob::dispatch($draft->id);
        }

        return $draft;
    }

    public function regenerateFromDraft(SupportDraft $draft): ?SupportDraft
    {
        if ($draft->status !== DraftStatus::PENDING_REVIEW) {
            return null;
        }

        $message = $draft->message;
        if (! $message->matches_support_address) {
            return null;
        }

        $payload = $this->buildDraftPayload($message, previousDraftBody: $draft->draft_body);
        if ($payload === null) {
            return null;
        }

        $draft->update([
            'draft_body' => $payload['draft_body'],
            'rationale' => $payload['rationale'],
            'edited_body' => null,
            'smart_action_tags' => $payload['smart_action_tags'],
            'mentioned_entities' => $payload['mentioned_entities'],
            'detected_language' => $payload['detected_language'],
            'draft_translation' => null,
            'edited_draft_translation' => null,
            'gaze_warnings' => $payload['gaze_warnings'],
            'clean_prompt' => $payload['clean_prompt'],
            'llm_raw_response' => $payload['llm_raw_response'],
            'gaze_detections' => $payload['gaze_detections'],
            'gaze_duration_ms' => $payload['gaze_duration_ms'],
            'gaze_ran_at' => $payload['gaze_ran_at'],
            'gaze_invocations' => $payload['gaze_invocations'],
        ]);

        $this->recordPromptHistory($payload, $message, $draft, isRegeneration: true);

        $draft->refresh();

        if ($draft->needsTranslation()) {
            GenerateDraftTranslationsJob::dispatch($draft->id);
        }

        return $draft;
    }

    /**
     * @return array{draft_body: string, rationale: array<string, mixed>, smart_action_tags: list<string>, mentioned_entities: list<array{type: string, query: string}>, detected_language: string|null, gaze_warnings: list<string>, clean_prompt: string, llm_raw_response: array{text: string, structured: array<string, mixed>|null}, gaze_detections: int, gaze_duration_ms: int, gaze_ran_at: CarbonInterface, gaze_invocations: list<array{stage: string, argv: list<string>, stdin_preview: string, stdin_bytes: int, duration_ms: int}>, _prompt_meta: array<string, mixed>}|null
     */
    private function buildDraftPayload(SupportMailMessage $message, ?string $previousDraftBody): ?array
    {
        $queryText = trim($message->subject."\n\n".$message->body_text);
        if ($queryText === '') {
            return null;
        }

        $bodyPlain = trim(str_replace(["\r\n", "\r"], "\n", (string) $message->body_text));
        $withholdRag = SupportMailBareGreetingDetector::isBareGreetingOrPing($bodyPlain);

        $retrieved = [];
        if (! $withholdRag) {
            $queryVector = null;
            try {
                $queryVector = Embeddings::for([$queryText])->generate(
                    provider: config('ai.default_for_embeddings'),
                )->first();
            } catch (Throwable $e) {
                Log::warning('Ghostwriter query embedding failed', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $topK = (int) config('gaze-ghostwriter.rag.top_k', 5);
            $retrieved = $this->retrieveChunks($message->id, $queryVector, $topK);
        }

        $locale = config('gaze-ghostwriter.locale', 'de');
        $localeLabel = $locale === 'de' ? 'Deutsch' : $locale;

        $agent = $this->resolveAgent($localeLabel);

        $userPrompt = $this->promptComposer->compose(
            $message,
            $retrieved,
            $withholdRag,
            $previousDraftBody,
        );

        $rulesReminder = $this->buildRulesReminder();
        if ($rulesReminder !== '') {
            $userPrompt .= "\n\n".$rulesReminder;
        }

        $systemPrompt = (string) $agent->instructions();
        $aiProvider = (string) config('ai.default');
        $aiModel = (string) config('gaze-ghostwriter.openai.chat_model');

        $startTime = hrtime(true);

        try {
            $guardedResponse = $this->runner->run(
                agent: $agent,
                message: $userPrompt,
                options: [
                    'provider' => $aiProvider,
                    'model' => $aiModel,
                ],
            );
        } catch (GazeDisabledException|GazeUnknownTokenException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Ghostwriter draft generation failed', [
                'support_mail_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        /** @var array<string, mixed>|null $structured */
        $structured = $guardedResponse->structured;
        if ($structured === null) {
            return null;
        }

        $rationale = [
            'thematische_begruendung' => (string) ($structured['thematische_begruendung'] ?? ''),
            'stilistische_begruendung' => (string) ($structured['stilistische_begruendung'] ?? ''),
            'referenzierte_chunk_ids' => $structured['referenzierte_chunk_ids'] ?? [],
            'retrieved_snippets' => array_map(static fn (array $row): array => [
                'chunk_id' => $row['chunk_id'],
                'score' => $row['score'],
                'excerpt' => Str::limit($row['excerpt'], 400),
            ], $retrieved),
        ];

        $draftBody = (string) ($structured['draft_body'] ?? '');
        if (! str_contains($draftBody, "\n") && str_contains($draftBody, '\n')) {
            $draftBody = str_replace(['\r\n', '\r', '\n'], ["\n", "\n", "\n"], $draftBody);
        }

        /** @var list<string> $smartActionTags */
        $smartActionTags = is_array($structured['smart_action_tags'] ?? null)
            ? array_values(array_filter($structured['smart_action_tags'], 'is_string'))
            : [];

        /** @var list<array{type: string, query: string}> $mentionedEntities */
        $mentionedEntities = is_array($structured['mentioned_entities'] ?? null)
            ? array_values(array_filter(
                $structured['mentioned_entities'],
                static fn (mixed $e): bool => is_array($e) && isset($e['type'], $e['query']),
            ))
            : [];

        $usage = $guardedResponse->usage;

        $detectedLanguage = is_string($structured['detected_language'] ?? null)
            ? mb_strtolower(trim($structured['detected_language']))
            : null;

        return [
            'draft_body' => $draftBody,
            'rationale' => $rationale,
            'smart_action_tags' => $smartActionTags,
            'mentioned_entities' => $mentionedEntities,
            'detected_language' => $detectedLanguage,
            'gaze_warnings' => $guardedResponse->warnings,
            'clean_prompt' => $guardedResponse->cleanPrompt,
            'llm_raw_response' => [
                'text' => $guardedResponse->rawResponseText,
                'structured' => $guardedResponse->rawStructured,
            ],
            'gaze_detections' => $guardedResponse->detections,
            'gaze_duration_ms' => $guardedResponse->durationMs,
            'gaze_ran_at' => now(),
            'gaze_invocations' => array_map(
                static fn ($invocation): array => $invocation->toArray(),
                $guardedResponse->gazeInvocations,
            ),
            '_prompt_meta' => [
                'system_prompt' => $systemPrompt,
                'user_prompt' => $userPrompt,
                'response_structured' => $structured,
                'ai_model' => $aiModel,
                'ai_provider' => $aiProvider,
                'duration_ms' => $durationMs,
                'prompt_tokens' => $usage?->promptTokens,
                'completion_tokens' => $usage?->completionTokens,
                'cache_read_input_tokens' => $usage?->cacheReadInputTokens,
                'cache_write_input_tokens' => $usage?->cacheWriteInputTokens,
                'reasoning_tokens' => $usage?->reasoningTokens,
            ],
        ];
    }

    private function resolveAgent(string $localeLabel): Agent
    {
        /** @var class-string<Agent> $agentClass */
        $agentClass = config('gaze-ghostwriter.agents.draft', GhostwriterDraftAgent::class);

        $promptResolver = app(PromptResolver::class);
        $additionalInstructions = $this->resolveAdditionalInstructions();

        return new $agentClass($localeLabel, $promptResolver, $additionalInstructions);
    }

    private function resolveAdditionalInstructions(): string
    {
        $rules = GhostwriterAdditionalPrompt::globalPrompts();

        $user = Auth::user();
        if ($user !== null) {
            $userRules = GhostwriterAdditionalPrompt::forUser((int) $user->getAuthIdentifier());
            $rules = $rules->merge($userRules);
        }

        $parts = [];

        foreach ($rules->values() as $index => $rule) {
            $num = $index + 1;
            $parts[] = "VERBINDLICHE ZUSATZREGEL #{$num} (STRICT — Nichtbeachtung ist ein Fehler):\n{$rule->body}";
        }

        if ($parts !== []) {
            $checklistLines = [];
            foreach ($rules->values() as $index => $rule) {
                $num = $index + 1;
                $summary = Str::limit(str_replace("\n", ' ', $rule->body), 100, '…');
                $checklistLines[] = '☐ Regel #'.$num.': '.$summary.' — Wurde diese Anweisung im Entwurf umgesetzt?';
            }

            $parts[] = "ABSCHLIESSENDE PFLICHTPRÜFUNG — Gehe JEDE Regel einzeln durch:\n"
                .implode("\n", $checklistLines)
                ."\nFalls eine Prüfung mit NEIN beantwortet wird: Entwurf überarbeiten bis alle Regeln erfüllt sind.";
        }

        $smartActionBlock = GhostwriterSmartAction::buildPromptInstructions();
        if ($smartActionBlock !== null) {
            $parts[] = $smartActionBlock;
        }

        $parts[] = implode("\n", [
            'Entity-Erkennung:',
            'Wenn der Kunde in seiner Mail Künstler, Releases, Songs, Singles oder Alben namentlich erwähnt, extrahiere sie in das Feld mentioned_entities.',
            'Jeder Eintrag hat "type" (artist, release) und "query" (der Name, so wie der Kunde ihn geschrieben hat).',
            'Songs, Singles und Alben sind immer type "release". Nur "artist" für Künstlernamen verwenden.',
            'Nur explizit genannte Namen aufnehmen — keine Vermutungen. Wenn nichts erwähnt wird, leer lassen.',
        ]);

        return implode("\n\n", $parts);
    }

    private function buildRulesReminder(): string
    {
        $rules = GhostwriterAdditionalPrompt::globalPrompts();

        $user = Auth::user();
        if ($user !== null) {
            $rules = $rules->merge(GhostwriterAdditionalPrompt::forUser((int) $user->getAuthIdentifier()));
        }

        if ($rules->isEmpty()) {
            return '';
        }

        $lines = ['ERINNERUNG — Folgende Zusatzregeln aus dem System-Prompt MÜSSEN im Entwurf umgesetzt sein:'];
        foreach ($rules->values() as $index => $rule) {
            $num = $index + 1;
            $summary = Str::limit(str_replace("\n", ' ', $rule->body), 120, '…');
            $lines[] = "#{$num}: {$summary}";
        }
        $lines[] = 'Prüfe den Entwurf auf Einhaltung ALLER Regeln bevor du ihn ausgibst.';

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordPromptHistory(array $payload, SupportMailMessage $message, SupportDraft $draft, bool $isRegeneration): void
    {
        $meta = $payload['_prompt_meta'] ?? null;
        if (! is_array($meta)) {
            return;
        }

        try {
            GhostwriterPromptHistory::query()->create([
                'support_mail_message_id' => $message->id,
                'support_draft_id' => $draft->id,
                'system_prompt' => $meta['system_prompt'],
                'user_prompt' => $meta['user_prompt'],
                'response_structured' => $meta['response_structured'],
                'ai_model' => $meta['ai_model'],
                'ai_provider' => $meta['ai_provider'],
                'duration_ms' => $meta['duration_ms'],
                'prompt_tokens' => $meta['prompt_tokens'] ?? null,
                'completion_tokens' => $meta['completion_tokens'] ?? null,
                'cache_read_input_tokens' => $meta['cache_read_input_tokens'] ?? null,
                'cache_write_input_tokens' => $meta['cache_write_input_tokens'] ?? null,
                'reasoning_tokens' => $meta['reasoning_tokens'] ?? null,
                'is_regeneration' => $isRegeneration,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record prompt history', [
                'support_mail_message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  list<float>|null  $queryVector
     * @return list<array{chunk_id: int, score: float, excerpt: string}>
     */
    private function retrieveChunks(int $excludeMessageId, ?array $queryVector, int $topK): array
    {
        if ($queryVector === null || $topK < 1) {
            return [];
        }

        $chunks = SupportMailChunk::query()
            ->whereNotNull('embedding')
            ->where('support_mail_message_id', '!=', $excludeMessageId)
            ->limit(500)
            ->get();

        $scored = [];
        foreach ($chunks as $chunk) {
            /** @var list<float>|null $emb */
            $emb = $chunk->embedding;
            if (! is_array($emb) || $emb === []) {
                continue;
            }
            $score = CosineSimilarity::score($queryVector, $emb);
            $scored[] = [
                'chunk_id' => $chunk->id,
                'score' => $score,
                'excerpt' => Str::limit($chunk->content, 1200),
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }
}
