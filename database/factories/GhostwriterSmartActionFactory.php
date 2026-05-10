<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Models\GhostwriterSmartAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhostwriterSmartAction> */
class GhostwriterSmartActionFactory extends Factory
{
    protected $model = GhostwriterSmartAction::class;

    public function definition(): array
    {
        return [
            'marker' => 'TEST_'.strtoupper(fake()->unique()->word()),
            'label' => fake()->words(2, true),
            'prompt_hint' => fake()->sentence(),
            'route_template' => '/admin/test/{customerId}',
            'is_active' => true,
        ];
    }
}
