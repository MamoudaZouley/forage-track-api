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
        $totalWells = Well::count();
        $operationalWells = $totalWells - $wellsNotWorking;

        return response()->json([
            'wells' => [
                'total' => $totalWells,
                'operational' => $operationalWells,
                'not_working' => $wellsNotWorking,
            ],
            'alerts' => [
                'total' => $totalAlerts,
                'critical' => $criticalAlerts,
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