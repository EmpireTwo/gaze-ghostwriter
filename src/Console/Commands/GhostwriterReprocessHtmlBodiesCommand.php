<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Console\Commands;

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\ChunkEmbeddingService;
use Empire2\GazeGhostwriter\Support\HtmlToPlainText;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class GhostwriterReprocessHtmlBodiesCommand extends Command
{
    protected $signature = 'ghostwriter:reprocess-html-bodies';

    protected $description = 'Ghostwriter: body_text aus body_html mit sauberem HTML→Text neu berechnen (entfernt CSS/JS-Artefakte)';

    public function handle(ChunkEmbeddingService $embeddingService): int
    {
        $messages = SupportMailMessage::query()
            ->whereNotNull('body_html')
            ->with('chunks')
            ->get();

        if ($messages->isEmpty()) {
            note('Keine Nachrichten mit body_html gefunden — nichts zu tun.');

            return Command::SUCCESS;
        }

        $updated = 0;
        $chunksUpdated = 0;

        foreach ($messages as $message) {
            $cleanText = HtmlToPlainText::convert((string) $message->body_html);

            if ($cleanText === $message->body_text) {
                continue;
            }

            $message->update(['body_text' => $cleanText]);
            $updated++;

            foreach ($message->chunks as $chunk) {
                $chunk->update(['content' => $cleanText, 'embedding' => null]);
                $embeddingService->embedChunk($chunk);
                $chunksUpdated++;
            }
        }

        if ($updated === 0) {
            note('Alle Nachrichten waren bereits sauber — nichts geändert.');
        } else {
            info("{$updated} Nachricht(en) bereinigt, {$chunksUpdated} Chunk(s) aktualisiert und neu eingebettet.");
        }

        $skipped = $messages->count() - $updated;
        if ($skipped > 0) {
            note("{$skipped} Nachricht(en) waren bereits identisch — übersprungen.");
        }

        return Command::SUCCESS;
    }
}
