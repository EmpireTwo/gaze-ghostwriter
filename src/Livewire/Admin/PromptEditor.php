<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Enums\AdditionalPromptScope;
use Empire2\GazeGhostwriter\Models\GhostwriterAdditionalPrompt;
use Empire2\GazeGhostwriter\Prompts\PromptResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ghostwriter · Prompt-Editor')]
class PromptEditor extends Component
{
    /** @var array<int, array{id: int|null, label: string, body: string}> */
    public array $globalPrompts = [];

    /** @var array<int, array{id: int|null, label: string, body: string}> */
    public array $userPrompts = [];

    public function mount(): void
    {
        $this->loadGlobalPrompts();
        $this->loadUserPrompts();
    }

    public function addGlobalPrompt(): void
    {
        $this->globalPrompts[] = ['id' => null, 'label' => '', 'body' => ''];
    }

    public function addUserPrompt(): void
    {
        $this->userPrompts[] = ['id' => null, 'label' => '', 'body' => ''];
    }

    public function saveGlobalPrompt(int $index): void
    {
        $this->validate([
            "globalPrompts.{$index}.body" => ['required', 'string', 'max:5000'],
            "globalPrompts.{$index}.label" => ['nullable', 'string', 'max:150'],
        ]);

        $data = $this->globalPrompts[$index];
        $this->persistPrompt($data, AdditionalPromptScope::GLOBAL, null, $index);

        $this->toast('success', 'Globale Regel gespeichert.', 'Prompt-Editor');
    }

    public function saveUserPrompt(int $index): void
    {
        $this->validate([
            "userPrompts.{$index}.body" => ['required', 'string', 'max:5000'],
            "userPrompts.{$index}.label" => ['nullable', 'string', 'max:150'],
        ]);

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $data = $this->userPrompts[$index];
        $this->persistPrompt($data, AdditionalPromptScope::USER, (int) $user->getAuthIdentifier(), $index);

        $this->toast('success', 'Persönliche Regel gespeichert.', 'Prompt-Editor');
    }

    public function removeGlobalPrompt(int $index): void
    {
        $data = $this->globalPrompts[$index] ?? null;
        if ($data === null) {
            return;
        }

        if ($data['id'] !== null) {
            GhostwriterAdditionalPrompt::query()->where('id', $data['id'])->delete();
        }

        unset($this->globalPrompts[$index]);
        $this->globalPrompts = array_values($this->globalPrompts);

        $this->toast('success', 'Globale Regel entfernt.', 'Prompt-Editor');
    }

    public function removeUserPrompt(int $index): void
    {
        $data = $this->userPrompts[$index] ?? null;
        if ($data === null) {
            return;
        }

        if ($data['id'] !== null) {
            GhostwriterAdditionalPrompt::query()->where('id', $data['id'])->delete();
        }

        unset($this->userPrompts[$index]);
        $this->userPrompts = array_values($this->userPrompts);

        $this->toast('success', 'Persönliche Regel entfernt.', 'Prompt-Editor');
    }

    public function moveGlobalPrompt(int $index, string $direction): void
    {
        $this->globalPrompts = $this->moveItem($this->globalPrompts, $index, $direction);
        $this->reorderScope(AdditionalPromptScope::GLOBAL, $this->globalPrompts);
    }

    public function moveUserPrompt(int $index, string $direction): void
    {
        $this->userPrompts = $this->moveItem($this->userPrompts, $index, $direction);
        $this->reorderScope(AdditionalPromptScope::USER, $this->userPrompts);
    }

    public function render(): View
    {
        $resolver = new PromptResolver;
        $locale = config('gaze-ghostwriter.locale', 'de');
        $localeLabel = $locale === 'de' ? 'Deutsch' : (string) $locale;

        $corePrompt = $resolver->resolve('draft-system', [
            'localeLabel' => $localeLabel,
        ]);

        $previewPrompt = $this->buildPreview($corePrompt);

        return view('gaze-ghostwriter::prompt-editor', [
            'corePrompt' => $corePrompt,
            'previewPrompt' => $previewPrompt,
        ])->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }

