<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
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
}