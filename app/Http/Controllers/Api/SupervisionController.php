<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supervision;
use App\Models\Well;
use Illuminate\Http\Request;

class SupervisionController extends Controller
{
    public function index(Request $request)
    {
        $query = Supervision::with('well')->withCount('alerts');

        if ($request->well_id) {
            $query->where('well_id', $request->well_id);
        }

        return response()->json(
            $query->orderBy('visit_date', 'desc')->paginate(15)
        );
    }

    public function show(Supervision $supervision)
    {
        $supervision->load(['well', 'alerts']);
        return response()->json($supervision);
    }

    public function byWell(Well $well)
    {
        $supervisions = $well->supervisions()
            ->withCount('alerts')
            ->orderBy('visit_date', 'desc')
            ->paginate(15);

        return response()->json($supervisions);
    }
}