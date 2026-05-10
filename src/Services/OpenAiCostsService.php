<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiCostsService
{
    private const CACHE_KEY = 'ghostwriter:openai_costs';

    private const CACHE_TTL_SECONDS = 3600;

    public function isConfigured(): bool
    {
        return filled(config('gaze-ghostwriter.openai.admin_key'));
    }

    /**
     * @return array{month_label: string, month_cost_usd: float, budget_usd: float|null, budget_pct: float|null, daily: list<array{date: string, cost_usd: float}>}|null
     */
    public function getCurrentMonthCosts(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember(self::CACHE_KEY.':month', self::CACHE_TTL_SECONDS, function (): ?array {
            $startOfMonth = now()->startOfMonth()->timestamp;

            return $this->fetchCosts($startOfMonth, now()->format('F Y'));
        });
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY.':month');
    }

    /**
     * @return array{month_label: string, month_cost_usd: float, budget_usd: float|null, budget_pct: float|null, daily: list<array{date: string, cost_usd: float}>}|null
     */
    private function fetchCosts(int $startTime, string $label): ?array
    {
        try {
            $allData = [];
            $page = null;

            do {
                $params = [
                    'start_time' => $startTime,
                    'bucket_width' => '1d',
                    'limit' => 31,
                ];

                if ($page !== null) {
                    $params['page'] = $page;
                }

                $response = Http::withToken(config('gaze-ghostwriter.openai.admin_key'))
                    ->timeout(15)
                    ->get('https://api.openai.com/v1/organization/costs', $params);

                if (! $response->successful()) {
                    Log::warning('OpenAI Costs API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                $json = $response->json();
                $allData = array_merge($allData, $json['data'] ?? []);
                $page = $json['next_page'] ?? null;
            } while ($page !== null);

            $totalCost = 0.0;
            $daily = [];

            foreach ($allData as $bucket) {
                $date = date('Y-m-d', $bucket['start_time'] ?? 0);
                $bucketCost = 0.0;

                foreach ($bucket['results'] ?? [] as $result) {
                    $bucketCost += (float) ($result['amount']['value'] ?? 0);
                }

                $totalCost += $bucketCost;

                if ($bucketCost > 0) {
                    $daily[] = [
                        'date' => $date,
                        'cost_usd' => $bucketCost,
                    ];
                }
            }

            $budget = config('gaze-ghostwriter.openai.monthly_budget');
            $budgetUsd = is_numeric($budget) ? (float) $budget : null;

            return [
                'month_label' => $label,
                'month_cost_usd' => $totalCost,
                'budget_usd' => $budgetUsd,
                'budget_pct' => $budgetUsd !== null && $budgetUsd > 0
                    ? min(($totalCost / $budgetUsd) * 100, 100)
                    : null,
                'daily' => $daily,
            ];
        } catch (Throwable $e) {
            Log::warning('OpenAI Costs API call failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
