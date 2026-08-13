<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use Illuminate\Console\Command;

class ParseMaintenanceComponents extends Command
{
    protected $signature = 'maintenance:parse-components';
    protected $description = 'Extrait les composants depuis work_description pour les anciennes soumissions';

    public function handle()
    {
        $maintenances = Maintenance::whereNull('components_changed')
            ->whereNotNull('work_description')
            ->get();

        $this->info("Traitement de {$maintenances->count()} maintenances...");
        $updated = 0;

        foreach ($maintenances as $m) {
            $desc = mb_strtolower($m->work_description ?? '');
            $components = [];
            $qtyPump = null;
            $qtySolar = null;
            $qtyController = null;
            $qtyBraker = null;
            $qtyTaps = null;
            $qtyWellhead = null;

            // Pompe
            if (str_contains($desc, 'pompe')) {
                $components[] = 'pump';
                // Cherche quantité (ex: "une nouvelle pompe" = 1, "2 pompes" = 2)
                if (preg_match('/(\d+)\s+pompe/i', $desc, $matches)) {
                    $qtyPump = (int) $matches[1];
                } else {
                    $qtyPump = 1;
                }
            }

            // Panneaux solaires
            if (str_contains($desc, 'panneau') || str_contains($desc, 'solaire')) {
                $components[] = 'panels';
                // Cherche quantité (ex: "2 panneaux", "les 4 panneaux", "un panneau")
                if (preg_match('/(\d+)\s+panneau/i', $desc, $matches)) {
                    $qtySolar = (int) $matches[1];
                } elseif (preg_match('/les\s+(\d+)\s+panneau/i', $desc, $matches)) {
                    $qtySolar = (int) $matches[1];
                } elseif (preg_match('/(un|une)\s+panneau/i', $desc)) {
                    $qtySolar = 1;
                } else {
                    $qtySolar = 1;
                }
            }

            // Contrôleur
            if (str_contains($desc, 'contrôleur') || str_contains($desc, 'controleur') || str_contains($desc, 'controller')) {
                $components[] = 'controller';
                if (preg_match('/(\d+)\s+contrôleur/i', $desc, $matches)) {
                    $qtyController = (int) $matches[1];
                } else {
                    $qtyController = 1;
                }
            }

            // Disjoncteur
            if (str_contains($desc, 'disjoncteur') || str_contains($desc, 'braker') || str_contains($desc, 'breaker')) {
                $components[] = 'braker';
                if (preg_match('/(\d+)\s+disjoncteur/i', $desc, $matches)) {
                    $qtyBraker = (int) $matches[1];
                } else {
                    $qtyBraker = 1;
                }
            }

            // Robinets
            if (str_contains($desc, 'robinet') || str_contains($desc, 'tap')) {
                $components[] = 'tap';
                if (preg_match('/(\d+)\s+robinet/i', $desc, $matches)) {
                    $qtyTaps = (int) $matches[1];
                } elseif (preg_match('/(un|une)\s+robinet/i', $desc)) {
                    $qtyTaps = 1;
                } else {
                    $qtyTaps = 1;
                }
            }

            // Tête de puits / forage
            if (str_contains($desc, 'tête de forage') || str_contains($desc, 'tête de puits') || str_contains($desc, 'wellhead')) {
                $components[] = 'wellhed';
                if (preg_match('/(\d+)\s+tête/i', $desc, $matches)) {
                    $qtyWellhead = (int) $matches[1];
                } else {
                    $qtyWellhead = 1;
                }
            }

            if (!empty($components)) {
                $m->update([
                    'components_changed' => implode(' ', $components),
                    'qty_pump' => $qtyPump ?? $m->qty_pump,
                    'qty_solar_panel' => $qtySolar ?? $m->qty_solar_panel,
                    'qty_controller' => $qtyController ?? $m->qty_controller,
                    'qty_other' => $qtyBraker ?? $m->qty_other,
                    'qty_taps' => $qtyTaps ?? $m->qty_taps,
                    'qty_tank' => $qtyWellhead ?? $m->qty_tank,
                ]);
                $updated++;
            }
        }

        $this->info("$updated maintenances mises à jour.");
        return Command::SUCCESS;
    }
}