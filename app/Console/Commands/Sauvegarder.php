<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class Sauvegarder extends Command
{
    protected $signature = 'ly:sauvegarder {--verifier : restaurer aussitôt l\'archive dans une base jetable et la comparer}';

    protected $description = 'Sauvegarde la base et les fichiers privés, copie l\'archive hors site (et la vérifie par restauration).';

    public function handle(Sauvegardes $sauvegardes): int
    {
        try {
            $archive = $sauvegardes->creer();
        } catch (Throwable $e) {
            Log::critical('Sauvegarde échouée', ['erreur' => $e->getMessage()]);
            $this->error('Sauvegarde échouée : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sauvegarde créée : '.$archive.' ('.number_format((int) filesize($archive) / 1024, 0, ',', ' ').' Ko)');
        $resultat = self::SUCCESS;

        if ($sauvegardes->horsSiteConfiguree()) {
            $copie = $sauvegardes->copierHorsSite($archive);
            if ($copie['ok']) {
                $this->info("Copie hors site relue et identique : {$copie['cible']}");
            } else {
                // L'archive locale reste : la copie se relance avec ly:copier-sauvegarde.
                Log::critical('Copie hors site échouée', $copie);
                $this->error('Copie hors site échouée : '.$copie['erreur']);
                $resultat = self::FAILURE;
            }
        } else {
            $this->warn('Pas de copie hors site : SAUVEGARDE_HORS_SITE_DISQUE ou SAUVEGARDE_HORS_SITE_DOSSIER non défini.');
        }

        if ($this->option('verifier') && $this->call('ly:verifier-sauvegarde', ['archive' => $archive]) !== self::SUCCESS) {
            $resultat = self::FAILURE;
        }

        return $resultat;
    }
}
