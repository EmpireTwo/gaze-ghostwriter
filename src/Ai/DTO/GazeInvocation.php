<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Ai\DTO;

final readonly class GazeInvocation
{
    /**
     * @param  list<string>  $argv
     */
    public function __construct(
        public string $stage,
        public array $argv,
        public string $stdinPreview,
        public int $stdinBytes,
        public int $durationMs,
    ) {}

    /**
     * @return array{stage: string, argv: list<string>, stdin_preview: string, stdin_bytes: int, duration_ms: int}
     */
    public function toArray(): array
    {
        return [
            'stage' => $this->stage,
            'argv' => $this->argv,
            'stdin_preview' => $this->stdinPreview,
            'stdin_bytes' => $this->stdinBytes,
            'duration_ms' => $this->durationMs,
        ];
    }
}
