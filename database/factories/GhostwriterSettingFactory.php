<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Models\GhostwriterSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhostwriterSetting> */
class GhostwriterSettingFactory extends Factory
{
    protected $model = GhostwriterSetting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(),
            'value' => fake()->sentence(),
        ];
    }
}
