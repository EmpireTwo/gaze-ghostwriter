<div class="p-8 max-w-3xl">
    <div class="mb-6">
        <a href="{{ route('gaze-ghostwriter.drafts.index') }}" wire:navigate class="font-poppins text-xs-plus text-art-violet-deep hover:underline">&larr; Zurück zu Entwürfen</a>
    </div>

    <h1 class="font-poppins text-2xl font-light text-art-black mb-1">Ghostwriter 3000 · Prompt-Editor</h1>
    <p class="font-poppins text-sm text-art-text-light mb-8">
        Der <strong class="font-medium text-art-black">Core-Prompt</strong> ist fest und wird durch Code und Tests geschützt.
        Darunter können <strong class="font-medium text-art-black">einzelne Zusatzregeln</strong> ergänzt werden — global für alle oder persönlich für dich.
        Jede Regel wird dem KI-Modell als eigene, nummerierte Pflichtanweisung übergeben.
    </p>

    {{-- Core-Prompt --}}
    <section class="border border-art-border rounded-lg p-5 bg-art-page/40 mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">Core-Prompt (nicht änderbar)</h2>
        <p class="font-poppins text-2xs text-art-text-muted mb-3">
            Dieser Text wird dem KI-Modell als System-Instruction mitgegeben. Änderungen nur über Code-Deployment.
        </p>
        <div class="font-mono text-sm text-art-black whitespace-pre-wrap bg-white border border-art-border rounded-lg p-4 select-all max-h-64 overflow-y-auto">{{ $corePrompt }}</div>
    </section>

    {{-- Globale Zusatzregeln --}}
    <section class="border border-art-border rounded-lg p-5 bg-white mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-1">Globale Zusatzregeln</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-4">
            Gelten für <strong class="font-medium text-art-black">alle Nutzer und den automatischen Scheduler</strong>.
            Jede Regel wird als eigene Pflichtanweisung übergeben.
        </p>

        <div class="space-y-4">
            @forelse ($globalPrompts as $index => $prompt)
                <div class="border border-art-border rounded-lg p-4 bg-art-page/30" wire:key="global-{{ $prompt['id'] ?? 'new-'.$index }}">
                    <div class="flex items-start gap-3">
                        <div class="flex flex-col gap-1 pt-1">
                            <button
                                type="button"
                                wire:click="moveGlobalPrompt({{ $index }}, 'up')"
                                @class(['text-art-text-muted hover:text-art-black transition-colors', 'opacity-30 pointer-events-none' => $index === 0])
                            >
                                <flux:icon.chevron-up class="size-4" />
                            </button>
                            <button
                                type="button"
                                wire:click="moveGlobalPrompt({{ $index }}, 'down')"
                                @class(['text-art-text-muted hover:text-art-black transition-colors', 'opacity-30 pointer-events-none' => $index === count($globalPrompts) - 1])
                            >
                                <flux:icon.chevron-down class="size-4" />
                            </button>
                        </div>

                        <div class="flex-1 space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center size-6 rounded-full bg-art-violet-deep/10 text-art-violet-deep text-xs font-semibold shrink-0">{{ $index + 1 }}</span>
                                <flux:input
                                    wire:model="globalPrompts.{{ $index }}.label"
                                    placeholder="Bezeichnung (optional)"
                                    size="sm"
                                    class="flex-1"
                                />
                            </div>
                            <flux:textarea
                                wire:model="globalPrompts.{{ $index }}.body"
                                rows="3"
                                placeholder="Regel-Text eingeben…"
                            />
                            <flux:error name="globalPrompts.{{ $index }}.body" />
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="primary" wire:click="saveGlobalPrompt({{ $index }})">Speichern</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="removeGlobalPrompt({{ $index }})" wire:confirm="Diese Regel wirklich löschen?">
                                    <flux:icon.trash class="size-4 text-red-500" />
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="font-poppins text-sm text-art-text-muted italic">Keine globalen Regeln vorhanden.</p>
            @endforelse
        </div>

        <div class="mt-4">
            <flux:button size="sm" variant="subtle" icon="plus" wire:click="addGlobalPrompt">Neue globale Regel</flux:button>
        </div>
    </section>

    {{-- Persönliche Zusatzregeln --}}
    <section class="border border-art-border rounded-lg p-5 bg-white mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-1">Persönliche Zusatzregeln</h2>
        <p class="font-poppins text-xs-plus text-art-text-muted mb-4">
            Gelten nur für <strong class="font-medium text-art-black">deine</strong> manuellen Regenerierungen, nicht für den automatischen Scheduler.
        </p>

        <div class="space-y-4">
            @forelse ($userPrompts as $index => $prompt)
                <div class="border border-art-border rounded-lg p-4 bg-art-page/30" wire:key="user-{{ $prompt['id'] ?? 'new-'.$index }}">
                    <div class="flex items-start gap-3">
                        <div class="flex flex-col gap-1 pt-1">
                            <button
                                type="button"
                                wire:click="moveUserPrompt({{ $index }}, 'up')"
                                @class(['text-art-text-muted hover:text-art-black transition-colors', 'opacity-30 pointer-events-none' => $index === 0])
                            >
                                <flux:icon.chevron-up class="size-4" />
                            </button>
                            <button
                                type="button"
                                wire:click="moveUserPrompt({{ $index }}, 'down')"
                                @class(['text-art-text-muted hover:text-art-black transition-colors', 'opacity-30 pointer-events-none' => $index === count($userPrompts) - 1])
                            >
                                <flux:icon.chevron-down class="size-4" />
                            </button>
                        </div>

                        <div class="flex-1 space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center size-6 rounded-full bg-blue-100 text-blue-700 text-xs font-semibold shrink-0">{{ $index + 1 }}</span>
                                <flux:input
                                    wire:model="userPrompts.{{ $index }}.label"
                                    placeholder="Bezeichnung (optional)"
                                    size="sm"
                                    class="flex-1"
                                />
                            </div>
                            <flux:textarea
                                wire:model="userPrompts.{{ $index }}.body"
                                rows="3"
                                placeholder="Regel-Text eingeben…"
                            />
                            <flux:error name="userPrompts.{{ $index }}.body" />
                            <div class="flex items-center gap-2">
                                <flux:button size="sm" variant="primary" wire:click="saveUserPrompt({{ $index }})">Speichern</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="removeUserPrompt({{ $index }})" wire:confirm="Diese Regel wirklich löschen?">
                                    <flux:icon.trash class="size-4 text-red-500" />
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <p class="font-poppins text-sm text-art-text-muted italic">Keine persönlichen Regeln vorhanden.</p>
            @endforelse
        </div>

        <div class="mt-4">
            <flux:button size="sm" variant="subtle" icon="plus" wire:click="addUserPrompt">Neue persönliche Regel</flux:button>
        </div>
    </section>

    {{-- Vorschau --}}
    <section class="border border-amber-200 rounded-lg p-5 bg-amber-50/60">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">Vorschau: Zusammengesetzter Prompt</h2>
        <p class="font-poppins text-2xs text-art-text-muted mb-3">So sieht der vollständige System-Prompt aus, den das KI-Modell erhält (Core + nummerierte Zusatzregeln).</p>
        <div class="font-mono text-sm text-art-black whitespace-pre-wrap bg-white border border-amber-200 rounded-lg p-4 max-h-96 overflow-y-auto">{{ $previewPrompt }}</div>
        <p class="font-poppins text-2xs text-amber-800 mt-3">
            Zusatzregeln ergänzen Stil und inhaltliche Details. Die Antwortsprache richtet sich nach der Kunden-Mail (Core-Prompt); Zusatzregeln dürfen diese Sprache nicht verdrängen. Bei Widersprüchen gilt der Core-Prompt.
        </p>
    </section>
</div>
