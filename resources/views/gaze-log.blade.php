<div wire:poll.2s class="p-8 space-y-6">
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="font-poppins text-2xl font-semibold text-art-black">Gaze Pipeline Log</h1>
            <p class="mt-1 font-poppins text-sm text-art-text-muted">
                {{ __('Live-polling feed of draft generations passing through the PII boundary.') }}
            </p>
        </div>

        <flux:select wire:model.live="statusFilter" size="sm" class="w-48">
            <flux:select.option value="all">{{ __('Alle') }}</flux:select.option>
            <flux:select.option value="pending_review">{{ __('Pending Review') }}</flux:select.option>
            <flux:select.option value="sent">{{ __('Sent') }}</flux:select.option>
            <flux:select.option value="dismissed">{{ __('Dismissed') }}</flux:select.option>
            <flux:select.option value="superseded">{{ __('Superseded') }}</flux:select.option>
        </flux:select>
    </div>

    @unless ($this->gazeEnabled)
        <flux:callout icon="shield-exclamation" color="amber">
            <flux:callout.heading>{{ __('Gaze boundary disabled') }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('GHOSTWRITER_GAZE_ENABLED is false — no traces are being captured. Flip the env flag (and the Pennant feature) to start seeing entries here.') }}
            </flux:callout.text>
        </flux:callout>
    @endunless

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Empfangen') }}</flux:table.column>
            <flux:table.column>{{ __('Betreff') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Detections') }}</flux:table.column>
            <flux:table.column>{{ __('Dauer') }}</flux:table.column>
            <flux:table.column>&nbsp;</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->drafts as $draft)
                <flux:table.row>
                    <flux:table.cell>
                        <time datetime="{{ $draft->gaze_ran_at?->toIso8601String() }}" class="text-xs text-art-text-muted">
                            {{ $draft->gaze_ran_at?->diffForHumans() }}
                        </time>
                    </flux:table.cell>
                    <flux:table.cell class="max-w-md truncate">
                        {{ $draft->message?->subject ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$draft->status->value === 'sent' ? 'emerald' : 'zinc'">
                            {{ $draft->status->value }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge color="violet" size="sm">{{ $draft->gaze_detections ?? 0 }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $draft->gaze_duration_ms ?? 0 }} ms</flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button wire:click="toggleExpand({{ $draft->id }})" size="xs" variant="outline">
                            {{ $expandedDraftId === $draft->id ? __('Einklappen') : __('Details') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>

                @if ($expandedDraftId === $draft->id)
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="grid gap-4 p-4 bg-art-page rounded">
                                <section>
                                    <h3 class="text-xs uppercase tracking-wide text-art-text-muted">{{ __('1. Original E-Mail') }}</h3>
                                    <pre class="mt-1 whitespace-pre-wrap font-mono text-xs bg-white border border-art-border rounded p-2 max-h-64 overflow-auto">{{ $draft->message?->body_text }}</pre>
                                </section>
                                <section>
                                    <h3 class="text-xs uppercase tracking-wide text-art-text-muted">{{ __('2. Clean Prompt (gesehen vom LLM)') }}</h3>
                                    <pre class="mt-1 whitespace-pre-wrap font-mono text-xs bg-white border border-art-border rounded p-2 max-h-64 overflow-auto">{{ $draft->clean_prompt }}</pre>
                                </section>
                                <section>
                                    <h3 class="text-xs uppercase tracking-wide text-art-text-muted">{{ __('3. Gaze commands') }}</h3>
                                    <p class="mt-1 text-[11px] italic text-art-text-muted">
                                        {{ __('Reconstructed from runner config. Session blobs in restore stdin are redacted — the literal bytes sent to gaze are not captured.') }}
                                    </p>
                                    @if (empty($draft->gaze_invocations))
                                        <p class="mt-2 text-xs text-art-text-muted">{{ __('Keine Invocations aufgezeichnet.') }}</p>
                                    @else
                                        <div class="mt-2 space-y-3">
                                            @foreach ($draft->gaze_invocations as $invocation)
                                                <div class="border border-art-border rounded bg-white p-2">
                                                    <div class="flex items-center gap-2 text-xs text-art-text-muted">
                                                        <flux:badge size="sm" :color="$invocation['stage'] === 'clean' ? 'blue' : 'green'">
                                                            {{ $invocation['stage'] }}
                                                        </flux:badge>
                                                        <span>{{ $invocation['stdin_bytes'] }} bytes · {{ $invocation['duration_ms'] }} ms</span>
                                                    </div>
                                                    <pre class="mt-2 whitespace-pre-wrap font-mono text-xs bg-art-page border border-art-border rounded p-2 overflow-auto">{{ implode(' ', array_map('escapeshellarg', $invocation['argv'])) }}</pre>
                                                    <pre class="mt-2 whitespace-pre-wrap font-mono text-xs bg-art-page border border-art-border rounded p-2 max-h-48 overflow-auto">{{ $invocation['stdin_preview'] }}</pre>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </section>
                                <section>
                                    <h3 class="text-xs uppercase tracking-wide text-art-text-muted">{{ __('4. LLM Raw Response (vor Restore)') }}</h3>
                                    <pre class="mt-1 whitespace-pre-wrap font-mono text-xs bg-white border border-art-border rounded p-2 max-h-64 overflow-auto">{{ json_encode($draft->llm_raw_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </section>
                                <section>
                                    <h3 class="text-xs uppercase tracking-wide text-art-text-muted">{{ __('5. Restored Draft Body') }}</h3>
                                    <pre class="mt-1 whitespace-pre-wrap font-mono text-xs bg-white border border-art-border rounded p-2 max-h-64 overflow-auto">{{ $draft->draft_body }}</pre>
                                </section>
                                <section class="grid grid-cols-3 gap-4 text-xs text-art-text-muted">
                                    <div>
                                        <dt>{{ __('Detections') }}</dt>
                                        <dd class="text-art-black">{{ $draft->gaze_detections ?? 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt>{{ __('Dauer') }}</dt>
                                        <dd class="text-art-black">{{ $draft->gaze_duration_ms ?? 0 }} ms</dd>
                                    </div>
                                    <div>
                                        <dt>{{ __('Warnings') }}</dt>
                                        <dd class="text-art-black">{{ count($draft->gaze_warnings ?? []) }}</dd>
                                    </div>
                                </section>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endif
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-art-text-muted py-12">
                        {{ __('Keine Einträge — entweder läuft keine Pipeline, oder die Gate ist aus.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div>{{ $this->drafts->links() }}</div>
</div>
