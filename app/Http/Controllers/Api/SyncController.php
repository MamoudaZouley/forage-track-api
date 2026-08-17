<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\KoboSyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
  

    public function sync(Request $request)
    {
        set_time_limit(600); // 10 minutes
        ini_set('memory_limit', '512M');
        
        try {
            $koboSync = new \App\Services\KoboSyncService();
            $results = $koboSync->syncAll();
            
            // Supprime les doublons supervisions
            $toKeep = \App\Models\Supervision::selectRaw('MAX(id) as id')->groupBy('well_id')->pluck('id');
            \App\Models\Alert::whereNotIn('supervision_id', $toKeep)->delete();
            \App\Models\Supervision::whereNotIn('id', $toKeep)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Synchronisation terminée',
                'results' => $results,
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