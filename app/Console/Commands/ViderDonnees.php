<?php

namespace App\Console\Commands;

use App\Support\Fichiers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Vide les données d'essai (demande du 2026-10-08 : « on fait des tests en production »).
 * Garde les comptes, les produits, les prix publiés, les actualités, les paramètres et, sauf
 * --referentiels, les référentiels. Contourne volontairement les registres immuables : c'est
 * une remise à zéro, pas une correction. Demande de taper VIDER ; rien n'est fait sans.
 */
class ViderDonnees extends Command
{
    protected $signature = 'ly:vider-donnees
        {--referentiels : vide aussi zones, villages, magasins, points de collecte, pisteurs, catégories de dépense et intrants}
        {--fichiers : supprime aussi les fichiers envoyés (justificatifs, reçus, accords, photos)}
        {--confirmation= : « VIDER », pour un lancement sans question (avec --force)}
        {--force : accepter de lancer en production}';

    protected $description = 'Vide les données d\'essai (opérations, campagnes, producteurs…) en gardant comptes, produits, prix publiés et paramètres.';

    /** Données d'essai : toutes les opérations et ce qui en dépend. */
    public const OPERATIONS = [
        'achats', 'alertes_envoyees', 'apports', 'campagnes', 'comptes_tresorerie', 'compteurs', 'confirmations_sms',
        'decaissements', 'decisions_plafond', 'depenses', 'diagnostics', 'encaissements', 'groupes_producteurs',
        'journal_activite', 'lignes_budget', 'lots', 'mouvements_intrants', 'mouvements_stock', 'mouvements_tresorerie',
        'notifications', 'operations_recues', 'parcelles', 'photos_terrain', 'pret_parcelle', 'prets', 'producteurs',
        'remboursements', 'synchronisations', 'validations_pret', 'valorisations_stock', 'ventes', 'visite_photo', 'visites',
        'jobs', 'failed_jobs', 'job_batches', 'cache', 'cache_locks',
    ];

    public const REFERENTIELS = [
        'zones', 'villages', 'magasins', 'points_collecte', 'pisteurs', 'categories_depense', 'intrants',
    ];

    /** Dossiers des fichiers envoyés, sur le disque des fichiers (local ou R2). */
    public const DOSSIERS = ['depenses', 'prets', 'producteurs', 'terrain', 'diagnostic'];

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Base de PRODUCTION : relancer avec --force si c\'est bien voulu.');

            return self::FAILURE;
        }

        $tables = array_values(array_filter(
            [...self::OPERATIONS, ...($this->option('referentiels') ? self::REFERENTIELS : [])],
            fn (string $t) => Schema::hasTable($t),
        ));

        $lignes = [];
        foreach ($tables as $table) {
            $lignes[] = [$table, DB::table($table)->count()];
        }
        $this->warn('Base : '.config('database.default').' — '.DB::connection()->getDatabaseName());
        $this->table(['Table vidée', 'Lignes'], $lignes);
        $this->line('Gardés : comptes, produits, prix publiés, actualités, paramètres'.($this->option('referentiels') ? '.' : ', référentiels.'));
        if ($this->option('fichiers')) {
            $this->line('Fichiers supprimés sur le disque « '.Fichiers::disque().' » : '.implode(', ', self::DOSSIERS).'.');
        }

        $confirmation = $this->option('confirmation') ?? ($this->input->isInteractive() ? $this->ask('Taper VIDER pour confirmer') : null);
        if ($confirmation !== 'VIDER') {
            $this->info('Rien n\'a été vidé.');

            return self::FAILURE;
        }

        try {
            $this->vider($tables);
        } catch (Throwable $e) {
            $this->error('Échec, rien n\'a été vidé : '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fichiers')) {
            foreach (self::DOSSIERS as $dossier) {
                Storage::disk(Fichiers::disque())->deleteDirectory($dossier);
            }
        }

        Log::warning('Données d\'essai vidées', ['tables' => $tables, 'fichiers' => (bool) $this->option('fichiers')]);
        $this->info(count($tables).' tables vidées.');

        return self::SUCCESS;
    }

    /** @param  list<string>  $tables */
    private function vider(array $tables): void
    {
        $connexion = DB::connection();

        match ($connexion->getDriverName()) {
            // Toutes ensemble : PostgreSQL vérifie les clés étrangères sur l'ensemble, et refuse
            // (sans rien vider) si une table gardée pointe encore vers une table vidée.
            'pgsql' => $connexion->transaction(fn () => $connexion->statement(
                'TRUNCATE TABLE '.implode(', ', array_map(fn ($t) => '"'.$t.'"', $tables)).' RESTART IDENTITY'
            )),
            'mysql', 'mariadb' => $this->sansClesEtrangeres(
                fn () => array_map(fn ($t) => $connexion->table($t)->truncate(), $tables),
                'SET FOREIGN_KEY_CHECKS=0', 'SET FOREIGN_KEY_CHECKS=1',
            ),
            // SQLite (postes, tests) : clés vérifiées à la fin de la transaction seulement.
            default => $connexion->transaction(function () use ($connexion, $tables) {
                $connexion->statement('PRAGMA defer_foreign_keys = ON');
                foreach ($tables as $t) {
                    $connexion->table($t)->delete();
                }
                if (Schema::hasTable('sqlite_sequence')) {
                    $connexion->table('sqlite_sequence')->whereIn('name', $tables)->delete();
                }
            }),
        };
    }

    private function sansClesEtrangeres(callable $travail, string $couper, string $remettre): void
    {
        DB::statement($couper);
        try {
            $travail();
        } finally {
            DB::statement($remettre);
        }
    }
}
