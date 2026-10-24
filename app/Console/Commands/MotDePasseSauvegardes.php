<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Génère le mot de passe des archives et l'écrit dans .env. Il s'affiche UNE fois : le
 * noter hors du serveur (coffre, papier chez la direction). Sans lui, aucune archive
 * chiffrée ne peut être restaurée — y compris par nous.
 */
class MotDePasseSauvegardes extends Command
{
    protected $signature = 'ly:mot-de-passe-sauvegardes
        {--remplacer : remplacer un mot de passe existant (les anciennes archives gardent l\'ancien)}
        {--sans-afficher : ne pas afficher le mot de passe (essais uniquement)}';

    protected $description = 'Génère le mot de passe de chiffrement des sauvegardes et l\'écrit dans .env.';

    public function handle(): int
    {
        $env = app()->environmentFilePath();
        if (! is_file($env)) {
            $this->error('Fichier .env introuvable.');

            return self::FAILURE;
        }

        $contenu = (string) file_get_contents($env);
        $existant = preg_match('/^SAUVEGARDE_MOT_DE_PASSE=(.+)$/m', $contenu, $m) === 1 && trim($m[1]) !== '';
        if ($existant && ! $this->option('remplacer')) {
            $this->error('Un mot de passe existe déjà. Le remplacer rend les anciennes archives illisibles sans l\'ancien : relancer avec --remplacer si c\'est voulu.');

            return self::FAILURE;
        }

        // Lettres et chiffres seulement : aucun caractère que .env ou le shell interpréterait.
        $motDePasse = Str::password(32, symbols: false);
        $ligne = 'SAUVEGARDE_MOT_DE_PASSE='.$motDePasse;
        $contenu = preg_match('/^SAUVEGARDE_MOT_DE_PASSE=.*$/m', $contenu) === 1
            ? (string) preg_replace('/^SAUVEGARDE_MOT_DE_PASSE=.*$/m', $ligne, $contenu)
            : rtrim($contenu)."\n".$ligne."\n";
        File::put($env, $contenu);

        $this->info('Mot de passe des sauvegardes écrit dans .env (empreinte '.Sauvegardes::empreinteMotDePasse($motDePasse).').');
        if (! $this->option('sans-afficher')) {
            $this->newLine();
            $this->line('  '.$motDePasse);
            $this->newLine();
            $this->warn('Le noter MAINTENANT hors du serveur (coffre, papier chez la direction) : il ne sera plus affiché.');
        }
        $this->line('Si la configuration est en cache : php artisan config:cache');

        return self::SUCCESS;
    }
}
