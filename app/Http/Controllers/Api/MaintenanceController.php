<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Well;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $request)
{
    $query = Maintenance::with('well');

    if ($request->region) {
        $query->where('region', $request->region);
    }

    if ($request->maintenance_type) {
        $query->where('maintenance_type', $request->maintenance_type);
    }

    if ($request->final_result) {
        $query->where('final_result', $request->final_result);
    }

    if ($request->search) {
        $query->where(function($q) use ($request) {
            $q->where('well_code', 'like', "%{$request->search}%")
              ->orWhere('village', 'like', "%{$request->search}%")
              ->orWhere('technician_username', 'like', "%{$request->search}%");
        });
    }

    $maintenances = $query->orderBy('visit_date', 'desc')->paginate(15);

    // Enrichit les villages manquants depuis le puits
    $maintenances->getCollection()->transform(function($m) {
        if (empty($m->village) && $m->well) {
            $m->village = $m->well->village;
        }
        if (empty($m->region) && $m->well) {
            $m->region = $m->well->region;
        }
        return $m;
    });

    return response()->json($maintenances);
}
}