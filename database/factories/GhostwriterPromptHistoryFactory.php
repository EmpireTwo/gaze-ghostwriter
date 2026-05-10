<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Models\GhostwriterPromptHistory;
use Empire2\GazeGhostwriter\Models\SupportMailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhostwriterPromptHistory> */
class GhostwriterPromptHistoryFactory extends Factory
{
    protected $model = GhostwriterPromptHistory::class;

    public function definition(): array
    {
        return [
            'support_mail_message_id' => SupportMailMessage::factory(),
            'support_draft_id' => null,
            'system_prompt' => fake()->paragraph(),
            'user_prompt' => fake()->paragraph(),
            'response_structured' => ['draft_body' => fake()->sentence()],
            'ai_model' => 'gpt-4o-mini',
            'ai_provider' => 'openai',
            'duration_ms' => fake()->numberBetween(100, 5000),
            'is_regeneration' => false,
        ];
    }
}
