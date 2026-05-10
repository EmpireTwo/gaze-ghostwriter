<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

declare(strict_types=1);

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\GazeLog;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

function gazeLogAdmin(): User
{
    Role::findOrCreate("admin");
    $user = User::factory()->create();
    $user->assignRole("admin");

    return $user;
}

test('admin can see the gaze log page with a captured draft', function () {
    $admin = gazeLogAdmin();
    $message = SupportMailMessage::factory()->create([
        'subject' => 'Kunde fragt nach Release-Datum',
        'body_text' => 'Hallo Team, wann kommt mein Album raus? – Alice',
    ]);
    SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'status' => DraftStatus::PENDING_REVIEW,
        'draft_body' => 'Hallo Alice, am Freitag geht es live.',
        'rationale' => [],
        'clean_prompt' => 'Hallo Team, … – Name_1',
        'llm_raw_response' => ['text' => 'Hallo Name_1, am Freitag geht es live.', 'structured' => null],
        'gaze_detections' => 2,
        'gaze_duration_ms' => 145,
        'gaze_ran_at' => now(),
        'gaze_warnings' => [],
    ]);

    actingAs($admin);

    Livewire::test(GazeLog::class)
        ->assertSee('Kunde fragt nach Release-Datum')
        ->assertSee('145 ms')
        ->assertSee('2');
});

test('non-admin users receive 403', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('gaze-ghostwriter.gaze-log'))
        ->assertForbidden();
});

test('gate-off state shows the disabled callout instead of the table', function () {
    config(['gaze-ghostwriter.gaze_enabled' => false]);
    $admin = gazeLogAdmin();

    actingAs($admin);

    Livewire::test(GazeLog::class)
        ->assertSee('Gaze boundary disabled');
});

test('expanding a row reveals all four pipeline stages', function () {
    $admin = gazeLogAdmin();
    $message = SupportMailMessage::factory()->create([
        'body_text' => 'Original mail body with Alice in it.',
    ]);
    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'status' => DraftStatus::PENDING_REVIEW,
        'draft_body' => 'Restored body with Alice.',
        'rationale' => [],
        'clean_prompt' => 'Clean prompt with Name_1.',
        'llm_raw_response' => ['text' => 'LLM response with Name_1.', 'structured' => ['k' => 'v']],
        'gaze_detections' => 1,
        'gaze_duration_ms' => 99,
        'gaze_ran_at' => now(),
        'gaze_warnings' => [],
    ]);

    actingAs($admin);

    Livewire::test(GazeLog::class)
        ->call('toggleExpand', $draft->id)
        ->assertSee('Original mail body with Alice in it.')
        ->assertSee('Clean prompt with Name_1.')
        ->assertSee('LLM response with Name_1.')
        ->assertSee('Restored body with Alice.')
        ->assertSee('99 ms');
});

test('status filter narrows results', function () {
    $admin = gazeLogAdmin();
    $pending = SupportMailMessage::factory()->create(['subject' => 'pending-subject']);
    SupportDraft::query()->create([
        'support_mail_message_id' => $pending->id,
        'status' => DraftStatus::PENDING_REVIEW,
        'draft_body' => 'Pending draft',
        'rationale' => [],
        'clean_prompt' => '',
        'llm_raw_response' => null,
        'gaze_detections' => 0,
        'gaze_duration_ms' => 10,
        'gaze_ran_at' => now(),
        'gaze_warnings' => [],
    ]);
    $sent = SupportMailMessage::factory()->create(['subject' => 'sent-subject']);
    SupportDraft::query()->create([
        'support_mail_message_id' => $sent->id,
        'status' => DraftStatus::SENT,
        'draft_body' => 'Sent draft',
        'rationale' => [],
        'clean_prompt' => '',
        'llm_raw_response' => null,
        'gaze_detections' => 0,
        'gaze_duration_ms' => 10,
        'gaze_ran_at' => now(),
        'gaze_warnings' => [],
    ]);

    actingAs($admin);

    Livewire::test(GazeLog::class)
        ->set('statusFilter', 'sent')
        ->assertSee('sent-subject')
        ->assertDontSee('pending-subject');
});
