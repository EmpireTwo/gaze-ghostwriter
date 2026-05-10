<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

final class MailReplyHistorySplitter
{
    private const MIN_LEADING_BYTES = 20;

    private const MIN_HISTORY_BYTES = 40;

    /**
     * Splits a mail body into the visible "latest" reply and optional quoted /
     * thread history, using common client markers (Outlook, Apple Mail,
     * Thunderbird, Gmail-style).
     *
     * @return array{latest: string, history: string|null}
     */
    public static function split(string $body): array
    {
        $body = str_replace("\r\n", "\n", $body);

        if (trim($body) === '') {
            return ['latest' => $body, 'history' => null];
        }

        $candidates = [];

        foreach (self::literalMarkers() as $literal) {
            $pos = strpos($body, $literal);
            if ($pos !== false && $pos >= self::MIN_LEADING_BYTES) {
                $candidates[] = $pos;
            }
        }

        if (preg_match('/\n-{10,}\s*\n/', $body, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $matches[0][1];
            if ($pos >= self::MIN_LEADING_BYTES) {
                $candidates[] = $pos;
            }
        }

        if (preg_match('/\n_{10,}\s*\n/', $body, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $matches[0][1];
            if ($pos >= self::MIN_LEADING_BYTES) {
                $candidates[] = $pos;
            }
        }

        $regexes = [
            '/\nOn [^\n]+ wrote:\s*\n/i',
            '/\nAm [^\n]+ schrieb[^:\n]*:\s*\n/u',
            '/\nVon:\s*[^\n]+\nGesendet:[^\n]*\n/i',
            '/\nFrom:\s*[^\n]+\nSent:[^\n]*\n/i',
            '/\nBegin forwarded message:\s*\n/i',
            '/\nWeitergeleitete Nachricht\s*\n/i',
        ];

        foreach ($regexes as $pattern) {
            if (preg_match($pattern, $body, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $pos = (int) $matches[0][1];
                if ($pos >= self::MIN_LEADING_BYTES) {
                    $candidates[] = $pos;
                }
            }
        }

        if (preg_match('/\n\n((?:>[^\n]*\n){3,})/', $body, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $pos = (int) $matches[0][1];
            if ($pos >= self::MIN_LEADING_BYTES) {
                $candidates[] = $pos;
            }
        }

        if ($candidates === []) {
            return ['latest' => $body, 'history' => null];
        }

        $splitAt = min($candidates);
        $latest = trim(substr($body, 0, $splitAt));
        $history = trim(substr($body, $splitAt));

        if ($history === '' || strlen($history) < self::MIN_HISTORY_BYTES) {
            return ['latest' => $body, 'history' => null];
        }

        if ($latest === '') {
            return ['latest' => $body, 'history' => null];
        }

        return ['latest' => $latest, 'history' => $history];
    }

    /**
     * @return list<string>
     */
    private static function literalMarkers(): array
    {
        return [
            "\n-----Original Message-----",
            "\n-----Ursprüngliche Nachricht-----",
        ];
    }
}
