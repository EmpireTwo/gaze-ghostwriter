<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Support\ConversationPartnerCache;
use Illuminate\Support\Facades\Log;
use Naoray\GazeLaravel\Exceptions\GazeUnknownTokenException;
use Naoray\GazeLaravel\Queue\Contracts\NonRetryable;

final readonly class GhostwriterInboxRunResult
{
    public function __construct(
        public int $messagesImported,
        public int $draftsCreated,
    ) {}
}

final class GhostwriterInboxProcessor
{
    public function __construct(
        private ImapInboundMailSync $imapInboundMailSync,
        private DraftGeneratorService $draftGeneratorService,
    ) {}

    public function run(): GhostwriterInboxRunResult
    {
        if (! config('gaze-ghostwriter.enabled')) {
            return new GhostwriterInboxRunResult(0, 0);
        }

        if (! filled(config('gaze-ghostwriter.imap.host')) || ! filled(config('gaze-ghostwriter.imap.username'))) {
            return new GhostwriterInboxRunResult(0, 0);
        }

        $imported = $this->imapInboundMailSync->sync();

        $partner = ConversationPartnerCache::effective();

        $candidateQuery = SupportMailMessage::query()
            ->where('matches_support_address', true)
            ->whereDoesntHave('drafts')
            ->orderByDesc('received_at')
            ->limit(20);

        if ($partner !== null) {
            $candidateQuery->where(function ($q) use ($partner): void {
                $q->whereRaw('LOWER(from_email) = ?', [$partner])
                    ->orWhereJsonContains('to_emails', $partner)
                    ->orWhere(function ($q2) use ($partner): void {
                        $q2->whereNotNull('cc_emails')
                            ->whereJsonContains('cc_emails', $partner);
                    });
            });
        }

        $candidates = $candidateQuery->get();

        $draftsCreated = 0;
        foreach ($candidates as $message) {
            try {
                if ($this->draftGeneratorService->generateForMessage($message) !== null) {
                    $draftsCreated++;
                }
            } catch (GazeDisabledException) {
                $message->update(['processing_status' => 'gaze_disabled']);
                Log::warning('gaze-ghostwriter.gaze.disabled_defer', [
                    'message_id' => $message->id,
                ]);
            } catch (GazeUnknownTokenException $e) {
                $message->update(['processing_status' => 'gaze_restore_exhausted']);
                Log::error('gaze-ghostwriter.gaze.restore_exhausted', [
                    'message_id' => $message->id,
                    'exception' => $e->getMessage(),
                ]);
            } catch (NonRetryable $e) {
                $message->update(['processing_status' => 'gaze_nonretryable']);
                Log::error('gaze-ghostwriter.gaze.nonretryable', [
                    'message_id' => $message->id,
                    'exception' => $e::class,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return new GhostwriterInboxRunResult($imported, $draftsCreated);
    }
}
