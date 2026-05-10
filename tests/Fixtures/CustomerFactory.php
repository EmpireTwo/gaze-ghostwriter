<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'firstname' => fake()->firstName(),
            'lastname' => fake()->lastName(),
        ];
    }
}
