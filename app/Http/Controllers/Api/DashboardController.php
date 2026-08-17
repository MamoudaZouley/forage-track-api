<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Maintenance;
use App\Models\Supervision;
use App\Models\Well;

class DashboardController extends Controller
{
        public function index()
    {
        $supervisions = Supervision::with('alerts')->get();

        $totalAlerts = 0;
        $criticalAlerts = 0;
        $mediumAlerts = 0;
        $lowAlerts = 0;

        $criticalComponents = ['Pump', 'Solar Panel', 'Controller'];
        $mediumComponents = ['Security', 'Tank', 'Pipes'];
        $lowComponents = ['Taps'];

        foreach ($supervisions as $supervision) {
            $alerts = $supervision->alerts;
            $totalAlerts += $alerts->count();
            foreach ($alerts as $alert) {
                if (in_array($alert->component, $criticalComponents)) $criticalAlerts++;
                elseif (in_array($alert->component, $mediumComponents)) $mediumAlerts++;
                elseif (in_array($alert->component, $lowComponents)) $lowAlerts++;
            }
        }

        $wellsNotWorking = Well::where('status', 'not_working')->count();
       // Compte les puits depuis wells_merged_utf8.csv (source de vérité)
        $totalWells = 0;
        $csvPath = base_path('wells_merged_utf8.csv');
        if (file_exists($csvPath)) {
            $f = fopen($csvPath, 'r');
            $header = array_map('trim', fgetcsv($f, 0, ',', '"', ''));
            $idx = array_flip($header);
            while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
                $row = array_map('trim', $row);
                $code = $row[$idx['name']] ?? null;
                $supervisor = $row[$idx['supervisor']] ?? null;
                if ($code && $supervisor && $supervisor !== 'pro_m' && !in_array($code, ['86', '102', '163'])) {
                    $totalWells++;
                }
            }
            fclose($f);
        }
        $wellsNotWorking = Well::where('status', 'not_working')->count();
        $operationalWells = $totalWells - $wellsNotWorking;
        $operationalWells = $totalWells - $wellsNotWorking;

        return response()->json([
            'wells' => [
                'total' => $totalWells,
                'operational' => $operationalWells,
                'not_working' => $wellsNotWorking,
            ],
            'alerts' => [
                'total' => $totalAlerts,
               'critical_alerts' => Alert::with('well')
                ->whereIn('component', $criticalComponents)
                ->where('resolved', false)
                ->orderBy('created_at', 'asc') // Les plus anciennes en premier
                ->limit(5)
                ->get()
                ->map(function($alert) {
                    $alert->days_open = now()->diffInDays($alert->created_at);
                    return $alert;
                }),
                'medium' => $mediumAlerts,
                'low' => $lowAlerts,
            ],
            'maintenances' => [
                'total' => Maintenance::count(),
                'emergency' => Maintenance::where('maintenance_type', 'emergency')->count(),
                'fully_working' => Maintenance::where('final_result', 'fully_working')->count(),
                'recent' => Maintenance::with('well')->orderBy('visit_date', 'desc')->limit(5)->get(),
            ],
            'recent_supervisions' => Supervision::with('well')
                ->orderBy('visit_date', 'desc')
                ->limit(5)
                ->get(),
            'critical_alerts' => Alert::with('well')
                ->whereIn('component', $criticalComponents)
                ->where('resolved', false)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ]);
    }
}