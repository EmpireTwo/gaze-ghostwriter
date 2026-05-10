<div class="p-8 max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('gaze-ghostwriter.drafts.index') }}" wire:navigate class="font-poppins text-xs-plus text-art-violet-deep hover:underline">&larr; Zurück zu Entwürfen</a>
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
            <h1 class="font-poppins text-2xl font-light text-art-black mb-1">Smart Actions</h1>
            <p class="font-poppins text-sm text-art-text-light">
                Konfiguriere kontextbezogene Aktionen: Die KI erkennt Themen in Kundenmails und das System zeigt passende Navigations-Buttons im Entwurf.
                Platzhalter in Routen: <code class="text-2xs bg-art-page px-1 rounded">{customerId}</code>.
            </p>
        </div>
    </div>

    {{-- Neue Smart Action --}}
    <section class="border border-art-border rounded-lg p-5 bg-white mb-6">
        <h2 class="font-poppins text-sm font-semibold text-art-black mb-4">Neue Smart Action</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
            <div>
                <flux:input
                    wire:model="newMarker"
                    label="Marker"
                    placeholder="INVOICES"
                />
                <p class="font-poppins text-2xs text-art-text-muted mt-1.5">SCREAMING_SNAKE_CASE, z. B. INVOICES</p>
            </div>
            <flux:input
                wire:model="newLabel"
                label="Button-Label"
                placeholder="Rechnungen"
            />
            <div class="sm:col-span-2">
                <flux:input
                    wire:model="newPromptHint"
                    label="Prompt-Hinweis"
                    placeholder="Wenn es um Rechnungen, Zahlungen oder Abrechnungen geht"
                    description="Wird der KI als Erkennungs-Anweisung übergeben"
                />
            </div>
            <div class="sm:col-span-2">
                <flux:input
                    wire:model="newRouteTemplate"
                    label="Route-Template"
                    placeholder="/admin/billings/customer/{customerId}"
                    description="Platzhalter {customerId} wird automatisch ersetzt"
                />
            </div>
        </div>
        <div class="mt-4">
            <flux:button type="button" variant="primary" wire:click="addAction">Erstellen</flux:button>
        </div>
    </section>

    {{-- Bestehende Smart Actions --}}
    <section class="border border-art-border rounded-lg bg-white">
        <h2 class="font-poppins text-sm font-semibold text-art-black p-5 pb-0">Konfigurierte Smart Actions</h2>
        @if ($actions->isEmpty())
            <p class="font-poppins text-sm text-art-text-muted p-5">Noch keine Smart Actions konfiguriert.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left font-poppins text-sm">
                    <thead class="text-2xs text-art-text-muted border-b border-art-border">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Marker</th>
                            <th class="px-5 py-3 font-semibold">Label</th>
                            <th class="px-5 py-3 font-semibold">Prompt-Hinweis</th>
                            <th class="px-5 py-3 font-semibold">Route</th>
                            <th class="px-5 py-3 font-semibold">Aktiv</th>
                            <th class="px-5 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-art-border">
                        @foreach ($actions as $action)
                            @if ($editingId === $action->id)
                                <tr class="bg-art-violet-bg/20">
                                    <td colspan="6" class="px-5 py-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                                            <div>
                                                <flux:input wire:model="editMarker" label="Marker" size="sm" />
                                                <p class="font-poppins text-2xs text-art-text-muted mt-1.5">SCREAMING_SNAKE_CASE, z. B. INVOICES</p>
                                            </div>
                                            <flux:input wire:model="editLabel" label="Button-Label" size="sm" />
                                            <div class="sm:col-span-2">
                                                <flux:input wire:model="editPromptHint" label="Prompt-Hinweis" size="sm" />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <flux:input wire:model="editRouteTemplate" label="Route-Template" size="sm" />
                                            </div>
                                        </div>
                                        <div class="flex gap-2 mt-4">
                                            <flux:button type="button" variant="primary" size="sm" wire:click="saveEditing">Speichern</flux:button>
                                            <flux:button type="button" variant="ghost" size="sm" wire:click="cancelEditing">Abbrechen</flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr class="{{ $action->is_active ? '' : 'opacity-50' }}">
                                    <td class="px-5 py-3">
                                        <code class="text-2xs bg-art-page px-1.5 py-0.5 rounded font-semibold">{{ $action->marker }}</code>
                                    </td>
                                    <td class="px-5 py-3 text-art-black">{{ $action->label }}</td>
                                    <td class="px-5 py-3 text-art-text-muted text-xs-plus max-w-[200px] truncate" title="{{ $action->prompt_hint }}">{{ $action->prompt_hint }}</td>
                                    <td class="px-5 py-3">
                                        <code class="text-2xs bg-art-page px-1 rounded break-all">{{ $action->route_template }}</code>
                                    </td>
                                    <td class="px-5 py-3">
                                        <flux:switch
                                            :checked="$action->is_active"
                                            wire:click="toggleActive({{ $action->id }})"
                                        />
                                    </td>
                                    <td class="px-5 py-3">
                                        <div class="flex gap-1">
                                            <flux:button type="button" variant="ghost" size="sm" wire:click="startEditing({{ $action->id }})">Bearbeiten</flux:button>
                                            <flux:button type="button" variant="ghost" size="sm" x-on:click="$store.confirm.open('Smart Action wirklich löschen?', () => $wire.deleteAction({{ $action->id }}))">Löschen</flux:button>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Prompt-Vorschau --}}
    @php
        $previewBlock = \Empire2\GazeGhostwriter\Models\GhostwriterSmartAction::buildPromptInstructions();
    @endphp
    @if ($previewBlock !== null)
        <section class="border border-art-border rounded-lg p-5 bg-art-page/40 mt-6">
            <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">Generierter Prompt-Block (Vorschau)</h2>
            <p class="font-poppins text-2xs text-art-text-muted mb-3">Wird automatisch den KI-Anweisungen angehängt.</p>
            <div class="font-mono text-xs text-art-text-muted whitespace-pre-wrap bg-white border border-art-border rounded-lg p-4">{{ $previewBlock }}</div>
        </section>
    @endif
</div>
