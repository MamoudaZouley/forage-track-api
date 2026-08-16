<?php

namespace App\Console\Commands;

use App\Services\KoboSyncService;
use Illuminate\Console\Command;

class SyncKoboData extends Command
{
    protected $signature = 'kobo:sync';
    protected $description = 'Synchronise les données depuis KoboToolbox';

    public function __construct(private KoboSyncService $koboSync)
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info('Démarrage de la synchronisation KoboToolbox...');

        $this->info('Synchronisation des supervisions...');
        $supStats = $this->koboSync->syncSupervisions();
        $this->table(
            ['Importées', 'Ignorées', 'Erreurs'],
            [[$supStats['imported'], $supStats['skipped'], $supStats['errors']]]
        );

        $this->info('Synchronisation des maintenances...');
        $mainStats = $this->koboSync->syncMaintenances();
        $this->table(
            ['Importées', 'Ignorées', 'Erreurs'],
            [[$mainStats['imported'], $mainStats['skipped'], $mainStats['errors']]]
        );

        $this->info('Synchronisation de l\'historique des supervisions...');
        $histStats = $this->koboSync->syncSupervisionHistory();
        $this->table(
            ['Importées', 'Ignorées', 'Erreurs'],
            [[$histStats['imported'], $histStats['skipped'], $histStats['errors']]]
        );

        $this->info('Synchronisation terminée !');
        return Command::SUCCESS;


    }
}