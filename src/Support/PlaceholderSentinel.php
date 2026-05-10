<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Protects bracketed placeholders (e.g. `[Dein Name]`) from being mangled
 * by an LLM-only step like translation.
 *
 * Tokens are swapped for sentinel strings (`__GWPH_0__`, `__GWPH_1__`, …)
 * before the LLM call and swapped back afterwards.
 */
final class PlaceholderSentinel
{
    /**
     * Single-line bracketed token, e.g. `[Dein Vorname]`.
     */
    private const PATTERN = '/\[[^\[\]\r\n]+\]/u';

    private const SENTINEL_PREFIX = '__GWPH_';

    private const SENTINEL_SUFFIX = '__';

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    public static function protect(string $text): array
    {
        $replacements = [];

        $protected = preg_replace_callback(
            self::PATTERN,
            function (array $match) use (&$replacements): string {
                $index = count($replacements);
                $replacements[$index] = $match[0];

                return self::sentinel($index);
            },
            $text
        );

        return [(string) $protected, $replacements];
    }

    /**
     * @param  array<int, string>  $replacements
     */
    public static function restore(string $text, array $replacements): string
    {
        if ($replacements === []) {
            return $text;
        }

        $search = [];
        $replace = [];
        foreach ($replacements as $index => $original) {
            $search[] = self::sentinel($index);
            $replace[] = $original;
        }

        return str_replace($search, $replace, $text);
    }

    private static function sentinel(int $index): string
    {
        return self::SENTINEL_PREFIX.$index.self::SENTINEL_SUFFIX;
    }
}
