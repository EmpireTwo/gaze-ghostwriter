<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterTranslationService;
use Empire2\GazeGhostwriter\Services\GitHubIssueCreateException;
use Empire2\GazeGhostwriter\Services\GitHubIssueService;
use Empire2\GazeGhostwriter\Services\SupportDraftReplySender;
use Empire2\GazeGhostwriter\Services\SupportDraftReplySendException;
use Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ghostwriter · Entwurf')]
class DraftShow extends Component
{
    #[Locked]
    public SupportDraft $draft;

    public string $ratingComment = '';

    public string $editableBody = '';

    public string $editableDraftTranslation = '';

    public bool $showGithubModal = false;

    public string $githubIssueTitle = '';

    public string $githubIssueBody = '';

    public bool $includeReplyInGithubIssue = false;

    /** @var list<string> */
    public array $githubIssueExtraLabels = [];

    public function mount(SupportDraft $draft): void
    {
        if ($draft->status === DraftStatus::SUPERSEDED) {
            $latest = SupportDraft::query()
                ->where('support_mail_message_id', $draft->support_mail_message_id)
                ->whereNot('status', DraftStatus::SUPERSEDED)
                ->latest('id')
                ->first();

            if ($latest !== null) {
                $this->redirect(route('gaze-ghostwriter.drafts.show', $latest), navigate: true);

                return;
            }
        }

        $this->draft = $draft->load(['message', 'sentByUser']);
        $this->ratingComment = (string) ($draft->rating_comment ?? '');
        $this->syncEditableBodyFromDraft();
        $this->syncEditableDraftTranslationFromDraft();
    }