    private function loadGlobalPrompts(): void
    {
        $this->globalPrompts = GhostwriterAdditionalPrompt::globalPrompts()
            ->map(fn (GhostwriterAdditionalPrompt $p): array => [
                'id' => $p->id,
                'label' => $p->label ?? '',
                'body' => $p->body,
            ])
            ->values()
            ->all();
    }

    private function loadUserPrompts(): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->userPrompts = [];

            return;
        }

        $this->userPrompts = GhostwriterAdditionalPrompt::forUser((int) $user->getAuthIdentifier())
            ->map(fn (GhostwriterAdditionalPrompt $p): array => [
                'id' => $p->id,
                'label' => $p->label ?? '',
                'body' => $p->body,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{id: int|null, label: string, body: string}  $data
     */
    private function persistPrompt(array $data, AdditionalPromptScope $scope, ?int $userId, int $position): void
    {
        $attributes = [
            'scope' => $scope,
            'user_id' => $userId,
            'label' => trim($data['label']) !== '' ? trim($data['label']) : null,
            'body' => trim($data['body']),
            'position' => $position,
        ];

        if ($data['id'] !== null) {
            GhostwriterAdditionalPrompt::query()
                ->where('id', $data['id'])
                ->update($attributes);
        } else {
            $record = GhostwriterAdditionalPrompt::query()->create($attributes);

            if ($scope === AdditionalPromptScope::GLOBAL) {
                $this->globalPrompts[$position]['id'] = $record->id;
            } else {
                $this->userPrompts[$position]['id'] = $record->id;
            }
        }
    }

    /**
     * @param  array<int, array{id: int|null, label: string, body: string}>  $items
     * @return array<int, array{id: int|null, label: string, body: string}>
     */
    private function moveItem(array $items, int $index, string $direction): array
    {
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if (! isset($items[$swapIndex])) {
            return $items;
        }

        [$items[$index], $items[$swapIndex]] = [$items[$swapIndex], $items[$index]];

        return array_values($items);
    }

    /**
     * @param  array<int, array{id: int|null, label: string, body: string}>  $items
     */
    private function reorderScope(AdditionalPromptScope $scope, array $items): void
    {
        foreach ($items as $position => $item) {
            if ($item['id'] !== null) {
                GhostwriterAdditionalPrompt::query()
                    ->where('id', $item['id'])
                    ->update(['position' => $position]);
            }
        }
    }

    private function buildPreview(string $corePrompt): string
    {
        $allBodies = collect($this->globalPrompts)
            ->merge($this->userPrompts)
            ->pluck('body')
            ->filter(fn (string $b): bool => trim($b) !== '')
            ->values();

        if ($allBodies->isEmpty()) {
            return $corePrompt;
        }

        $ruleBlocks = $allBodies->map(function (string $body, int $index): string {
            $num = $index + 1;

            return "VERBINDLICHE ZUSATZREGEL #{$num} (STRICT — Nichtbeachtung ist ein Fehler):\n{$body}";
        });

        $checklistLines = $allBodies->map(function (string $body, int $index): string {
            $num = $index + 1;
            $summary = Str::limit(str_replace("\n", ' ', $body), 100, '…');

            return '☐ Regel #'.$num.': '.$summary.' — Wurde diese Anweisung im Entwurf umgesetzt?';
        });

        $ruleBlocks->push(
            "ABSCHLIESSENDE PFLICHTPRÜFUNG — Gehe JEDE Regel einzeln durch:\n"
            .$checklistLines->implode("\n")
            ."\nFalls eine Prüfung mit NEIN beantwortet wird: Entwurf überarbeiten bis alle Regeln erfüllt sind."
        );

        return $corePrompt."\n\nZusätzliche Anweisungen (STRICT — vollständig einhalten):\n".$ruleBlocks->implode("\n\n");
    }

    private function toast(string $type, string $message, ?string $heading = null): void
    {
        $this->dispatch('toast', type: $type, message: $message, heading: $heading);
    }
}
