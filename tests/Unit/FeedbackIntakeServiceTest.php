<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Unit;

use Empire2\GazeGhostwriter\DTO\FeedbackIntakeDto;
use Empire2\GazeGhostwriter\Jobs\GenerateDraftForFeedbackJob;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\FeedbackIntakeService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gaze-ghostwriter.support_addresses', ['support@example.test']);
    config()->set('app.url', 'https://app.example.test');
    Bus::fake();
});

it('creates web row for guest with email', function (): void {
    $svc = app(FeedbackIntakeService::class);
    $dto = new FeedbackIntakeDto(
        message: 'Hello, my order is broken.',
        subject: '',
        guestEmail: 'guest@example.test',
        guestName: 'Guest McName',
        topic: null,
    );

    $msg = $svc->intake($dto, user: null, sourceUrl: 'https://app.example.test/checkout');

    expect($msg)->toBeInstanceOf(SupportMailMessage::class)
        ->and($msg->channel)->toBe('web')
        ->and($msg->from_email)->toBe('guest@example.test')
        ->and($msg->from_name)->toBe('Guest McName')
        ->and($msg->to_emails)->toBe(['support@example.test'])
        ->and($msg->matches_support_address)->toBeTrue()
        ->and($msg->client_user_id)->toBeNull()
        ->and($msg->client_context)->toBeNull()
        ->and($msg->source_url)->toBe('https://app.example.test/checkout')
        ->and($msg->subject)->toBe('Web feedback from Guest McName')
        ->and($msg->body_text)->toContain('[Web feedback]')
        ->and($msg->body_text)->toContain('Hello, my order is broken.')
        ->and($msg->rfc_message_id)->toStartWith('web-')
        ->and($msg->rfc_message_id)->toEndWith('@app.example.test');

    Bus::assertDispatched(GenerateDraftForFeedbackJob::class, function (GenerateDraftForFeedbackJob $job) use ($msg): bool {
        return $job->messageId === $msg->id;
    });
});

it('captures auth user as client context', function (): void {
    $svc = app(FeedbackIntakeService::class);
    $user = new class implements Authenticatable
    {
        public string $email = 'alice@example.test';

        public string $name = 'Alice';

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 99;
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($v): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };

    $msg = $svc->intake(
        new FeedbackIntakeDto(message: 'Hi', subject: 'Question', guestEmail: '', guestName: '', topic: 'bug'),
        user: $user,
        sourceUrl: null,
    );

    expect($msg->from_email)->toBe('alice@example.test')
        ->and($msg->from_name)->toBe('Alice')
        ->and($msg->client_user_id)->toBe(99)
        ->and($msg->client_context)->toBe(['id' => 99, 'email' => 'alice@example.test', 'name' => 'Alice'])
        ->and($msg->subject)->toBe('Question')
        ->and($msg->topic)->toBe('bug');
});

it('uses anonymous sentinel when guest provides no email', function (): void {
    $svc = app(FeedbackIntakeService::class);
    $msg = $svc->intake(
        new FeedbackIntakeDto(message: 'Anon msg', subject: '', guestEmail: '', guestName: '', topic: null),
        user: null,
        sourceUrl: null,
    );

    expect($msg->from_email)->toBe(FeedbackIntakeService::ANONYMOUS_SENDER_SENTINEL)
        ->and($msg->from_name)->toBeNull()
        ->and($msg->subject)->toBe('Web feedback');
});
