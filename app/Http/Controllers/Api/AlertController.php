<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Supervision;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = Alert::with(['well', 'supervision']);

        if ($request->severity) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('resolved')) {
            $query->where('resolved', $request->resolved === 'true');
        }

        if ($request->region) {
            $query->whereHas('well', function($q) use ($request) {
                $q->where('region', $request->region);
            });
        }

        return response()->json(
            $query->orderByRaw("FIELD(severity, 'CRITICAL','HIGH','MEDIUM','LOW')")
                  ->orderBy('created_at', 'desc')
                  ->paginate(15)
        );
    }

    public function show(Alert $alert)
    {
        $alert->load(['well', 'supervision']);
        return response()->json($alert);
    }

    public function resolve(Request $request, Alert $alert)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $alert->update([
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        return response()->json($alert);
    }
  public function wellsStatus(Request $request)
{
    set_time_limit(120);

    // Récupère la dernière supervision par puits en une seule requête
    $latestSupervisions = Supervision::with(['well', 'alerts'])
        ->whereIn('id', function($query) {
            $query->selectRaw('MAX(id)')
                  ->from('supervisions')
                  ->whereNotNull('well_id')
                  ->groupBy('well_id');
        });

    if ($request->region) {
        $latestSupervisions->whereHas('well', function($q) use ($request) {
            $q->where('region', $request->region);
        });
    }

    if ($request->search) {
        $latestSupervisions->whereHas('well', function($q) use ($request) {
            $q->where('code', 'like', "%{$request->search}%")
              ->orWhere('village', 'like', "%{$request->search}%");
        });
    }

    $supervisions = $latestSupervisions->orderBy('visit_date', 'desc')->get();

    // Charge toutes les dernières maintenances en UNE seule requête
    $wellIds = $supervisions->pluck('well_id')->filter()->unique()->toArray();

    $lastMaintenances = \App\Models\Maintenance::whereIn('well_id', $wellIds)
        ->whereIn('id', function($query) use ($wellIds) {
            $query->selectRaw('MAX(id)')
                  ->from('maintenances')
                  ->whereIn('well_id', $wellIds)
                  ->groupBy('well_id');
        })
        ->get()
        ->keyBy('well_id');

    $results = $supervisions->map(function($supervision) use ($lastMaintenances) {
        $well = $supervision->well;
        $alerts = $supervision->alerts;
        $hasProblems = $alerts->count() > 0;

        $lastMaintenance = $lastMaintenances->get($supervision->well_id);

        $status = 'no_problem';
        if ($hasProblems) {
            if (!$lastMaintenance) {
                $status = 'unresolved';
            } elseif ($lastMaintenance->visit_date > $supervision->visit_date) {
                $status = $lastMaintenance->final_result === 'fully_working'
                    ? 'resolved'
                    : 'unresolved';
            } else {
                $status = 'unresolved';
            }
        }

        return [
            'well_id' => $well?->id,
            'well_code' => $well?->code ?? $supervision->well_code,
            'village' => $well?->village ?? '—',
            'region' => $well?->region ?? '—',
            'last_visit_date' => $supervision->visit_date,
            'overall_status' => $supervision->overall_status,
            'alerts_count' => $alerts->count(),
            'alerts' => $alerts->map(function($alert) use ($supervision) {
                return [
                    'id' => $alert->id,
                    'component' => $alert->component,
                    'issue' => $alert->issue,
                    'severity' => $alert->severity,
                    'resolved' => $alert->resolved,
                    'days_open' => $alert->resolved ? 0 : (int) now()->diffInDays($supervision->visit_date, true),
                    'created_at' => $alert->created_at,
                ];
             }),
                        'status' => $status,
            'last_maintenance_date' => $lastMaintenance?->visit_date,
            'last_maintenance_result' => $lastMaintenance?->final_result,
            'supervision_id' => $supervision->id,
        ];
    });

    // Tri
    $sorted = $results->sortBy(function($item) {
        return match($item['status']) {
            'unresolved' => 0,
            'resolved' => 1,
            'no_problem' => 2,
            default => 3,
        };
    })->values();

    // Filtre par statut
    if ($request->status) {
        $sorted = $sorted->filter(fn($item) => $item['status'] === $request->status)->values();
    }

    $stats = [
        'total' => $sorted->count(),
        'unresolved' => $sorted->where('status', 'unresolved')->count(),
        'resolved' => $sorted->where('status', 'resolved')->count(),
        'no_problem' => $sorted->where('status', 'no_problem')->count(),
    ];

    return response()->json([
        'stats' => $stats,
        'data' => $sorted,
    ]);
   }
   
}