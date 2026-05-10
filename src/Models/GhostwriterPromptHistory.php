<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $support_mail_message_id
 * @property int|null $support_draft_id
 * @property string $system_prompt
 * @property string $user_prompt
 * @property array<string, mixed>|null $response_structured
 * @property string|null $ai_model
 * @property string|null $ai_provider
 * @property int|null $duration_ms
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $cache_read_input_tokens
 * @property int|null $cache_write_input_tokens
 * @property int|null $reasoning_tokens
 * @property bool $is_regeneration
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 */
class GhostwriterPromptHistory extends Model
{
    protected $table = 'ghostwriter_prompt_history';

    protected $fillable = [
        'support_mail_message_id',
        'support_draft_id',
        'system_prompt',
        'user_prompt',
        'response_structured',
        'ai_model',
        'ai_provider',
        'duration_ms',
        'prompt_tokens',
        'completion_tokens',
        'cache_read_input_tokens',
        'cache_write_input_tokens',
        'reasoning_tokens',
        'is_regeneration',
    ];

    protected function casts(): array
    {
        return [
            'response_structured' => 'array',
            'is_regeneration' => 'boolean',
            'duration_ms' => 'integer',
        ];
    }

    public function totalTokens(): int
    {
        return ($this->prompt_tokens ?? 0) + ($this->completion_tokens ?? 0);
    }

    /**
     * Estimated cost in USD based on known model pricing (per 1M tokens).
     *
     * @var array<string, array{input: float, output: float, cached_input: float}>
     */
    private const MODEL_PRICING = [
        'gpt-4o' => ['input' => 2.50, 'output' => 10.00, 'cached_input' => 1.25],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60, 'cached_input' => 0.075],
        'gpt-4.1' => ['input' => 2.00, 'output' => 8.00, 'cached_input' => 0.50],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60, 'cached_input' => 0.10],
        'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40, 'cached_input' => 0.025],
        'o3-mini' => ['input' => 1.10, 'output' => 4.40, 'cached_input' => 0.55],
    ];

    public function estimatedCostUsd(): ?float
    {
        $model = $this->ai_model;
        if ($model === null || ! isset(self::MODEL_PRICING[$model])) {
            return null;
        }

        $pricing = self::MODEL_PRICING[$model];
        $inputTokens = ($this->prompt_tokens ?? 0) - ($this->cache_read_input_tokens ?? 0);
        $cachedTokens = $this->cache_read_input_tokens ?? 0;
        $outputTokens = $this->completion_tokens ?? 0;

        return ($inputTokens * $pricing['input'] / 1_000_000)
            + ($cachedTokens * $pricing['cached_input'] / 1_000_000)
            + ($outputTokens * $pricing['output'] / 1_000_000);
    }

    /**
     * @return BelongsTo<SupportMailMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMailMessage::class, 'support_mail_message_id');
    }

    /**
     * @return BelongsTo<SupportDraft, $this>
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(SupportDraft::class, 'support_draft_id');
    }
}
