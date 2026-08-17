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
        try {
            // Lance la sync en arrière-plan
            dispatch(function() {
                $koboSync = new \App\Services\KoboSyncService();
                $koboSync->syncAll();
            })->afterResponse();

            return response()->json([
                'success' => true,
                'message' => 'Synchronisation démarrée en arrière-plan',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
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