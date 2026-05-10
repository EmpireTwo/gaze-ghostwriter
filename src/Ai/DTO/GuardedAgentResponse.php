<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Ai\DTO;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;

final readonly class GuardedAgentResponse
{
    /**
     * @param  list<string>  $warnings
     * @param  array<string, mixed>|null  $structured
     * @param  array<string, mixed>|null  $rawStructured
     * @param  list<GazeInvocation>  $gazeInvocations
     */
    public function __construct(
        public string $text,
        public string $rawResponseText,
        public array $warnings,
        public int $detections,
        public int $durationMs,
        public string $cleanPrompt,
        public ?array $structured = null,
        public ?array $rawStructured = null,
        public ?Usage $usage = null,
        public ?Meta $meta = null,
        public array $gazeInvocations = [],
    ) {}
}
