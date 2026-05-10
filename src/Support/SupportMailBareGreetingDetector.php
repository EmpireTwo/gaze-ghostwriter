<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Detects extremely short inputs without a recognizable support question
 * (e.g. just "Hi"). Used to suppress RAG snippets in those cases — otherwise
 * randomly-similar chunks pollute the prompt with business phrasing the
 * customer never asked about.
 */
final class SupportMailBareGreetingDetector
{
    public static function isBareGreetingOrPing(string $body): bool
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", $body));
        if ($body === '') {
            return false;
        }

        if (str_contains($body, '?')) {
            return false;
        }

        $normalized = mb_strtolower($body);
        $singleLine = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');
        if (mb_strlen($singleLine) > 100) {
            return false;
        }

        /** @var list<string> $words */
        $words = preg_split('/\s+/u', $singleLine, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) > 8) {
            return false;
        }

        if (preg_match(
            '/\b(problem|fehler|bug|ticket|hilfe|dringend|wichtig|bitte|login|passwort|rechnung|kündigung|gutschrift|account|zahlung|abo|abonnement|funktioniert|kaputt|defekt|support|frage|kunde|vertrag)\b/u',
            $singleLine
        ) === 1) {
            return false;
        }

        if (count($words) <= 5 && preg_match(
            '/^(hi|hallo|hey|hello|moin|servus|guten tag|guten morgen|guten abend|guten nachmittag|dear|liebe |lieber |good morning|thanks|thank you|danke|vielen dank|lg\b|vg\b)\b/u',
            $singleLine
        ) === 1) {
            return true;
        }

        return count($words) <= 3 && mb_strlen($singleLine) <= 24;
    }
}
