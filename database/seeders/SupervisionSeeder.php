<?php

namespace Database\Seeders;

use App\Models\Supervision;
use Illuminate\Database\Seeder;

class SupervisionSeeder extends Seeder
{
    public function run(): void
    {
        $supervisors = [
            ['name' => 'Ibrahim Maïkano', 'username' => 'ibrahim_m'],
            ['name' => 'Halilou Sounbalou', 'username' => 'halilou_s'],
            ['name' => 'Adamou Ibrahim', 'username' => 'adamou_i'],
            ['name' => 'Zouley Mamouda', 'username' => 'zouley_m'],
        ];

        $statuses = ['operational', 'operational', 'operational', 'not_working', 'suspended'];

        for ($wellId = 1; $wellId <= 20; $wellId++) {
            $nbVisits = rand(3, 8);
            for ($v = 0; $v < $nbVisits; $v++) {
                $supervisor = $supervisors[array_rand($supervisors)];
                $daysAgo = rand(1, 180);
                $date = now()->subDays($daysAgo)->format('Y-m-d');
                $status = $statuses[array_rand($statuses)];

                Supervision::create([
                    'well_id' => $wellId,
                    'supervisor_name' => $supervisor['name'],
                    'supervisor_username' => $supervisor['username'],
                    'visit_date' => $date,
                    'submission_time' => $date . ' ' . rand(7,17) . ':' . rand(10,59) . ':00',
                    'overall_status' => $status,
                    'duration_minutes' => rand(20, 90),
                    'week_number' => now()->subDays($daysAgo)->weekOfYear,
                ]);
            }
        }
    }
}