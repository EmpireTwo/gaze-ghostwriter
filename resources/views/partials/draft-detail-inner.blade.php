@php
    /** @var \Empire2\GazeGhostwriter\Models\SupportDraft $draft */
    $msg = $draft->message;
    $r = $draft->rationale;
    $originalParts = \Empire2\GazeGhostwriter\Support\MailReplyHistorySplitter::split((string) ($msg->body_text ?? ''));
    $isAnonymousSender = $msg->from_email === \Empire2\GazeGhostwriter\Services\FeedbackIntakeService::ANONYMOUS_SENDER_SENTINEL;
    $canSendReply = in_array($draft->status, [\Empire2\GazeGhostwriter\Enums\DraftStatus::PENDING_REVIEW, \Empire2\GazeGhostwriter\Enums\DraftStatus::ACCEPTED], true)
        && $draft->sent_at === null
        && ! $isAnonymousSender;
    $smtpReady = filled(config('gaze-ghostwriter.smtp.host')) && filled(config('gaze-ghostwriter.reply.from_address'));
    $gwViewer = auth()->user();
    $gwKiBody = $gwViewer !== null
        ? \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::apply($draft->draft_body, $gwViewer)
        : $draft->draft_body;
    $gwStandBody = $gwViewer !== null
        ? \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::appendReplySignature(
            \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::apply($draft->resolvedReplyBody(), $gwViewer),
            $gwViewer
        )
        : $draft->resolvedReplyBody();
    $gwReplySignature = $gwViewer !== null
        ? \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::replySignature($gwViewer)
        : '';
    $gwReplySignatureHtml = $gwViewer !== null
        ? \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::replySignatureHtml($gwViewer)
        : '';
    $canRate = in_array($draft->status, [
        \Empire2\GazeGhostwriter\Enums\DraftStatus::PENDING_REVIEW,
        \Empire2\GazeGhostwriter\Enums\DraftStatus::SENT,
        \Empire2\GazeGhostwriter\Enums\DraftStatus::DISMISSED,
    ], true);
@endphp

