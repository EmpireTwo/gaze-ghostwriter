<div class="p-8">
    <h1 class="sr-only">Ghostwriter 3000 — Entwürfe</h1>

    <div class="flex flex-wrap gap-4 items-center mb-4">
        <div class="flex-1 min-w-[200px] max-w-sm">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Absender, E-Mail, Betreff …"
                class="w-full rounded border border-art-border px-3 py-1.5 font-poppins text-xs-plus text-art-black bg-white placeholder:text-art-text-muted/60 focus:outline-none focus:ring-2 focus:ring-art-violet-deep/40"
            />
        </div>
        <div class="flex gap-2 items-center">
            <label class="font-poppins text-xs-plus text-art-text-muted" for="gw-status">Status</label>
            <select
                id="gw-status"
                wire:model.live="statusFilter"
                class="rounded border border-art-border px-2 py-1.5 font-poppins text-xs-plus text-art-black bg-white"
            >
                <option value="">Alle</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <label class="inline-flex gap-2 items-center font-poppins text-xs-plus text-art-text-muted cursor-pointer">
            <input type="checkbox" wire:model.live="includeSuperseded" class="rounded border-art-border" />
            Ersetzte Entwürfe anzeigen
        </label>
        <div class="ml-auto">
            <flux:button
                type="button"
                variant="ghost"
                size="sm"
                icon="arrow-path"
                wire:click="runInboxSync"
                wire:loading.attr="disabled"
                wire:target="runInboxSync"
                title="Postfach abrufen"
            />
        </div>
    </div>
    <p class="font-poppins text-2xs text-art-text-muted mb-6">
        Import <strong class="text-art-black font-medium">{{ $importedMailTotal }}</strong>
        @if ($importedMailTotal > 0)
            · Eingehend an Support <strong class="text-art-black font-medium">{{ $importedMailInboundSupport }}</strong>
        @endif
        · <a href="{{ route('gaze-ghostwriter.settings') }}" wire:navigate class="text-art-violet-deep hover:underline">Einstellungen</a>
        @if (! $ghostwriterEnabled)
            · <span class="text-amber-800">Ghostwriter aus — <code class="bg-art-page px-1 rounded">GHOSTWRITER_ENABLED=true</code></span>
        @elseif ($schedulerPaused)
            · <span class="text-amber-800">Scheduler pausiert — <a href="{{ route('gaze-ghostwriter.settings') }}" wire:navigate class="underline hover:no-underline">Einstellungen</a></span>
        @endif
    </p>

    @if ($importedMailTotal > 0 && $importedMailInboundSupport === 0 && $drafts->isEmpty())
        <div class="border border-amber-200 bg-amber-50/90 rounded-lg p-4 mb-6 font-poppins text-xs-plus text-art-text-muted">
            <p class="font-semibold text-art-black mb-2">Import läuft, aber es gibt keine Entwurfs-Kandidaten</p>
            <p class="mb-2">
                In der Datenbank liegen <strong>{{ $importedMailTotal }}</strong> importierte Mail(s), aber <strong>keine</strong>, bei denen eine deiner
                <code class="text-2xs bg-white px-1 rounded border border-art-border">GHOSTWRITER_SUPPORT_ADDRESSES</code>
                in <strong>To/Cc</strong> steht. Typisch sind das <strong>eigene Gesendet-Mails</strong> (Absender = Support, Empfänger = Kunde) — die werden für RAG/Chunks genutzt, lösen aber <strong>keinen</strong> KI-Antwortentwurf aus.
            </p>
            <p>
                Entwürfe entstehen nur für <strong>eingehende</strong> Mails an eine deiner
                <code class="text-2xs bg-white px-1 rounded">GHOSTWRITER_SUPPORT_ADDRESSES</code>.
                Prüfe <strong>INBOX</strong>, Zeitraum <code class="text-2xs bg-white px-1 rounded">GHOSTWRITER_IMAP_LOOKBACK_DAYS</code>,
                und ob Kundenmails wirklich an diese Adresse adressiert sind (To/Cc). Optional: Konversationsfilter unter
                <a href="{{ route('gaze-ghostwriter.settings') }}" wire:navigate class="text-art-violet-deep hover:underline">Einstellungen</a>.
            </p>
        </div>
    @endif

    <p class="font-poppins text-2xs text-art-text-muted mb-2">
        Entwürfe (aktueller Filter): <strong class="text-art-black">{{ $drafts->total() }}</strong>
        @if ($drafts->hasPages())
            <span class="text-art-text-muted">— Seite {{ $drafts->currentPage() }} von {{ $drafts->lastPage() }}</span>
        @endif
    </p>

    <div x-data="{ savedScroll: 0 }">
    @if ($modalDraft)
        {{-- Split view: message sidebar + detail panel --}}
        <div
            x-data="{
                editDirty: false,
                editActive: false,
                closing: false,
                closePanel() {
                    if (this.closing) return;
                    this.closing = true;
                    if (this.$refs.sidebarScroll) savedScroll = this.$refs.sidebarScroll.scrollTop;
                    const panel = this.$refs.detailPanel;
                    const sidebar = this.$refs.sidebarPanel;
                    if (!panel) { $wire.closeDraftModal(); return; }
                    panel.addEventListener('animationend', () => $wire.closeDraftModal(), { once: true });
                    panel.classList.remove('animate-gw-detail-in');
                    panel.classList.add('animate-gw-detail-out');
                }
            }"
            x-on:gw-edit-dirty.window="editDirty = $event.detail.dirty"
            x-on:gw-edit-active.window="editActive = $event.detail.active"
            x-on:keydown.escape.window="if (editActive || editDirty || closing) return; if ($wire.showTicketPanel) { $wire.closeTicketPanel(); return; } closePanel()"
            class="flex rounded-lg border border-art-border bg-white overflow-clip"
            style="height: calc(100vh - 240px)"
        >
            {{-- Left: message list sidebar --}}
            <div x-ref="sidebarPanel" class="w-80 shrink-0 border-r border-art-border flex flex-col">
                <div class="bg-art-page border-b border-art-border px-4 py-2.5 flex items-center justify-between min-h-[42px]">
                    <span class="font-poppins text-xs-plus font-semibold text-art-text-muted">Nachrichten</span>
                    <flux:button
                        type="button"
                        variant="ghost"
                        size="xs"
                        icon="x-mark"
                        x-on:click="closePanel()"
                        title="Zurück zur Listenansicht"
                    />
                </div>
                <div x-ref="sidebarScroll" class="flex-1 overflow-y-auto" x-init="$nextTick(() => $el.scrollTop = savedScroll)">
                    @foreach ($drafts as $draft)
                        @php
                            $receivedAt = $draft->message->received_at;
                            $receivedLabel = $receivedAt->isToday()
                                ? $receivedAt->format('H:i')
                                : ($receivedAt->isCurrentYear()
                                    ? $receivedAt->format('d.m. H:i')
                                    : $receivedAt->format('d.m.Y'));
                            $isActive = $draft->id === $modalDraft->id;
                        @endphp
                        <div
                            role="button"
                            tabindex="0"
                            wire:click="openDraftModal({{ $draft->id }})"
                            wire:keydown.enter.prevent="openDraftModal({{ $draft->id }})"
                            class="px-4 py-3 min-h-[84px] border-b border-art-border cursor-pointer transition-colors duration-100 {{ $isActive ? 'bg-art-violet-bg/50 border-l-2 border-l-art-violet-deep' : 'hover:bg-art-page/60 border-l-2 border-l-transparent' }}"
                        >
                            <div class="flex items-start gap-2 min-w-0">
                                @if ($draft->status === \Empire2\GazeGhostwriter\Enums\DraftStatus::PENDING_REVIEW)
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-blue-500"></span>
                                @else
                                    <span class="mt-1.5 size-2 shrink-0"></span>
                                @endif
                                <div class="flex flex-col gap-0.5 min-w-0">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="text-sm font-semibold text-art-black truncate {{ $isActive ? '' : '' }}">{{ $draft->message->from_name ?: $draft->message->from_email }}</span>
                                        <span class="text-2xs text-art-text-muted whitespace-nowrap shrink-0">{{ $receivedLabel }}</span>
                                    </div>
                                    <p class="text-xs-plus truncate {{ filled($draft->message->subject) ? 'text-art-black' : 'text-art-text-muted italic' }}">{{ filled($draft->message->subject) ? $draft->message->subject : '(Kein Betreff)' }}</p>
                                    @php
                                        $isWeb = ($draft->message->channel ?? 'smtp') === 'web';
                                    @endphp
                                    <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-semibold {{ $isWeb ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-700' }}">{{ $isWeb ? 'WWW' : 'MAIL' }}</span>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-2xs font-medium {{ $draft->status->badgeClasses() }}">{{ $draft->status->label() }}</span>
                                        @if ($draft->user_rating)
                                            <span class="text-2xs text-art-text-muted">{{ $draft->user_rating }}/5</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if ($drafts->hasPages())
                    <div class="border-t border-art-border px-3 py-2 bg-white">
                        <x-admin.pagination :paginator="$drafts" />
                    </div>
                @endif
            </div>

            {{-- Right: detail panel or ticket create panel --}}
            @if ($showTicketPanel && $ticketPanelDraftId)
                <div
                    x-ref="detailPanel"
                    class="flex-1 min-w-0 overflow-y-auto animate-gw-detail-in"
                    wire:key="ticket-panel-{{ $ticketPanelDraftId }}"
                >
                    <div class="sticky top-0 z-10">
                        <div class="bg-art-page border-b border-art-border px-6 py-2.5 flex items-center justify-between min-h-[42px]">
                            <h2 class="font-poppins text-xs-plus font-semibold text-art-black">Ticket erstellen</h2>
                            <flux:button variant="ghost" size="xs" wire:click="closeTicketPanel" icon="x-mark">
                                Zurück zum Entwurf
                            </flux:button>
                        </div>
                    </div>
                    <div class="px-6 py-5">
                        <livewire:ticketing.admin.ticket-create-modal :draft-id="$ticketPanelDraftId" :key="'tcm-'.$ticketPanelDraftId" />
                    </div>
                </div>
            @else
                <div
                    x-ref="detailPanel"
                    class="flex-1 min-w-0 overflow-y-auto animate-gw-detail-in"
                    wire:key="draft-detail-{{ $modalDraft->id }}"
                >
                    <div>
                        <div class="sticky top-0 z-10">
                            <div class="bg-art-page border-b border-art-border px-6 py-2.5 flex items-center justify-between min-h-[42px]">
                                <h2 class="font-poppins text-xs-plus font-semibold text-art-black">Entwurf #{{ $modalDraft->id }}</h2>
                                <flux:button variant="ghost" size="xs" href="{{ route('gaze-ghostwriter.drafts.show', $modalDraft) }}" wire:navigate>
                                    Vollseite
                                </flux:button>
                            </div>
                            <div class="bg-white border-b border-art-border px-6 py-3 flex flex-col gap-2 min-h-[91px] justify-center">
                                <p class="font-poppins text-xs-plus text-art-text-muted">Status: {{ $modalDraft->status->label() }}</p>
                                @include('gaze-ghostwriter::partials.draft-smart-actions', ['draft' => $modalDraft])
                            </div>
                            @if ($lastCreatedTicketId)
                                <div class="bg-emerald-50 border-b border-emerald-200 px-6 py-2.5 flex items-center justify-between">
                                    <span class="font-poppins text-xs-plus text-emerald-800">
                                        <flux:icon.check-circle class="inline size-4 -mt-0.5" />
                                        Ticket <strong>{{ $lastCreatedTicketNumber }}</strong> erstellt
                                    </span>
                                    @php $tsr = config('gaze-ghostwriter.routes.ticket_show', 'admin.tickets.show'); @endphp
                                    <a href="{{ \Illuminate\Support\Facades\Route::has($tsr) ? route($tsr, $lastCreatedTicketId) : '#' }}" wire:navigate class="font-poppins text-xs-plus font-medium text-emerald-700 hover:text-emerald-900 no-underline hover:underline">
                                        Zum Ticket →
                                    </a>
                                </div>
                            @endif
                        </div>
                        <div class="px-6 py-5">
                            @include('gaze-ghostwriter::partials.draft-detail-inner', ['draft' => $modalDraft])
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @elseif ($draftModalOpen)
        <div class="border border-art-border rounded-lg bg-white p-5">
            <p class="font-poppins text-sm text-art-text-muted">Dieser Entwurf ist nicht mehr verfügbar (z. B. gelöscht oder gefiltert).</p>
            <flux:button type="button" variant="primary" size="sm" class="mt-3" wire:click="closeDraftModal">Zurück zur Liste</flux:button>
        </div>
    @else
        {{-- Full table view --}}
        <div class="border border-art-border rounded-lg overflow-hidden bg-white flex flex-col" style="height: calc(100vh - 240px)">
            <table class="w-full table-fixed text-left font-poppins text-xs-plus">
                <thead class="bg-art-page border-b border-art-border text-art-text-muted">
                    <tr>
                        <th class="px-4 py-3 font-semibold w-[45%]">Nachricht</th>
                        <th class="px-4 py-3 font-semibold text-art-text-muted/70">Entwurf</th>
                        <th class="px-4 py-3 font-semibold w-36">Status</th>
                        <th class="px-4 py-3 font-semibold w-24 text-right">Bewertung</th>
                    </tr>
                </thead>
            </table>
            <div class="flex-1 overflow-y-auto" x-init="$nextTick(() => $el.scrollTop = savedScroll)" @click="savedScroll = $el.scrollTop">
            <table class="w-full table-fixed text-left font-poppins text-xs-plus">
                <colgroup>
                    <col class="w-[45%]" />
                    <col />
                    <col class="w-36" />
                    <col class="w-24" />
                </colgroup>
                <tbody>
                    @forelse ($drafts as $draft)
                        @php
                            $receivedAt = $draft->message->received_at;
                            $receivedLabel = $receivedAt->isToday()
                                ? $receivedAt->format('H:i')
                                : ($receivedAt->isCurrentYear()
                                    ? $receivedAt->format('d.m. H:i')
                                    : $receivedAt->format('d.m.Y'));
                        @endphp
                        <tr
                            role="button"
                            tabindex="0"
                            class="border-b border-art-border last:border-0 hover:bg-art-page/60 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-art-violet-deep focus-visible:outline-offset-[-2px]"
                            wire:click="openDraftModal({{ $draft->id }})"
                            wire:keydown.enter.prevent="openDraftModal({{ $draft->id }})"
                            wire:keydown.space.prevent="openDraftModal({{ $draft->id }})"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    @if ($draft->status === \Empire2\GazeGhostwriter\Enums\DraftStatus::PENDING_REVIEW)
                                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full bg-blue-500"></span>
                                    @else
                                        <span class="mt-1.5 size-2.5 shrink-0"></span>
                                    @endif
                                <div class="flex flex-col gap-0.5 min-w-0">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="text-sm font-semibold text-art-black truncate">{{ $draft->message->from_name ?: $draft->message->from_email }}</span>
                                        <span class="text-2xs text-art-text-muted whitespace-nowrap shrink-0">{{ $receivedLabel }}</span>
                                    </div>
                                    <p class="text-xs-plus truncate {{ filled($draft->message->subject) ? 'text-art-black' : 'text-art-text-muted italic' }}">{{ filled($draft->message->subject) ? $draft->message->subject : '(Kein Betreff)' }}</p>
                                    @php
                                        $isWeb = ($draft->message->channel ?? 'smtp') === 'web';
                                    @endphp
                                    <span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-2xs font-semibold {{ $isWeb ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-700' }}">{{ $isWeb ? 'WWW' : 'MAIL' }}</span>
                                    <p class="text-2xs text-art-text-muted line-clamp-1">{{ Str::limit(str_replace(["\r\n", "\n"], ' ', strip_tags((string) $draft->message->body_text)), 120) }}</p>
                                    <p class="text-2xs text-art-text-muted/70">{{ $draft->message->from_email }}</p>
                                </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <p class="text-2xs text-art-text-muted line-clamp-3">{{ Str::limit(str_replace(["\r\n", "\n"], ' ', (string) ($draft->edited_body ?? $draft->draft_body)), 200) }}</p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex px-2 py-0.5 rounded text-2xs font-semibold {{ $draft->status->badgeClasses() }}">
                                    {{ $draft->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-art-text-muted whitespace-nowrap align-top text-right">
                                @if ($draft->user_rating)
                                    {{ $draft->user_rating }}/5
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-art-text-muted">
                                Noch keine Entwürfe.
                                @if ($importedMailTotal > 0)
                                    <span class="block mt-2 text-2xs max-w-md mx-auto">Hinweis: {{ $importedMailTotal }} importierte Nachricht(en) in der DB — siehe gelber Kasten oben, falls keine davon „eingehend an Support" ist.</span>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
            @if ($drafts->hasPages())
                <div class="border-t border-art-border px-3 py-2 bg-white shrink-0">
                    <x-admin.pagination :paginator="$drafts" />
                </div>
            @endif
        </div>
    @endif
    </div>
</div>
