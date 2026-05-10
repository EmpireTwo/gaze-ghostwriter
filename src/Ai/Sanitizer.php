<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Ai;

use Naoray\GazeLaravel\Gaze;

/**
 * Pure-redaction utility for paths that persist sanitized text externally
 * (e.g. GitHub issue bodies) — no restore step.
 *
 * Boundary off (config `gaze-ghostwriter.gaze_enabled` is false) → returns
 * null and the caller falls back to its own heuristics.
 */
final class Sanitizer
{
    public function __construct(
        private readonly Gaze $gaze,
    ) {}

    public function sanitize(string $text): ?string
    {
        if (! (bool) config('gaze-ghostwriter.gaze_enabled', false)) {
            return null;
        }

        return $this->gaze->clean($text)->cleanText;
    }
}
