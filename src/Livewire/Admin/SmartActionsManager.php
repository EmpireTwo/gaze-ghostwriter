<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Models\GhostwriterSmartAction;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ghostwriter · Smart Actions')]
class SmartActionsManager extends Component
{
    public string $newMarker = '';

    public string $newLabel = '';

    public string $newPromptHint = '';

    public string $newRouteTemplate = '';

    public ?int $editingId = null;

    public string $editMarker = '';

    public string $editLabel = '';

    public string $editPromptHint = '';

    public string $editRouteTemplate = '';

    public function addAction(): void
    {
        $this->validate([
            'newMarker' => 'required|string|max:50|regex:/^[A-Z_]+$/|unique:ghostwriter_smart_actions,marker',
            'newLabel' => 'required|string|max:100',
            'newPromptHint' => 'required|string|max:500',
            'newRouteTemplate' => 'required|string|max:255',
        ], [
            'newMarker.regex' => 'Marker muss SCREAMING_SNAKE_CASE sein (z. B. INVOICES, SUBSCRIPTION).',
            'newMarker.unique' => 'Dieser Marker existiert bereits.',
        ]);

        GhostwriterSmartAction::query()->create([
            'marker' => $this->newMarker,
            'label' => $this->newLabel,
            'prompt_hint' => $this->newPromptHint,
            'route_template' => $this->newRouteTemplate,
            'is_active' => true,
        ]);

        $this->reset('newMarker', 'newLabel', 'newPromptHint', 'newRouteTemplate');

        $this->toast('success', 'Smart Action erstellt.');
    }

    public function startEditing(int $id): void
    {
        $action = GhostwriterSmartAction::query()->findOrFail($id);

        $this->editingId = $id;
        $this->editMarker = $action->marker;
        $this->editLabel = $action->label;
        $this->editPromptHint = $action->prompt_hint;
        $this->editRouteTemplate = $action->route_template;
    }

    public function cancelEditing(): void
    {
        $this->reset('editingId', 'editMarker', 'editLabel', 'editPromptHint', 'editRouteTemplate');
    }

    public function saveEditing(): void
    {
        $this->validate([
            'editMarker' => 'required|string|max:50|regex:/^[A-Z_]+$/|unique:ghostwriter_smart_actions,marker,'.$this->editingId,
            'editLabel' => 'required|string|max:100',
            'editPromptHint' => 'required|string|max:500',
            'editRouteTemplate' => 'required|string|max:255',
        ], [
            'editMarker.regex' => 'Marker muss SCREAMING_SNAKE_CASE sein.',
            'editMarker.unique' => 'Dieser Marker existiert bereits.',
        ]);

        $action = GhostwriterSmartAction::query()->findOrFail($this->editingId);
        $action->update([
            'marker' => $this->editMarker,
            'label' => $this->editLabel,
            'prompt_hint' => $this->editPromptHint,
            'route_template' => $this->editRouteTemplate,
        ]);

        $this->cancelEditing();

        $this->toast('success', 'Smart Action aktualisiert.');
    }

    public function toggleActive(int $id): void
    {
        $action = GhostwriterSmartAction::query()->findOrFail($id);
        $action->update(['is_active' => ! $action->is_active]);
    }

    public function deleteAction(int $id): void
    {
        GhostwriterSmartAction::query()->where('id', $id)->delete();

        if ($this->editingId === $id) {
            $this->cancelEditing();
        }

        $this->toast('success', 'Smart Action gelöscht.');
    }

    public function render(): View
    {
        return view('gaze-ghostwriter::smart-actions', [
            'actions' => GhostwriterSmartAction::query()->orderBy('marker')->get(),
        ])->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }

    private function toast(string $type, string $message, ?string $heading = 'Ghostwriter'): void
    {
        $this->dispatch('toast', type: $type, message: $message, heading: $heading);
    }
}
