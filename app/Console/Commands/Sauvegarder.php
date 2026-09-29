<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class Sauvegarder extends Command
{
    protected $signature = 'ly:sauvegarder {--verifier : restaurer aussitôt l\'archive dans une base jetable et la comparer}';

    protected $description = 'Sauvegarde la base et les fichiers privés dans une archive datée (et la vérifie par restauration).';

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

        return $this->option('verifier') ? $this->call('ly:verifier-sauvegarde', ['archive' => $archive]) : self::SUCCESS;
    }
}
