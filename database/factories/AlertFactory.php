<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    public function definition(): array
    {
        $severity = $this->faker->randomElement(['CRITICAL', 'HIGH', 'MEDIUM', 'LOW']);
        $priorityMap = ['CRITICAL' => 4, 'HIGH' => 24, 'MEDIUM' => 72, 'LOW' => 168];

        return [
            'supervision_id' => 1,
            'well_id' => 1,
            'village' => $this->faker->city(),
            'component' => $this->faker->randomElement(['Solar Panel', 'Pump', 'Taps', 'Pipes', 'Security']),
            'issue' => $this->faker->sentence(4),
            'severity' => $severity,
            'priority_hours' => $priorityMap[$severity],
            'resolved' => false,
            'resolved_at' => null,
        ];
    }
}