<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Livewire\Admin;

use Empire2\GazeGhostwriter\Models\GhostwriterPromptHistory;
use Empire2\GazeGhostwriter\Services\OpenAiCostsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Ghostwriter · Prompt-History')]
class PromptHistory extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $detailModalOpen = false;

    public ?int $modalEntryId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openDetail(int $id): void
    {
        $this->modalEntryId = $id;
        $this->detailModalOpen = true;
    }

    public function closeDetail(): void
    {
        $this->detailModalOpen = false;
        $this->modalEntryId = null;
    }

    public function updatedDetailModalOpen(bool $value): void
    {
        if (! $value) {
            $this->modalEntryId = null;
        }
    }

    public function render(): View
    {
        $query = GhostwriterPromptHistory::query()
            ->with('message', 'draft')
            ->orderByDesc('created_at');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->whereHas('message', function ($mq) use ($term): void {
                    $mq->where('from_email', 'like', $term)
                        ->orWhere('from_name', 'like', $term)
                        ->orWhere('subject', 'like', $term);
                })
                    ->orWhere('ai_model', 'like', $term)
                    ->orWhere('user_prompt', 'like', $term);
            });
        }

        $entries = $query->paginate(20);

        $modalEntry = null;
        if ($this->detailModalOpen && $this->modalEntryId !== null) {
            $modalEntry = GhostwriterPromptHistory::query()
                ->with('message', 'draft')
                ->find($this->modalEntryId);
        }

        $stats = GhostwriterPromptHistory::query()
            ->select([
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN is_regeneration = 0 THEN 1 ELSE 0 END) as initial_calls'),
                DB::raw('SUM(CASE WHEN is_regeneration = 1 THEN 1 ELSE 0 END) as regeneration_calls'),
                DB::raw('COALESCE(SUM(prompt_tokens), 0) as total_prompt_tokens'),
                DB::raw('COALESCE(SUM(completion_tokens), 0) as total_completion_tokens'),
                DB::raw('COALESCE(SUM(COALESCE(prompt_tokens, 0) + COALESCE(completion_tokens, 0)), 0) as total_tokens'),
                DB::raw('COALESCE(AVG(duration_ms), 0) as avg_duration_ms'),
            ])
            ->first();

        $allForCost = GhostwriterPromptHistory::query()
            ->whereNotNull('prompt_tokens')
            ->get(['ai_model', 'prompt_tokens', 'completion_tokens', 'cache_read_input_tokens']);

        $totalCost = $allForCost->sum(fn (GhostwriterPromptHistory $entry): float => $entry->estimatedCostUsd() ?? 0);

        $costsService = app(OpenAiCostsService::class);
        $openAiCosts = $costsService->isConfigured() ? $costsService->getCurrentMonthCosts() : null;

        return view('gaze-ghostwriter::prompt-history', [
            'entries' => $entries,
            'modalEntry' => $modalEntry,
            'stats' => $stats,
            'totalCost' => $totalCost,
            'openAiCosts' => $openAiCosts,
            'openAiCostsConfigured' => $costsService->isConfigured(),
        ])->layout(config('gaze-ghostwriter.layout', 'components.layouts.app'));
    }
}
