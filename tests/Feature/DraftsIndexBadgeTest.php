<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Feature;

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders a WWW pill for web rows and MAIL pill for smtp rows', function (): void {
    config(['gaze-ghostwriter.enabled' => true]);

    $smtp = SupportMailMessage::factory()->create();
    $web = SupportMailMessage::factory()->web()->create([
        'client_context' => ['email' => 'alice@example.test', 'id' => 1, 'name' => 'Alice'],
    ]);

    SupportDraft::factory()->create([
        'support_mail_message_id' => $smtp->id,
        'status' => DraftStatus::PENDING_REVIEW,
    ]);
    SupportDraft::factory()->create([
        'support_mail_message_id' => $web->id,
        'status' => DraftStatus::PENDING_REVIEW,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(DraftsIndex::class)
        ->assertSeeHtml('>WWW<')
        ->assertSeeHtml('>MAIL<');
});

it('search matches client email for web rows', function (): void {
    config(['gaze-ghostwriter.enabled' => true]);

    $web = SupportMailMessage::factory()->web()->create([
        'client_context' => ['email' => 'findme@example.test', 'id' => 1, 'name' => 'F'],
        'from_email' => 'noisesender@example.test',
        'from_name' => 'Decoy Sender',
        'subject' => 'unrelated subject',
    ]);
    SupportDraft::factory()->create([
        'support_mail_message_id' => $web->id,
        'status' => DraftStatus::PENDING_REVIEW,
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test(DraftsIndex::class)
        ->set('search', 'findme@example.test')
        ->assertSee('unrelated subject');
});
