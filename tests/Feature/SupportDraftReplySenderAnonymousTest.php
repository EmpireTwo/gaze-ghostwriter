<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Feature;

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\FeedbackIntakeService;
use Empire2\GazeGhostwriter\Services\SupportDraftReplySender;
use Empire2\GazeGhostwriter\Services\SupportDraftReplySendException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuses to send a reply to the anonymous sentinel', function (): void {
    config()->set('gaze-ghostwriter.smtp.host', '127.0.0.1');
    config()->set('gaze-ghostwriter.reply.from_address', 'support@example.test');

    $msg = SupportMailMessage::factory()->web()->create([
        'from_email' => FeedbackIntakeService::ANONYMOUS_SENDER_SENTINEL,
    ]);
    $draft = SupportDraft::factory()->create([
        'support_mail_message_id' => $msg->id,
        'status' => DraftStatus::PENDING_REVIEW,
        'draft_body' => 'Hi anon',
    ]);

    $sender = app(SupportDraftReplySender::class);

    expect(fn () => $sender->send($draft, sentByUserId: 1))
        ->toThrow(SupportDraftReplySendException::class);
});
