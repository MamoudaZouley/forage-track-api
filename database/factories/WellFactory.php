<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WellFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('?-###')),
            'village' => $this->faker->city(),
            'region' => $this->faker->randomElement(['Maradi', 'Zinder']),
            'department' => $this->faker->word(),
            'commune' => $this->faker->word(),
            'status' => $this->faker->randomElement(['operational', 'not_working', 'suspended']),
        ];
    }
}