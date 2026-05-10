@php
    /** @var \Empire2\GazeGhostwriter\Models\SupportDraft $draft */
    $githubRepoDisplay = config('gaze-ghostwriter.github.repo') ?: '—';
    $ghAllLabelsModal = config('gaze-ghostwriter.github.labels', []);
    $ghAllLabelsModal = is_array($ghAllLabelsModal) ? array_values(array_filter($ghAllLabelsModal, fn ($l): bool => is_string($l) && $l !== '')) : [];
    $ghRequiredLabelModal = $ghAllLabelsModal[0] ?? null;
    $ghOptionalLabelsModal = array_slice($ghAllLabelsModal, 1);
@endphp

<flux:modal wire:model="showGithubModal" class="w-full max-w-2xl">
    <div class="space-y-4">
        <h3 class="font-poppins text-lg font-semibold text-art-black">GitHub Issue erstellen</h3>
        <p class="font-poppins text-xs-plus text-art-text-muted">
            Ziel-Repo: <code class="bg-art-page px-1 rounded text-2xs">{{ $githubRepoDisplay }}</code>
        </p>
        @if ($ghRequiredLabelModal)
            <p class="font-poppins text-xs-plus text-art-text-muted">
                Label <code class="bg-art-page px-1 rounded text-2xs">{{ $ghRequiredLabelModal }}</code> wird immer gesetzt.
            </p>
        @endif
        @if (count($ghOptionalLabelsModal) > 0)
            <div class="space-y-2">
                <p class="font-poppins text-xs-plus font-medium text-art-black">Weitere Labels</p>
                @foreach ($ghOptionalLabelsModal as $optLabel)
                    <label class="flex items-center gap-2 cursor-pointer font-poppins text-sm text-art-text-muted">
                        <input
                            type="checkbox"
                            wire:model.live="githubIssueExtraLabels"
                            value="{{ $optLabel }}"
                            class="rounded border-art-border text-art-violet-deep focus:ring-art-violet-deep"
                        />
                        <span><code class="text-2xs bg-art-page px-1 rounded">{{ $optLabel }}</code></span>
                    </label>
                @endforeach
            </div>
        @endif
        <flux:input wire:model="githubIssueTitle" label="Titel" />
        <flux:checkbox
            wire:model.live="includeReplyInGithubIssue"
            label="Antwort (Ghostwriter) in die Beschreibung aufnehmen"
        />
        <p class="font-poppins text-2xs text-art-text-muted -mt-2">
            Nutzt den aktuellen Antworttext aus dem Entwurf (inkl. Bearbeitung im Textfeld, sofern vorhanden). Beim An- oder Abwählen wird die Beschreibung neu aus Mail und optional Antwort zusammengesetzt — bestehende manuelle Änderungen in der Box gehen dabei verloren.
        </p>
        <flux:textarea
            wire:model="githubIssueBody"
            label="Beschreibung"
            rows="12"
            class="font-mono text-sm"
        />
        <div class="flex flex-wrap gap-2">
            <flux:button type="button" variant="primary" wire:click="createGithubIssue">Issue erstellen</flux:button>
            <flux:button type="button" variant="ghost" wire:click="closeGithubIssueModal">Abbrechen</flux:button>
        </div>
    </div>
</flux:modal>
