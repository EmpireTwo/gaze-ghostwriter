<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Empire2\GazeGhostwriter\Enums\DraftStatus;
use Empire2\GazeGhostwriter\Models\SupportDraft;
use Illuminate\Support\Facades\Cache;

class DraftStatusCounts
{
    /**
     * @return array<string, int>
     */
    public static function get(): array
    {
        return Cache::remember('ghostwriter:draft_status_counts', 60, function (): array {
            $counts = SupportDraft::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->all();

            $result = [];
            foreach (DraftStatus::cases() as $status) {
                $result[$status->value] = (int) ($counts[$status->value] ?? 0);
            }

            return $result;
        });
    }
}
