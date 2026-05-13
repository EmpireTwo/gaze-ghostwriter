<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Feature;

use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Support\Facades\Schema;

it('adds web-feedback columns with correct defaults', function (): void {
    expect(Schema::hasColumns('ghostwriter_support_mail_messages', [
        'channel', 'client_user_id', 'client_context', 'source_url', 'topic',
    ]))->toBeTrue();

    $msg = SupportMailMessage::factory()->create();

    expect($msg->channel)->toBe('smtp')
        ->and($msg->client_user_id)->toBeNull()
        ->and($msg->client_context)->toBeNull()
        ->and($msg->source_url)->toBeNull()
        ->and($msg->topic)->toBeNull();
});

it('persists web channel + client context as expected', function (): void {
    $msg = SupportMailMessage::factory()->web()->create([
        'client_user_id' => 42,
        'client_context' => ['id' => 42, 'email' => 'a@b.test', 'name' => 'Alice'],
        'source_url' => 'https://example.test/feedback',
        'topic' => 'bug',
    ]);

    $msg->refresh();

    expect($msg->channel)->toBe('web')
        ->and($msg->client_user_id)->toBe(42)
        ->and($msg->client_context)->toBe(['id' => 42, 'email' => 'a@b.test', 'name' => 'Alice'])
        ->and($msg->source_url)->toBe('https://example.test/feedback')
        ->and($msg->topic)->toBe('bug');
});
