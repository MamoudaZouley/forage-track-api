<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KoboSyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function __construct(private KoboSyncService $koboSync) {}

   public function sync(Request $request)
{
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    try {
        $results = $this->koboSync->syncAll();

        return response()->json([
            'success' => true,
            'message' => 'Synchronisation terminée',
            'results' => $results,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la synchronisation : ' . $e->getMessage(),
        ], 500);
    }
}

    public function status()
    {
        return response()->json([
            'supervisions' => \App\Models\Supervision::whereNotNull('kobo_id')->count(),
            'maintenances' => \App\Models\Maintenance::count(),
            'wells' => \App\Models\Well::count(),
            'last_sync' => \App\Models\Supervision::whereNotNull('kobo_id')
                ->latest()
                ->value('created_at'),
        ]);
    }
}