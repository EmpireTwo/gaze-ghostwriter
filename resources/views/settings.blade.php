<div class="p-8 max-w-3xl">
    <div class="mb-6">
        <a href="{{ route('gaze-ghostwriter.drafts.index') }}" wire:navigate class="font-poppins text-xs-plus text-art-violet-deep hover:underline">← Zurück zu Entwürfen</a>
    </div>

    <div class="flex items-start gap-5 mb-8">
        <img
            src="{{ asset('images/ghostwriter/mascot.png') }}"
            width="220"
            height="220"
            alt="Ghostwriter 3000 Maskottchen: freundlicher Geist am Laptop"
            class="w-40 h-auto shrink-0 select-none"
            loading="lazy"
            decoding="async"
        />
        <div>
            <h1 class="font-poppins text-2xl font-light text-art-black mb-1">Einstellungen</h1>
            <p class="font-poppins text-sm text-art-text-light">
                IMAP-Konversation filtern sowie <strong class="font-medium text-art-black">IMAP- und SMTP-Verbindung</strong> testen (Einstellungen aus <code class="text-2xs bg-art-page px-1 rounded">.env</code>). Die Entwurfsliste: <a href="{{ route('gaze-ghostwriter.drafts.index') }}" wire:navigate class="text-art-violet-deep hover:underline">Entwürfe</a>.
            </p>
        </div>
    </div>

    <section class="border rounded-lg p-5 mb-6 {{ $schedulerPaused ? 'border-amber-300 bg-amber-50/60' : 'border-green-200 bg-green-50/60' }}">
        <div class="flex items-center justify-between gap-4 mb-1">
            <h2 class="font-poppins text-sm font-semibold text-art-black">Automatischer Postfach-Abruf</h2>
            <flux:switch
                :checked="! $schedulerPaused"
                wire:click="toggleSchedulerPause"
                label="{{ $schedulerPaused ? 'Pausiert' : 'Aktiv' }}"
            />
        </div>
        <p class="font-poppins text-xs-plus text-art-text-muted">
            @if ($schedulerPaused)
                Scheduler ist <strong class="text-amber-800">pausiert</strong> — kein automatischer Abruf. Manueller Sync über „Postfach abrufen" funktioniert weiterhin.
            @else
                Scheduler ist <strong class="text-green-800">aktiv</strong> — Postfach wird alle 15 Minuten automatisch abgerufen.
            @endif
        </p>
    </section>

    <section class="border border-art-border rounded-lg p-5 bg-white mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-1">Absendername &amp; Signatur in Antworten</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-4">
            <strong class="font-medium text-art-black">Name</strong> gilt für die Platzhalter <code class="text-2xs bg-art-page px-1 rounded">[Dein Name]</code> und <code class="text-2xs bg-art-page px-1 rounded">[Dein Vorname]</code> in KI-Entwürfen (Vorschau in Entwurf &amp; Versand).
            Leer lassen = Vorname aus dem verknüpften Kundenprofil bzw. Account.
            <strong class="font-medium text-art-black">Signatur</strong> (optional) wird beim E-Mail-Versand mit zwei Zeilenumbrüchen unter den Antworttext gesetzt und in der Vorschau „Antwort (Stand)“ mit angezeigt.
        </p>
        <div class="space-y-4 max-w-xl">
            <flux:input
                wire:model="ghostwriterSigningNameInput"
                type="text"
                label="Name in der Mail (z. B. Markus von Artistfy)"
                placeholder="Leer = automatischer Vorname"
            />
            <flux:textarea
                wire:model="ghostwriterReplySignatureInput"
                label="Signatur (optional, Plain-Text)"
                rows="5"
                placeholder="z. B. Kontaktzeilen — leer lassen, wenn keine Signatur gewünscht ist"
                class="font-mono text-sm"
            />
            <flux:button type="button" variant="primary" wire:click="saveGhostwriterSigningProfile">Speichern</flux:button>
        </div>
        @if (auth()->user())
            <p class="font-poppins text-2xs text-art-text-muted mt-3">
                Aktuell für Platzhalter <code class="bg-art-page px-1 rounded">[Dein Name]</code>:
                <strong class="text-art-black">{{ \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::signingName(auth()->user()) }}</strong>
                · <code class="bg-art-page px-1 rounded">[Dein Vorname]</code>:
                <strong class="text-art-black">{{ \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::firstNameOnly(auth()->user()) }}</strong>
            </p>
        @endif
        <div class="mt-4 pt-4 border-t border-art-border">
            <flux:button
                type="button"
                variant="outline"
                wire:click="normalizeDraftBodies"
                wire:loading.attr="disabled"
                wire:target="normalizeDraftBodies"
            >
                <span wire:loading.remove wire:target="normalizeDraftBodies">Entwürfe normalisieren</span>
                <span wire:loading wire:target="normalizeDraftBodies">Normalisiere …</span>
            </flux:button>
            <span class="font-poppins text-2xs text-art-text-muted ms-2">Bereinigt gespeicherte Entwurfstexte (z. B. escaped Zeilenumbrüche) — ohne KI-Neuberechnung.</span>
        </div>
    </section>

    <section class="border border-art-border rounded-lg p-5 bg-white mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-1">HTML-Signatur (Rich-Text, Bilder, Links)</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-4">
            Kopiere deine Signatur aus <strong class="font-medium text-art-black">Gmail, Outlook oder Thunderbird</strong> (markieren → Cmd+C) und füge sie unten ein (Cmd+V).
            Bilder und Links werden automatisch übernommen. Wird beim Versand als HTML-Teil der E-Mail angehängt — die Plain-Text-Signatur oben bleibt als Fallback erhalten.
        </p>
        <div
            x-data="{
                showSource: false,
                handlePaste(event) {
                    const html = event.clipboardData?.getData('text/html') || '';
                    if (html) {
                        event.preventDefault();
                        $wire.set('ghostwriterReplySignatureHtmlInput', html);
                    }
                }
            }"
            class="space-y-4 max-w-2xl"
        >
            @if ($ghostwriterReplySignatureHtmlInput !== '')
                <div class="space-y-2">
                    <p class="font-poppins text-xs-plus font-medium text-art-black">Vorschau</p>
                    <div class="border border-art-border rounded-lg p-4 bg-white overflow-x-auto [&_img]:inline [&_img]:align-middle">
                        {!! $ghostwriterReplySignatureHtmlInput !!}
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="button" variant="ghost" size="sm" x-on:click="showSource = !showSource">
                            <span x-text="showSource ? 'HTML-Quelltext ausblenden' : 'HTML-Quelltext anzeigen'"></span>
                        </flux:button>
                        <flux:button type="button" variant="ghost" size="sm" x-on:click="$store.confirm.open('HTML-Signatur wirklich entfernen?', () => $wire.clearHtmlSignature())">
                            Entfernen
                        </flux:button>
                    </div>
                    <div x-show="showSource" x-cloak x-transition.opacity.duration.200ms>
                        <flux:textarea
                            wire:model="ghostwriterReplySignatureHtmlInput"
                            label="HTML-Quelltext"
                            rows="8"
                            class="font-mono text-xs"
                        />
                    </div>
                </div>
            @else
                <div
                    x-on:paste="handlePaste($event)"
                    contenteditable="true"
                    class="min-h-[120px] border-2 border-dashed border-art-border rounded-lg p-4 text-sm text-art-text-muted focus:border-art-violet-deep focus:outline-none cursor-text transition-colors"
                >
                    <p class="pointer-events-none select-none">Signatur hier einfügen (Cmd+V / Ctrl+V) …</p>
                </div>
                <p class="font-poppins text-2xs text-art-text-muted">
                    Alternativ kannst du den HTML-Quelltext direkt eingeben:
                </p>
                <div>
                    <flux:textarea
                        wire:model="ghostwriterReplySignatureHtmlInput"
                        label="HTML-Quelltext (optional)"
                        rows="5"
                        placeholder="<table>…</table>"
                        class="font-mono text-xs"
                    />
                </div>
            @endif
            <flux:button type="button" variant="primary" wire:click="saveGhostwriterSigningProfile">Alles speichern</flux:button>
        </div>
    </section>

    <section class="border border-art-border rounded-lg p-5 bg-white mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-1">Konversation mit einer Adresse</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-4">
            Es werden nur Mails importiert, an denen diese Adresse beteiligt ist (Absender oder Empfänger). Für <strong>eigene Antworten</strong> zusätzlich in <code class="text-2xs bg-art-page px-1 rounded">.env</code>
            <code class="text-2xs bg-art-page px-1 rounded">GHOSTWRITER_IMAP_EXTRA_FOLDERS</code> den Ordner „Gesendet“ (Provider-Syntax) eintragen.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 sm:items-end max-w-xl">
            <div class="flex-1 w-full">
                <flux:input
                    wire:model="conversationPartnerEmailInput"
                    type="email"
                    label="Nur Mails mit Beteiligung von (E-Mail)"
                    placeholder="kunde@beispiel.de"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:button type="button" variant="primary" wire:click="saveConversationPartnerFilter">Speichern</flux:button>
                @if ($conversationPartnerAdminOverride)
                    <flux:button type="button" variant="ghost" wire:click="clearAdminConversationPartnerFilter">Admin-Filter löschen</flux:button>
                @endif
            </div>
        </div>
        <p class="font-poppins text-2xs text-art-text-muted mt-3">
            Aktuell für Sync:
            @if ($conversationPartnerEffective)
                <strong class="text-art-black">{{ $conversationPartnerEffective }}</strong>
                @if ($conversationPartnerAdminOverride)
                    (aus Admin, überschreibt .env)
                @else
                    (aus .env)
                @endif
            @else
                <span class="text-art-black">kein Filter</span> — alle Mails im Abruf (wie bisher).
            @endif
        </p>
        <div class="mt-4 pt-4 border-t border-art-border">
            <flux:button
                type="button"
                variant="outline"
                wire:click="testMailConnections"
                wire:loading.attr="disabled"
                wire:target="testMailConnections"
            >
                <span wire:loading.remove wire:target="testMailConnections">Verbindung testen</span>
                <span wire:loading wire:target="testMailConnections">Teste …</span>
            </flux:button>
            <span class="font-poppins text-2xs text-art-text-muted ms-2">IMAP (wie <code class="bg-art-page px-1 rounded">ghostwriter:imap-test</code>) + SMTP aus <code class="bg-art-page px-1 rounded">GHOSTWRITER_SMTP_*</code></span>
        </div>
    </section>

    @if ($imapDiagnosticsResult !== null)
        <section
            wire:key="imap-diagnostics-{{ $imapDiagnosticsResult['ok'] ? '1' : '0' }}-{{ md5(json_encode($imapDiagnosticsResult)) }}"
            class="border rounded-lg p-5 mb-6 {{ $imapDiagnosticsResult['ok'] ? 'border-green-200 bg-green-50/80' : 'border-red-200 bg-red-50/80' }}"
        >
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">{{ $imapDiagnosticsResult['headline'] }}</h2>
            <ul class="font-poppins text-xs-plus text-art-text-muted list-disc pl-5 space-y-1 mb-4">
                @foreach ($imapDiagnosticsResult['notes'] as $line)
                    <li class="whitespace-pre-wrap">{{ $line }}</li>
                @endforeach
            </ul>
            @if (count($imapDiagnosticsResult['folders']) > 0)
                <h3 class="font-poppins text-xs-plus font-semibold text-art-black mb-2">IMAP-Ordner</h3>
                <div class="overflow-x-auto border border-art-border rounded-lg bg-white">
                    <table class="w-full text-left font-poppins text-2xs">
                        <thead class="bg-art-page text-art-text-muted">
                            <tr>
                                <th class="px-3 py-2 font-semibold">path (für .env)</th>
                                <th class="px-3 py-2 font-semibold">name</th>
                                <th class="px-3 py-2 font-semibold">Trenner</th>
                                <th class="px-3 py-2 font-semibold">Hinweis</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($imapDiagnosticsResult['folders'] as $row)
                                <tr class="border-t border-art-border">
                                    <td class="px-3 py-2 font-mono text-art-black break-all">{{ $row['path'] }}</td>
                                    <td class="px-3 py-2 text-art-text-muted">{{ $row['name'] }}</td>
                                    <td class="px-3 py-2 text-art-text-muted">{{ $row['delimiter'] }}</td>
                                    <td class="px-3 py-2 text-art-text-muted">{{ $row['hint'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if (count($imapDiagnosticsResult['folder_checks']) > 0)
                <h3 class="font-poppins text-xs-plus font-semibold text-art-black mt-4 mb-2">Konfigurierte Ghostwriter-Ordner</h3>
                <ul class="font-poppins text-xs-plus space-y-1">
                    @foreach ($imapDiagnosticsResult['folder_checks'] as $check)
                        <li class="{{ ($check['ok'] ?? false) ? 'text-green-800' : 'text-red-800' }}">
                            @if ($check['ok'] ?? false)
                                OK: <span class="font-mono">{{ $check['path'] }}</span>
                            @else
                                Fehler: <span class="font-mono">{{ $check['path'] }}</span>
                                @if (! empty($check['message']))
                                    — {{ $check['message'] }}
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($smtpDiagnosticsResult !== null)
        <section
            wire:key="smtp-diagnostics-{{ $smtpDiagnosticsResult['ok'] ? '1' : '0' }}-{{ md5(json_encode($smtpDiagnosticsResult)) }}"
            class="border rounded-lg p-5 mb-6 {{ $smtpDiagnosticsResult['ok'] ? 'border-green-200 bg-green-50/80' : 'border-red-200 bg-red-50/80' }}"
        >
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">{{ $smtpDiagnosticsResult['headline'] }}</h2>
            <ul class="font-poppins text-xs-plus text-art-text-muted list-disc pl-5 space-y-1">
                @foreach ($smtpDiagnosticsResult['notes'] as $line)
                    <li class="whitespace-pre-wrap">{{ $line }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="border rounded-lg p-5 mb-6 border-slate-200">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Feedback-Kanal</h2>

        <label class="flex items-center gap-2 text-sm mb-2">
            <input type="checkbox" wire:model.live="feedbackEnabled" class="rounded border-slate-300" />
            <span>Feedback-Formular aktivieren</span>
        </label>

        <label class="flex items-center gap-2 text-sm mb-2">
            <input type="checkbox" wire:model.live="feedbackRequireSubject" class="rounded border-slate-300" />
            <span>Betreff-Feld einblenden und verpflichten</span>
        </label>

        <label class="flex items-center gap-2 text-sm mb-3">
            <input type="checkbox" wire:model.live="feedbackRequireEmailForGuests" class="rounded border-slate-300" />
            <span>E-Mail bei Gast-Feedback verlangen</span>
        </label>

        <div class="space-y-2 mb-3">
            <label class="block text-sm font-medium">Themen (optional)</label>
            <ul class="space-y-1">
                @foreach ($feedbackTopics as $i => $topic)
                    <li class="flex items-center gap-2 text-sm">
                        <span class="flex-1 rounded bg-slate-100 px-2 py-1">{{ $topic }}</span>
                        <button type="button" wire:click="removeFeedbackTopic({{ $i }})"
                                class="text-xs text-red-600 hover:underline">entfernen</button>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-center gap-2">
                <input type="text" wire:model.defer="newFeedbackTopic" placeholder="Neues Thema"
                       class="flex-1 rounded border border-slate-300 px-2 py-1 text-sm" />
                <button type="button" wire:click="addFeedbackTopic"
                        class="rounded bg-slate-200 px-3 py-1 text-sm font-medium hover:bg-slate-300">+ hinzufügen</button>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm mb-3">
            <span class="w-48">Rate-Limit pro Minute / IP</span>
            <input type="number" min="0" max="9999" wire:model.defer="feedbackRateLimitPerMinute"
                   class="w-24 rounded border border-slate-300 px-2 py-1 text-right text-sm" />
        </label>

        <div>
            <button type="button" wire:click="saveFeedbackSettings"
                    class="rounded bg-art-black px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                Feedback-Einstellungen speichern
            </button>
        </div>
    </section>
</div>
