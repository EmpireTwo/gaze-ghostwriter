<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Jobs\ProcessGhostwriterInboxJob;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Empire2\GazeGhostwriter\Services\DraftGeneratorService;
use Empire2\GazeGhostwriter\Services\GhostwriterTranslationService;
use Empire2\GazeGhostwriter\Services\GitHubIssueCreateException;
use Empire2\GazeGhostwriter\Services\GitHubIssueService;
use Empire2\GazeGhostwriter\Services\SupportDraftReplySender;
use Empire2\GazeGhostwriter\Services\SupportDraftReplySendException;
use Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer;
use Empire2\GazeGhostwriter\Support\GhostwriterSchedulerPause;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ghostwriter · Entwürfe')]
class DraftsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    public bool $includeSuperseded = false;

    public bool $draftModalOpen = false;

    public ?int $modalDraftId = null;

    public bool $showTicketPanel = false;

    public ?int $ticketPanelDraftId = null;

    public ?string $lastCreatedTicketNumber = null;

    public ?int $lastCreatedTicketId = null;

    public string $editableBody = '';

    public string $editableDraftTranslation = '';

    public string $ratingComment = '';

    public bool $showGithubModal = false;

    public string $githubIssueTitle = '';

    public string $githubIssueBody = '';

    public bool $includeReplyInGithubIssue = false;

    /** @var list<string> */
    public array $githubIssueExtraLabels = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingIncludeSuperseded(): void
    {
        $this->resetPage();
    }

    public function updatedDraftModalOpen(bool $value): void
    {
        if (! $value) {
            $this->modalDraftId = null;
            $this->editableBody = '';
            $this->editableDraftTranslation = '';
            $this->ratingComment = '';
            $this->showGithubModal = false;
            $this->includeReplyInGithubIssue = false;
            $this->githubIssueExtraLabels = [];
            $this->githubIssueTitle = '';
            $this->githubIssueBody = '';
        }
    }

    public function openDraftModal(int $id): void
    {
        $draft = SupportDraft::query()->with('message')->findOrFail($id);
        $this->modalDraftId = $draft->id;
        $this->ratingComment = (string) ($draft->rating_comment ?? '');
        $this->syncEditableBodyFromDraftModel($draft);
        $this->syncEditableDraftTranslationFromDraftModel($draft);
        $this->draftModalOpen = true;
        $this->showTicketPanel = false;
        $this->ticketPanelDraftId = null;
        $this->lastCreatedTicketId = null;
        $this->lastCreatedTicketNumber = null;
    }

    public function closeDraftModal(): void
    {
        $this->draftModalOpen = false;
        $this->modalDraftId = null;
        $this->editableBody = '';
        $this->editableDraftTranslation = '';
        $this->ratingComment = '';
        $this->showGithubModal = false;
        $this->includeReplyInGithubIssue = false;
        $this->githubIssueExtraLabels = [];
        $this->githubIssueTitle = '';
        $this->githubIssueBody = '';
    }

    public function saveEditedBody(): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || $draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $this->validate([
            'editableBody' => ['string', 'max:65000'],
        ]);

        $trimmed = trim($this->editableBody);
        if ($trimmed === '') {
            $draft->update(['edited_body' => null]);
            $draft->refresh();
            $this->syncEditableBodyFromDraftModel($draft);
            $this->dispatch('draft-body-reset');
            $this->toast('success', 'Bearbeitung geleert — es gilt wieder der KI-Vorschlag.', 'Ghostwriter');

            return;
        }

        $aiResolvedTrimmed = $this->aiDraftBodyResolvedForCurrentUser($draft);
        $draft->update([
            'edited_body' => $trimmed === $aiResolvedTrimmed ? null : $this->editableBody,
        ]);

        $draft->refresh();
        $this->syncEditableBodyFromDraftModel($draft);
        $this->dispatch('draft-body-synced');

        $this->toast('success', 'Antwort gespeichert.', 'Ghostwriter');
    }

    public function resetEditedBodyToAi(): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || $draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $draft->update(['edited_body' => null]);
        $draft->refresh();
        $this->syncEditableBodyFromDraftModel($draft);
        $this->dispatch('draft-body-reset');

        $this->toast('success', 'Text auf KI-Vorschlag zurückgesetzt.', 'Ghostwriter');
    }

    public function dismiss(): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null) {
            return;
        }

        $draft->update(['status' => DraftStatus::DISMISSED]);
        $this->draftModalOpen = false;
        $this->modalDraftId = null;
        $this->editableBody = '';
        $this->ratingComment = '';
        $this->resetPage();
    }

    public function accept(): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null) {
            return;
        }

        $draft->update(['status' => DraftStatus::ACCEPTED]);
        $this->draftModalOpen = false;
        $this->modalDraftId = null;
        $this->editableBody = '';
        $this->ratingComment = '';
        $this->resetPage();
    }

    public function reopen(): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || $draft->status !== DraftStatus::DISMISSED) {
            return;
        }

        $draft->update(['status' => DraftStatus::PENDING_REVIEW]);

        $this->toast('success', 'Entwurf wieder geöffnet.', 'Ghostwriter');
    }

    public function rate(int $stars): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null) {
            return;
        }

        if ($stars < 1 || $stars > 5) {
            return;
        }

        $this->validate([
            'ratingComment' => ['nullable', 'string', 'max:2000'],
        ]);

        $draft->update([
            'user_rating' => $stars,
            'rating_comment' => trim($this->ratingComment) !== '' ? trim($this->ratingComment) : null,
            'rated_at' => now(),
            'rated_by_user_id' => Auth::id(),
        ]);

        $draft->refresh();

        $this->toast('success', 'Bewertung gespeichert.', 'Ghostwriter');
    }

    public function regenerate(DraftGeneratorService $draftGeneratorService): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || $draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $result = $draftGeneratorService->regenerateFromDraft($draft);

        if ($result === null) {
            $this->toast('danger', 'Neuer Entwurf konnte nicht erzeugt werden. Bitte Logs prüfen.', 'Ghostwriter');

            return;
        }

        $draft->refresh();
        $draft->load('message');
        $this->ratingComment = (string) ($draft->rating_comment ?? '');
        $this->syncEditableBodyFromDraftModel($draft);
        $this->syncEditableDraftTranslationFromDraftModel($draft);

        $this->toast('success', 'Entwurf wurde neu generiert.', 'Ghostwriter');
    }

    public function sendReply(SupportDraftReplySender $supportDraftReplySender): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null) {
            return;
        }

        if (! in_array($draft->status, [DraftStatus::PENDING_REVIEW, DraftStatus::ACCEPTED], true)) {
            return;
        }

        if ($draft->sent_at !== null) {
            $this->toast('warning', 'Bereits gesendet.', 'Ghostwriter');

            return;
        }

        if (! $supportDraftReplySender->isConfigured()) {
            $this->toast('danger', 'SMTP nicht konfiguriert. Siehe .env: GHOSTWRITER_SMTP_HOST und GHOSTWRITER_REPLY_FROM_ADDRESS.', 'Ghostwriter');

            return;
        }

        $this->flushEditableBodyToDraftForSend($draft);
        $draft->refresh();

        try {
            $supportDraftReplySender->send($draft, (int) Auth::id());
        } catch (SupportDraftReplySendException $e) {
            $this->toast('danger', $e->getMessage(), 'Ghostwriter');

            return;
        }

        $this->toast('success', 'Antwort wurde gesendet.', 'Ghostwriter');
        $this->draftModalOpen = false;
        $this->modalDraftId = null;
        $this->editableBody = '';
        $this->ratingComment = '';
        $this->resetPage();
    }

    public function updatedIncludeReplyInGithubIssue(): void
    {
        if (! $this->showGithubModal) {
            return;
        }

        $draft = $this->modalDraftRecord();
        if ($draft === null) {
            return;
        }

        $this->refreshGithubIssuePrefill($draft, app(GitHubIssueService::class));
    }

    public function openGithubIssueModal(GitHubIssueService $githubIssueService): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || filled($draft->github_issue_url)) {
            return;
        }

        $this->includeReplyInGithubIssue = false;
        $this->githubIssueExtraLabels = [];
        $this->refreshGithubIssuePrefill($draft, $githubIssueService);
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
        $draft = $this->modalDraftRecord();
        if ($draft === null) {
            return;
        }

        if (filled($draft->github_issue_url)) {
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

        $draft->update(['github_issue_url' => $result['html_url']]);
        $this->showGithubModal = false;
        $this->includeReplyInGithubIssue = false;
        $this->githubIssueExtraLabels = [];
        $this->githubIssueTitle = '';
        $this->githubIssueBody = '';

        $this->toast('success', 'GitHub-Issue #'.$result['number'].' erstellt.', 'Ghostwriter');
    }

    private function refreshGithubIssuePrefill(SupportDraft $draft, GitHubIssueService $githubIssueService): void
    {
        $prefill = $githubIssueService->prefillFromDraft(
            $draft,
            $this->includeReplyInGithubIssue,
            $this->resolvedReplyTextForGithubIssue($draft)
        );
        $this->githubIssueTitle = $prefill['title'];
        $this->githubIssueBody = $prefill['body'];
    }

    private function resolvedReplyTextForGithubIssue(SupportDraft $draft): string
    {
        if ($draft->status === DraftStatus::PENDING_REVIEW) {
            return trim($this->editableBody);
        }

        $user = Auth::user();
        $raw = $draft->resolvedReplyBody();

        return $user !== null
            ? GhostwriterPlaceholderReplacer::apply($raw, $user)
            : $raw;
    }

    public function runInboxSync(): void
    {
        if (! (bool) config('gaze-ghostwriter.enabled')) {
            $this->toast('warning', 'Ghostwriter ist deaktiviert — setze GHOSTWRITER_ENABLED=true in .env (danach ggf. php artisan config:clear).', 'Ghostwriter');

            return;
        }

        if (! filled(config('gaze-ghostwriter.imap.host')) || ! filled(config('gaze-ghostwriter.imap.username'))) {
            $this->toast('danger', 'IMAP-Host oder Benutzername fehlt in der Konfiguration.', 'Ghostwriter');

            return;
        }

        $isSync = config('queue.default') === 'sync';

        if ($isSync) {
            set_time_limit(300);
        }

        ProcessGhostwriterInboxJob::dispatch();

        $toastMessage = $isSync
            ? 'Postfach-Sync läuft synchron — Seite lädt nach Abschluss neu.'
            : 'Postfach-Sync gestartet — neue Mails und Entwürfe erscheinen in Kürze.';

        $this->toast('success', $toastMessage, 'Ghostwriter');
    }

    public function openTicketPanel(int $draftId): void
    {
        $this->ticketPanelDraftId = $draftId;
        $this->showTicketPanel = true;
    }

    public function closeTicketPanel(): void
    {
        $this->showTicketPanel = false;
        $this->ticketPanelDraftId = null;
    }

    #[On('ticket-created')]
    public function onTicketCreated(int $ticketId, string $ticketNumber): void
    {
        $this->closeTicketPanel();
        $this->lastCreatedTicketId = $ticketId;
        $this->lastCreatedTicketNumber = $ticketNumber;
    }

    public function render(): View
    {
        $query = SupportDraft::query()
            ->with('message')
            ->select('ghostwriter_support_drafts.*')
            ->join('ghostwriter_support_mail_messages', 'ghostwriter_support_mail_messages.id', '=', 'ghostwriter_support_drafts.support_mail_message_id')
            ->orderByDesc('ghostwriter_support_mail_messages.received_at');

        if (! $this->includeSuperseded) {
            $query->where('status', '!=', DraftStatus::SUPERSEDED);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('ghostwriter_support_mail_messages.from_email', 'like', $term)
                    ->orWhere('ghostwriter_support_mail_messages.from_name', 'like', $term)
                    ->orWhere('ghostwriter_support_mail_messages.subject', 'like', $term)
                    ->orWhere('ghostwriter_support_mail_messages.client_context', 'like', $term);
            });
        }

        $drafts = (clone $query)->paginate(15);

        if ($drafts->total() > 0 && $drafts->isEmpty()) {
            $this->resetPage();
            $drafts = (clone $query)->paginate(15);
        }

        $importedTotal = SupportMailMessage::query()->count();
        $importedInboundSupport = SupportMailMessage::query()->where('matches_support_address', true)->count();

        $modalDraft = null;
        if ($this->draftModalOpen && $this->modalDraftId !== null) {
            $modalDraft = SupportDraft::query()->with(['message', 'sentByUser'])->find($this->modalDraftId);
        }

        return view('gaze-ghostwriter::drafts-index', [
            'drafts' => $drafts,
            'modalDraft' => $modalDraft,
            'statuses' => DraftStatus::cases(),
            'ghostwriterEnabled' => (bool) config('gaze-ghostwriter.enabled'),
            'schedulerPaused' => GhostwriterSchedulerPause::isPaused(),
            'importedMailTotal' => $importedTotal,
            'importedMailInboundSupport' => $importedInboundSupport,
        ])->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }

    private function modalDraftRecord(): ?SupportDraft
    {
        if ($this->modalDraftId === null) {
            return null;
        }

        return SupportDraft::query()->with('message')->find($this->modalDraftId);
    }

    private function syncEditableBodyFromDraftModel(SupportDraft $draft): void
    {
        $raw = $draft->hasEditedBody()
            ? (string) $draft->edited_body
            : (string) $draft->draft_body;

        $user = Auth::user();
        $this->editableBody = $user !== null
            ? GhostwriterPlaceholderReplacer::apply($raw, $user)
            : $raw;
    }

    private function flushEditableBodyToDraftForSend(SupportDraft $draft): void
    {
        if ($draft->status !== DraftStatus::PENDING_REVIEW) {
            return;
        }

        $this->validate([
            'editableBody' => ['string', 'max:65000'],
        ]);

        $trimmed = trim($this->editableBody);
        if ($trimmed === '') {
            $draft->update(['edited_body' => null]);
        } else {
            $aiResolvedTrimmed = $this->aiDraftBodyResolvedForCurrentUser($draft);
            $draft->update([
                'edited_body' => $trimmed === $aiResolvedTrimmed ? null : $this->editableBody,
            ]);
        }

        $draft->refresh();
        $this->syncEditableBodyFromDraftModel($draft);
    }

    public function saveEditedDraftTranslation(): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || $draft->status !== DraftStatus::PENDING_REVIEW || ! $draft->needsTranslation()) {
            return;
        }

        $this->validate([
            'editableDraftTranslation' => ['string', 'max:65000'],
        ]);

        $trimmed = trim($this->editableDraftTranslation);
        $draft->update([
            'edited_draft_translation' => $trimmed === '' || $trimmed === trim((string) $draft->draft_translation)
                ? null
                : $this->editableDraftTranslation,
        ]);

        $draft->refresh();
        $this->syncEditableDraftTranslationFromDraftModel($draft);
        $this->dispatch('draft-translation-synced');

        $this->toast('success', 'Übersetzung gespeichert.', 'Ghostwriter');
    }

    public function translateAndApply(GhostwriterTranslationService $translationService): void
    {
        $draft = $this->modalDraftRecord();
        if ($draft === null || $draft->status !== DraftStatus::PENDING_REVIEW || ! $draft->needsTranslation()) {
            return;
        }

        $germanText = trim($this->editableDraftTranslation);
        if ($germanText === '') {
            $this->toast('warning', 'Kein Text zum Übersetzen vorhanden.', 'Ghostwriter');

            return;
        }

        $translated = $translationService->translateFromGerman($germanText, (string) $draft->detected_language);

        if ($translated === null) {
            $this->toast('danger', 'Übersetzung fehlgeschlagen. Bitte erneut versuchen.', 'Ghostwriter');

            return;
        }

        $draft->update([
            'edited_body' => $translated,
            'edited_draft_translation' => $germanText === trim((string) $draft->draft_translation)
                ? null
                : $germanText,
        ]);

        $draft->refresh();
        $this->syncEditableBodyFromDraftModel($draft);
        $this->syncEditableDraftTranslationFromDraftModel($draft);
        $this->dispatch('draft-body-synced');
        $this->dispatch('draft-translation-synced');

        $this->toast('success', 'Antwort wurde übersetzt und übernommen.', 'Ghostwriter');
    }

    private function syncEditableDraftTranslationFromDraftModel(SupportDraft $draft): void
    {
        $this->editableDraftTranslation = (string) ($draft->resolvedDraftTranslation() ?? '');
    }

    private function aiDraftBodyResolvedForCurrentUser(SupportDraft $draft): string
    {
        $user = Auth::user();
        if ($user === null) {
            return trim((string) $draft->draft_body);
        }

        return trim(GhostwriterPlaceholderReplacer::apply((string) $draft->draft_body, $user));
    }

    private function toast(string $type, string $message, ?string $heading = null): void
    {
        $this->dispatch('toast', type: $type, message: $message, heading: $heading);
    }
}
