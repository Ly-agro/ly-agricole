<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CopierSauvegarde extends Command
{
    protected $signature = 'ly:copier-sauvegarde {archive? : chemin de l\'archive ; la plus récente par défaut}';

    protected $description = 'Copie une sauvegarde hors site, la relit et compare son empreinte (relance après un échec).';

    public function handle(Sauvegardes $sauvegardes): int
    {
        $copie = $sauvegardes->copierHorsSite($this->argument('archive'));

        if ($copie['ok']) {
            $this->info("Copie hors site relue et identique : {$copie['archive']} → {$copie['cible']}");

            return self::SUCCESS;
        }

        Log::critical('Copie hors site échouée', $copie);
        $this->error('Copie hors site échouée : '.$copie['erreur']);

        return self::FAILURE;
    }
}
