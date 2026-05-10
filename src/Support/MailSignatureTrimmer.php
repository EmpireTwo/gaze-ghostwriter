<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Removes common e-mail signature blocks from plain-text bodies before public
 * export (e.g. GitHub). Heuristic only — no AI.
 */
final class MailSignatureTrimmer
{
    private const MIN_BYTES_BEFORE_CUT = 12;

    public static function trimForGithubIssue(string $body): string
    {
        return self::trimForGithubIssueWithMeta($body)['text'];
    }

    /**
     * @return array{text: string, was_trimmed: bool}
     */
    public static function trimForGithubIssueWithMeta(string $body): array
    {
        $normalized = str_replace("\r\n", "\n", $body);
        if (trim($normalized) === '') {
            return ['text' => $normalized, 'was_trimmed' => false];
        }

        $pos = self::earliestSignatureBoundary($normalized);
        if ($pos === null || $pos < self::MIN_BYTES_BEFORE_CUT) {
            return ['text' => $normalized, 'was_trimmed' => false];
        }

        return [
            'text' => rtrim(substr($normalized, 0, $pos)),
            'was_trimmed' => true,
        ];
    }

    private static function earliestSignatureBoundary(string $body): ?int
    {
        $candidates = [];

        if (preg_match_all('/^--[ \t]*$/m', $body, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as $match) {
                $candidates[] = (int) $match[1];
            }
        }

        if (preg_match_all('/\n-{10,}\h*\n/', $body, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as $match) {
                $candidates[] = (int) $match[1];
            }
        }

        if (preg_match_all('/\n_{10,}\h*\n/', $body, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as $match) {
                $candidates[] = (int) $match[1];
            }
        }

        $footerLinePatterns = [
            '/^Sent from .+$/mi',
            '/^Von meinem .+ gesendet\.?$/mi',
            '/^Gesendet von .+$/mi',
            '/^Get Outlook for .+$/mi',
            '/^Diese E-Mail wurde von .+ gesendet\.$/mi',
        ];

        foreach ($footerLinePatterns as $pattern) {
            if (preg_match($pattern, $body, $m, PREG_OFFSET_CAPTURE) === 1) {
                $candidates[] = (int) $m[0][1];
            }
        }

        if ($candidates === []) {
            return null;
        }

        return min($candidates);
    }
}
