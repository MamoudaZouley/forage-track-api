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
    // CLIENT HTTP
    // ═══════════════════════════════════════
    private function httpGet(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $this->token,
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Log::error('cURL error', ['error' => $error, 'url' => $url]);
            return null;
        }

        return json_decode($response, true);
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

        // Charge tous les puits existants en mémoire
        $existingWells = [];
        foreach (Well::all(['id', 'code', 'village']) as $w) {
            $existingWells[$w->code . '|' . $w->village] = $w->id;
        }

        // Sites fermés à exclure
        $excludedWells = ['86', '102', '163'];

        // Première passe — collecte uniquement le kobo_id de la dernière soumission par puits
        $latestByWell = [];

        $url = "{$this->baseUrl}/{$this->supervisionUid}/data/?format=json&limit=1000&sort=%7B%22_submission_time%22%3A1%7D";

        while ($url) {
            $data = $this->httpGet($url);
            if (!$data) break;
            $next = $data['next'] ?? null;

            foreach ($data['results'] ?? [] as $s) {
                $wellCode = $s['general_info/well_id'] ?? null;
                $villageName = $s['general_info/village_name'] ?? null;
                if (!$wellCode) continue;
                if (in_array($wellCode, $excludedWells)) continue;
                // Clé unique = code + village
                $key = $wellCode . '|' . $villageName;
                $latestByWell[$key] = $s['_id'];
            }
            echo "Page traitée: " . count($data['results'] ?? []) . " soumissions, " . count($latestByWell) . " puits uniques\n";
            flush();

            unset($data);
            $url = $next;
        }

        echo "Dernières supervisions trouvées : " . count($latestByWell) . "\n";
        flush();

        // Deuxième passe — importe chaque soumission individuellement
       foreach ($latestByWell as $key => $koboId) {
            [$wellCode, $villageName] = explode('|', $key, 2);
            try {
                $url = "{$this->baseUrl}/{$this->supervisionUid}/data/{$koboId}/?format=json";
                $s = $this->httpGet($url);
                if ($stats['imported'] === 0 && $stats['skipped'] === 0) {
                echo "Test kobo_id: " . $koboId . "\n";
                echo "pump_working: " . ($s['pump_section/pump_working'] ?? 'ABSENT') . "\n";
                echo "overall_status: " . ($s['overall_status'] ?? 'ABSENT') . "\n";
                echo "kobo_id in response: " . ($s['_id'] ?? 'ABSENT') . "\n";
                flush();
                }
                if (!$s) {
                    $stats['errors']++;
                    continue;
                }

                if ($stats['imported'] === 0) {
                Log::info('Premier submission', ['kobo_id' => $koboId, 'pump_working' => $s['pump_section/pump_working'] ?? 'ABSENT', 'keys' => array_keys($s)]);
                }

                // Crée ou met à jour le puits
               // Cherche d'abord par code + village exact
                $well = Well::where('code', $wellCode)
                            ->where('village', $villageName)
                            ->first();

                // Si pas trouvé, cherche par code seul
                if (!$well) {
                    $well = Well::where('code', $wellCode)->first();
                }

                // Si toujours pas trouvé, crée le puits
                if (!$well) {
                    $well = Well::create([
                        'code' => $wellCode,
                        'village' => $villageName ?? 'Inconnu',
                        'region' => 'Inconnu',
                        'department' => 'Inconnu',
                        'commune' => 'Inconnu',
                        'status' => $this->mapStatus($s['overall_status'] ?? 'operational'),
                    ]);
                }

                $wellId = $well->id;
                // Détermine le vrai statut basé sur pump_working ET inverter_working
                $pumpWorking = $s['pump_section/pump_working'] ?? 'yes';
                $inverterWorking = $s['solar_section/inverter_working'] ?? 'yes';
                $overallStatus = $s['overall_status'] ?? 'operational';

                if ($pumpWorking === 'no' || $inverterWorking === 'no') {
                    $realStatus = 'not_working';
                } else {
                    $realStatus = $this->mapStatus($overallStatus);
                }

                $well->update(['status' => $realStatus]);

                // Calcul durée
                $duration = null;
                if (!empty($s['start_time']) && !empty($s['end_time'])) {
                    try {
                        $start = \Carbon\Carbon::parse($s['start_time']);
                        $end = \Carbon\Carbon::parse($s['end_time']);
                        $diff = round($end->diffInMinutes($start, false), 1);
                        $duration = ($diff > 0 && $diff <= 480) ? $diff : null;
                    } catch (\Exception $e) {}
                }

                $supervisionData = [
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
                    'pump_working' => $s['pump_section/pump_working'] ?? null,
                    'pump_condition' => $s['pump_section/pump_condition'] ?? null,
                    'inverter_working' => $s['solar_section/inverter_working'] ?? null,
                    'water_flow' => $s['water_section/water_flow'] ?? null,
                ];

                $existing = Supervision::where('kobo_id', $koboId)->first();
                if ($existing) {
                    $existing->update($supervisionData);
                    $supervision = $existing;
                    Alert::where('supervision_id', $supervision->id)->delete();
                    $stats['skipped']++;
                } else {
                    $supervision = Supervision::create($supervisionData);
                    $stats['imported']++;
                }

                $this->generateAlerts($supervision, $well, $s);

            } catch (\Exception $e) {
                $stats['errors']++;
                Log::error('Supervision sync error', [
                    'well_code' => $wellCode,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $stats;
    }

    // ═══════════════════════════════════════
    // GÉNÉRATION DES ALERTES
    // ═══════════════════════════════════════

    private function generateAlerts(Supervision $supervision, Well $well, array $s): void
    {
        $alerts = [];

       // Pompe — deux conditions séparées
        if (($s['pump_section/pump_working'] ?? '') === 'no') {
            $alerts[] = [
                'component' => 'Pump',
                'issue' => 'Pump not working',
                'severity' => 'CRITICAL',
                'priority_hours' => 4,
            ];
        }
       

       
        // Sécurité — garde absent
        if (($s['guard_section/guard_present'] ?? '') === 'no') {
            $alerts[] = [
                'component' => 'Security',
                'issue' => 'Guard absent',
                'severity' => 'MEDIUM',
                'priority_hours' => 72,
            ];
        }
        if (($s['infrastructure_section/fence_condition'] ?? '') === 'bad') {
            $alerts[] = [
                'component' => 'Security',
                'issue' => 'Fence in bad condition',
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

        // Onduleur / Contrôleur
        if (($s['solar_section/inverter_working'] ?? '') === 'no') {
            $alerts[] = [
                'component' => 'Controller',
                'issue' => 'Inverter not working',
                'severity' => 'CRITICAL',
                'priority_hours' => 4,
            ];
        }

        // Cuve
        if (($s['tank_section/tank_leak'] ?? '') === 'yes') {
            $alerts[] = [
                'component' => 'Tank',
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

        // Clôture / Sécurité
        if (($s['infrastructure_section/fence_condition'] ?? '') === 'bad') {
            $alerts[] = [
                'component' => 'Security',
                'issue' => 'Fence in bad condition',
                'severity' => 'HIGH',
                'priority_hours' => 24,
            ];
        }

        // Fuite d'eau / Tuyaux
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
            $data = $this->httpGet($url);
            if (!$data) {
                Log::error('Kobo API error (maintenances)');
                break;
            }
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

        // Gère les deux versions du formulaire
        $wellCode = $m['general_info/well_id'] ?? $m['well_section/well_id'] ?? null;
        $village = $m['general_info/village_name'] ?? $m['well_section/village_name'] ?? null;
        $region = $m['general_info/region'] ?? null;
        $maintenanceType = $m['general_info/maintenance_type'] ?? $m['well_section/maintenance_type'] ?? null;
        $requestSource = $m['general_info/request_source'] ?? $m['well_section/request_source'] ?? null;

        $well = $wellCode ? Well::where('code', $wellCode)->first() : null;

        Maintenance::create([
            'kobo_id' => $koboId,
            'well_id' => $well?->id,
            'well_code' => $wellCode ?? 'Inconnu',
            'village' => $village ?? ($well?->village),
            'region' => $region ?? null,
            'technician_username' => $m['technician_username'] ?? $m['_submitted_by'] ?? null,
            'team_leader_name' => $m['general_info/team_leader_name'] ?? null,
            'visit_date' => $m['visit_date'],
            'maintenance_type' => $maintenanceType,
            'request_source' => $requestSource,
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
            'components_changed' => $m['maintenance_work/components_changed'] ?? null,
            'qty_pump' => isset($m['maintenance_work/pump_details/pump_quantity']) ? (int)$m['maintenance_work/pump_details/pump_quantity'] : null,
            'qty_controller' => isset($m['maintenance_work/controllerr']) ? (int)$m['maintenance_work/controllerr'] : null,
            'qty_solar_panel' => isset($m['maintenance_work/solar_details/solar_quantity']) ? (int)$m['maintenance_work/solar_details/solar_quantity'] : null,
            'qty_pipes' => null,
            'qty_taps' => isset($m['maintenance_work/taps']) ? (int)$m['maintenance_work/taps'] : null,
            'qty_tank' => isset($m['maintenance_work/wellhead']) ? (int)$m['maintenance_work/wellhead'] : null,
            'qty_other' => isset($m['maintenance_work/brakerr']) ? (int)$m['maintenance_work/brakerr'] : null,
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