@php
    /** @var \Empire2\GazeGhostwriter\Models\SupportDraft $draft */
    $githubReady = filled(config('gaze-ghostwriter.github.repo')) && filled(config('gaze-ghostwriter.github.token'));
    $githubRepoDisplay = config('gaze-ghostwriter.github.repo') ?: '—';
    /** @var list<string> $ghAllLabels */
    $ghAllLabels = config('gaze-ghostwriter.github.labels', []);
    $ghAllLabels = is_array($ghAllLabels) ? array_values(array_filter($ghAllLabels, fn ($l): bool => is_string($l) && $l !== '')) : [];
    $ghRequiredLabel = $ghAllLabels[0] ?? null;
    $ghOptionalLabels = array_slice($ghAllLabels, 1);
@endphp

<section class="border border-art-border rounded-lg p-5 bg-white">
    <h2 class="font-poppins text-sm font-semibold text-art-black mb-2">GitHub Issue</h2>
    @if (filled($draft->github_issue_url))
        <p class="font-poppins text-xs-plus text-art-text-muted mb-2">Issue angelegt:</p>
        <a
            href="{{ $draft->github_issue_url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="font-poppins text-sm text-art-violet-deep hover:underline break-all"
        >{{ $draft->github_issue_url }}</a>
    @else
        <p class="font-poppins text-xs-plus text-art-text-muted mb-3">
            Issue im Repo <code class="text-2xs bg-art-page px-1 rounded">{{ $githubRepoDisplay }}</code> anlegen
            (<code class="text-2xs bg-art-page px-1 rounded">GITHUB_REPO</code> / <code class="text-2xs bg-art-page px-1 rounded">GITHUB_TOKEN</code>).
        </p>
        @if ($githubReady)
            <flux:button type="button" variant="outline" wire:click="openGithubIssueModal">GitHub Issue erstellen</flux:button>
        @else
            <p class="font-poppins text-xs-plus text-amber-800">
                GitHub nicht konfiguriert — setze <code class="bg-white px-1 rounded border border-art-border">GITHUB_REPO</code> und
                <code class="bg-white px-1 rounded border border-art-border">GITHUB_TOKEN</code> in <code class="bg-white px-1 rounded border border-art-border">.env</code>.
            </p>
        @endif
    @endif
</section>

<flux:modal wire:model="showGithubModal" class="w-full max-w-2xl">
    <div class="space-y-4">
        <h3 class="font-poppins text-lg font-semibold text-art-black">GitHub Issue erstellen</h3>
        <p class="font-poppins text-xs-plus text-art-text-muted">
            Ziel-Repo: <code class="bg-art-page px-1 rounded text-2xs">{{ $githubRepoDisplay }}</code>
        </p>
        @if ($ghRequiredLabel)
            <p class="font-poppins text-xs-plus text-art-text-muted">
                Label <code class="bg-art-page px-1 rounded text-2xs">{{ $ghRequiredLabel }}</code> wird immer gesetzt.
            </p>
        @endif
        @if (count($ghOptionalLabels) > 0)
            <div class="space-y-2">
                <p class="font-poppins text-xs-plus font-medium text-art-black">Weitere Labels</p>
                @foreach ($ghOptionalLabels as $optLabel)
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
