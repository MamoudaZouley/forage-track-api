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
        if ($request->supervisor) {
            $query->whereHas('well', function($q) use ($request) {
                $q->where('supervisor', $request->supervisor);
            });
        }

        if ($request->zone) {
            $query->whereHas('well', function($q) use ($request) {
                $q->where('zone', $request->zone);
            });
        }
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
        ->get()
        ->map(function($m) {
            if (strtolower(trim($m->team_leader_name)) === 'mauntaka') {
                $m->team_leader_name = 'Maman Mountaka Abdou';
            }
            return $m;
        });

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
       public function exportData(Request $request)
{
    $query = \App\Models\Maintenance::with('well');

    if ($request->region) {
        $query->where('region', $request->region);
    }
    if ($request->maintenance_type) {
        $query->where('maintenance_type', $request->maintenance_type);
    }
    if ($request->final_result) {
        $query->where('final_result', $request->final_result);
    }

    return response()->json(
        $query->orderBy('visit_date', 'desc')->get()->map(function($m) {
            return [
                'visit_date'            => $m->visit_date,
                'village'               => $m->village ?? $m->well?->village,
                'region'                => $m->region ?? $m->well?->region,
                'well_code'             => $m->well_code,
                'technician_username'   => $m->technician_username,
                'team_leader_name'      => $m->team_leader_name,
                'maintenance_type'      => $m->maintenance_type,
                'work_performed'        => $m->work_performed,
                'work_description'      => $m->work_description,
                'work_duration'         => $m->work_duration,
                'pump_condition_before' => $m->pump_condition_before,
                'pump_condition_after'  => $m->pump_condition_after,
                'water_flow_before'     => $m->water_flow_before,
                'water_flow_after'      => $m->water_flow_after,
                'final_result'          => $m->final_result,
                'needs_followup'        => $m->needs_followup,
                'observations'          => $m->observations,
               ];
           })
       );
}

    public function export(Request $request)
    {
        $query = \App\Models\Maintenance::with('well');

        if ($request->region) {
            $query->where('region', $request->region);
        }
        if ($request->maintenance_type) {
            $query->where('maintenance_type', $request->maintenance_type);
        }
        if ($request->final_result) {
            $query->where('final_result', $request->final_result);
        }

        $maintenances = $query->orderBy('visit_date', 'desc')->get();

        $typeLabels = [
            'emergency'   => 'Urgence',
            'repair'      => 'Réparation',
            'replacement' => 'Remplacement',
            'scheduled'   => 'Planifié',
            'inspection'  => 'Inspection',
        ];

        $resultLabels = [
            'fully_working'     => 'Fonctionnel',
            'partially_working' => 'Partiel',
            'not_working'       => 'En panne',
            'needs_parts'       => 'Pièces requises',
            'needs_specialist'  => 'Spécialiste requis',
            'not_repairable'    => 'Non réparable',
        ];

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="maintenances_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function() use ($maintenances, $typeLabels, $resultLabels) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, [
                '#', 'Date', 'Village', 'Région', 'Code puits',
                'Technicien', 'Chef équipe', 'Type', 'Travaux effectués',
                'Description', 'Durée (h)', 'Pompe avant', 'Pompe après',
                'Débit avant', 'Débit après', 'Résultat', 'Suivi requis',
                'Observations',
            ], ';');

            foreach ($maintenances as $i => $m) {
                $village = $m->village ?? $m->well?->village ?? '—';

                fputcsv($file, [
                    $i + 1,
                    $m->visit_date,
                    $village,
                    $m->region ?? $m->well?->region ?? '—',
                    $m->well_code,
                    $m->technician_username ?? '—',
                    $m->team_leader_name ?? '—',
                    $typeLabels[$m->maintenance_type ?? ''] ?? ($m->maintenance_type ?? '—'),
                    $m->work_performed ?? '—',
                    $m->work_description ?? '—',
                    $m->work_duration ?? '—',
                    $m->pump_condition_before ?? '—',
                    $m->pump_condition_after ?? '—',
                    $m->water_flow_before ?? '—',
                    $m->water_flow_after ?? '—',
                    $resultLabels[$m->final_result ?? ''] ?? ($m->final_result ?? '—'),
                    $m->needs_followup ? 'Oui' : 'Non',
                    $m->observations ?? '—',
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}