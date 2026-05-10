<div class="p-8 max-w-5xl">
    <div class="mb-6">
        <a href="{{ route('gaze-ghostwriter.drafts.index') }}" wire:navigate class="font-poppins text-xs-plus text-art-violet-deep hover:underline">&larr; Zurück zu Entwürfen</a>
    </div>

    <h1 class="font-poppins text-2xl font-light text-art-black mb-1">Prompt-History</h1>
    <p class="font-poppins text-sm text-art-text-light mb-6">
        Alle an die KI gesendeten Prompts und deren Antworten — zur systematischen Analyse und Verbesserung.
    </p>

    {{-- Usage Summary Card --}}
    @if ($stats->total_calls > 0)
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
            <div class="border border-art-border rounded-lg bg-white p-4">
                <p class="font-poppins text-2xs text-art-text-muted">API-Aufrufe</p>
                <p class="font-poppins text-lg font-semibold text-art-black tabular-nums">{{ number_format($stats->total_calls, 0, ',', '.') }}</p>
                <p class="font-poppins text-2xs text-art-text-muted/70 mt-0.5">
                    {{ number_format($stats->initial_calls, 0, ',', '.') }} initial · {{ number_format($stats->regeneration_calls, 0, ',', '.') }} regen.
                </p>
            </div>
            <div class="border border-art-border rounded-lg bg-white p-4">
                <p class="font-poppins text-2xs text-art-text-muted">Tokens gesamt</p>
                <p class="font-poppins text-lg font-semibold text-art-black tabular-nums">{{ number_format($stats->total_tokens, 0, ',', '.') }}</p>
                <p class="font-poppins text-2xs text-art-text-muted/70 mt-0.5">
                    Ø {{ number_format($stats->total_calls > 0 ? $stats->total_tokens / $stats->total_calls : 0, 0, ',', '.') }} / Aufruf
                </p>
            </div>
            <div class="border border-art-border rounded-lg bg-white p-4">
                <p class="font-poppins text-2xs text-art-text-muted">Prompt-Tokens</p>
                <p class="font-poppins text-lg font-semibold text-art-black tabular-nums">{{ number_format($stats->total_prompt_tokens, 0, ',', '.') }}</p>
                <p class="font-poppins text-2xs text-art-text-muted/70 mt-0.5">Input</p>
            </div>
            <div class="border border-art-border rounded-lg bg-white p-4">
                <p class="font-poppins text-2xs text-art-text-muted">Completion-Tokens</p>
                <p class="font-poppins text-lg font-semibold text-art-black tabular-nums">{{ number_format($stats->total_completion_tokens, 0, ',', '.') }}</p>
                <p class="font-poppins text-2xs text-art-text-muted/70 mt-0.5">Output</p>
            </div>
            <div class="border border-art-border rounded-lg bg-white p-4">
                <p class="font-poppins text-2xs text-art-text-muted">Kosten (berechnet)</p>
                <p class="font-poppins text-lg font-semibold text-art-black tabular-nums">${{ number_format($totalCost, 2, '.', '') }}</p>
                <p class="font-poppins text-2xs text-art-text-muted/70 mt-0.5">
                    Ø ${{ number_format($stats->total_calls > 0 ? $totalCost / $stats->total_calls : 0, 4, '.', '') }} / Aufruf
                </p>
            </div>
            <div class="border border-art-border rounded-lg bg-white p-4">
                <p class="font-poppins text-2xs text-art-text-muted">Ø Antwortzeit</p>
                <p class="font-poppins text-lg font-semibold text-art-black tabular-nums">{{ number_format($stats->avg_duration_ms / 1000, 1, ',', '.') }}s</p>
                <p class="font-poppins text-2xs text-art-text-muted/70 mt-0.5">Durchschnitt</p>
            </div>
        </div>

        {{-- OpenAI Costs API --}}
        @if ($openAiCosts !== null)
            <div class="border border-art-border rounded-lg bg-white p-4 mb-6">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <p class="font-poppins text-sm font-semibold text-art-black">OpenAI Account — {{ $openAiCosts['month_label'] }}</p>
                        <p class="font-poppins text-2xs text-art-text-muted">Echte Kosten via Costs API (gesamter Account, alle Produkte)</p>
                    </div>
                    <div class="text-right shrink-0">
                        @if ($openAiCosts['budget_usd'] !== null)
                            <p class="font-poppins text-2xl font-semibold text-art-black tabular-nums">
                                ${{ number_format($openAiCosts['month_cost_usd'], 2, '.', '') }}
                                <span class="text-sm font-normal text-art-text-muted">/ ${{ number_format($openAiCosts['budget_usd'], 0, '.', '') }}</span>
                            </p>
                        @else
                            <p class="font-poppins text-2xl font-semibold text-art-black tabular-nums">${{ number_format($openAiCosts['month_cost_usd'], 2, '.', '') }}</p>
                        @endif
                    </div>
                </div>
                @if ($openAiCosts['budget_pct'] !== null)
                    <div class="w-full bg-art-page rounded-full h-2 mb-3">
                        <div
                            class="h-2 rounded-full transition-all {{ $openAiCosts['budget_pct'] > 80 ? 'bg-red-400' : ($openAiCosts['budget_pct'] > 50 ? 'bg-amber-400' : 'bg-art-violet-deep/70') }}"
                            style="width: {{ max($openAiCosts['budget_pct'], 0.5) }}%"
                        ></div>
                    </div>
                @endif
                @if (count($openAiCosts['daily']) > 0)
                    <div class="flex items-end gap-px h-12">
                        @php
                            $maxDaily = max(array_column($openAiCosts['daily'], 'cost_usd'));
                        @endphp
                        @foreach ($openAiCosts['daily'] as $day)
                            @php
                                $heightPct = $maxDaily > 0 ? ($day['cost_usd'] / $maxDaily) * 100 : 0;
                            @endphp
                            <div
                                class="flex-1 bg-art-violet-deep/60 rounded-t-sm min-w-[3px] hover:bg-art-violet-deep transition-colors"
                                style="height: {{ max($heightPct, 2) }}%"
                                title="{{ $day['date'] }}: ${{ number_format($day['cost_usd'], 4, '.', '') }}"
                            ></div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="font-poppins text-2xs text-art-text-muted/60">{{ $openAiCosts['daily'][0]['date'] ?? '' }}</span>
                        <span class="font-poppins text-2xs text-art-text-muted/60">{{ end($openAiCosts['daily'])['date'] ?? '' }}</span>
                    </div>
                @endif
            </div>
        @elseif (! $openAiCostsConfigured)
            <p class="font-poppins text-2xs text-art-text-muted mb-6">
                Tipp: Setze <code class="bg-art-page px-1 rounded">OPENAI_ADMIN_KEY</code> (<a href="https://platform.openai.com/settings/organization/admin-keys" target="_blank" class="text-art-violet-deep hover:underline">Admin Key erstellen</a>) für echte Kostendaten von OpenAI.
            </p>
        @endif
    @endif

    <div class="flex flex-wrap gap-4 items-center mb-4">
        <div class="flex-1 min-w-[200px] max-w-sm">
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Absender, Betreff, Modell …"
                class="w-full rounded border border-art-border px-3 py-1.5 font-poppins text-xs-plus text-art-black bg-white placeholder:text-art-text-muted/60 focus:outline-none focus:ring-2 focus:ring-art-violet-deep/40"
            />
        </div>
    </div>

    <p class="font-poppins text-2xs text-art-text-muted mb-2">
        Einträge: <strong class="text-art-black">{{ $entries->total() }}</strong>
        @if ($entries->hasPages())
            <span class="text-art-text-muted">— Seite {{ $entries->currentPage() }} von {{ $entries->lastPage() }}</span>
        @endif
    </p>

    <div class="border border-art-border rounded-lg overflow-hidden bg-white">
        <table class="w-full table-fixed text-left font-poppins text-xs-plus">
            <thead class="bg-art-page border-b border-art-border text-art-text-muted">
                <tr>
                    <th class="px-4 py-3 font-semibold w-[35%]">Nachricht</th>
                    <th class="px-4 py-3 font-semibold w-24">Modell</th>
                    <th class="px-4 py-3 font-semibold w-24 text-right">Tokens</th>
                    <th class="px-4 py-3 font-semibold w-20 text-right">Kosten</th>
                    <th class="px-4 py-3 font-semibold w-20 text-right">Dauer</th>
                    <th class="px-4 py-3 font-semibold w-16 text-right">Typ</th>
                    <th class="px-4 py-3 font-semibold w-28 text-right">Zeitpunkt</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    @php
                        $createdAt = $entry->created_at;
                        $timeLabel = $createdAt->isToday()
                            ? $createdAt->format('H:i')
                            : ($createdAt->isCurrentYear()
                                ? $createdAt->format('d.m. H:i')
                                : $createdAt->format('d.m.Y H:i'));
                    @endphp
                    <tr
                        role="button"
                        tabindex="0"
                        class="border-b border-art-border last:border-0 hover:bg-art-page/60 cursor-pointer focus-visible:outline focus-visible:outline-2 focus-visible:outline-art-violet-deep focus-visible:outline-offset-[-2px]"
                        wire:click="openDetail({{ $entry->id }})"
                        wire:keydown.enter.prevent="openDetail({{ $entry->id }})"
                        wire:keydown.space.prevent="openDetail({{ $entry->id }})"
                    >
                        <td class="px-4 py-3">
                            @if ($entry->message)
                                <div class="flex flex-col gap-0.5 min-w-0">
                                    <span class="text-sm font-semibold text-art-black truncate">{{ $entry->message->from_name ?: $entry->message->from_email }}</span>
                                    <p class="text-xs-plus truncate {{ filled($entry->message->subject) ? 'text-art-black' : 'text-art-text-muted italic' }}">{{ filled($entry->message->subject) ? $entry->message->subject : '(Kein Betreff)' }}</p>
                                    <p class="text-2xs text-art-text-muted/70">{{ $entry->message->from_email }}</p>
                                </div>
                            @else
                                <span class="text-art-text-muted italic">Nachricht gelöscht</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top">
                            <code class="text-2xs bg-art-page px-1.5 py-0.5 rounded">{{ $entry->ai_model ?? '—' }}</code>
                        </td>
                        <td class="px-4 py-3 align-top text-right text-art-text-muted tabular-nums">
                            @if ($entry->totalTokens() > 0)
                                {{ number_format($entry->totalTokens(), 0, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-right text-art-text-muted tabular-nums">
                            @php $cost = $entry->estimatedCostUsd(); @endphp
                            @if ($cost !== null)
                                ${{ number_format($cost, 4, '.', '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-right text-art-text-muted tabular-nums">
                            @if ($entry->duration_ms !== null)
                                {{ number_format($entry->duration_ms / 1000, 1, ',', '.') }}s
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-right">
                            @if ($entry->is_regeneration)
                                <span class="inline-flex px-2 py-0.5 rounded bg-amber-50 text-amber-700 text-2xs font-semibold">Regen.</span>
                            @else
                                <span class="inline-flex px-2 py-0.5 rounded bg-art-violet-bg text-art-violet-deep text-2xs font-semibold">Initial</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 align-top text-right text-art-text-muted whitespace-nowrap">
                            {{ $timeLabel }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-art-text-muted">
                            Noch keine Prompt-History vorhanden. Einträge werden automatisch bei der Draft-Generierung angelegt.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($entries->hasPages())
        <div class="mt-6">
            <x-admin.pagination :paginator="$entries" />
        </div>
    @endif

    {{-- Detail Modal --}}
    <flux:modal wire:model="detailModalOpen" :closable="false" class="w-full max-w-5xl">
        <div class="space-y-4">
            @if ($modalEntry)
                <div class="border-b border-art-border pb-4">
                    <div class="flex flex-wrap gap-3 justify-between items-start">
                        <div>
                            <h2 class="font-poppins text-lg font-semibold text-art-black">Prompt #{{ $modalEntry->id }}</h2>
                            <p class="font-poppins text-xs-plus text-art-text-muted mt-1">
                                {{ $modalEntry->created_at->format('d.m.Y H:i:s') }}
                                · {{ $modalEntry->ai_provider }} / {{ $modalEntry->ai_model }}
                                @if ($modalEntry->duration_ms !== null)
                                    · {{ number_format($modalEntry->duration_ms / 1000, 1, ',', '.') }}s
                                @endif
                                @if ($modalEntry->is_regeneration)
                                    · <span class="text-amber-700 font-semibold">Regeneration</span>
                                @endif
                            </p>
                            @if ($modalEntry->message)
                                <p class="font-poppins text-xs-plus text-art-text-muted mt-1">
                                    Nachricht: <strong class="text-art-black">{{ $modalEntry->message->from_name ?: $modalEntry->message->from_email }}</strong>
                                    — {{ $modalEntry->message->subject }}
                                </p>
                            @endif
                            @if ($modalEntry->draft)
                                <p class="font-poppins text-xs-plus text-art-text-muted mt-0.5">
                                    Entwurf:
                                    <a href="{{ route('gaze-ghostwriter.drafts.show', $modalEntry->draft) }}" wire:navigate class="text-art-violet-deep hover:underline">
                                        #{{ $modalEntry->draft->id }}
                                    </a>
                                </p>
                            @endif
                        </div>
                        <flux:button type="button" variant="outline" size="sm" wire:click="closeDetail">Schließen</flux:button>
                    </div>
                </div>

                <div class="max-h-[min(70vh,720px)] overflow-y-auto pe-1 -me-1 space-y-5">
                    {{-- Usage --}}
                    @if ($modalEntry->prompt_tokens || $modalEntry->completion_tokens)
                        <section class="bg-art-page/60 border border-art-border rounded-lg p-4">
                            <h3 class="font-poppins text-sm font-semibold text-art-black mb-3">API-Usage</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <div>
                                    <p class="font-poppins text-2xs text-art-text-muted">Prompt-Tokens</p>
                                    <p class="font-poppins text-sm font-semibold text-art-black tabular-nums">{{ number_format($modalEntry->prompt_tokens ?? 0, 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="font-poppins text-2xs text-art-text-muted">Completion-Tokens</p>
                                    <p class="font-poppins text-sm font-semibold text-art-black tabular-nums">{{ number_format($modalEntry->completion_tokens ?? 0, 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="font-poppins text-2xs text-art-text-muted">Gesamt</p>
                                    <p class="font-poppins text-sm font-semibold text-art-black tabular-nums">{{ number_format($modalEntry->totalTokens(), 0, ',', '.') }}</p>
                                </div>
                                <div>
                                    <p class="font-poppins text-2xs text-art-text-muted">Geschätzte Kosten</p>
                                    @php $modalCost = $modalEntry->estimatedCostUsd(); @endphp
                                    <p class="font-poppins text-sm font-semibold text-art-black tabular-nums">
                                        @if ($modalCost !== null)
                                            ${{ number_format($modalCost, 4, '.', '') }}
                                        @else
                                            —
                                        @endif
                                    </p>
                                </div>
                            </div>
                            @if (($modalEntry->cache_read_input_tokens ?? 0) > 0 || ($modalEntry->cache_write_input_tokens ?? 0) > 0 || ($modalEntry->reasoning_tokens ?? 0) > 0)
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mt-3 pt-3 border-t border-art-border">
                                    @if (($modalEntry->cache_read_input_tokens ?? 0) > 0)
                                        <div>
                                            <p class="font-poppins text-2xs text-art-text-muted">Cache-Read</p>
                                            <p class="font-poppins text-xs text-art-text-muted tabular-nums">{{ number_format($modalEntry->cache_read_input_tokens, 0, ',', '.') }}</p>
                                        </div>
                                    @endif
                                    @if (($modalEntry->cache_write_input_tokens ?? 0) > 0)
                                        <div>
                                            <p class="font-poppins text-2xs text-art-text-muted">Cache-Write</p>
                                            <p class="font-poppins text-xs text-art-text-muted tabular-nums">{{ number_format($modalEntry->cache_write_input_tokens, 0, ',', '.') }}</p>
                                        </div>
                                    @endif
                                    @if (($modalEntry->reasoning_tokens ?? 0) > 0)
                                        <div>
                                            <p class="font-poppins text-2xs text-art-text-muted">Reasoning</p>
                                            <p class="font-poppins text-xs text-art-text-muted tabular-nums">{{ number_format($modalEntry->reasoning_tokens, 0, ',', '.') }}</p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </section>
                    @endif

                    {{-- System Prompt --}}
                    <section>
                        <h3 class="font-poppins text-sm font-semibold text-art-black mb-2">System-Prompt</h3>
                        <div class="font-mono text-xs text-art-text-muted whitespace-pre-wrap bg-art-page border border-art-border rounded-lg p-4 max-h-64 overflow-y-auto">{{ $modalEntry->system_prompt }}</div>
                    </section>

                    {{-- User Prompt --}}
                    <section>
                        <h3 class="font-poppins text-sm font-semibold text-art-black mb-2">User-Prompt</h3>
                        <div class="font-mono text-xs text-art-text-muted whitespace-pre-wrap bg-art-page border border-art-border rounded-lg p-4 max-h-64 overflow-y-auto">{{ $modalEntry->user_prompt }}</div>
                    </section>

                    {{-- AI Response --}}
                    <section>
                        <h3 class="font-poppins text-sm font-semibold text-art-black mb-2">KI-Antwort (strukturiert)</h3>
                        @if ($modalEntry->response_structured)
                            @php
                                $response = $modalEntry->response_structured;
                            @endphp

                            @if (isset($response['draft_body']))
                                <div class="mb-3">
                                    <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-1">Entwurf-Text</h4>
                                    <div class="font-mono text-xs text-art-black whitespace-pre-wrap bg-white border border-art-border rounded-lg p-4 max-h-48 overflow-y-auto">{{ $response['draft_body'] }}</div>
                                </div>
                            @endif

                            @if (isset($response['thematische_begruendung']) || isset($response['stilistische_begruendung']))
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                    @if (isset($response['thematische_begruendung']))
                                        <div>
                                            <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-1">Thematische Begründung</h4>
                                            <div class="text-xs text-art-text-muted bg-art-page border border-art-border rounded-lg p-3">{{ $response['thematische_begruendung'] }}</div>
                                        </div>
                                    @endif
                                    @if (isset($response['stilistische_begruendung']))
                                        <div>
                                            <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-1">Stilistische Begründung</h4>
                                            <div class="text-xs text-art-text-muted bg-art-page border border-art-border rounded-lg p-3">{{ $response['stilistische_begruendung'] }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if (! empty($response['smart_action_tags']))
                                <div class="mb-3">
                                    <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-1">Smart Action Tags</h4>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($response['smart_action_tags'] as $tag)
                                            <code class="text-2xs bg-art-page px-1.5 py-0.5 rounded font-semibold">{{ $tag }}</code>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (! empty($response['mentioned_entities']))
                                <div class="mb-3">
                                    <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-1">Erkannte Entitäten</h4>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($response['mentioned_entities'] as $entity)
                                            <span class="text-2xs bg-art-page px-1.5 py-0.5 rounded">{{ $entity['type'] ?? '?' }}: {{ $entity['query'] ?? '?' }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @php
                                $snippets = $modalEntry->draft?->rationale['retrieved_snippets'] ?? [];
                            @endphp
                            @if (! empty($snippets) && is_array($snippets))
                                <div>
                                    <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-2">Ähnliche historische Snippets (RAG)</h4>
                                    <ul class="list-disc pl-5 space-y-2 text-xs text-art-text-muted">
                                        @foreach ($snippets as $snippet)
                                            <li>
                                                Chunk #{{ $snippet['chunk_id'] ?? '?' }}
                                                @if (isset($snippet['score']))
                                                    <span class="text-art-text-light">(Score {{ number_format((float) $snippet['score'], 4) }})</span>
                                                @endif
                                                — {{ \Illuminate\Support\Str::limit($snippet['excerpt'] ?? '', 200) }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @elseif (! empty($response['referenzierte_chunk_ids']))
                                <div>
                                    <h4 class="font-poppins text-xs font-semibold text-art-text-muted mb-1">Referenzierte Chunk-IDs</h4>
                                    <p class="text-xs text-art-text-muted">{{ implode(', ', $response['referenzierte_chunk_ids']) }}</p>
                                </div>
                            @endif
                        @else
                            <p class="text-xs text-art-text-muted italic">Keine strukturierte Antwort vorhanden.</p>
                        @endif
                    </section>
                </div>
            @elseif ($detailModalOpen)
                <p class="font-poppins text-sm text-art-text-muted">Dieser Eintrag ist nicht mehr verfügbar.</p>
                <flux:button type="button" variant="primary" wire:click="closeDetail">Schließen</flux:button>
            @endif
        </div>
    </flux:modal>
</div>
