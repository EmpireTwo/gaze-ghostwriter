<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Models\SupportMailChunk;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Embeddings;
use Throwable;

class ChunkEmbeddingService
{
    public function embedChunk(SupportMailChunk $chunk): void
    {
        $text = trim($chunk->content);
        if ($text === '') {
            return;
        }

        try {
            $response = Embeddings::for([$text])->generate(
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
