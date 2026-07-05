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
}