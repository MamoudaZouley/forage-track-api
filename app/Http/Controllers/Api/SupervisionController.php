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
        public function supervisorStats()
    {
        $supervisions = Supervision::with(['well', 'alerts'])->get();

        $stats = $supervisions->groupBy('supervisor_username')->map(function($group, $username) {
            $total = $group->count();

            $wellsVisited = $group->pluck('well_id')->filter()->unique()->count();
            $totalAlerts = $group->sum(fn($s) => $s->alerts->count());
            $withAlerts = $group->filter(fn($s) => $s->alerts->count() > 0)->count();
            $detectionRate = $total > 0 ? round($withAlerts / $total * 100, 1) : 0;

            $byStatus = $group->groupBy('overall_status')->map->count();

            $byMonth = $group->groupBy(function($s) {
                return \Carbon\Carbon::parse($s->visit_date)->format('Y-m');
            })->map->count()->sortKeys();

            $alertsByComponent = [];
            foreach ($group as $s) {
                foreach ($s->alerts as $alert) {
                    $alertsByComponent[$alert->component] = ($alertsByComponent[$alert->component] ?? 0) + 1;
                }
            }
            arsort($alertsByComponent);

            $well = $group->first()?->well;
            $zone = $well?->zone ?? 'Inconnu';
            $region = $well?->region ?? 'Inconnu';
            $supervisor = $well?->supervisor_name ?? $well?->supervisor ?? $username;

            return [
                'username' => $username,
                'supervisor' => $supervisor,
                'region' => $region,
                'zone' => $zone,
                'total_visits' => $total,
                'wells_visited' => $wellsVisited,
                'total_alerts' => $totalAlerts,
                'detection_rate' => $detectionRate,
                'by_status' => $byStatus,
                'by_month' => $byMonth,
                'alerts_by_component' => $alertsByComponent,
            ];
        })->sortByDesc('total_visits')->values();

        return response()->json($stats);
    }
        public function kpiSupervisors(Request $request)
    {
        $month = $request->month ?? now()->format('Y-m');
        $year = substr($month, 0, 4);
        $monthNum = substr($month, 5, 2);

        // Fonction de normalisation des villages
        $normalize = function($str) {
        $str = mb_strtolower(trim($str ?? ''));
        // Remplace les accents connus
        $search = ['é','è','ê','ë','à','â','ä','î','ï','ô','ö','ù','û','ü','ç','ñ','É','È','Ê','À','Â','Î','Ô','Ù','Û','Ç'];
        $replace = ['e','e','e','e','a','a','a','i','i','o','o','u','u','u','c','n','e','e','e','a','a','i','o','u','u','c'];
        $str = str_replace($search, $replace, $str);
        // Supprime tous les caractères non-ASCII restants
        $str = preg_replace('/[^\x00-\x7F]/', '', $str);
        return trim($str);
    };

        // Toutes les soumissions du mois depuis l'historique complet
        $supervisions = \Illuminate\Support\Facades\DB::table('supervision_history')
            ->whereYear('visit_date', $year)
            ->whereMonth('visit_date', $monthNum)
            ->orderBy('supervisor_username')
            ->orderBy('well_code')
            ->orderBy('visit_date')
            ->get()
            ->map(fn($s) => (array) $s);

        // Charge les puits avec correspondance village normalisé
       // Charge les puits depuis wells_merged_utf8.csv (source de vérité)
        $cutoffMonth = '2026-08';
        $csvPath = $month < $cutoffMonth 
        ? base_path('wells_kpi.csv') 
        : base_path('wells_merged_utf8.csv');
        $assignmentMap = [];
        if (file_exists($csvPath)) {
            $f = fopen($csvPath, 'r');
            $header = array_map('trim', fgetcsv($f, 0, ',', '"', ''));
            $idx = array_flip($header);
            while (($row = fgetcsv($f, 0, ',', '"', '')) !== false) {
                $row = array_map('trim', $row);
                $code = $row[$idx['name']] ?? null;
                $village = $row[$idx['village_name']] ?? null;
                $supervisor = $row[$idx['supervisor']] ?? null;
                if (!$code || !$supervisor || $supervisor === 'pro_m' || in_array($code, ['86', '102', '163'])) continue;
                $assignmentMap[$supervisor][] = [
                    'code' => $code,
                    'village_norm' => $normalize($village),
                ];
            }
            fclose($f);
        }
        // Registre des superviseurs
        $supervisorRegistry = [
            'sup14' => ['name' => 'Inoussa Amadou', 'zone' => 'Aguie-Gazaoua'],
            'sup15' => ['name' => 'Abdoul Kader Aboubacar', 'zone' => 'Bader Goula'],
            'sup16' => ['name' => 'Oumarou Ousseini', 'zone' => 'Bermo'],
            'sup17' => ['name' => 'Soupia Oumarou', 'zone' => 'Dan Goulbi'],
            'sup18' => ['name' => 'Mahamadou Bello Ali', 'zone' => 'Guidan Roumdji'],
            'sup19' => ['name' => 'Habibou Souleymane', 'zone' => 'Kornaka'],
            'sup20' => ['name' => 'Nassirou Abdou Goube', 'zone' => 'Mayahi'],
            'sup21' => ['name' => 'Abdoul Wahab Issoufou', 'zone' => 'Mayahi Middle'],
            'sup22' => ['name' => 'Ousseini Abdou', 'zone' => 'Mayahi North'],
            'sup23' => ['name' => 'Laouali Yacouba', 'zone' => 'Mayahi West'],
            'sup24' => ['name' => 'Hayya Moussa', 'zone' => 'Dakoro Nord'],
            'sup25' => ['name' => 'Maman Hamissou Mani', 'zone' => 'Ourafane North'],
            'sup26' => ['name' => 'Ibrahim Mahaman Dan Jari', 'zone' => 'Dakoro (South+Nord)'],
            'sup27' => ['name' => 'Laouali Garba', 'zone' => 'Mayahi Sud'],
            'sup28' => ['name' => 'Hassane Daouda', 'zone' => 'Ourafane South'],
            'sup29' => ['name' => 'ABOU Soufia Rabiou Nomaou', 'zone' => 'Tchadoua Sud'],
            'sup30' => ['name' => 'Daouda Amani Ousmane', 'zone' => 'Tchadoua'],
            'sup31' => ['name' => 'Djafar Maty', 'zone' => 'Tessaoua'],
            'sup33' => ['name' => 'Ali Oumarou Dan Dango', 'zone' => 'Dakoro-Kornaka'],
        ];

        // Fonction semaine
        $getWeek = fn($day) => $day <= 7 ? 1 : ($day <= 14 ? 2 : ($day <= 21 ? 3 : 4));

        // Validation exacte comme le Python
        $validated = [];
        $lastValid = [];
        $visitsByWellWeek = [];

        foreach ($supervisions as $s) {
            $sup = $s['supervisor_username'];
            if (!isset($supervisorRegistry[$sup])) continue;

            $village = $s['village'] ?? '';
            $villageNorm = $normalize($village);
            $date = \Carbon\Carbon::parse($s['visit_date']);
            $day = (int) $date->format('d');
            $week = $getWeek($day);

            $ruleFailed = null;

            // Règle 1 : doublon même semaine (même sup, même village, même semaine)
            $weekKey = "{$sup}|{$villageNorm}|{$week}";
            if (isset($visitsByWellWeek[$weekKey])) {
                $ruleFailed = 'DUPLICATE_SAME_WEEK';
            }

            // Règle 2 : gap < 4 jours (même sup, même village)
            if (!$ruleFailed) {
                $lastKey = "{$sup}|{$villageNorm}";
                if (isset($lastValid[$lastKey])) {
                    $gap = abs($date->diffInDays(\Carbon\Carbon::parse($lastValid[$lastKey])));
                    if ($gap < 4) {
                        $ruleFailed = "GAP_TOO_SHORT({$gap}d)";
                    }
                }
            }

            if (!$ruleFailed) {
                $visitsByWellWeek[$weekKey] = true;
                $lastValid["{$sup}|{$villageNorm}"] = $date->format('Y-m-d');
            }

            $validated[] = [
                'sup' => $sup,
                'village' => $village,
                'date' => $date->format('Y-m-d'),
                'week' => $week,
                'is_valid' => !$ruleFailed,
                'rule_failed' => $ruleFailed,
            ];
        }

        $validatedCollection = collect($validated);

        // KPI par superviseur
        $stats = collect($supervisorRegistry)->map(function($info, $supUsername) use ($validatedCollection, $assignmentMap) {
            $nWells = count($assignmentMap[$supUsername] ?? []);
            if ($nWells === 0) return null;
            $target = $nWells * 4;

            $supAll = $validatedCollection->filter(fn($v) => $v['sup'] === $supUsername);
            $supValid = $supAll->filter(fn($v) => $v['is_valid']);

            $raw = $supAll->count();
            $dupes = $supAll->filter(fn($v) => $v['rule_failed'] === 'DUPLICATE_SAME_WEEK')->count();
            $gapFail = $supAll->filter(fn($v) => str_starts_with($v['rule_failed'] ?? '', 'GAP'))->count();
            $validVisits = $supValid->count();

            $kpi = $target > 0 ? round($validVisits / $target * 100, 1) : 0.0;

            $grade = match(true) {
                $raw === 0 => 'ABSENT',
                $kpi >= 100 => 'EXCELLENT',
                $kpi >= 90 => 'GOOD',
                $kpi >= 75 => 'PARTIAL',
                $kpi >= 50 => 'LOW',
                default => 'CRITICAL',
            };

            $weekly = [];
            for ($wk = 1; $wk <= 4; $wk++) {
                $weekly["w{$wk}"] = $supValid->filter(fn($v) => $v['week'] === $wk)->count();
            }

            return [
                'username' => $supUsername,
                'name' => $info['name'],
                'zone' => $info['zone'],
                'assigned_wells' => $nWells,
                'target' => $target,
                'raw_submitted' => $raw,
                'duplicates' => $dupes,
                'gap_violations' => $gapFail,
                'valid_visits' => $validVisits,
                'w1' => $weekly['w1'],
                'w2' => $weekly['w2'],
                'w3' => $weekly['w3'],
                'w4' => $weekly['w4'],
                'kpi_percent' => $kpi,
                'grade' => $grade,
            ];
        })->filter()->sortBy('kpi_percent')->values();

        $totals = [
            'assigned_wells' => $stats->sum('assigned_wells'),
            'target' => $stats->sum('target'),
            'raw_submitted' => $stats->sum('raw_submitted'),
            'duplicates' => $stats->sum('duplicates'),
            'gap_violations' => $stats->sum('gap_violations'),
            'valid_visits' => $stats->sum('valid_visits'),
            'w1' => $stats->sum('w1'),
            'w2' => $stats->sum('w2'),
            'w3' => $stats->sum('w3'),
            'w4' => $stats->sum('w4'),
            'kpi_percent' => $stats->count() > 0 ? round($stats->avg('kpi_percent'), 1) : 0,
        ];

        return response()->json([
            'month' => $month,
            'stats' => $stats,
            'totals' => $totals,
        ]);
    }
        public function waterConsumption(Request $request)
    {
        $supervisions = Supervision::with('well')
            ->whereNotNull('meter_reading')
            ->orderBy('visit_date', 'desc')
            ->get();

        // Stats globales
        $totalConsumption = $supervisions->sum('weekly_consumption');
        $avgConsumption = $supervisions->avg('weekly_consumption');
        $totalWells = $supervisions->pluck('well_id')->unique()->count();

        // Par puits
        $byWell = $supervisions->groupBy('well_id')->map(function($group) {
            $last = $group->first();
            return [
                'well_id' => $last->well_id,
                'well_code' => $last->well_code,
                'village' => $last->well?->village ?? '—',
                'region' => $last->well?->region ?? '—',
                'supervisor' => $last->well?->supervisor ?? '—',
                'zone' => $last->well?->zone ?? '—',
                'last_reading' => $last->meter_reading,
                'last_consumption' => $last->weekly_consumption,
                'last_visit' => $last->visit_date,
                'water_flow' => $last->water_flow,
                'total_readings' => $group->count(),
            ];
        })->sortByDesc('last_consumption')->values();

        // Par mois
        $byMonth = $supervisions->groupBy(function($s) {
            return \Carbon\Carbon::parse($s->visit_date)->format('Y-m');
        })->map(function($group, $month) {
            return [
                'month' => $month,
                'total_consumption' => round($group->sum('weekly_consumption'), 2),
                'avg_consumption' => round($group->avg('weekly_consumption'), 2),
                'wells_count' => $group->pluck('well_id')->unique()->count(),
            ];
        })->sortKeys()->values();

        // Par zone
        $byZone = $supervisions->groupBy(function($s) {
            return $s->well?->zone ?? 'Inconnu';
        })->map(function($group, $zone) {
            return [
                'zone' => $zone,
                'total_consumption' => round($group->sum('weekly_consumption'), 2),
                'avg_consumption' => round($group->avg('weekly_consumption'), 2),
                'wells_count' => $group->pluck('well_id')->unique()->count(),
            ];
        })->sortByDesc('total_consumption')->values();

        return response()->json([
            'stats' => [
                'total_consumption' => round($totalConsumption, 2),
                'avg_consumption' => round($avgConsumption, 2),
                'total_wells' => $totalWells,
            ],
            'by_well' => $byWell,
            'by_month' => $byMonth,
            'by_zone' => $byZone,
        ]);
    }
}