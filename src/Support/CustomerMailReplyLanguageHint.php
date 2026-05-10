<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Heuristic reply-language hint from customer subject + body.
 * Used in user prompts so regeneration does not follow a wrongly-languaged
 * prior draft.
 */
final class CustomerMailReplyLanguageHint
{
    /** @var list<string> */
    private const GERMAN_MARKERS = [
        'der', 'die', 'das', 'und', 'oder', 'ich', 'nicht', 'mit', 'für', 'von', 'zu', 'auf',
        'ist', 'sind', 'war', 'waren', 'haben', 'hat', 'kann', 'können', 'müssen', 'bitte',
        'danke', 'vielen', 'grüße', 'hallo', 'sehr', 'geehrte', 'mfg', 'noch', 'auch', 'schon',
        'kannst', 'möchte', 'möchten', 'gerne', 'falls', 'wenn', 'dass', 'mir', 'mich',
        'uns', 'euch', 'ihnen', 'ihr', 'mein', 'meine', 'dein', 'deine', 'sein', 'seine',
        'geht', 'gibt', 'habe', 'hätte', 'würde', 'wäre', 'frage', 'fragen',
        'verschieben', 'klären', 'melden', 'nutzen', 'warten',
    ];

    /** @var list<string> */
    private const ENGLISH_MARKERS = [
        'the', 'and', 'you', 'your', 'are', 'were', 'have', 'has', 'could', 'would', 'should',
        'please', 'thanks', 'thank', 'hello', 'hey', 'what', 'how', 'when', 'where', 'this', 'that',
        'with', 'from', 'about', 'help', 'team', 'wondering', 'question', 'any', 'just', 'get',
        'into', 'onto', 'invoice', 'billing', 'account', 'support',
    ];

    public static function buildPromptDirective(string $subject, string $bodyPlain, string $fallbackLocaleLabel): string
    {
        $combined = trim($subject."\n".$bodyPlain);
        $lower = mb_strtolower($combined);

        if ($lower === '') {
            return "Pflicht — Antwortsprache für draft_body: Nutze ausschließlich {$fallbackLocaleLabel}, da kein Kundentext vorliegt.";
        }

        $de = self::scoreTokens($lower, self::GERMAN_MARKERS);
        $en = self::scoreTokens($lower, self::ENGLISH_MARKERS);

        if (preg_match('/[äöüß]/u', $combined) === 1) {
            $de += 4;
        }

        if (preg_match('/\b(thanks|thank you|hello|hi there|best regards|kind regards|cheers)\b/u', $lower) === 1) {
            $en += 3;
        }

        $regen = ' Bei Regenerierung: die Sprache eines früheren Entwurfs ignorieren, wenn sie davon abweicht.';

        if ($de >= $en + 2) {
            return 'Pflicht — Antwortsprache für draft_body: ausschließlich Deutsch (aus Betreff und Kunden-Text abgeleitet).'.$regen;
        }

        if ($en >= $de + 2) {
            return 'Pflicht — Antwortsprache für draft_body: ausschließlich Englisch (aus Betreff und Kunden-Text abgeleitet).'.$regen;
        }

        return 'Antwortsprache für draft_body: bestimme sie aus der dominanten Sprache der Kunden-Mail in [ORIGINAL_EMAIL]. Bei Regenerierung ignoriere die Sprache des früheren Entwurfs vollständig, falls abweichend. Wenn die Sprache wirklich unklar ist: '.$fallbackLocaleLabel.'.';
    }

    /**
     * @param  list<string>  $markers
     */
    private static function scoreTokens(string $lowerText, array $markers): int
    {
        if (preg_match_all('/\p{L}+/u', $lowerText, $matches) === false) {
            return 0;
        }

        /** @var list<string> $tokens */
        $tokens = $matches[0];
        $counts = array_count_values($tokens);
        $score = 0;
        foreach ($markers as $marker) {
            $score += $counts[$marker] ?? 0;
        }

        return $score;
    }
}
