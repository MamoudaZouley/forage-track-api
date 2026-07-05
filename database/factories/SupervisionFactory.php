<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SupervisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'well_id' => 1,
            'supervisor_name' => $this->faker->name(),
            'supervisor_username' => $this->faker->userName(),
            'visit_date' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'submission_time' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'overall_status' => $this->faker->randomElement(['operational', 'not_working', 'suspended']),
            'duration_minutes' => $this->faker->randomFloat(1, 20, 90),
            'week_number' => $this->faker->numberBetween(1, 52),
        ];
    }
}