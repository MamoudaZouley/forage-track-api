<?php

namespace App\Console\Commands;

use App\Models\Well;
use Illuminate\Console\Command;

class ImportWellsFromCsv extends Command
{
    protected $signature = 'wells:import-csv {file}';
    protected $description = 'Enrichit les puits depuis le fichier wells CSV';

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Fichier introuvable : $file");
            return;
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);
        $header = array_map('trim', $header); // Supprime les espaces
        $idx = array_flip($header);

        $updated = 0;
        $skipped = 0;
        $notFound = 0;

        $excludedSupervisors = ['pro_m'];
        $excludedCodes = ['86', '102'];

        while (($row = fgetcsv($handle)) !== false) {
            $row = array_map('trim', $row);
            $code = trim($row[$idx['name']]);
            $village = trim($row[$idx['village_name']]);
            $region = trim($row[$idx['region']] ?? '');
            $department = isset($idx['department']) ? trim($row[$idx['department']] ?? '') : '';
            $commune = isset($idx['commune']) ? trim($row[$idx['commune']] ?? '') : '';
            $supervisor = isset($idx['supervisors']) ? trim($row[$idx['supervisors']] ?? '') :
              (isset($idx['supervisor']) ? trim($row[$idx['supervisor']] ?? '') : '');

            if (in_array($code, $excludedCodes)) {
                $skipped++;
                continue;
            }

            if (in_array($supervisor, $excludedSupervisors)) {
                $skipped++;
                continue;
            }

            // Zone depuis superviseur
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
            ];
            $zone = $supervisorZones[$supervisor] ?? 'Inconnu';

            // Cherche le puits dans la base par code
            $well = Well::where('code', $code)->first();

            if (!$well) {
                $notFound++;
                continue;
            }

            // Enrichit uniquement — ne crée pas de nouveau puits
            $updateData = [
            'village' => $village ?: $well->village,
            'region' => $region ?: $well->region,
            'department' => $department ?: $well->department,
            'commune' => $commune ?: $well->commune,
             ];

            if (!empty($supervisor)) {
                $updateData['supervisor'] = $supervisor;
                $updateData['zone'] = $zone !== 'Inconnu' ? $zone : $well->zone;
            }

            $well->update($updateData);
            // Debug temporaire
            if ($updated <= 3) {
                $this->info("Updated: code={$code}, supervisor={$supervisor}, well_supervisor=" . $well->fresh()->supervisor);
            }
            $updated++;
            }
        fclose($handle);

        $this->table(
            ['Mis à jour', 'Ignorés', 'Non trouvés en base'],
            [[$updated, $skipped, $notFound]]
        );

        $this->info('Import terminé !');
    }
}