<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Ai\Sanitizer;
use Empire2\GazeGhostwriter\Models\SupportMailChunk;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

class ChunkEmbeddingService
{
    public function __construct(
        private readonly Sanitizer $sanitizer,
    ) {}

    public function embedChunk(SupportMailChunk $chunk): void
    {
        $text = trim($chunk->content);
        if ($text === '') {
            return;
        }

        // Fail-closed: chunk text contains the original mail body, which holds
        // PII (names, emails, addresses). Even though embeddings are stored
        // and never shown to the user, the embedding provider receives the
        // raw text — so we route through Gaze::clean() first. When the
        // boundary is off (or sanitization fails / returns empty), we SKIP
        // the embedding rather than ship pre-sanitized text. Choosing skip
        // over send is intentional: a missing embedding only hurts RAG
        // recall; a leaked one is a privacy incident.
        $sanitized = $this->sanitizer->sanitize($text);
        if ($sanitized === null || trim($sanitized) === '') {
            Log::warning('Ghostwriter chunk embedding skipped (boundary off or sanitize empty)', [
                'chunk_id' => $chunk->id,
            ]);

            return;
        }

        try {
            $response = Embeddings::for([$sanitized])->generate(
                provider: config('ai.default_for_embeddings'),
            );
            $vector = $response->first();
            $chunk->update(['embedding' => $vector]);
        } catch (Throwable $e) {
            Log::warning('Ghostwriter chunk embedding failed', [
                'chunk_id' => $chunk->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
