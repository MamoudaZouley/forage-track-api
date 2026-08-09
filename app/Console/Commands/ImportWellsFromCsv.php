<?php

namespace App\Console\Commands;

use App\Models\Well;
use Illuminate\Console\Command;

class ImportWellsFromCsv extends Command
{
    protected $signature = 'wells:import-csv {file}';
    protected $description = 'Enrichit les puits depuis le fichier wells.csv';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Fichier introuvable : $file");
            return;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        // Map colonnes
        $idx = array_flip($header);

        $updated = 0;
        $created = 0;
        $skipped = 0;

        $supervisorZones = [
            'sup14' => 'Aguie-Gazaoua',
            'sup15' => 'Bader Goula',
            'sup16' => 'Bermo',
            'sup17' => 'Dan Goulbi',
            'sup18' => 'Guidan Roumdji',
            'sup19' => 'Kornaka',
            'sup20' => 'Mayahi',
            'sup21' => 'Mayahi Middle',
            'sup22' => 'Mayahi North',
            'sup23' => 'Mayahi West',
            'sup24' => 'Dakoro Nord',
            'sup25' => 'Ourafane North',
            'sup26' => 'Dakoro (South+Nord)',
            'sup27' => 'Mayahi Sud',
            'sup28' => 'Ourafane South',
            'sup29' => 'Tchadoua Sud',
            'sup30' => 'Tchadoua',
            'sup31' => 'Tessaoua',
            'pro_m' => 'Bureau',
        ];

        $excluded = ['86', '102'];

        while (($row = fgetcsv($handle)) !== false) {
            $code = trim($row[$idx['name']]);
            $village = trim($row[$idx['village_name']]);
            $region = trim($row[$idx['region']]);
            $supervisor = trim($row[$idx['supervisor']]);

            if (in_array($code, $excluded)) {
                $skipped++;
                continue;
            }

            $zone = $supervisorZones[$supervisor] ?? 'Inconnu';

            $well = Well::where('code', $code)->first();

            if ($well) {
                $well->update([
                    'village' => $village ?: $well->village,
                    'region' => $region ?: $well->region,
                    'supervisor' => $supervisor,
                    'zone' => $zone,
                ]);
                $updated++;
            } else {
                Well::create([
                    'code' => $code,
                    'village' => $village ?: 'Inconnu',
                    'region' => $region ?: 'Inconnu',
                    'department' => 'Inconnu',
                    'commune' => 'Inconnu',
                    'status' => 'operational',
                    'supervisor' => $supervisor,
                    'zone' => $zone,
                ]);
                $created++;
            }
        }

        fclose($handle);

        $this->table(
            ['Mis à jour', 'Créés', 'Ignorés (fermés)'],
            [[$updated, $created, $skipped]]
        );

        $this->info('Import terminé !');
    }
}