<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Models\SupportDraft;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ghostwriter · Gaze Pipeline Log')]
final class GazeLog extends Component
{
    use WithPagination;

    #[Url(as: 'expand', except: null)]
    public ?int $expandedDraftId = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public function toggleExpand(int $draftId): void
    {
        $this->expandedDraftId = $this->expandedDraftId === $draftId ? null : $draftId;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function gazeEnabled(): bool
    {
        return (bool) config('gaze-ghostwriter.gaze_enabled');
    }

    #[Computed]
    public function drafts(): LengthAwarePaginator
    {
        return SupportDraft::query()
            ->with('message')
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->whereNotNull('gaze_ran_at')
            ->orderByDesc('gaze_ran_at')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('gaze-ghostwriter::gaze-log')
            ->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }
}
