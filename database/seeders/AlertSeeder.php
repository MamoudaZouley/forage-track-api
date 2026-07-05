<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Supervision;
use Illuminate\Database\Seeder;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        $components = ['Solar Panel', 'Pump', 'Water Tank', 'Pipes', 'Taps', 'Security', 'Structure'];
        $issues = [
            'Solar Panel' => ['Panel cracked', 'Panel dirty', 'Panel disconnected'],
            'Pump' => ['Pump not working', 'Pump making noise', 'Pump leaking'],
            'Water Tank' => ['Tank leaking', 'Tank dirty', 'Tank overflow'],
            'Pipes' => ['Pipe broken', 'Pipe leaking', 'Pipe blocked'],
            'Taps' => ['Tap leaking', 'Tap broken', 'No water flow'],
            'Security' => ['Fence damaged', 'Lock broken', 'Unauthorized access'],
            'Structure' => ['Foundation cracked', 'Shelter damaged', 'Sign missing'],
        ];
        $severities = ['CRITICAL', 'CRITICAL', 'HIGH', 'HIGH', 'MEDIUM', 'MEDIUM', 'LOW'];
        $priorityMap = [
            'CRITICAL' => 4,
            'HIGH' => 24,
            'MEDIUM' => 72,
            'LOW' => 168,
        ];

        $supervisions = Supervision::where('overall_status', '!=', 'operational')->get();

        foreach ($supervisions as $supervision) {
            $nbAlerts = rand(1, 3);
            for ($i = 0; $i < $nbAlerts; $i++) {
                $component = $components[array_rand($components)];
                $severity = $severities[array_rand($severities)];
                $resolved = rand(0, 1);

                Alert::create([
                    'supervision_id' => $supervision->id,
                    'well_id' => $supervision->well_id,
                    'village' => $supervision->well->village,
                    'component' => $component,
                    'issue' => $issues[$component][array_rand($issues[$component])],
                    'severity' => $severity,
                    'priority_hours' => $priorityMap[$severity],
                    'resolved' => $resolved,
                    'resolved_at' => $resolved ? $supervision->visit_date . ' 14:00:00' : null,
                ]);
            }
        }
    }
}