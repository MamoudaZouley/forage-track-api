<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Well;
use Illuminate\Http\Request;

class WellController extends Controller
{
    public function index(Request $request)
    {
        $query = Well::withCount('supervisions');

        if ($request->region) {
            $query->where('region', $request->region);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->supervisor) {
            $query->where('supervisor', $request->supervisor);
        }

        if ($request->zone) {
            $query->where('zone', $request->zone);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                ->orWhere('village', 'like', "%{$request->search}%");
            });
        }

        return response()->json($query->orderBy('code')->paginate(15));
    }

    public function show(Well $well)
    {
        $well->load(['supervisions' => function($q) {
            $q->withCount('alerts')->orderBy('visit_date', 'desc');
        }]);

        $well->supervisions_count = $well->supervisions->count();

        return response()->json($well);
    }
    public function filters()
    {
        $supervisors = Well::whereNotNull('supervisor')
            ->where('supervisor', '!=', 'pro_m')
            ->distinct()
            ->get(['supervisor', 'supervisor_name', 'zone'])
            ->groupBy('supervisor')
            ->map(fn($g) => [
                'username' => $g->first()->supervisor,
                'name' => $g->first()->supervisor_name ?? $g->first()->supervisor,
                'zone' => $g->first()->zone,
            ])
            ->sortBy('name')
            ->values();

        $zones = Well::whereNotNull('zone')
            ->distinct('zone')
            ->pluck('zone')
            ->sort()
            ->values();

        return response()->json([
            'supervisors' => $supervisors,
            'zones' => $zones,
        ]);
    }
}