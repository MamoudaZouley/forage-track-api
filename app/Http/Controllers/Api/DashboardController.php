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
    $criticalComponents = ['Pump', 'Solar Panel', 'Controller'];
    $mediumComponents = ['Security', 'Tank', 'Pipes'];
    $lowComponents = ['Taps'];

    // Comptage direct en SQL — beaucoup plus rapide
    $totalAlerts = \App\Models\Alert::where('resolved', false)->count();
    $criticalAlerts = \App\Models\Alert::where('resolved', false)
        ->whereIn('component', $criticalComponents)->count();
    $mediumAlerts = \App\Models\Alert::where('resolved', false)
        ->whereIn('component', $mediumComponents)->count();
    $lowAlerts = \App\Models\Alert::where('resolved', false)
        ->whereIn('component', $lowComponents)->count();

    $wellsNotWorking = Well::where('status', 'not_working')->count();
    $totalWells = Well::whereNotNull('supervisor')
                     ->where('supervisor', '!=', 'pro_m')
                     ->whereNotIn('code', ['86', '102'])
                     ->count();
    $operationalWells = $totalWells - $wellsNotWorking;

    // Visites de la semaine en cours
    $today = now();
    $day = (int) $today->format('d');
    $weekStart = $day <= 7 ? 1 : ($day <= 14 ? 8 : ($day <= 21 ? 15 : 22));
    $weekEnd = $day <= 7 ? 7 : ($day <= 14 ? 14 : ($day <= 21 ? 21 : (int) $today->format('t')));
    $weekVisits = \DB::table('supervision_history')
        ->whereYear('visit_date', $today->year)
        ->whereMonth('visit_date', $today->month)
        ->whereDay('visit_date', '>=', $weekStart)
        ->whereDay('visit_date', '<=', $weekEnd)
        ->count();

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
        'week_visits' => $weekVisits,
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