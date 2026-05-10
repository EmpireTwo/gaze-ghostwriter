<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Database\Factories;

use Empire2\GazeGhostwriter\Models\GhostwriterUserData;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GhostwriterUserData> */
class GhostwriterUserDataFactory extends Factory
{
    protected $model = GhostwriterUserData::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'signing_name' => fake()->firstName(),
            'reply_signature' => null,
            'reply_signature_html' => null,
        ];
    }
}
