<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

use App\Enums\Roles;
use Domain\Account\Models\User;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftShow;
use Empire2\GazeGhostwriter\Livewire\Admin\DraftsIndex;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\GitHubIssueCreateException;
use Empire2\GazeGhostwriter\Services\GitHubIssueService;
use Empire2\GazeGhostwriter\Support\GithubIssueExportMarkers;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Naoray\GazeLaravel\EncryptedBlob;
use Naoray\GazeLaravel\Facades\Gaze;
use Naoray\GazeLaravel\GazeSession;
use Spatie\Permission\Models\Role;

function ghostwriterAdminForGithubIssue(): User
{
    Role::findOrCreate(Roles::ADMIN->value);

    $user = User::factory()->create();
    $user->assignRole(Roles::ADMIN);

    return $user;
}

function ghostwriterDraftForGithubIssue(SupportMailMessage $message): SupportDraft
{
    return SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'draft_body' => 'Antwort',
        'rationale' => [
            'thematische_begruendung' => 'a',
            'stilistische_begruendung' => 'b',
            'referenzierte_chunk_ids' => [],
            'retrieved_snippets' => [],
        ],
        'status' => DraftStatus::PENDING_REVIEW,
    ]);
}

beforeEach(function () {
    config([
        'ghostwriter.github.repo' => 'Artistfy/Dashboard',
        'ghostwriter.github.token' => 'ghp_test_token',
        'ghostwriter.github.labels' => [],
    ]);
});

test('github issue service creates issue via http', function () {
    Http::fake([
        'https://api.github.com/repos/Artistfy/Dashboard/issues' => Http::response([
            'number' => 99,
            'html_url' => 'https://github.com/Artistfy/Dashboard/issues/99',
        ], 201),
    ]);

    $service = app(GitHubIssueService::class);
    $result = $service->createIssue('Title', 'Body', []);

    expect($result['number'])->toBe(99)
        ->and($result['html_url'])->toBe('https://github.com/Artistfy/Dashboard/issues/99');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://api.github.com/repos/Artistfy/Dashboard/issues'
            && $data['title'] === 'Title'
            && $data['body'] === 'Body'
            && ! array_key_exists('labels', $data);
    });
});

test('github issue resolve labels keeps first label and optional selections only', function () {
    config(['ghostwriter.github.labels' => ['support', 'bug', 'enhancement']]);

    $service = app(GitHubIssueService::class);

    expect($service->resolveLabelsForIssuePayload([]))->toBe(['support'])
        ->and($service->resolveLabelsForIssuePayload(['bug']))->toBe(['support', 'bug'])
        ->and($service->resolveLabelsForIssuePayload(['not-in-config']))->toBe(['support'])
        ->and($service->resolveLabelsForIssuePayload(['enhancement', 'bug']))->toEqualCanonicalizing(['support', 'bug', 'enhancement']);
});

test('github issue create sends labels when resolved list non-empty', function () {
    config(['ghostwriter.github.labels' => ['support', 'triage']]);

    Http::fake([
        'https://api.github.com/repos/Artistfy/Dashboard/issues' => Http::response([
            'number' => 1,
            'html_url' => 'https://github.com/Artistfy/Dashboard/issues/1',
        ], 201),
    ]);

    $service = app(GitHubIssueService::class);
    $labels = $service->resolveLabelsForIssuePayload(['triage']);
    $service->createIssue('T', 'B', $labels);

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return ($data['labels'] ?? null) === ['support', 'triage'];
    });
});

test('github issue service throws when not configured', function () {
    config([
        'ghostwriter.github.repo' => '',
        'ghostwriter.github.token' => '',
    ]);

    $service = app(GitHubIssueService::class);

    expect(fn () => $service->createIssue('T', 'B'))
        ->toThrow(GitHubIssueCreateException::class, 'GitHub ist nicht konfiguriert');
});

