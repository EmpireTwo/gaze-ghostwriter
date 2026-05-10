<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Ai;

use Empire2\GazeGhostwriter\Ai\Contracts\GuardedAgentRunnerContract;
use Empire2\GazeGhostwriter\Ai\DTO\GazeInvocation;
use Empire2\GazeGhostwriter\Ai\DTO\GuardedAgentResponse;
use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Naoray\GazeLaravel\Gaze;
use Naoray\GazeLaravel\GazeSession;

/**
 * Single boundary between the host and any outbound Laravel AI call.
 *
 * Default implementation that enforces one Gaze::clean() / Gaze::restore()
 * pair per run(). Structured responses are tree-walked so every string
 * leaf is restored — otherwise placeholder tokens leak into caller-persisted
 * fields (SupportDraft.draft_body).
 *
 * Boundary off → GazeDisabledException. No bypass branch.
 */
final class GuardedAgentRunner implements GuardedAgentRunnerContract
{
    public function __construct(
        private readonly Gaze $gaze,
    ) {}

    public function run(
        Agent $agent,
        string $message,
        array $options = [],
    ): GuardedAgentResponse {
        if (! $this->boundaryActive()) {
            throw new GazeDisabledException('Gaze boundary disabled.');
        }

        $provider = $options['provider'] ?? null;
        $model = $options['model'] ?? null;

        /** @var list<GazeInvocation> $invocations */
        $invocations = [];

        $startNs = hrtime(true);

        $cleanStart = hrtime(true);
        $session = $this->gaze->clean($message);
        $invocations[] = new GazeInvocation(
            stage: 'clean',
            argv: $this->reconstructCleanArgv(),
            stdinPreview: mb_substr($message, 0, 2048),
            stdinBytes: strlen($message),
            durationMs: (int) ((hrtime(true) - $cleanStart) / 1_000_000),
        );

        $response = $agent->prompt($session->cleanText, provider: $provider, model: $model);

        $rawText = $response->text;
        $restoredText = $this->restoreAndRecord($rawText, $session, $invocations);

        $rawStructured = null;
        $restoredStructured = null;
        if ($response instanceof StructuredAgentResponse) {
            $rawStructured = $response->structured;
            $restoredStructured = $this->restoreStructured($rawStructured, $session, $invocations);
        }

        $durationMs = (int) ((hrtime(true) - $startNs) / 1_000_000);

        return new GuardedAgentResponse(
            text: $restoredText,
            rawResponseText: $rawText,
            warnings: [],
            detections: $session->detections,
            durationMs: $durationMs,
            cleanPrompt: $session->cleanText,
            structured: $restoredStructured,
            rawStructured: $rawStructured,
            usage: $response->usage,
            meta: $response->meta,
            gazeInvocations: $invocations,
        );
    }

    private function boundaryActive(): bool
    {
        return (bool) config('gaze-ghostwriter.gaze_enabled', false);
    }

    /**
     * @param  list<GazeInvocation>  $invocations
     */
    private function restoreAndRecord(string $text, GazeSession $session, array &$invocations): string
    {
        $restoreStart = hrtime(true);
        $restored = $this->gaze->restore($session, $text);
        $invocations[] = new GazeInvocation(
            stage: 'restore',
            argv: $this->reconstructRestoreArgv(),
            stdinPreview: mb_substr($this->buildRestoreStdin($session, $text), 0, 2048),
            stdinBytes: strlen($this->buildRestoreStdin($session, $text)),
            durationMs: (int) ((hrtime(true) - $restoreStart) / 1_000_000),
        );

        return $restored;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<GazeInvocation>  $invocations
     * @return array<string, mixed>
     */
    private function restoreStructured(array $data, GazeSession $session, array &$invocations): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && $value !== '') {
                $data[$key] = $this->restoreAndRecord($value, $session, $invocations);
            } elseif (is_array($value)) {
                $data[$key] = $this->restoreStructured($value, $session, $invocations);
            }
        }

        return $data;
    }

    private function buildRestoreStdin(GazeSession $session, string $text): string
    {
        return json_encode([
            'session_blob' => sprintf('<redacted, %d-char ciphertext>', strlen($session->ciphertext->ciphertext())),
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function reconstructCleanArgv(): array
    {
        $argv = [$this->gazeBinary(), 'clean'];

        $policy = config('gaze.policy_path');
        if (is_string($policy) && $policy !== '') {
            $argv[] = '--policy='.$policy;
        }

        $argv[] = '--format=json';

        $max = config('gaze.max_bytes');
        if ($max !== null && $max !== '') {
            $argv[] = '--max-bytes='.$max;
        }

        $ttl = config('gaze.session_ttl_seconds');
        if ($ttl !== null && $ttl !== '') {
            $argv[] = '--session-ttl='.$ttl;
        }

        return $argv;
    }

    /**
     * @return list<string>
     */
    private function reconstructRestoreArgv(): array
    {
        return [$this->gazeBinary(), 'restore', '--format=json'];
    }

    private function gazeBinary(): string
    {
        $binary = config('gaze.binary');

        return is_string($binary) && $binary !== '' ? $binary : 'gaze';
    }
}
