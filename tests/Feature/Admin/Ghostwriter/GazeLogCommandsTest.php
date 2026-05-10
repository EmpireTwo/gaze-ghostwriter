<?php

// GHOSTWRITER-TODO (host-fixture coupling): This test still references
// host-specific factories / models (User, Customer, Artist, Release, Ticket,
// or App\Features\GhostwriterGaze). It will not run unmodified inside the
// package test suite. To enable: provide local stand-ins (e.g. an Eloquent
// `User` model + factory under `tests/Fixtures`) and replace references below.

declare(strict_types=1);

// Replaced host App\Enums\Roles with literal "admin" string
use Empire2\GazeGhostwriter\Features\GhostwriterGaze;
use Empire2\GazeGhostwriter\Tests\Fixtures\User;
use Empire2\GazeGhostwriter\Agents\GhostwriterDraftAgent;
use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Livewire\Admin\GazeLog;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Laravel\Ai\Ai;
use Laravel\Ai\Embeddings;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Naoray\GazeLaravel\EncryptedBlob;
use Naoray\GazeLaravel\Facades\Gaze;
use Naoray\GazeLaravel\GazeSession;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    config(['gaze-ghostwriter.gaze_enabled' => true]);
    Feature::define(GhostwriterGaze::class, fn () => true);
});

function gazeCommandsAdmin(): User
{
    Role::findOrCreate("admin");
    $user = User::factory()->create();
    $user->assignRole("admin");

    return $user;
}

test('gaze_invocations column populates after a draft generation', function () {
    config(['gaze-ghostwriter.openai.chat_model' => 'gpt-4o-mini']);

    Gaze::fake(
        cleanHandler: fn (string $text): GazeSession => new GazeSession(
            cleanText: str_replace('Alice', 'Name_1', $text),
            ciphertext: EncryptedBlob::wrap('scripted-blob'),
            detections: 1,
        ),
        restoreHandler: fn (GazeSession $session, string $text): string => str_replace('Name_1', 'Alice', $text),
    );

    Embeddings::fake([[[0.1, 0.2, 0.3]]]);

    Ai::fakeAgent(GhostwriterDraftAgent::class, [
        [
            'draft_body' => 'Hallo Name_1, danke für deine Nachricht.',
            'thematische_begruendung' => 'Antwort auf Kundenfrage.',
            'stilistische_begruendung' => 'Freundlich.',
            'referenzierte_chunk_ids' => [],
            'smart_action_tags' => [],
            'mentioned_entities' => [],
        ],
    ]);

    $message = SupportMailMessage::factory()->create([
        'matches_support_address' => true,
        'subject' => 'Frage von Alice',
        'body_text' => 'Hallo Team, eine Frage von Alice.',
    ]);

    $draft = app(DraftGeneratorService::class)->generateForMessage($message);

    expect($draft)->toBeInstanceOf(SupportDraft::class);

    $invocations = $draft->gaze_invocations;

    expect($invocations)->toBeArray();
    expect(count($invocations ?? []))->toBeGreaterThan(0);

    $stages = array_column($invocations, 'stage');
    expect($stages)->toContain('clean');
    expect($stages)->toContain('restore');

    $clean = collect($invocations)->firstWhere('stage', 'clean');
    expect($clean)->toHaveKeys(['stage', 'argv', 'stdin_preview', 'stdin_bytes', 'duration_ms']);
    expect($clean['argv'][0] ?? null)->toBe((string) config('gaze.binary'));
    expect($clean['argv'])->toContain('clean');
    expect($clean['argv'])->toContain('--format=json');
    expect($clean['stdin_bytes'])->toBeGreaterThan(0);

    $restore = collect($invocations)->firstWhere('stage', 'restore');
    expect($restore['argv'])->toContain('restore');
    expect($restore['stdin_preview'])->toContain('<redacted,');
    expect($restore['stdin_preview'])->not->toContain('scripted-blob');
});

test('row expansion renders the Gaze commands pane with argv + stdin_preview + metrics', function () {
    $admin = gazeCommandsAdmin();

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Commands pane subject',
        'body_text' => 'Body for commands pane test.',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'status' => DraftStatus::PENDING_REVIEW,
        'draft_body' => 'Draft body.',
        'rationale' => [],
        'clean_prompt' => 'Clean prompt.',
        'llm_raw_response' => ['text' => 'raw', 'structured' => null],
        'gaze_detections' => 1,
        'gaze_duration_ms' => 42,
        'gaze_ran_at' => now(),
        'gaze_warnings' => [],
        'gaze_invocations' => [
            [
                'stage' => 'clean',
                'argv' => ['gaze', 'clean', '--policy=/tmp/policy.toml', '--format=json'],
                'stdin_preview' => 'Hallo Team, eine Frage.',
                'stdin_bytes' => 2048,
                'duration_ms' => 17,
            ],
            [
                'stage' => 'restore',
                'argv' => ['gaze', 'restore', '--format=json'],
                'stdin_preview' => '{"session_blob":"<redacted, 128-char ciphertext>","text":"Hallo Name_1"}',
                'stdin_bytes' => 256,
                'duration_ms' => 9,
            ],
        ],
    ]);

    actingAs($admin);

    Livewire::test(GazeLog::class)
        ->call('toggleExpand', $draft->id)
        ->assertSee('Gaze commands')
        ->assertSee("'gaze' 'clean'")
        ->assertSee("'--policy=/tmp/policy.toml'")
        ->assertSee("'gaze' 'restore' '--format=json'")
        ->assertSee('Hallo Team, eine Frage.')
        ->assertSee('2048 bytes · 17 ms')
        ->assertSee('256 bytes · 9 ms')
        ->assertSee('&lt;redacted, 128-char ciphertext&gt;', escape: false);
});

test('expand query param auto-opens the matching draft row', function () {
    $admin = gazeCommandsAdmin();

    $message = SupportMailMessage::factory()->create([
        'subject' => 'Auto-expand subject',
        'body_text' => 'Body for auto-expand test.',
    ]);

    $draft = SupportDraft::query()->create([
        'support_mail_message_id' => $message->id,
        'status' => DraftStatus::PENDING_REVIEW,
        'draft_body' => 'Auto-expanded draft body marker.',
        'rationale' => [],
        'clean_prompt' => 'Clean prompt marker.',
        'llm_raw_response' => ['text' => 'raw', 'structured' => null],
        'gaze_detections' => 0,
        'gaze_duration_ms' => 5,
        'gaze_ran_at' => now(),
        'gaze_warnings' => [],
        'gaze_invocations' => [
            [
                'stage' => 'clean',
                'argv' => ['gaze', 'clean', '--format=json'],
                'stdin_preview' => 'preview-marker',
                'stdin_bytes' => 100,
                'duration_ms' => 3,
            ],
        ],
    ]);

    actingAs($admin);

    Livewire::withQueryParams(['expand' => $draft->id])
        ->test(GazeLog::class)
        ->assertSet('expandedDraftId', $draft->id)
        ->assertSee('Auto-expanded draft body marker.')
        ->assertSee('Clean prompt marker.')
        ->assertSee('preview-marker');
});
