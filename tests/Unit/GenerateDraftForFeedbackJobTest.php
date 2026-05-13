<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Unit;

use Empire2\GazeGhostwriter\Ai\Exceptions\GazeDisabledException;
use Empire2\GazeGhostwriter\Jobs\GenerateDraftForFeedbackJob;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Naoray\GazeLaravel\Exceptions\GazeUnknownTokenException;
use Naoray\GazeLaravel\Queue\Contracts\NonRetryable;

uses(RefreshDatabase::class);

it('calls DraftGeneratorService::generateForMessage', function (): void {
    $msg = SupportMailMessage::factory()->web()->create();

    $svc = Mockery::mock(DraftGeneratorService::class);
    $svc->shouldReceive('generateForMessage')->once()->with(Mockery::on(fn ($m) => $m->id === $msg->id))->andReturn(null);
    app()->instance(DraftGeneratorService::class, $svc);

    (new GenerateDraftForFeedbackJob($msg->id))->handle($svc);

    expect($msg->fresh()->processing_status)->toBeNull();
});

it('sets gaze_disabled status when GazeDisabledException thrown', function (): void {
    $msg = SupportMailMessage::factory()->web()->create();

    $svc = Mockery::mock(DraftGeneratorService::class);
    $svc->shouldReceive('generateForMessage')->andThrow(new GazeDisabledException('off'));

    (new GenerateDraftForFeedbackJob($msg->id))->handle($svc);

    expect($msg->fresh()->processing_status)->toBe('gaze_disabled');
});

it('sets gaze_restore_exhausted on GazeUnknownTokenException', function (): void {
    $msg = SupportMailMessage::factory()->web()->create();

    $svc = Mockery::mock(DraftGeneratorService::class);
    $svc->shouldReceive('generateForMessage')->andThrow(new GazeUnknownTokenException('unknown token', 3, hash('sha256', '')));

    (new GenerateDraftForFeedbackJob($msg->id))->handle($svc);

    expect($msg->fresh()->processing_status)->toBe('gaze_restore_exhausted');
});

it('sets gaze_nonretryable for NonRetryable', function (): void {
    $msg = SupportMailMessage::factory()->web()->create();

    $nonRetryable = new class('halt') extends \RuntimeException implements NonRetryable {};

    $svc = Mockery::mock(DraftGeneratorService::class);
    $svc->shouldReceive('generateForMessage')->andThrow($nonRetryable);

    (new GenerateDraftForFeedbackJob($msg->id))->handle($svc);

    expect($msg->fresh()->processing_status)->toBe('gaze_nonretryable');
});

it('no-ops when message id missing', function (): void {
    $svc = Mockery::mock(DraftGeneratorService::class);
    $svc->shouldNotReceive('generateForMessage');

    (new GenerateDraftForFeedbackJob(999999))->handle($svc);
});
