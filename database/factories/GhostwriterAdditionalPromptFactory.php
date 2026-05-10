<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Enums\AdditionalPromptScope;
use Empire2\GazeGhostwriter\Models\GhostwriterAdditionalPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhostwriterAdditionalPrompt> */
class GhostwriterAdditionalPromptFactory extends Factory
{
    protected $model = GhostwriterAdditionalPrompt::class;

    public function definition(): array
    {
        return [
            'scope' => AdditionalPromptScope::GLOBAL,
            'user_id' => null,
            'label' => fake()->words(3, true),
            'body' => fake()->paragraph(),
            'position' => 0,
        ];
    }
}
