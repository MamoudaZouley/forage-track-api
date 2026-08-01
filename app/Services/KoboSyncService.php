<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Maintenance;
use App\Models\Supervision;
use App\Models\Well;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KoboSyncService
{
    private string $token;
    private string $baseUrl = 'https://kf.kobotoolbox.org/api/v2/assets';
    private string $supervisionUid = 'a2VA2PMSPBN9akCGz4MurA';
    private string $maintenanceUid = 'aS7sntRtc8eZazrhmn58QN';

    public function __construct()
    {
        $this->token = config('services.kobo.token');
    }

    // ═══════════════════════════════════════
    // CLIENT HTTP AVEC SSL
    // ═══════════════════════════════════════

private function httpClient()
{
    return Http::withHeaders([
                   'Authorization' => 'Token ' . $this->token,
               ])
               ->withOptions([
                   'verify' => false,
               ]);
}
    // ═══════════════════════════════════════
    // POINT D'ENTRÉE PRINCIPAL
    // ═══════════════════════════════════════

    public function syncAll(): array
    {
        return [
            'supervisions' => $this->syncSupervisions(),
            'maintenances' => $this->syncMaintenances(),
        ];
    }

    // ═══════════════════════════════════════
    // SYNCHRONISATION SUPERVISIONS
    // ═══════════════════════════════════════

  public function syncSupervisions(): array
{
    $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
    $url = "{$this->baseUrl}/{$this->supervisionUid}/data/?format=json&limit=1000";
    $page = 1;

    // Charge tous les kobo_ids existants en mémoire
    $existingKoboIds = Supervision::whereNotNull('kobo_id')
        ->pluck('kobo_id')
        ->flip()
        ->toArray();

    // Charge tous les puits existants en mémoire
    $existingWells = Well::pluck('id', 'code')->toArray();

    while ($url) {
        echo "Page {$page}...\n";
        flush();

        $response = $this->httpClient()->timeout(120)->get($url);

        if (!$response->successful()) {
            echo "Erreur API: " . $response->status() . "\n";
            break;
        }

        $data = $response->json();
        $submissions = $data['results'] ?? [];
        echo "Page {$page}: " . count($submissions) . " soumissions récupérées\n";
        flush();

        $supervisionsToInsert = [];
        $wellsToCreate = [];

        foreach ($submissions as $s) {
            $koboId = $s['_id'];

            // Skip si déjà importé
            if (isset($existingKoboIds[$koboId])) {
                $stats['skipped']++;
                continue;
            }

            $wellCode = $s['general_info/well_id'] ?? null;
            $village = $s['general_info/village_name'] ?? 'Inconnu';

            // Crée le puits si nécessaire
            if ($wellCode && !isset($existingWells[$wellCode])) {
                $well = Well::firstOrCreate(
                    ['code' => $wellCode],
                    [
                        'village' => $village,
                        'region' => 'Inconnu',
                        'department' => 'Inconnu',
                        'commune' => 'Inconnu',
                        'status' => $this->mapStatus($s['overall_status'] ?? 'operational'),
                    ]
                );
                $existingWells[$wellCode] = $well->id;
            }

            $wellId = $wellCode ? ($existingWells[$wellCode] ?? null) : null;

            // Calcul durée
          $duration = null;
          if (!empty($s['start_time']) && !empty($s['end_time'])) {
             try {
                $start = \Carbon\Carbon::parse($s['start_time']);
                $end = \Carbon\Carbon::parse($s['end_time']);
                $diff = round($end->diffInMinutes($start, false), 1);
        // Ignore les valeurs négatives ou supérieures à 8 heures (480 min)
               $duration = ($diff > 0 && $diff <= 480) ? $diff : null;
               } catch (\Exception $e) {}
            }

            $now = now()->toDateTimeString();
            $supervisionsToInsert[] = [
                'kobo_id' => $koboId,
                'well_id' => $wellId,
                'well_code' => $wellCode,
                'supervisor_name' => $s['_submitted_by'] ?? 'Inconnu',
                'supervisor_username' => $s['supervisor_username'] ?? $s['_submitted_by'] ?? 'Inconnu',
                'visit_date' => $s['visit_date'] ?? now()->toDateString(),
                'submission_time' => $s['_submission_time'] ?? null,
                'overall_status' => $this->mapStatus($s['overall_status'] ?? 'operational'),
                'duration_minutes' => $duration,
                'week_number' => !empty($s['visit_date'])
                    ? (int) \Carbon\Carbon::parse($s['visit_date'])->format('W')
                    : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $existingKoboIds[$koboId] = true;
            $stats['imported']++;
        }

        // Insertion en masse
        if (!empty($supervisionsToInsert)) {
            foreach (array_chunk($supervisionsToInsert, 100) as $chunk) {
                \Illuminate\Support\Facades\DB::table('supervisions')->insert($chunk);
            }
        }

        // Génère les alertes pour cette page
        $insertedSupervisions = Supervision::whereIn('kobo_id', array_column($supervisionsToInsert, 'kobo_id'))->get()->keyBy('kobo_id');
        foreach ($submissions as $s) {
            $koboId = $s['_id'];
            if (isset($insertedSupervisions[$koboId])) {
                $supervision = $insertedSupervisions[$koboId];
                $wellId = $supervision->well_id;
                if ($wellId) {
                    $well = Well::find($wellId);
                    if ($well) {
                        $this->generateAlerts($supervision, $well, $s);
                    }
                }
            }
        }

        echo "Stats: importées={$stats['imported']}, ignorées={$stats['skipped']}, erreurs={$stats['errors']}\n";
        flush();

        $url = $data['next'] ?? null;
        $page++;
    }

    return $stats;
}
    private function processSupervision(array $s): bool
    {
        $koboId = $s['_id'];

        if (Supervision::where('kobo_id', $koboId)->exists()) {
            return false;
        }

        $wellCode = $s['general_info/well_id'] ?? null;
        $village = $s['general_info/village_name'] ?? null;

        $well = null;
        if ($wellCode) {
            $well = Well::firstOrCreate(
                ['code' => $wellCode],
                [
                    'village' => $village ?? 'Inconnu',
                    'region' => 'Inconnu',
                    'department' => 'Inconnu',
                    'commune' => 'Inconnu',
                    'status' => $this->mapStatus($s['overall_status'] ?? 'operational'),
                ]
            );

            $well->update(['status' => $this->mapStatus($s['overall_status'] ?? 'operational')]);
        }

        $duration = null;
        if (!empty($s['start_time']) && !empty($s['end_time'])) {
            $start = \Carbon\Carbon::parse($s['start_time']);
            $end = \Carbon\Carbon::parse($s['end_time']);
            $duration = round($end->diffInMinutes($start), 1);
        }

        $supervision = Supervision::create([
            'kobo_id' => $koboId,
            'well_id' => $well?->id,
            'well_code' => $wellCode,
            'supervisor_name' => $s['_submitted_by'] ?? 'Inconnu',
            'supervisor_username' => $s['supervisor_username'] ?? $s['_submitted_by'] ?? 'Inconnu',
            'visit_date' => $s['visit_date'],
            'submission_time' => $s['_submission_time'],
            'overall_status' => $this->mapStatus($s['overall_status'] ?? 'operational'),
            'duration_minutes' => $duration,
            'week_number' => !empty($s['visit_date'])
                ? (int) \Carbon\Carbon::parse($s['visit_date'])->format('W')
                : null,
        ]);

        if ($well) {
            $this->generateAlerts($supervision, $well, $s);
        }

        return true;
    }

    private function generateAlerts(Supervision $supervision, Well $well, array $s): void
    {
        $alerts = [];

        // Pompe
        if (($s['pump_section/pump_condition'] ?? '') === 'bad' ||
            ($s['pump_section/pump_working'] ?? '') === 'no') {
            $alerts[] = [
                'component' => 'Pump',
                'issue' => 'Pump not working or in bad condition',
                'severity' => 'CRITICAL',
                'priority_hours' => 4,
            ];
        } elseif (($s['pump_section/pump_condition'] ?? '') === 'average') {
            $alerts[] = [
                'component' => 'Pump',
                'issue' => 'Pump in average condition',
                'severity' => 'HIGH',
                'priority_hours' => 24,
            ];
        }

        // Panneaux solaires
        $panelsBroken = (int) ($s['solar_section/panels_broken'] ?? 0);
        $panelsTotal = (int) ($s['solar_section/panels_total'] ?? 1);
        if ($panelsBroken > 0) {
            $ratio = $panelsBroken / max($panelsTotal, 1);
            $alerts[] = [
                'component' => 'Solar Panel',
                'issue' => "{$panelsBroken} panel(s) broken out of {$panelsTotal}",
                'severity' => $ratio > 0.3 ? 'CRITICAL' : ($ratio > 0.1 ? 'HIGH' : 'MEDIUM'),
                'priority_hours' => $ratio > 0.3 ? 4 : ($ratio > 0.1 ? 24 : 72),
            ];
        }

        // Cuve
        if (($s['tank_section/tank_leak'] ?? '') === 'yes') {
            $alerts[] = [
                'component' => 'Water Tank',
                'issue' => 'Tank leaking — ' . ($s['tank_section/leaktank_location'] ?? ''),
                'severity' => 'HIGH',
                'priority_hours' => 24,
            ];
        }

        // Robinets
        $tapsBroken = (int) ($s['taps_section/taps_broken'] ?? 0);
        if ($tapsBroken > 0) {
            $alerts[] = [
                'component' => 'Taps',
                'issue' => "{$tapsBroken} tap(s) broken",
                'severity' => 'MEDIUM',
                'priority_hours' => 72,
            ];
        }

        // Clôture
        if (($s['infrastructure_section/fence_condition'] ?? '') === 'bad') {
            $alerts[] = [
                'component' => 'Security',
                'issue' => 'Fence in bad condition',
                'severity' => 'HIGH',
                'priority_hours' => 24,
            ];
        }

        // Fuite d'eau
        if (($s['water_section/water_leak'] ?? '') === 'yes') {
            $alerts[] = [
                'component' => 'Pipes',
                'issue' => 'Water leak detected',
                'severity' => 'HIGH',
                'priority_hours' => 24,
            ];
        }

        foreach ($alerts as $alertData) {
            Alert::create([
                'supervision_id' => $supervision->id,
                'well_id' => $well->id,
                'village' => $well->village,
                'component' => $alertData['component'],
                'issue' => $alertData['issue'],
                'severity' => $alertData['severity'],
                'priority_hours' => $alertData['priority_hours'],
                'resolved' => false,
            ]);
        }
    }

    // ═══════════════════════════════════════
    // SYNCHRONISATION MAINTENANCES
    // ═══════════════════════════════════════

    public function syncMaintenances(): array
    {
        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => 0];
        $url = "{$this->baseUrl}/{$this->maintenanceUid}/data/?format=json&limit=1000";

        while ($url) {
            $response = $this->httpClient()->get($url);

            if (!$response->successful()) {
                Log::error('Kobo API error (maintenances)', ['status' => $response->status()]);
                break;
            }

            $data = $response->json();
            $submissions = $data['results'] ?? [];

            foreach ($submissions as $submission) {
                try {
                    $result = $this->processMaintenance($submission);
                    $result ? $stats['imported']++ : $stats['skipped']++;
                } catch (\Exception $e) {
                    $stats['errors']++;
                    Log::error('Maintenance sync error', [
                        'kobo_id' => $submission['_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $url = $data['next'] ?? null;
        }

        return $stats;
    }

    private function processMaintenance(array $m): bool
    {
        $koboId = $m['_id'];

        if (Maintenance::where('kobo_id', $koboId)->exists()) {
            return false;
        }

        $wellCode = $m['general_info/well_id'] ?? null;
        $well = $wellCode ? Well::where('code', $wellCode)->first() : null;

        Maintenance::create([
            'kobo_id' => $koboId,
            'well_id' => $well?->id,
            'well_code' => $wellCode ?? 'Inconnu',
            'village' => $m['general_info/village_name'] ?? null,
            'region' => $m['general_info/region'] ?? null,
            'technician_username' => $m['technician_username'] ?? $m['_submitted_by'] ?? null,
            'team_leader_name' => $m['general_info/team_leader_name'] ?? null,
            'visit_date' => $m['visit_date'],
            'maintenance_type' => $m['general_info/maintenance_type'] ?? null,
            'request_source' => $m['general_info/request_source'] ?? null,
            'work_performed' => $m['maintenance_work/work_performed'] ?? null,
            'work_description' => $m['maintenance_work/work_description'] ?? null,
            'parts_used' => $m['maintenance_work/parts_used'] ?? null,
            'work_duration' => $m['maintenance_work/work_duration'] ?? null,
            'final_result' => $m['after_maintenance/final_result'] ?? null,
            'pump_condition_before' => $m['pump_before/pump_condition_before'] ?? null,
            'pump_condition_after' => $m['after_maintenance/pump_condition_after'] ?? null,
            'water_flow_before' => $m['pump_before/water_flow_before'] ?? null,
            'water_flow_after' => $m['after_maintenance/water_flow_after'] ?? null,
            'needs_followup' => ($m['followup/needs_followup'] ?? 'no') === 'yes',
            'observations' => $m['followup/observations'] ?? null,
            'submission_time' => $m['_submission_time'] ?? null,
        ]);

        return true;
    }

    // ═══════════════════════════════════════
    // UTILITAIRES
    // ═══════════════════════════════════════

    private function mapStatus(string $status): string
    {
        return match($status) {
            'operational', 'working', 'good' => 'operational',
            'not_working', 'broken', 'major_issue' => 'not_working',
            'suspended', 'suspended_technical', 'suspended_other' => 'suspended',
            default => 'operational',
        };
    }
}