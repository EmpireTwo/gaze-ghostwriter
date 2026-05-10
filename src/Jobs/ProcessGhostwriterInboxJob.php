<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Jobs;

use Empire2\GazeGhostwriter\Ai\Exceptions\BoundaryViolationException;
use Empire2\GazeGhostwriter\Services\GhostwriterInboxProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Naoray\GazeLaravel\Queue\Contracts\NonRetryable;
use Naoray\GazeLaravel\Queue\Contracts\Retryable;
use Throwable;

class ProcessGhostwriterInboxJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function handle(GhostwriterInboxProcessor $ghostwriterInboxProcessor): void
    {
        $ghostwriterInboxProcessor->run();
    }

    /**
     * Gaze exceptions that escape the per-message catches inside the
     * inbox processor land here.
     */
    public function failed(?Throwable $e): void
    {
        if ($e instanceof NonRetryable) {
            Log::error('ghostwriter.gaze.nonretryable_escaped', [
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        if ($e instanceof Retryable) {
            Log::warning('ghostwriter.gaze.retryable_exhausted', [
                'exception' => $e::class,
            ]);

            return;
        }

        if ($e instanceof BoundaryViolationException) {
            Log::error('ghostwriter.gaze.boundary_violation', [
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            return;
        }
    }
}