    public function saveEditedBody(): void
    {
        if ($this->draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $this->validate([
            'editableBody' => ['string', 'max:65000'],
        ]);

        $trimmed = trim($this->editableBody);
        if ($trimmed === '') {
            $this->draft->update(['edited_body' => null]);
            $this->draft->refresh();
            $this->syncEditableBodyFromDraft();
            $this->dispatch('draft-body-reset');
            $this->toast('success', 'Bearbeitung geleert — es gilt wieder der KI-Vorschlag.', 'Ghostwriter');

            return;
        }

        $aiResolvedTrimmed = $this->aiDraftBodyResolvedForCurrentUser();
        $this->draft->update([
            'edited_body' => $trimmed === $aiResolvedTrimmed ? null : $this->editableBody,
        ]);

        $this->draft->refresh();
        $this->syncEditableBodyFromDraft();
        $this->dispatch('draft-body-synced');

        $this->toast('success', 'Antwort gespeichert.', 'Ghostwriter');
    }

    public function resetEditedBodyToAi(): void
    {
        if ($this->draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $this->draft->update(['edited_body' => null]);
        $this->draft->refresh();
        $this->syncEditableBodyFromDraft();
        $this->dispatch('draft-body-reset');

        $this->toast('success', 'Text auf KI-Vorschlag zurückgesetzt.', 'Ghostwriter');
    }

    private function syncEditableBodyFromDraft(): void
    {
        $raw = $this->draft->hasEditedBody()
            ? (string) $this->draft->edited_body
            : (string) $this->draft->draft_body;

        $user = Auth::user();
        $this->editableBody = $user !== null
            ? GhostwriterPlaceholderReplacer::apply($raw, $user)
            : $raw;
    }

    private function flushEditableBodyToDraftForSend(): void
    {
        if ($this->draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $this->validate([
            'editableBody' => ['string', 'max:65000'],
        ]);

        $trimmed = trim($this->editableBody);
        if ($trimmed === '') {
            $this->draft->update(['edited_body' => null]);
        } else {
            $aiResolvedTrimmed = $this->aiDraftBodyResolvedForCurrentUser();
            $this->draft->update([
                'edited_body' => $trimmed === $aiResolvedTrimmed ? null : $this->editableBody,
            ]);
        }

        $this->draft->refresh();
        $this->syncEditableBodyFromDraft();
    }

    public function saveEditedDraftTranslation(): void
    {
        if ($this->draft->status !== DraftStatus::PENDING_REVIEW || ! $this->draft->needsTranslation()) {
            return;
        }

        $this->validate([
            'editableDraftTranslation' => ['string', 'max:65000'],
        ]);

        $trimmed = trim($this->editableDraftTranslation);
        $this->draft->update([
            'edited_draft_translation' => $trimmed === '' || $trimmed === trim((string) $this->draft->draft_translation)
                ? null
                : $this->editableDraftTranslation,
        ]);

        $this->draft->refresh();
        $this->syncEditableDraftTranslationFromDraft();
        $this->dispatch('draft-translation-synced');

        $this->toast('success', 'Übersetzung gespeichert.', 'Ghostwriter');
    }

    public function translateAndApply(GhostwriterTranslationService $translationService): void
    {
        if ($this->draft->status !== DraftStatus::PENDING_REVIEW || ! $this->draft->needsTranslation()) {
            return;
        }

        $germanText = trim($this->editableDraftTranslation);
        if ($germanText === '') {
            $this->toast('warning', 'Kein Text zum Übersetzen vorhanden.', 'Ghostwriter');

            return;
        }

        $translated = $translationService->translateFromGerman($germanText, (string) $this->draft->detected_language);

        if ($translated === null) {
            $this->toast('danger', 'Übersetzung fehlgeschlagen. Bitte erneut versuchen.', 'Ghostwriter');

            return;
        }

        $this->draft->update([
            'edited_body' => $translated,
            'edited_draft_translation' => $germanText === trim((string) $this->draft->draft_translation)
                ? null
                : $germanText,
        ]);

        $this->draft->refresh();
        $this->syncEditableBodyFromDraft();
        $this->syncEditableDraftTranslationFromDraft();
        $this->dispatch('draft-body-synced');
        $this->dispatch('draft-translation-synced');

        $this->toast('success', 'Antwort wurde übersetzt und übernommen.', 'Ghostwriter');
    }

    private function syncEditableDraftTranslationFromDraft(): void
    {
        $this->editableDraftTranslation = (string) ($this->draft->resolvedDraftTranslation() ?? '');
    }

    private function aiDraftBodyResolvedForCurrentUser(): string
    {
        $user = Auth::user();
        if ($user === null) {
            return trim((string) $this->draft->draft_body);
        }

        return trim(GhostwriterPlaceholderReplacer::apply((string) $this->draft->draft_body, $user));
    }

    public function dismiss(): void
    {
        $this->draft->update(['status' => DraftStatus::DISMISSED]);
        $this->redirect(route('gaze-ghostwriter.drafts.index'), navigate: true);
    }

    public function accept(): void
    {
        $this->draft->update(['status' => DraftStatus::ACCEPTED]);
        $this->redirect(route('gaze-ghostwriter.drafts.index'), navigate: true);
    }

    public function reopen(): void
    {
        if ($this->draft->status !== DraftStatus::DISMISSED) {
            return;
        }

        $this->draft->update(['status' => DraftStatus::PENDING_REVIEW]);
        $this->draft->refresh();
        $this->syncEditableBodyFromDraft();

        $this->toast('success', 'Entwurf wieder geöffnet.', 'Ghostwriter');
    }

    public function rate(int $stars): void
    {
        if ($stars < 1 || $stars > 5) {
            return;
        }

        $this->validate([
            'ratingComment' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->draft->update([
            'user_rating' => $stars,
            'rating_comment' => trim($this->ratingComment) !== '' ? trim($this->ratingComment) : null,
            'rated_at' => now(),
            'rated_by_user_id' => Auth::id(),
        ]);

        $this->draft->refresh();

        $this->toast('success', 'Bewertung gespeichert.', 'Ghostwriter');
    }

    public function regenerate(DraftGeneratorService $draftGeneratorService): void
    {
        if ($this->draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $result = $draftGeneratorService->regenerateFromDraft($this->draft);

        if ($result === null) {
            $this->toast('danger', 'Neuer Entwurf konnte nicht erzeugt werden. Bitte Logs prüfen.', 'Ghostwriter');

            return;
        }

        $this->draft->refresh();
        $this->ratingComment = (string) ($this->draft->rating_comment ?? '');
        $this->syncEditableBodyFromDraft();
        $this->syncEditableDraftTranslationFromDraft();

        $this->toast('success', 'Entwurf wurde neu generiert.', 'Ghostwriter');
    }

    public function sendReply(SupportDraftReplySender $supportDraftReplySender): void
    {
        if (! in_array($this->draft->status, [DraftStatus::PENDING_REVIEW, DraftStatus::ACCEPTED], true)) {
            return;
        }

        if ($this->draft->sent_at !== null) {
            $this->toast('warning', 'Bereits gesendet.', 'Ghostwriter');

            return;
        }

        if (! $supportDraftReplySender->isConfigured()) {
            $this->toast('danger', 'SMTP nicht konfiguriert. Siehe .env: GHOSTWRITER_SMTP_HOST und GHOSTWRITER_REPLY_FROM_ADDRESS.', 'Ghostwriter');

            return;
        }

        $this->flushEditableBodyToDraftForSend();
        $this->draft->refresh();

        try {
            $supportDraftReplySender->send($this->draft, (int) Auth::id());
        } catch (SupportDraftReplySendException $e) {
            $this->toast('danger', $e->getMessage(), 'Ghostwriter');

            return;
        }

        $this->draft->refresh();
        $this->draft->load('sentByUser');
        $this->syncEditableBodyFromDraft();

        $this->toast('success', 'Antwort wurde gesendet.', 'Ghostwriter');
    }

    public function updatedIncludeReplyInGithubIssue(): void
    {
        if (! $this->showGithubModal) {
            return;
        }

        $this->refreshGithubIssuePrefill(app(GitHubIssueService::class));
    }

    public function openGithubIssueModal(GitHubIssueService $githubIssueService): void
    {
        if (filled($this->draft->github_issue_url)) {
            return;
        }

        $this->includeReplyInGithubIssue = false;
        $this->githubIssueExtraLabels = [];
        $this->refreshGithubIssuePrefill($githubIssueService);
        $this->showGithubModal = true;
    }

    public function closeGithubIssueModal(): void
    {
        $this->showGithubModal = false;
        $this->includeReplyInGithubIssue = false;
        $this->githubIssueExtraLabels = [];
    }

    public function createGithubIssue(GitHubIssueService $githubIssueService): void
    {
        if (filled($this->draft->github_issue_url)) {
            $this->toast('warning', 'Für diesen Entwurf existiert bereits ein GitHub-Issue.', 'Ghostwriter');
            $this->showGithubModal = false;

            return;
        }

        if (! $githubIssueService->isConfigured()) {
            $this->toast('danger', 'GitHub nicht konfiguriert. Siehe .env: GITHUB_REPO und GITHUB_TOKEN.', 'Ghostwriter');

            return;
        }

        $this->validate([
            'githubIssueTitle' => ['required', 'string', 'max:255'],
            'githubIssueBody' => ['required', 'string', 'max:65535'],
        ]);

        try {
            $labels = $githubIssueService->resolveLabelsForIssuePayload($this->githubIssueExtraLabels);
            $result = $githubIssueService->createIssue(
                trim($this->githubIssueTitle),
                $this->githubIssueBody,
                $labels
            );
        } catch (GitHubIssueCreateException $e) {
            $this->toast('danger', $e->getMessage(), 'Ghostwriter');

            return;
        }

        $this->draft->update(['github_issue_url' => $result['html_url']]);
        $this->draft->refresh();
        $this->showGithubModal = false;
        $this->includeReplyInGithubIssue = false;
        $this->githubIssueExtraLabels = [];
        $this->githubIssueTitle = '';
        $this->githubIssueBody = '';

        $this->toast('success', 'GitHub-Issue #'.$result['number'].' erstellt.', 'Ghostwriter');
    }

    private function refreshGithubIssuePrefill(GitHubIssueService $githubIssueService): void
    {
        $prefill = $githubIssueService->prefillFromDraft(
            $this->draft,
            $this->includeReplyInGithubIssue,
            $this->resolvedReplyTextForGithubIssue()
        );
        $this->githubIssueTitle = $prefill['title'];
        $this->githubIssueBody = $prefill['body'];
    }

    private function resolvedReplyTextForGithubIssue(): string
    {
        if ($this->draft->status === DraftStatus::PENDING_REVIEW) {
            return trim($this->editableBody);
        }

        $user = Auth::user();
        $raw = $this->draft->resolvedReplyBody();

        return $user !== null
            ? GhostwriterPlaceholderReplacer::apply($raw, $user)
            : $raw;
    }

    public function render(): View
    {
        return view('gaze-ghostwriter::draft-show')
            ->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }

    private function toast(string $type, string $message, ?string $heading = null): void
    {
        $this->dispatch('toast', type: $type, message: $message, heading: $heading);
    }
}
