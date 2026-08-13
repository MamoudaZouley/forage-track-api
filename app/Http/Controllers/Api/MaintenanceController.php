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
        public function stats()
    {
        $maintenances = \App\Models\Maintenance::with('well')->get();

        // Par type
        $byType = $maintenances->groupBy('maintenance_type')
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Par résultat
        $byResult = $maintenances->groupBy('final_result')
            ->map(fn($g) => $g->count())
            ->sortDesc();

        // Par mois
        $byMonth = $maintenances->groupBy(function($m) {
            return \Carbon\Carbon::parse($m->visit_date)->format('Y-m');
        })->map(fn($g) => $g->count())->sortKeys();

        // Top 10 sites
        $bySite = $maintenances
            ->filter(fn($m) => !empty($m->village) && $m->village !== 'Inconnu')
            ->groupBy('village')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->take(10);

      
       // Quantité de composants utilisés (somme réelle)
       // Quantité réelle de composants remplacés
      
        $byComponent = collect([
        'Pompe' => (int) $maintenances->sum('qty_pump'),
        'Panneau solaire' => (int) $maintenances->sum('qty_solar_panel'),
        'Contrôleur' => (int) $maintenances->sum('qty_controller'),
        'Robinets' => (int) $maintenances->sum('qty_taps'),
        'Tête de puits' => (int) $maintenances->sum('qty_tank'),
        'Disjoncteur' => (int) $maintenances->sum('qty_other'),
        ])->filter(fn($v) => $v > 0)->sortDesc();

        return response()->json([
            'total' => $maintenances->count(),
            'by_type' => $byType,
            'by_result' => $byResult,
            'by_month' => $byMonth,
            'top_sites' => $bySite,
            'by_component' => $byComponent,
        ]);
    }
}