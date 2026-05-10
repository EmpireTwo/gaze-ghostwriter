<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use App\Enums\Roles;
use Domain\Account\Models\User;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function ghostwriterAdminUserForDraftsPagination(): User
{
    Role::findOrCreate(Roles::ADMIN->value);

    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    return $user;
}

test('drafts index resets stale page when url page is beyond last page', function () {
    config(['ghostwriter.enabled' => true]);

    $message = SupportMailMessage::factory()->create([
        'subject' => 'PaginationUniqueSubjectXyz',
    ]);

    SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'body',
        'rationale' => [
            'thematische_begruendung' => '',
            'stilistische_begruendung' => '',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::PENDING_REVIEW,
    ]);

    Livewire::actingAs(ghostwriterAdminUserForDraftsPagination())
        ->withQueryParams(['page' => 9])
        ->test(DraftsIndex::class)
        ->assertSee('PaginationUniqueSubjectXyz')
        ->assertSee('Entwürfe (aktueller Filter):');
});