test('draft show create github issue persists url', function () {
    Http::fake([
        'https://api.github.com/repos/Artistfy/Dashboard/issues' => Http::response([
            'number' => 5,
            'html_url' => 'https://github.com/Artistfy/Dashboard/issues/5',
        ], 201),
    ]);

    $admin = ghostwriterAdminForGithubIssue();
    $message = SupportMailMessage::factory()->create();
    $draft = ghostwriterDraftForGithubIssue($message);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('showGithubModal', true)
        ->set('githubIssueTitle', 'Bug from mail')
        ->set('githubIssueBody', 'Details here')
        ->call('createGithubIssue');

    $draft->refresh();

    expect($draft->github_issue_url)->toBe('https://github.com/Artistfy/Dashboard/issues/5');
});

test('draft show does not create second github issue when url exists', function () {
    Http::fake();

    $admin = ghostwriterAdminForGithubIssue();
    $message = SupportMailMessage::factory()->create();
    $draft = ghostwriterDraftForGithubIssue($message);
    $draft->update(['github_issue_url' => 'https://github.com/Artistfy/Dashboard/issues/1']);
    $draft->refresh();

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('showGithubModal', true)
        ->set('githubIssueTitle', 'Another')
        ->set('githubIssueBody', 'Body')
        ->call('createGithubIssue');

    Http::assertNothingSent();
    expect($draft->fresh()->github_issue_url)->toBe('https://github.com/Artistfy/Dashboard/issues/1');
});

test('draft show create github issue does nothing when github not configured', function () {
    Http::fake();
    config(['ghostwriter.github.token' => '']);

    $admin = ghostwriterAdminForGithubIssue();
    $message = SupportMailMessage::factory()->create();
    $draft = ghostwriterDraftForGithubIssue($message);

    Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->set('showGithubModal', true)
        ->set('githubIssueTitle', 'T')
        ->set('githubIssueBody', 'B')
        ->call('createGithubIssue');

    Http::assertNothingSent();
    expect($draft->fresh()->github_issue_url)->toBeNull();
});

test('drafts index modal create github issue persists url', function () {
    Http::fake([
        'https://api.github.com/repos/Artistfy/Dashboard/issues' => Http::response([
            'number' => 7,
            'html_url' => 'https://github.com/Artistfy/Dashboard/issues/7',
        ], 201),
    ]);

    $admin = ghostwriterAdminForGithubIssue();
    $message = SupportMailMessage::factory()->create();
    $draft = ghostwriterDraftForGithubIssue($message);

    Livewire::actingAs($admin)
        ->test(DraftsIndex::class)
        ->set('draftModalOpen', true)
        ->set('modalDraftId', $draft->id)
        ->set('showGithubModal', true)
        ->set('githubIssueTitle', 'From modal')
        ->set('githubIssueBody', 'Modal body')
        ->call('createGithubIssue');

    $draft->refresh();

    expect($draft->github_issue_url)->toBe('https://github.com/Artistfy/Dashboard/issues/7');
});

test('github issue prefill footer uses entwurf id without hash to avoid github issue links', function () {
    $message = SupportMailMessage::factory()->create(['body_text' => 'Body']);
    $draft = ghostwriterDraftForGithubIssue($message);
    $id = $draft->id;

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->toContain('Entwurf-ID '.$id)
        ->and($prefill['body'])->not->toContain('Entwurf #'.$id);
});

