<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Empire2\GazeGhostwriter\Models\SupportDraft;

/**
 * Applies text normalization to existing draft bodies without re-calling AI.
 * Safe to run repeatedly — only touches drafts that need changes.
 */
final class DraftBodyNormalizer
{
    /**
     * @return array{normalized: int, skipped: int}
     */
    public static function normalizeAll(): array
    {
        $normalized = 0;
        $skipped = 0;

        SupportDraft::query()
            ->whereNotNull('draft_body')
            ->lazyById(100)
            ->each(function (SupportDraft $draft) use (&$normalized, &$skipped): void {
                $changes = [];

                $cleanDraftBody = self::normalizeText($draft->draft_body);
                if ($cleanDraftBody !== $draft->draft_body) {
                    $changes['draft_body'] = $cleanDraftBody;
                }

                if ($draft->edited_body !== null) {
                    $cleanEditedBody = self::normalizeText($draft->edited_body);
                    if ($cleanEditedBody !== $draft->edited_body) {
                        $changes['edited_body'] = $cleanEditedBody;
                    }
                }

                if ($changes !== []) {
                    $draft->update($changes);
                    $normalized++;
                } else {
                    $skipped++;
                }
            });

        return ['normalized' => $normalized, 'skipped' => $skipped];
    }

    public static function normalizeText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        if (! str_contains($text, "\n") && str_contains($text, '\n')) {
            $text = str_replace(['\r\n', '\r', '\n'], ["\n", "\n", "\n"], $text);
        }

        return $text;
    }
}
