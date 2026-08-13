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
        public function technicianStats()
    {
        $maintenances = \App\Models\Maintenance::whereNotNull('team_leader_name')
            ->where('team_leader_name', '!=', '')
            ->get();

        $stats = $maintenances->groupBy('team_leader_name')->map(function($group, $name) {
            $total = $group->count();
            $byResult = $group->groupBy('final_result')->map->count();
            $byType = $group->groupBy('maintenance_type')->map->count();
            $byMonth = $group->groupBy(function($m) {
                return \Carbon\Carbon::parse($m->visit_date)->format('Y-m');
            })->map->count()->sortKeys();

            // Composants remplacés
           // Composants — utilise qty si disponible, sinon compte depuis components_changed
            $components = [
                'pump' => (int) $group->sum('qty_pump'),
                'solar_panel' => (int) $group->sum('qty_solar_panel'),
                'controller' => (int) $group->sum('qty_controller'),
                'taps' => (int) $group->sum('qty_taps'),
                'other' => (int) $group->sum('qty_other'),
            ];
            // Détail des composants "autres"
            $otherDetails = [];

            if (array_sum($components) === 0) {
                foreach ($group as $m) {
                    if (empty($m->components_changed)) continue;
                    foreach (explode(' ', $m->components_changed) as $part) {
                        $part = trim($part);
                        if ($part === 'pump') $components['pump']++;
                        elseif ($part === 'panels') $components['solar_panel']++;
                        elseif ($part === 'controller') $components['controller']++;
                        elseif ($part === 'tap') $components['taps']++;
                        elseif ($part === 'braker') { $components['other']++; $otherDetails['Disjoncteur'] = ($otherDetails['Disjoncteur'] ?? 0) + 1; }
                        elseif ($part === 'wellhed') { $components['other']++; $otherDetails['Tête de puits'] = ($otherDetails['Tête de puits'] ?? 0) + 1; }
                        elseif ($part === 'adapter_32') { $components['other']++; $otherDetails['Adaptateur'] = ($otherDetails['Adaptateur'] ?? 0) + 1; }
                        elseif ($part === 'silicon') { $components['other']++; $otherDetails['Silicone'] = ($otherDetails['Silicone'] ?? 0) + 1; }
                        elseif ($part === 'pvc_glue') { $components['other']++; $otherDetails['Colle PVC'] = ($otherDetails['Colle PVC'] ?? 0) + 1; }
                        elseif ($part === 'riser') { $components['other']++; $otherDetails['Colonne montante'] = ($otherDetails['Colonne montante'] ?? 0) + 1; }
                        elseif ($part === 'teflon') { $components['other']++; $otherDetails['Téflon'] = ($otherDetails['Téflon'] ?? 0) + 1; }
                        elseif ($part === 'resin') { $components['other']++; $otherDetails['Résine'] = ($otherDetails['Résine'] ?? 0) + 1; }
                        elseif ($part === 'cement') { $components['other']++; $otherDetails['Ciment'] = ($otherDetails['Ciment'] ?? 0) + 1; }
                        elseif ($part === 'pvc_63') { $components['other']++; $otherDetails['PVC 63'] = ($otherDetails['PVC 63'] ?? 0) + 1; }
                    }
                }
            }

            // Sites distincts
            $sites = $group->pluck('village')->filter()->unique()->count();

            return [
                'name' => $name,
                'total' => $total,
                'sites' => $sites,
                'by_result' => $byResult,
                'by_type' => $byType,
                'by_month' => $byMonth,
                'components' => $components,
                'other_details' => $otherDetails,
            ];
        })->sortByDesc('total')->values();

        return response()->json($stats);
    }
}