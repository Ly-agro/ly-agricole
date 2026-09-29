<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifierSauvegarde extends Command
{
    protected $signature = 'ly:verifier-sauvegarde {archive? : chemin de l\'archive ; la plus récente par défaut}';

    protected $description = 'Restaure une sauvegarde dans une base jetable, compare comptes et sommes, puis supprime la base.';

    public function handle(Sauvegardes $sauvegardes): int
    {
        $rapport = $sauvegardes->verifier($this->argument('archive'));

        if ($rapport['ok']) {
            $this->info("Restauration vérifiée : {$rapport['archive']} — {$rapport['lignes']} lignes, {$rapport['fichiers']} fichier(s) : identiques.");
            Log::info('Sauvegarde vérifiée par restauration', $rapport);

            return self::SUCCESS;
        }

        foreach ($rapport['erreurs'] as $erreur) {
            $this->error($erreur);
        }
        Log::critical('Vérification de sauvegarde ÉCHOUÉE', $rapport);

        return self::FAILURE;
    }
}
