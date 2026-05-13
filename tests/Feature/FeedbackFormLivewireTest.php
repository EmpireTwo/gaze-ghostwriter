<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Feature;

use Empire2\GazeGhostwriter\Jobs\GenerateDraftForFeedbackJob;
use Empire2\GazeGhostwriter\Livewire\FeedbackForm;
use Empire2\GazeGhostwriter\Models\GhostwriterSetting;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gaze-ghostwriter.support_addresses', ['support@example.test']);
    config()->set('app.url', 'https://app.example.test');
    Bus::fake();
});

it('aborts when feedback is disabled', function (): void {
    Livewire::test(FeedbackForm::class)->assertStatus(404);
});

it('renders the form when enabled', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');

    Livewire::test(FeedbackForm::class)
        ->assertSet('submitted', false)
        ->assertSee('feedback');
});

it('requires message and creates a row on submit', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');

    Livewire::test(FeedbackForm::class)
        ->set('message', '')
        ->set('guestEmail', 'g@example.test')
        ->call('submit')
        ->assertHasErrors(['message' => 'required']);

    expect(SupportMailMessage::count())->toBe(0);

    Livewire::test(FeedbackForm::class)
        ->set('message', 'Real message body')
        ->set('guestEmail', 'g@example.test')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(SupportMailMessage::count())->toBe(1);
    Bus::assertDispatched(GenerateDraftForFeedbackJob::class);
});

it('requires guest email when setting is on', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');
    GhostwriterSetting::setValue('feedback_require_email_for_guests', 'true');

    Livewire::test(FeedbackForm::class)
        ->set('message', 'Hello')
        ->set('guestEmail', '')
        ->call('submit')
        ->assertHasErrors(['guestEmail' => 'required']);

    expect(SupportMailMessage::count())->toBe(0);
});

it('allows guest with no email when setting is off', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');
    GhostwriterSetting::setValue('feedback_require_email_for_guests', 'false');

    Livewire::test(FeedbackForm::class)
        ->set('message', 'Hello')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(SupportMailMessage::first()->from_email)->toBe('anonymous@web.local');
});

it('requires subject when setting is on', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');
    GhostwriterSetting::setValue('feedback_require_subject', 'true');

    Livewire::test(FeedbackForm::class)
        ->set('message', 'Hello')
        ->set('guestEmail', 'g@example.test')
        ->call('submit')
        ->assertHasErrors(['subject' => 'required']);
});

it('rejects unknown topic when topics are configured', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');
    GhostwriterSetting::setValue('feedback_topics', json_encode(['Bug', 'Feature']));

    Livewire::test(FeedbackForm::class)
        ->set('message', 'Hello')
        ->set('guestEmail', 'g@example.test')
        ->set('topic', 'NotInList')
        ->call('submit')
        ->assertHasErrors(['topic']);
});

it('silently succeeds on honeypot trip and creates nothing', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');

    Livewire::test(FeedbackForm::class)
        ->set('message', 'spam content')
        ->set('guestEmail', 'g@example.test')
        ->set('hp', 'bot-filled')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(SupportMailMessage::count())->toBe(0);
    Bus::assertNotDispatched(GenerateDraftForFeedbackJob::class);
});

it('persists referer captured at mount as source_url on submit', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');

    Livewire::withHeaders(['referer' => 'https://app.example.test/checkout'])
        ->test(FeedbackForm::class)
        ->assertSet('sourceUrl', 'https://app.example.test/checkout')
        ->set('message', 'Page broke on checkout')
        ->set('guestEmail', 'g@example.test')
        ->call('submit')
        ->assertSet('submitted', true);

    expect(SupportMailMessage::first()->source_url)->toBe('https://app.example.test/checkout');
});

it('rate-limits after configured per-minute count', function (): void {
    GhostwriterSetting::setValue('feedback_enabled', 'true');
    GhostwriterSetting::setValue('feedback_rate_limit_per_minute', '2');

    for ($i = 0; $i < 2; $i++) {
        Livewire::test(FeedbackForm::class)
            ->set('message', 'Hello '.$i)
            ->set('guestEmail', 'g@example.test')
            ->call('submit')
            ->assertSet('submitted', true);
    }

    Livewire::test(FeedbackForm::class)
        ->set('message', 'Third')
        ->set('guestEmail', 'g@example.test')
        ->call('submit')
        ->assertSet('submitted', false);

    expect(SupportMailMessage::count())->toBe(2);
});
