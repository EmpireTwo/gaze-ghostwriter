<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Strips dangerous HTML elements and attributes from pasted email signatures.
 * Intentionally lightweight — admin-only feature, not a public input.
 */
final class HtmlSignatureSanitizer
{
    /** @var list<string> */
    private const DANGEROUS_TAGS = [
        'script',
        'iframe',
        'object',
        'embed',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'applet',
        'meta',
        'link',
        'base',
    ];

    public static function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = self::removeDangerousTags($html);
        $html = self::removeEventHandlerAttributes($html);

        return trim($html);
    }

    private static function removeDangerousTags(string $html): string
    {
        foreach (self::DANGEROUS_TAGS as $tag) {
            $html = preg_replace(
                '/<'.$tag.'\b[^>]*>.*?<\/'.$tag.'>/is',
                '',
                $html
            ) ?? $html;

            $html = preg_replace(
                '/<'.$tag.'\b[^>]*\/?>/i',
                '',
                $html
            ) ?? $html;
        }

        return $html;
    }

    private static function removeEventHandlerAttributes(string $html): string
    {
        return preg_replace(
            '/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
            '',
            $html
        ) ?? $html;
    }
}
