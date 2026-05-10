<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('secret'),
        ];
    }
}