<div class="grid gap-6 min-w-0 overflow-hidden">
    <section class="border border-art-border rounded-lg p-5 bg-white">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Original ({{ $msg->from_email }})</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-2"><strong>Eingegangen:</strong> {{ $msg->received_at->format('d.m.Y H:i') }}</p>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-2"><strong>Betreff:</strong> {{ $msg->subject ?? '—' }}</p>
        @if ($msg->body_html)
            @php
                $sanitizedHtml = \Empire2\GazeGhostwriter\Support\MailHtmlSanitizer::sanitizeForPreview($msg->body_html);
            @endphp
            <div
                wire:key="email-frame-{{ $draft->id }}"
                class="border-t border-art-border pt-3"
                x-data="{ iframeHeight: 200 }"
                x-on:message.window="if ($event.data?.type === 'gw-iframe-resize' && $event.source === $refs.emailFrame?.contentWindow) iframeHeight = Math.max(100, $event.data.h)"
            >
                <iframe
                    x-ref="emailFrame"
                    srcdoc="{{ $sanitizedHtml }}"
                    sandbox="allow-scripts"
                    scrolling="no"
                    referrerpolicy="no-referrer"
                    x-bind:style="'height:' + iframeHeight + 'px'"
                    class="w-full border-0"
                ></iframe>
            </div>
        @else
            <div class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere] border-t border-art-border pt-3">{{ $originalParts['latest'] }}</div>
            @if ($originalParts['history'] !== null)
                <div class="mt-2" x-data="{ historyOpen: false }">
                    <button
                        type="button"
                        class="font-poppins text-xs-plus text-art-violet-deep hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-art-violet-deep rounded"
                        x-on:click="historyOpen = !historyOpen"
                        x-bind:aria-expanded="historyOpen ? 'true' : 'false'"
                    >
                        <span x-text="historyOpen ? 'Antworthistorie ausblenden' : 'Antworthistorie einblenden'"></span>
                    </button>
                    <div
                        x-show="historyOpen"
                        x-cloak
                        x-transition.opacity.duration.200ms
                        class="mt-3 font-poppins text-sm text-art-text-muted whitespace-pre-wrap [overflow-wrap:anywhere] border-t border-art-border pt-3"
                    >
                        {{ $originalParts['history'] }}
                    </div>
                </div>
            @endif
        @endif
        @if ($draft->needsTranslation())
            <div class="mt-4 pt-4 border-t border-dashed border-art-border/60">
                <p class="font-poppins text-2xs font-semibold text-art-text-muted mb-2 uppercase tracking-wide">Deutsche Übersetzung</p>
                @if ($draft->mail_translation !== null)
                    <div class="font-poppins text-sm text-art-text-muted whitespace-pre-wrap [overflow-wrap:anywhere]">{{ $draft->mail_translation }}</div>
                @else
                    <div wire:poll.3s="$refresh" class="font-poppins text-xs-plus text-art-text-muted italic">Übersetzung wird vorbereitet…</div>
                @endif
            </div>
        @endif
    </section>

    @if (($msg->channel ?? 'smtp') === 'web')
        <section class="border border-teal-200 bg-teal-50/50 rounded-lg p-5">
            <h2 class="font-poppins text-sm font-semibold text-teal-800 mb-3">Client-Kontext (Web-Feedback)</h2>
            <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs-plus">
                <dt class="text-art-text-muted">User-ID</dt>
                <dd>{{ $msg->client_user_id ?? '—' }}</dd>
                <dt class="text-art-text-muted">E-Mail</dt>
                <dd>{{ data_get($msg->client_context, 'email', '—') }}</dd>
                <dt class="text-art-text-muted">Name</dt>
                <dd>{{ data_get($msg->client_context, 'name', '—') }}</dd>
                <dt class="text-art-text-muted">Quelle</dt>
                <dd class="truncate">
                    @if (filled($msg->source_url))
                        <a href="{{ $msg->source_url }}" target="_blank" rel="noopener" class="text-teal-700 underline">{{ $msg->source_url }}</a>
                    @else
                        —
                    @endif
                </dd>
                <dt class="text-art-text-muted">Thema</dt>
                <dd>{{ $msg->topic ?? '—' }}</dd>
            </dl>
        </section>
    @endif

    @if ($draft->status === \Empire2\GazeGhostwriter\Enums\DraftStatus::SENT)
        <section class="border border-art-border rounded-lg p-5 bg-art-violet-bg/30">
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">
                Antwort versendet am {{ $draft->sent_at->format('d.m.Y H:i') }}
                @if ($draft->relationLoaded('sentByUser') && $draft->sentByUser)
                    <span class="font-normal text-art-text-muted text-2xs">({{ $draft->sentByUser->email }})</span>
                @endif
            </h2>
            <div class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere]">{{ $gwStandBody }}</div>
            @if ($gwReplySignatureHtml !== '')
                <div class="mt-3 pt-3 border-t border-dashed border-art-border/60 overflow-x-auto [&_img]:inline [&_img]:align-middle">{!! $gwReplySignatureHtml !!}</div>
            @endif
            @if ($draft->hasEditedBody())
                <div class="mt-3" x-data="{ showOriginal: false }">
                    <button
                        type="button"
                        class="font-poppins text-xs-plus text-art-violet-deep hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-art-violet-deep rounded"
                        x-on:click="showOriginal = !showOriginal"
                        x-bind:aria-expanded="showOriginal ? 'true' : 'false'"
                    >
                        <span x-text="showOriginal ? 'Original KI-Vorschlag ausblenden' : 'Original KI-Vorschlag anzeigen'"></span>
                    </button>
                    <div
                        x-show="showOriginal"
                        x-cloak
                        x-transition.opacity.duration.200ms
                        class="mt-2 font-poppins text-sm text-art-text-muted whitespace-pre-wrap [overflow-wrap:anywhere] border-t border-dashed border-art-border/60 pt-2"
                    >{{ $gwKiBody }}</div>
                </div>
            @endif
        </section>
    @elseif ($draft->status === \Empire2\GazeGhostwriter\Enums\DraftStatus::PENDING_REVIEW)
        <section
            class="border border-art-border rounded-lg p-5 bg-art-violet-bg/30 relative"
            x-data="{
                editing: false,
                isDirty: false,
                showUnsavedModal: false,
                baseline: @js($this->editableBody ?? $gwKiBody),
                enterEdit() {
                    this.editing = true;
                    $dispatch('gw-edit-active', { active: true });
                    this.$nextTick(() => { if (this.$refs.body) this.$refs.body.focus() });
                },
                exitEdit() {
                    this.editing = false;
                    this.showUnsavedModal = false;
                    $dispatch('gw-edit-active', { active: false });
                    $dispatch('gw-edit-dirty', { dirty: false });
                },
                discard() {
                    if (this.$refs.body) this.$refs.body.value = this.baseline;
                    $wire.editableBody = this.baseline;
                    this.isDirty = false;
                    this.exitEdit();
                },
                promptUnsaved() {
                    this.showUnsavedModal = true;
                }
            }"
            x-on:draft-body-synced.window="isDirty = false; exitEdit(); if ($refs.body) baseline = $refs.body.value"
            x-on:draft-body-reset.window="isDirty = false; exitEdit(); baseline = $wire.editableBody"
            x-on:keydown.meta.s.prevent="if (editing && isDirty) $wire.saveEditedBody()"
            x-on:keydown.ctrl.s.prevent="if (editing && isDirty) $wire.saveEditedBody()"
            x-on:keydown.escape.window="if (!editing) return; if (!isDirty) { exitEdit(); } else { promptUnsaved(); }"
            x-on:click.window="if (!editing || $refs.editArea?.contains($event.target) || $refs.unsavedModal?.contains($event.target)) return; if (!isDirty) { exitEdit(); return; } promptUnsaved();"
        >
            <div
                wire:loading.flex
                wire:target="regenerate"
                class="absolute inset-0 z-20 items-center justify-center rounded-lg bg-art-violet-bg/80 backdrop-blur-[2px]"
            >
                <div class="flex flex-col items-center gap-3">
                    <svg class="size-6 animate-spin text-art-violet-deep" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span class="font-poppins text-xs-plus font-medium text-art-violet-deep">Neuer Entwurf wird erstellt …</span>
                </div>
            </div>

            <div class="flex items-center justify-between mb-3">
                <h2 class="font-poppins text-sm font-semibold text-art-black">
                    @if ($draft->hasEditedBody())
                        Ghostwriter 3000 — Antwort <span class="font-normal text-art-text-muted text-2xs">(bearbeitet)</span>
                    @else
                        Vorschlag Ghostwriter 3000 <span class="font-normal text-art-text-muted text-2xs">(KI, unverändert)</span>
                    @endif
                </h2>
                <button
                    x-show="!editing"
                    type="button"
                    class="font-poppins text-xs-plus text-art-violet-deep hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-art-violet-deep rounded"
                    x-on:click.stop="enterEdit()"
                >Bearbeiten</button>
            </div>

            {{-- Read-only --}}
            <div x-show="!editing">
                <div
                    class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere] cursor-text rounded-md -mx-2 -my-1 px-2 py-1 hover:bg-white/60 transition-colors duration-150"
                    x-on:click.stop="enterEdit()"
                    title="Klicken zum Bearbeiten"
                >{{ $draft->hasEditedBody() ? ($gwViewer !== null ? \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::apply($draft->edited_body, $gwViewer) : $draft->edited_body) : $gwKiBody }}</div>
                @if ($draft->hasEditedBody())
                    <div class="mt-3" x-data="{ showOriginal: false }">
                        <button
                            type="button"
                            class="font-poppins text-xs-plus text-art-violet-deep hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-art-violet-deep rounded"
                            x-on:click.stop="showOriginal = !showOriginal"
                            x-bind:aria-expanded="showOriginal ? 'true' : 'false'"
                        >
                            <span x-text="showOriginal ? 'Original KI-Vorschlag ausblenden' : 'Original KI-Vorschlag anzeigen'"></span>
                        </button>
                        <div
                            x-show="showOriginal"
                            x-cloak
                            x-transition.opacity.duration.200ms
                            class="mt-2 font-poppins text-sm text-art-text-muted whitespace-pre-wrap [overflow-wrap:anywhere] border-t border-dashed border-art-border/60 pt-2"
                        >{{ $gwKiBody }}</div>
                    </div>
                @endif
                {{-- Action row (read-only mode) --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mt-4 pt-4 border-t border-art-border/60">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:button type="button" variant="ghost" size="sm" x-on:click.stop="enterEdit()">Bearbeiten</flux:button>
                        <flux:button type="button" variant="ghost" size="sm" x-on:click="$store.confirm.open('Einen neuen KI-Entwurf erzeugen? Der aktuelle wird archiviert.', () => $wire.regenerate())">Neu generieren</flux:button>
                        <flux:button type="button" variant="ghost" size="sm" x-on:click="$store.confirm.open('Diesen Entwurf als Keine Antwort nötig markieren?', () => $wire.dismiss())">Keine Antwort</flux:button>
                    </div>
                    @if ($canSendReply && $smtpReady)
                        <flux:button type="button" variant="outline" size="sm" color="emerald" x-on:click="$store.confirm.open('Antwort jetzt per E-Mail an {{ $msg->from_email }} senden?', () => $wire.sendReply())">Antwort senden</flux:button>
                    @endif
                </div>
            </div>

            {{-- Edit mode --}}
            <div x-show="editing" x-cloak x-ref="editArea">
                <flux:textarea
                    wire:model="editableBody"
                    x-ref="body"
                    x-on:input="isDirty = $el.value !== baseline; $dispatch('gw-edit-dirty', { dirty: isDirty })"
                    rows="10"
                    class="font-poppins text-sm"
                />
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <flux:button
                        type="button"
                        variant="primary"
                        size="sm"
                        wire:click="saveEditedBody"
                        x-bind:disabled="!isDirty"
                        ::class="isDirty ? '' : 'opacity-50 cursor-not-allowed'"
                    >Speichern</flux:button>
                    <flux:button type="button" variant="ghost" size="sm" x-on:click="discard()">Verwerfen</flux:button>
                    @if ($canSendReply && $smtpReady)
                        <flux:button type="button" variant="outline" size="sm" color="emerald" x-on:click="$store.confirm.open('Antwort jetzt per E-Mail an {{ $msg->from_email }} senden?', () => $wire.sendReply())">Senden</flux:button>
                    @endif
                    <p class="font-poppins text-2xs text-art-text-muted ml-auto" x-show="isDirty" x-cloak>⌘S / Strg+S</p>
                </div>
            </div>

            {{-- Unsaved changes overlay (teleported to body to escape overflow-clip) --}}
            <template x-teleport="body">
                <div
                    x-ref="unsavedModal"
                    x-show="showUnsavedModal"
                    x-cloak
                    class="fixed inset-0 z-50 flex items-center justify-center"
                    x-on:keydown.escape.stop="showUnsavedModal = false; $refs.body?.focus()"
                >
                    <div
                        class="absolute inset-0 bg-black/30"
                        x-show="showUnsavedModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                    ></div>
                    <div
                        class="relative bg-white rounded-xl shadow-xl border border-art-border p-6 w-full max-w-sm"
                        x-show="showUnsavedModal"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                    >
                        <h3 class="font-poppins text-sm font-semibold text-art-black mb-1">Ungespeicherte Änderungen</h3>
                        <p class="font-poppins text-xs-plus text-art-text-muted mb-5">Möchtest du deine Änderungen speichern oder verwerfen?</p>
                        <div class="flex gap-2 justify-end">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                x-on:click="showUnsavedModal = false; $refs.body?.focus()"
                            >Abbrechen</flux:button>
                            <flux:button
                                type="button"
                                variant="outline"
                                size="sm"
                                x-on:click="discard()"
                            >Verwerfen</flux:button>
                            <flux:button
                                type="button"
                                variant="primary"
                                size="sm"
                                x-on:click="$wire.saveEditedBody()"
                            >Speichern</flux:button>
                        </div>
                    </div>
                </div>
            </template>

            @if ($gwReplySignatureHtml !== '' || $gwReplySignature !== '')
                <div class="mt-3 pt-3 border-t border-dashed border-art-border/60">
                    <p class="font-poppins text-2xs text-art-text-muted mb-1">Signatur (wird beim Versand angehängt)</p>
                    @if ($gwReplySignatureHtml !== '')
                        <div class="overflow-x-auto [&_img]:inline [&_img]:align-middle">{!! $gwReplySignatureHtml !!}</div>
                    @else
                        <div class="font-poppins text-sm text-art-text-muted whitespace-pre-wrap">{{ $gwReplySignature }}</div>
                    @endif
                </div>
            @endif

            @if ($draft->needsTranslation())
                <div
                    class="mt-4 pt-4 border-t border-art-border/60"
                    x-data="{
                        editingTranslation: false,
                        isDirtyTranslation: false,
                        baselineTranslation: @js($this->editableDraftTranslation),
                        enterTranslationEdit() {
                            this.editingTranslation = true;
                            this.$nextTick(() => { if (this.$refs.translationBody) this.$refs.translationBody.focus() });
                        },
                        exitTranslationEdit() {
                            this.editingTranslation = false;
                            this.isDirtyTranslation = false;
                        },
                        discardTranslation() {
                            if (this.$refs.translationBody) this.$refs.translationBody.value = this.baselineTranslation;
                            $wire.editableDraftTranslation = this.baselineTranslation;
                            this.isDirtyTranslation = false;
                            this.exitTranslationEdit();
                        }
                    }"
                    x-on:draft-translation-synced.window="isDirtyTranslation = false; exitTranslationEdit(); if ($refs.translationBody) baselineTranslation = $refs.translationBody.value"
                >
                    <div class="flex items-center justify-between mb-2">
                        <p class="font-poppins text-2xs font-semibold text-art-text-muted uppercase tracking-wide">Deutsche Übersetzung des Entwurfs</p>
                        @if ($draft->translationsReady())
                            <button
                                x-show="!editingTranslation"
                                type="button"
                                class="font-poppins text-xs-plus text-art-violet-deep hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-art-violet-deep rounded"
                                x-on:click.stop="enterTranslationEdit()"
                            >Bearbeiten</button>
                        @endif
                    </div>

                    @if ($draft->translationsReady())
                        {{-- Read mode --}}
                        <div x-show="!editingTranslation">
                            <div
                                class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere] cursor-text rounded-md -mx-2 -my-1 px-2 py-1 hover:bg-white/60 transition-colors duration-150"
                                x-on:click.stop="enterTranslationEdit()"
                                title="Klicken zum Bearbeiten"
                            >{{ $draft->resolvedDraftTranslation() }}</div>
                            <div class="mt-3">
                                <flux:button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    wire:click="translateAndApply"
                                >Übersetzen und übernehmen</flux:button>
                            </div>
                        </div>

                        {{-- Edit mode --}}
                        <div x-show="editingTranslation" x-cloak>
                            <flux:textarea
                                wire:model="editableDraftTranslation"
                                x-ref="translationBody"
                                x-on:input="isDirtyTranslation = $el.value !== baselineTranslation"
                                rows="8"
                                class="font-poppins text-sm"
                            />
                            <div class="flex flex-wrap items-center gap-2 mt-3">
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    wire:click="saveEditedDraftTranslation"
                                    x-bind:disabled="!isDirtyTranslation"
                                    ::class="isDirtyTranslation ? '' : 'opacity-50 cursor-not-allowed'"
                                >Speichern</flux:button>
                                <flux:button
                                    type="button"
                                    variant="primary"
                                    size="sm"
                                    wire:click="translateAndApply"
                                >Übersetzen und übernehmen</flux:button>
                                <flux:button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    x-on:click="discardTranslation()"
                                >Verwerfen</flux:button>
                            </div>
                        </div>
                    @else
                        <div wire:poll.3s="$refresh" class="font-poppins text-xs-plus text-art-text-muted italic">Übersetzung wird vorbereitet…</div>
                    @endif
                </div>
            @endif
        </section>
    @else
        <section class="border border-art-border rounded-lg p-5 bg-art-violet-bg/30">
            @if ($draft->hasEditedBody())
                <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Ghostwriter 3000 — Antwort <span class="font-normal text-art-text-muted text-2xs">(bearbeitet)</span></h2>
                <div class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere]">{{ $gwViewer !== null ? \Empire2\GazeGhostwriter\Support\GhostwriterPlaceholderReplacer::apply($draft->edited_body, $gwViewer) : $draft->edited_body }}</div>
                <div class="mt-3" x-data="{ showOriginal: false }">
                    <button
                        type="button"
                        class="font-poppins text-xs-plus text-art-violet-deep hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-art-violet-deep rounded"
                        x-on:click="showOriginal = !showOriginal"
                        x-bind:aria-expanded="showOriginal ? 'true' : 'false'"
                    >
                        <span x-text="showOriginal ? 'Original KI-Vorschlag ausblenden' : 'Original KI-Vorschlag anzeigen'"></span>
                    </button>
                    <div
                        x-show="showOriginal"
                        x-cloak
                        x-transition.opacity.duration.200ms
                        class="mt-2 font-poppins text-sm text-art-text-muted whitespace-pre-wrap [overflow-wrap:anywhere] border-t border-dashed border-art-border/60 pt-2"
                    >{{ $gwKiBody }}</div>
                </div>
            @else
                <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Vorschlag Ghostwriter 3000 <span class="font-normal text-art-text-muted text-2xs">(KI, unverändert)</span></h2>
                <div class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere]">{{ $gwKiBody }}</div>
            @endif
            @if ($gwReplySignatureHtml !== '' || $gwReplySignature !== '')
                <div class="mt-3 pt-3 border-t border-dashed border-art-border/60">
                    <p class="font-poppins text-2xs text-art-text-muted mb-1">Signatur (wird beim Versand angehängt)</p>
                    @if ($gwReplySignatureHtml !== '')
                        <div class="overflow-x-auto [&_img]:inline [&_img]:align-middle">{!! $gwReplySignatureHtml !!}</div>
                    @else
                        <div class="font-poppins text-sm text-art-text-muted whitespace-pre-wrap">{{ $gwReplySignature }}</div>
                    @endif
                </div>
            @endif
            @if ($canSendReply && $smtpReady)
                <div class="mt-4 pt-4 border-t border-art-border/60 flex flex-wrap items-center gap-2">
                    <flux:button type="button" variant="outline" size="sm" color="emerald" x-on:click="$store.confirm.open('Antwort jetzt per E-Mail an {{ $msg->from_email }} senden?', () => $wire.sendReply())">Antwort senden</flux:button>
                </div>
            @endif
        </section>
    @endif

    @if ($draft->status === \Empire2\GazeGhostwriter\Enums\DraftStatus::DISMISSED)
        <section class="border border-art-border rounded-lg p-5 bg-white">
            <div class="flex flex-wrap items-center gap-2">
                <flux:button type="button" variant="outline" size="sm" x-on:click="$store.confirm.open('Entwurf wieder zur Bearbeitung öffnen?', () => $wire.reopen())">Wieder öffnen</flux:button>
            </div>
        </section>
    @endif

    @if ($draft->status !== \Empire2\GazeGhostwriter\Enums\DraftStatus::SENT && $draft->status !== \Empire2\GazeGhostwriter\Enums\DraftStatus::PENDING_REVIEW)
        <section class="border border-art-border rounded-lg p-5 bg-white">
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Antwort (Stand)</h2>
            <div class="font-poppins text-sm text-art-black whitespace-pre-wrap [overflow-wrap:anywhere]">{{ $gwStandBody }}</div>
            @if ($gwReplySignatureHtml !== '')
                <div class="mt-3 pt-3 border-t border-dashed border-art-border/60 overflow-x-auto [&_img]:inline [&_img]:align-middle">{!! $gwReplySignatureHtml !!}</div>
            @endif
            @if ($draft->hasEditedBody())
                <p class="font-poppins text-2xs text-art-text-muted mt-3">Enthält deine manuelle Bearbeitung.</p>
            @endif
        </section>
    @endif

    <section class="border border-art-border rounded-lg p-5 bg-white">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Warum dieser Entwurf?</h2>
        <dl class="space-y-3 font-poppins text-sm text-art-text-muted">
            <div>
                <dt class="font-semibold text-art-black">Thematisch</dt>
                <dd class="mt-1">{{ $r['thematische_begruendung'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="font-semibold text-art-black">Stil / Formulierung</dt>
                <dd class="mt-1">{{ $r['stilistische_begruendung'] ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    @if ($canRate)
        <section class="border border-art-border rounded-lg p-5 bg-white">
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-3">Bewertung (1–5 Sterne)</h2>
            <p class="font-poppins text-xs-plus text-art-text-muted mb-3">
                Hilft später bei Auswertung und Verbesserung der Vorschläge — derzeit ohne automatisches Modell-Training.
            </p>
            <div class="flex flex-wrap gap-2 mb-4">
                @for ($s = 1; $s <= 5; $s++)
                    <flux:button
                        type="button"
                        size="sm"
                        variant="{{ (int) $draft->user_rating === $s ? 'primary' : 'ghost' }}"
                        wire:click="rate({{ $s }})"
                    >{{ $s }} ★</flux:button>
                @endfor
            </div>
            @if ($draft->user_rating)
                <p class="font-poppins text-xs-plus text-art-text-muted mb-2">Aktuell: {{ $draft->user_rating }}/5</p>
            @endif
            <flux:textarea
                wire:model="ratingComment"
                label="Optionaler Kommentar"
                rows="3"
                placeholder="Was war gut oder schlecht?"
            />
            <p class="font-poppins text-2xs text-art-text-muted mt-2">Speichern: Stern-Button wählen (Kommentar wird mitgespeichert).</p>
        </section>
    @elseif ($draft->user_rating)
        <section class="border border-art-border rounded-lg p-5 bg-art-page/40">
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">Gespeicherte Bewertung</h2>
            <p class="font-poppins text-sm text-art-text-muted">{{ $draft->user_rating }}/5 Sterne</p>
            @if ($draft->rating_comment)
                <p class="font-poppins text-xs-plus text-art-text-muted mt-2 whitespace-pre-wrap">{{ $draft->rating_comment }}</p>
            @endif
        </section>
    @endif

</div>