test('github issue prefill strips mail signature after standard delimiter', function () {
    config(['gaze_boundary.enabled' => false]);

    $message = SupportMailMessage::factory()->create([
        'body_text' => "Problem beschrieben mit genug Text davor.\n\n-- \nDr. Evil\nGeheime Adresse 1\n",
    ]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->toContain('Problem beschrieben')
        ->and($prefill['body'])->not->toContain('Geheime Adresse')
        ->and($prefill['body'])->toContain(GithubIssueExportMarkers::PII_REMOVED);
});

test('github issue prefill heuristic adds thread omitted marker when quoted history is split off', function () {
    config(['gaze_boundary.enabled' => false]);

    $history = str_repeat('Old quoted mail line content. ', 5);
    $body = "Current part with enough characters in the latest block.\n\n-----Original Message-----\n".$history;
    $message = SupportMailMessage::factory()->create(['body_text' => $body]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->toContain('Current part with enough')
        ->and($prefill['body'])->toContain(GithubIssueExportMarkers::THREAD_HISTORY_OMITTED)
        ->and($prefill['body'])->not->toContain('Old quoted mail');
});

test('github issue prefill masks sender email and omits plain address', function () {
    $message = SupportMailMessage::factory()->create([
        'from_email' => 'secret-customer@example.test',
        'body_text' => 'Question from customer',
    ]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->not->toContain('secret-customer@example.test')
        ->and($prefill['body'])->toContain('se****@example.**')
        ->and($prefill['body'])->toContain('Question from customer');
});

test('github issue prefill appends ghostwriter reply when include reply is true', function () {
    $message = SupportMailMessage::factory()->create(['body_text' => 'Customer asks']);
    $draft = ghostwriterDraftForGithubIssue($message);
    $draft->update(['draft_body' => 'Suggested answer']);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft, true, 'Suggested answer');

    expect($prefill['body'])->toContain('**Antwort (Ghostwriter):**')
        ->and($prefill['body'])->toContain('Suggested answer')
        ->and($prefill['body'])->toContain('Customer asks');
});

test('github issue prefill omits reply section when include reply is true but text empty', function () {
    $message = SupportMailMessage::factory()->create(['body_text' => 'Only mail']);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft, true, '   ');

    expect($prefill['body'])->not->toContain('**Antwort (Ghostwriter):**');
});

test('github issue prefill masks dotted local part like melanie dot gottschau at web dot de', function () {
    $message = SupportMailMessage::factory()->create([
        'from_email' => 'melanie.gottschau@web.de',
        'body_text' => 'Hi',
    ]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->toContain('me****.g***@web.**')
        ->and($prefill['body'])->not->toContain('melanie.gottschau@web.de');
});

test('github issue prefill uses Gaze boundary sanitize on full mail body when gate is active', function () {
    Gaze::fake(
        cleanHandler: fn (string $text): GazeSession => new GazeSession(
            cleanText: 'NUR_KERN_FRAGEN'."\n\n".GithubIssueExportMarkers::PII_REMOVED,
            ciphertext: EncryptedBlob::wrap('test-blob'),
            detections: 0,
        ),
    );

    $message = SupportMailMessage::factory()->create([
        'body_text' => "Aktuelle Frage zum Produkt\n\n--\nSignatur Privat 012345\n\nAm Montag schrieb Support:\nAlte Mail mit Details",
    ]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->toContain('NUR_KERN_FRAGEN')
        ->and($prefill['body'])->toContain(GithubIssueExportMarkers::PII_REMOVED)
        ->and($prefill['body'])->not->toContain('Signatur Privat');

    Gaze::assertCleaned();
});

test('github issue prefill falls back to heuristic when Gaze gate is off', function () {
    config(['gaze_boundary.enabled' => false]);

    $message = SupportMailMessage::factory()->create([
        'body_text' => "Problem beschrieben mit genug Text.\n\n-- \nSignatur Privat\n",
    ]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $prefill = app(GitHubIssueService::class)->prefillFromDraft($draft);

    expect($prefill['body'])->toContain('Problem beschrieben')
        ->and($prefill['body'])->not->toContain('Signatur Privat')
        ->and($prefill['body'])->toContain(GithubIssueExportMarkers::PII_REMOVED);
});

test('open github issue modal prefills from draft', function () {
    $admin = ghostwriterAdminForGithubIssue();
    $message = SupportMailMessage::factory()->create([
        'subject' => 'My subject line',
        'body_text' => 'Customer question text',
    ]);
    $draft = ghostwriterDraftForGithubIssue($message);

    $component = Livewire::actingAs($admin)
        ->test(DraftShow::class, ['draft' => $draft])
        ->call('openGithubIssueModal');

    expect($component->get('showGithubModal'))->toBeTrue()
        ->and($component->get('githubIssueTitle'))->toBe('My subject line')
        ->and($component->get('githubIssueBody'))->toContain('Customer question text');
});
