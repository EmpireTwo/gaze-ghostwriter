<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

/**
 * Converts HTML email bodies to clean plain text.
 *
 * Unlike bare `strip_tags()`, this removes the *content* of <style>, <script>,
 * and <head> blocks first, so embedded CSS/JS never leaks into the text output.
 */
final class HtmlToPlainText
{
    public static function convert(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $text = $html;

        $text = preg_replace('/<head\b[^>]*>.*?<\/head>/si', '', $text) ?? $text;
        $text = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $text) ?? $text;
        $text = preg_replace('/<style\b[^>]*>.*$/si', '', $text) ?? $text;
        $text = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $text) ?? $text;
        $text = preg_replace('/<script\b[^>]*>.*$/si', '', $text) ?? $text;
        $text = preg_replace('/<!--.*?-->/s', '', $text) ?? $text;

        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/(p|div|tr|li|h[1-6])>/i', "\n", $text) ?? $text;

        $text = strip_tags($text);

        $text = preg_replace('/@media\b[^{]*\{(?:[^{}]*\{[^}]*\})*[^}]*\}/s', '', $text) ?? $text;
        $text = preg_replace('/@[a-z-]+\b[^{]*\{[^}]*\}/si', '', $text) ?? $text;

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = preg_replace('/[ \t\x{00A0}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/^ +$/m', '', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
