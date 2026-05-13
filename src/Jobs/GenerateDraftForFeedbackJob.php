<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Jobs;

use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Naoray\GazeLaravel\Exceptions\GazeUnknownTokenException;
use Naoray\GazeLaravel\Queue\Contracts\NonRetryable;

final class GenerateDraftForFeedbackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 120;

    public function __construct(public int $messageId) {}

    public function handle(DraftGeneratorService $draftGeneratorService): void
    {
        $message = SupportMailMessage::find($this->messageId);
        if ($message === null) {
            return;
        }

        try {
            $draftGeneratorService->generateForMessage($message);
        } catch (GazeDisabledException) {
            $message->update(['processing_status' => 'gaze_disabled']);
            Log::warning('gaze-ghostwriter.gaze.disabled_defer', [
                'message_id' => $message->id,
                'channel' => 'web',
            ]);
        } catch (GazeUnknownTokenException $e) {
            $message->update(['processing_status' => 'gaze_restore_exhausted']);
            Log::error('gaze-ghostwriter.gaze.restore_exhausted', [
                'message_id' => $message->id,
                'channel' => 'web',
                'exception' => $e->getMessage(),
            ]);
        } catch (NonRetryable $e) {
            $message->update(['processing_status' => 'gaze_nonretryable']);
            Log::error('gaze-ghostwriter.gaze.nonretryable', [
                'message_id' => $message->id,
                'channel' => 'web',
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);
        }
    }
}
