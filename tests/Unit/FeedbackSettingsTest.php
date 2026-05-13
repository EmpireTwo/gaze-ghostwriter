<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Unit;

use Empire2\GazeGhostwriter\Models\GhostwriterSetting;
use Empire2\GazeGhostwriter\Support\FeedbackSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns defaults when no settings present', function (): void {
    $s = FeedbackSettings::all();
    expect($s->enabled)->toBeFalse()
        ->and($s->requireSubject)->toBeFalse()
        ->and($s->requireEmailForGuests)->toBeTrue()
        ->and($s->topics)->toBe([])
        ->and($s->rateLimitPerMinute)->toBe(5);
});

it('reads stored bool/int/json values', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');
    GhostwriterSetting::setValue('feedback_require_subject', 'true');
    GhostwriterSetting::setValue('feedback_require_email_for_guests', 'false');
    GhostwriterSetting::setValue('feedback_topics', json_encode(['Bug', 'Feature']));
    GhostwriterSetting::setValue('feedback_rate_limit_per_minute', '10');

    $s = FeedbackSettings::all();
    expect($s->enabled)->toBeTrue()
        ->and($s->requireSubject)->toBeTrue()
        ->and($s->requireEmailForGuests)->toBeFalse()
        ->and($s->topics)->toBe(['Bug', 'Feature'])
        ->and($s->rateLimitPerMinute)->toBe(10);
});

it('ignores malformed topics json', function (): void {
    GhostwriterSetting::setValue('feedback_topics', 'not-json');
    expect(FeedbackSettings::all()->topics)->toBe([]);
});
