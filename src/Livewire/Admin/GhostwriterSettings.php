<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Services\GhostwriterImapDiagnostics;
use Empire2\GazeGhostwriter\Services\GhostwriterSmtpDiagnostics;
use Empire2\GazeGhostwriter\Support\ConversationPartnerCache;
use Empire2\GazeGhostwriter\Support\DraftBodyNormalizer;
use Empire2\GazeGhostwriter\Support\GhostwriterSchedulerPause;
use Empire2\GazeGhostwriter\Support\HtmlSignatureSanitizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ghostwriter · Einstellungen')]
class GhostwriterSettings extends Component
{
    public string $conversationPartnerEmailInput = '';

    /** @var array<string, mixed>|null */
    public ?array $imapDiagnosticsResult = null;

    /** @var array<string, mixed>|null */
    public ?array $smtpDiagnosticsResult = null;

    public string $ghostwriterSigningNameInput = '';

    public string $ghostwriterReplySignatureInput = '';

    public string $ghostwriterReplySignatureHtmlInput = '';

    public bool $schedulerPaused = false;

    public function mount(): void
    {
        $this->syncConversationPartnerInputFromEffective();
        $this->syncGhostwriterReplyProfileFromUser();
        $this->schedulerPaused = GhostwriterSchedulerPause::isPaused();
    }

    public function toggleSchedulerPause(): void
    {
        $nowPaused = GhostwriterSchedulerPause::toggle();
        $this->schedulerPaused = $nowPaused;

        $this->toast('success',
            $nowPaused
                ? 'Scheduler pausiert — automatischer Postfach-Abruf gestoppt.'
                : 'Scheduler aktiv — automatischer Postfach-Abruf alle 15 Min.',
            'Ghostwriter'
        );
    }

    public function saveGhostwriterSigningProfile(): void
    {
        $this->validate([
            'ghostwriterSigningNameInput' => ['nullable', 'string', 'max:150'],
            'ghostwriterReplySignatureInput' => ['nullable', 'string', 'max:65000'],
            'ghostwriterReplySignatureHtmlInput' => ['nullable', 'string', 'max:200000'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $nameTrimmed = trim($this->ghostwriterSigningNameInput);
        $signatureTrimmed = trim($this->ghostwriterReplySignatureInput);
        $htmlTrimmed = HtmlSignatureSanitizer::sanitize($this->ghostwriterReplySignatureHtmlInput);

        if (! method_exists($user, 'ghostwriterUserData')) {
            $this->toast('danger', 'Host User model fehlt das HasGhostwriterUserData-Trait.');

            return;
        }

        $user->ghostwriterUserData()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'signing_name' => $nameTrimmed === '' ? null : $nameTrimmed,
                'reply_signature' => $signatureTrimmed === '' ? null : $signatureTrimmed,
                'reply_signature_html' => $htmlTrimmed === '' ? null : $htmlTrimmed,
            ]
        );

        $this->toast('success', 'Antwort-Profil für Ghostwriter gespeichert.', 'Ghostwriter');
    }

    public function clearHtmlSignature(): void
    {
        $this->ghostwriterReplySignatureHtmlInput = '';

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if (! method_exists($user, 'ghostwriterUserData')) {
            return;
        }

        $user->ghostwriterUserData()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            ['reply_signature_html' => null]
        );

        $this->toast('success', 'HTML-Signatur entfernt.', 'Ghostwriter');
    }

    public function normalizeDraftBodies(): void
    {
        $result = DraftBodyNormalizer::normalizeAll();

        if ($result['normalized'] === 0) {
            $this->toast('success', 'Alle Entwürfe waren bereits sauber — nichts geändert.', 'Ghostwriter');

            return;
        }

        $this->toast('success',
            "{$result['normalized']} Entwurf(e) normalisiert, {$result['skipped']} bereits OK.",
            'Ghostwriter'
        );
    }

    public function saveConversationPartnerFilter(): void
    {
        $this->validate([
            'conversationPartnerEmailInput' => ['nullable', 'email', 'max:255'],
        ]);

        $trimmed = trim($this->conversationPartnerEmailInput);
        if ($trimmed === '') {
            ConversationPartnerCache::forget();
            $this->syncConversationPartnerInputFromEffective();
            $this->toast('success', 'Admin-Filter entfernt. Es gilt ggf. weiterhin .env oder kein Filter.', 'Ghostwriter');

            return;
        }

        ConversationPartnerCache::put($trimmed);
        $this->syncConversationPartnerInputFromEffective();
        $this->toast('success', 'Konversations-Filter gespeichert. Gilt ab dem nächsten IMAP-Sync.', 'Ghostwriter');
    }

    public function clearAdminConversationPartnerFilter(): void
    {
        ConversationPartnerCache::forget();
        $this->syncConversationPartnerInputFromEffective();
        $this->toast('success', 'Admin-Filter entfernt.', 'Ghostwriter');
    }

    public function testMailConnections(
        GhostwriterImapDiagnostics $ghostwriterImapDiagnostics,
        GhostwriterSmtpDiagnostics $ghostwriterSmtpDiagnostics,
    ): void {
        $this->imapDiagnosticsResult = $ghostwriterImapDiagnostics->run();
        $this->smtpDiagnosticsResult = $ghostwriterSmtpDiagnostics->run();

        if ($this->imapDiagnosticsResult['ok'] && $this->smtpDiagnosticsResult['ok']) {
            $this->toast('success', 'IMAP- und SMTP-Test abgeschlossen.', 'Ghostwriter');
        } elseif (! $this->imapDiagnosticsResult['ok']) {
            $this->toast('danger', $this->imapDiagnosticsResult['headline'], 'Ghostwriter');
        } else {
            $this->toast('danger', $this->smtpDiagnosticsResult['headline'], 'Ghostwriter');
        }
    }

    public function render(): View
    {
        return view('gaze-ghostwriter::settings', [
            'conversationPartnerEffective' => ConversationPartnerCache::effective(),
            'conversationPartnerAdminOverride' => ConversationPartnerCache::hasAdminOverride(),
        ])->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }

    private function syncConversationPartnerInputFromEffective(): void
    {
        $this->conversationPartnerEmailInput = ConversationPartnerCache::effective() ?? '';
    }

    private function syncGhostwriterReplyProfileFromUser(): void
    {
        $user = Auth::user();
        if ($user === null || ! method_exists($user, 'loadMissing')) {
            $this->ghostwriterSigningNameInput = '';
            $this->ghostwriterReplySignatureInput = '';
            $this->ghostwriterReplySignatureHtmlInput = '';

            return;
        }

        $user->loadMissing('ghostwriterUserData');
        $row = $user->getAttribute('ghostwriterUserData');
        $this->ghostwriterSigningNameInput = $row !== null && is_string($row->signing_name)
            ? $row->signing_name
            : '';
        $this->ghostwriterReplySignatureInput = $row !== null && is_string($row->reply_signature)
            ? $row->reply_signature
            : '';
        $this->ghostwriterReplySignatureHtmlInput = $row !== null && is_string($row->reply_signature_html)
            ? $row->reply_signature_html
            : '';
    }

    private function toast(string $type, string $message, ?string $heading = null): void
    {
        $this->dispatch('toast', type: $type, message: $message, heading: $heading);
    }
}
