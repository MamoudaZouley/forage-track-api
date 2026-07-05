<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Supervision;
use App\Models\Well;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'wells' => [
                'total' => Well::count(),
                'operational' => Well::where('status', 'operational')->count(),
                'not_working' => Well::where('status', 'not_working')->count(),
                'suspended' => Well::where('status', 'suspended')->count(),
            ],
            'alerts' => [
                'total_unresolved' => Alert::where('resolved', false)->count(),
                'critical' => Alert::where('severity', 'CRITICAL')->where('resolved', false)->count(),
                'high' => Alert::where('severity', 'HIGH')->where('resolved', false)->count(),
                'medium' => Alert::where('severity', 'MEDIUM')->where('resolved', false)->count(),
            ],
            'recent_supervisions' => Supervision::with('well')
                ->orderBy('visit_date', 'desc')
                ->limit(5)
                ->get(),
            'critical_alerts' => Alert::with('well')
                ->where('severity', 'CRITICAL')
                ->where('resolved', false)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ]);
    }
}