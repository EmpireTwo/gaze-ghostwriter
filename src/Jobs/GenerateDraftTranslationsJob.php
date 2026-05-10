<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Jobs;

use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Services\GhostwriterTranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateDraftTranslationsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $tries = 2;

    public function __construct(
        public readonly int $draftId,
    ) {}

    public function handle(GhostwriterTranslationService $service): void
    {
        $draft = SupportDraft::query()->with('message')->find($this->draftId);

        if ($draft === null || ! $draft->needsTranslation()) {
            return;
        }

        $service->generateTranslationsForDraft($draft);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('GenerateDraftTranslationsJob failed', [
            'draft_id' => $this->draftId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
