<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Sauvegarde et vérification par restauration (plan, semaine 12 : « une restauration de
 * sauvegarde réussie » ; « jamais coupable : sauvegardes »).
 *
 * Une archive = base.sql (mysqldump, instantané cohérent) + fichiers privés + empreinte
 * (manifest.json) : nombre de lignes par table et SOMMES des registres (trésorerie, stock,
 * remboursements…). Vérifier = restaurer dans une base jetable, recalculer l'empreinte,
 * comparer. Une sauvegarde qu'on n'a jamais restaurée n'est pas une sauvegarde.
 */
class Sauvegardes
{
    /** Tables qui bougent sans cesse et ne portent aucune donnée métier. */
    private const TABLES_VOLATILES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations_lock'];

    /** Contrôles de sommes : ce qu'on ne doit JAMAIS perdre. */
    private const SOMMES = [
        'tresorerie_entrees_fcfa' => "SELECT COALESCE(SUM(montant_fcfa), 0) FROM mouvements_tresorerie WHERE sens = 'entree'",
        'tresorerie_sorties_fcfa' => "SELECT COALESCE(SUM(montant_fcfa), 0) FROM mouvements_tresorerie WHERE sens = 'sortie'",
        'stock_grammes' => 'SELECT COALESCE(SUM(grammes), 0) FROM mouvements_stock',
        'remboursements_fcfa' => 'SELECT COALESCE(SUM(montant_fcfa), 0) FROM remboursements',
        'decaissements_fcfa' => 'SELECT COALESCE(SUM(montant_fcfa), 0) FROM decaissements',
        'achats_fcfa' => 'SELECT COALESCE(SUM(montant_fcfa), 0) FROM achats',
        'prets_fcfa' => 'SELECT COALESCE(SUM(montant_fcfa), 0) FROM prets',
    ];

    /** Colonnes qui citent un fichier du disque privé : il doit être dans l'archive. */
    private const FICHIERS_CITES = [
        ['depenses', 'justificatif'],
        ['decaissements', 'justificatif'],
        ['prets', 'accord_ecrit'],
        ['producteurs', 'photo'],
        ['photos_terrain', 'chemin'],
    ];

    /** Crée l'archive et rend son chemin. */
    public function creer(): string
    {
        $motDePasse = config('sauvegardes.mot_de_passe');
        $minimum = (int) config('sauvegardes.mot_de_passe_longueur_min');
        if ($motDePasse !== null && mb_strlen((string) $motDePasse) < $minimum) {
            throw new RuntimeException("Mot de passe des sauvegardes trop court (moins de {$minimum} caractères) : en générer un avec « php artisan ly:mot-de-passe-sauvegardes ».");
        }

        $dossier = (string) config('sauvegardes.dossier');
        File::ensureDirectoryExists($dossier);
        $horodatage = now()->format('Ymd-His');
        $travail = $dossier.DIRECTORY_SEPARATOR.'.en-cours-'.$horodatage;
        File::ensureDirectoryExists($travail);

        try {
            // Empreinte avant ET après le dump : identiques, sinon quelqu'un a écrit pendant
            // la sauvegarde et l'empreinte ne décrirait pas l'instantané. On recommence.
            for ($essai = 1; ; $essai++) {
                $avant = $this->empreinte(DB::getDefaultConnection());
                $this->dump($travail.DIRECTORY_SEPARATOR.'base.sql');
                $apres = $this->empreinte(DB::getDefaultConnection());
                if ($avant === $apres) {
                    break;
                }
                if ($essai === 3) {
                    throw new RuntimeException('La base a changé pendant chacune des 3 tentatives de sauvegarde : réessayer à une heure calme.');
                }
            }

            $archive = $dossier.DIRECTORY_SEPARATOR.'ly-agricole-'.$horodatage.'.zip';
            $this->archiver($archive, $travail.DIRECTORY_SEPARATOR.'base.sql', $avant);
        } finally {
            File::deleteDirectory($travail);
        }

        $this->purger();

        return $archive;
    }

    /**
     * Restaure l'archive dans la base de vérification, recalcule l'empreinte, compare,
     * supprime la base. Ne touche JAMAIS à la base de production.
     *
     * @return array{ok: bool, archive: string, erreurs: list<string>, lignes: int, fichiers: int, verifie_at: string}
     */
    public function verifier(?string $archive = null): array
    {
        $archive ??= $this->derniere() ?? throw new RuntimeException('Aucune sauvegarde à vérifier dans '.config('sauvegardes.dossier').'.');
        $travail = dirname($archive).DIRECTORY_SEPARATOR.'.verification-'.Str::random(8);
        $erreurs = [];
        $manifeste = [];

        try {
            $this->extraire($archive, $travail);
            $manifeste = json_decode((string) file_get_contents($travail.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

            // 1. Intégrité : chaque fichier a l'empreinte SHA-256 notée à la sauvegarde.
            foreach ($manifeste['sha256'] as $chemin => $attendu) {
                $fichier = $travail.'/'.$chemin;
                if (! is_file($fichier) || hash_file('sha256', $fichier) !== $attendu) {
                    $erreurs[] = "Fichier altéré ou manquant dans l'archive : {$chemin}";
                }
            }

            // 2. Restauration réelle, puis mêmes comptes et mêmes sommes qu'à la sauvegarde.
            if ($erreurs === []) {
                $connexion = $this->restaurer($travail.'/base.sql');
                $obtenue = $this->empreinte($connexion);
                foreach (['tables', 'sommes'] as $partie) {
                    foreach ($manifeste['empreinte'][$partie] as $cle => $valeur) {
                        if (($obtenue[$partie][$cle] ?? null) !== $valeur) {
                            $erreurs[] = "Restauration : {$cle} = ".var_export($obtenue[$partie][$cle] ?? null, true).', attendu '.var_export($valeur, true).'.';
                        }
                    }
                }

                // 3. Chaque fichier cité par la base est bien dans l'archive.
                foreach ($this->fichiersCites($connexion) as $chemin) {
                    if (! is_file($travail.'/fichiers/'.$chemin)) {
                        $erreurs[] = "Fichier cité par la base absent de l'archive : {$chemin}";
                    }
                }
            }
        } catch (\Throwable $e) {
            $erreurs[] = $e->getMessage();
        } finally {
            $this->supprimerBaseVerification();
            File::deleteDirectory($travail);
        }

        $rapport = [
            'ok' => $erreurs === [],
            'archive' => basename($archive),
            'erreurs' => $erreurs,
            'lignes' => (int) array_sum($manifeste['empreinte']['tables'] ?? []),
            'fichiers' => count($manifeste['sha256'] ?? []) - 1,
            'verifie_at' => now()->toIso8601String(),
        ];
        File::put(config('sauvegardes.dossier').DIRECTORY_SEPARATOR.'derniere-verification.json', json_encode($rapport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $rapport;
    }

    /** @return array{ok: bool, archive: string, erreurs: list<string>, verifie_at: string}|null */
    public function derniereVerification(): ?array
    {
        $fichier = config('sauvegardes.dossier').DIRECTORY_SEPARATOR.'derniere-verification.json';

        return is_file($fichier) ? json_decode((string) file_get_contents($fichier), true) : null;
    }

    /**
     * Copie l'archive hors site, la RELIT et compare son SHA-256 : une copie qu'on n'a
     * pas relue ne compte pas. Purge les copies trop anciennes (les 7 dernières restent).
     *
     * @return array{ok: bool, configuree: bool, archive: string, cible: string|null, erreur: string|null, copie_at: string}
     */
    public function copierHorsSite(?string $archive = null): array
    {
        $archive ??= $this->derniere() ?? throw new RuntimeException('Aucune sauvegarde à copier.');
        $nom = basename($archive);
        $cible = config('sauvegardes.hors_site_disque') ?? config('sauvegardes.hors_site_dossier');
        $statut = ['ok' => false, 'configuree' => $cible !== null, 'archive' => $nom, 'cible' => $cible, 'erreur' => null, 'copie_at' => now()->toIso8601String()];

        try {
            $disque = $this->disqueHorsSite();
            if ($disque === null) {
                throw new RuntimeException('Copie hors site non configurée (SAUVEGARDE_HORS_SITE_DISQUE ou SAUVEGARDE_HORS_SITE_DOSSIER).');
            }

            $source = fopen($archive, 'rb');
            try {
                $disque->writeStream($nom, $source);
            } finally {
                is_resource($source) && fclose($source);
            }

            $relue = $disque->readStream($nom);
            $contexte = hash_init('sha256');
            hash_update_stream($contexte, $relue);
            is_resource($relue) && fclose($relue);
            if (hash_final($contexte) !== hash_file('sha256', $archive)) {
                $disque->delete($nom);
                throw new RuntimeException('La copie relue diffère de l\'archive : copie supprimée, à refaire.');
            }

            $this->purgerHorsSite($disque);
            $statut['ok'] = true;
        } catch (\Throwable $e) {
            $statut['erreur'] = $e->getMessage();
        }

        File::ensureDirectoryExists((string) config('sauvegardes.dossier'));
        File::put(config('sauvegardes.dossier').DIRECTORY_SEPARATOR.'derniere-copie-hors-site.json', json_encode($statut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $statut;
    }

    /** @return array{ok: bool, configuree: bool, archive: string, cible: string|null, erreur: string|null, copie_at: string}|null */
    public function derniereCopieHorsSite(): ?array
    {
        $fichier = config('sauvegardes.dossier').DIRECTORY_SEPARATOR.'derniere-copie-hors-site.json';

        return is_file($fichier) ? json_decode((string) file_get_contents($fichier), true) : null;
    }

    /**
     * Cette sauvegarde (mysqldump, disque du serveur) s'applique-t-elle à la base en service ?
     * Non sur PostgreSQL : c'est la production Vercel + Neon, protégée par l'historique de Neon.
     */
    public function gereLaBase(): bool
    {
        return DB::connection()->getDriverName() !== 'pgsql';
    }

    public function horsSiteConfiguree(): bool
    {
        return config('sauvegardes.hors_site_disque') !== null || config('sauvegardes.hors_site_dossier') !== null;
    }

    private function disqueHorsSite(): ?Filesystem
    {
        if (($nom = config('sauvegardes.hors_site_disque')) !== null) {
            return Storage::disk((string) $nom);
        }
        if (($dossier = config('sauvegardes.hors_site_dossier')) === null) {
            return null;
        }

        // Le même dossier que les sauvegardes locales n'est pas « hors site ».
        $local = realpath((string) config('sauvegardes.dossier'));
        File::ensureDirectoryExists((string) $dossier);
        if ($local !== false && realpath((string) $dossier) === $local) {
            throw new RuntimeException('Le dossier hors site est le dossier des sauvegardes locales : ce n\'est pas une copie hors site.');
        }

        return Storage::build(['driver' => 'local', 'root' => (string) $dossier, 'throw' => true]);
    }

    private function purgerHorsSite(Filesystem $disque): void
    {
        $archives = array_values(array_filter($disque->files(), fn (string $f) => (bool) preg_match('/^ly-agricole-\d{8}-\d{6}\.zip$/', basename($f))));
        rsort($archives);
        $limite = now()->subDays((int) config('sauvegardes.hors_site_conserver_jours'))->getTimestamp();

        foreach (array_slice($archives, 7) as $ancienne) {
            if ($disque->lastModified($ancienne) < $limite) {
                $disque->delete($ancienne);
            }
        }
    }

    public function derniere(): ?string
    {
        $archives = glob(config('sauvegardes.dossier').DIRECTORY_SEPARATOR.'ly-agricole-*.zip') ?: [];
        sort($archives);

        return $archives === [] ? null : end($archives);
    }

    /**
     * Comptes par table et sommes des registres : ce qui doit être identique après
     * restauration.
     *
     * @return array{tables: array<string, int>, sommes: array<string, int>}
     */
    public function empreinte(string $connexion): array
    {
        $schema = Schema::connection($connexion);
        // Base courante SEULEMENT : sans schéma, MySQL liste les tables de toutes les bases
        // du serveur (vu le 2026-09-29 avec d'autres projets sur le même XAMPP).
        $tables = collect($schema->getTableListing(schema: $schema->getCurrentSchemaListing(), schemaQualified: false))
            ->reject(fn (string $t) => in_array($t, self::TABLES_VOLATILES, true) || str_starts_with($t, 'sqlite_'))
            ->sort()->values();

        $comptes = [];
        foreach ($tables as $table) {
            $comptes[$table] = DB::connection($connexion)->table($table)->count();
        }

        $sommes = [];
        foreach (self::SOMMES as $cle => $sql) {
            $table = Str::of($sql)->after('FROM ')->before(' ')->value();
            $sommes[$cle] = $tables->contains($table) ? (int) DB::connection($connexion)->scalar($sql) : 0;
        }

        return ['tables' => $comptes, 'sommes' => $sommes];
    }

    protected function dump(string $fichier): void
    {
        $c = config('database.connections.'.DB::getDefaultConnection());
        if (($c['driver'] ?? null) !== 'mysql' && ($c['driver'] ?? null) !== 'mariadb') {
            throw new RuntimeException('Sauvegarde prévue pour MySQL / MariaDB seulement.');
        }

        $resultat = Process::env(['MYSQL_PWD' => (string) ($c['password'] ?? '')])->timeout(3600)->run([
            (string) config('sauvegardes.mysqldump'),
            '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            // Instantané cohérent sans verrouiller l'application (InnoDB).
            '--single-transaction', '--quick', '--routines', '--triggers', '--hex-blob',
            '--default-character-set=utf8mb4',
            '--result-file='.$fichier,
            (string) $c['database'],
        ]);

        if (! $resultat->successful() || ! is_file($fichier) || filesize($fichier) === 0) {
            throw new RuntimeException('mysqldump a échoué : '.trim($resultat->errorOutput() ?: $resultat->output()));
        }
    }

    /** Restaure base.sql dans la base de vérification ; rend le nom de connexion. */
    protected function restaurer(string $sql): string
    {
        $c = config('database.connections.'.DB::getDefaultConnection());
        $base = (string) config('sauvegardes.base_verification');
        if ($base === $c['database']) {
            throw new RuntimeException('La base de vérification ne peut pas être la base de production.');
        }

        DB::statement("DROP DATABASE IF EXISTS `{$base}`");
        DB::statement("CREATE DATABASE `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $resultat = Process::env(['MYSQL_PWD' => (string) ($c['password'] ?? '')])->timeout(3600)->run([
            (string) config('sauvegardes.mysql'),
            '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
            '--default-character-set=utf8mb4',
            $base,
            '--execute=source '.str_replace('\\', '/', $sql),
        ]);
        if (! $resultat->successful()) {
            throw new RuntimeException('Restauration impossible : '.trim($resultat->errorOutput() ?: $resultat->output()));
        }

        config(['database.connections.verification_sauvegarde' => array_merge($c, ['database' => $base])]);
        DB::purge('verification_sauvegarde');

        return 'verification_sauvegarde';
    }

    private function supprimerBaseVerification(): void
    {
        $c = config('database.connections.'.DB::getDefaultConnection());
        if (in_array($c['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            DB::purge('verification_sauvegarde');
            DB::statement('DROP DATABASE IF EXISTS `'.config('sauvegardes.base_verification').'`');
        }
    }

    /**
     * @param  array{tables: array<string, int>, sommes: array<string, int>}  $empreinte
     */
    private function archiver(string $archive, string $sql, array $empreinte): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
            throw new RuntimeException("Impossible de créer l'archive {$archive}.");
        }
        $motDePasse = config('sauvegardes.mot_de_passe');
        if ($motDePasse !== null) {
            $zip->setPassword((string) $motDePasse);
        }

        $sha = ['base.sql' => (string) hash_file('sha256', $sql)];
        $this->ajouter($zip, $sql, 'base.sql', $motDePasse);

        $disque = Storage::disk('local');
        foreach ($disque->allFiles() as $chemin) {
            if (str_starts_with(basename($chemin), '.')) {
                continue;
            }
            $sha['fichiers/'.$chemin] = (string) hash_file('sha256', $disque->path($chemin));
            $this->ajouter($zip, $disque->path($chemin), 'fichiers/'.$chemin, $motDePasse);
        }

        $zip->addFromString('manifest.json', (string) json_encode([
            'application' => 'LY AGRICOLE',
            'cree_at' => now()->toIso8601String(),
            'base' => config('database.connections.'.DB::getDefaultConnection().'.database'),
            'chiffree' => $motDePasse !== null,
            // Dit QUEL mot de passe ouvre l'archive (après un changement) sans le révéler.
            'empreinte_mot_de_passe' => $motDePasse === null ? null : self::empreinteMotDePasse((string) $motDePasse),
            'empreinte' => $empreinte,
            'sha256' => $sha,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // Le manifeste reste lisible sans mot de passe : il ne contient que des comptes.

        if (! $zip->close()) {
            throw new RuntimeException("Écriture de l'archive {$archive} impossible (disque plein ?).");
        }
    }

    private function ajouter(ZipArchive $zip, string $source, string $nom, mixed $motDePasse): void
    {
        $zip->addFile($source, $nom);
        if ($motDePasse !== null) {
            $zip->setEncryptionName($nom, ZipArchive::EM_AES_256);
        }
    }

    /**
     * Empreinte lente (PBKDF2, 200 000 tours) : assez pour reconnaître le bon mot de
     * passe, trop coûteuse pour le deviner à partir du manifeste (lisible sans lui).
     */
    public static function empreinteMotDePasse(string $motDePasse): string
    {
        return hash_pbkdf2('sha256', $motDePasse, 'ly-agricole-sauvegarde', 200_000, 16);
    }

    private function extraire(string $archive, string $vers): void
    {
        $zip = new ZipArchive;
        if ($zip->open($archive) !== true) {
            throw new RuntimeException("Archive illisible : {$archive}");
        }

        // Le manifeste n'est pas chiffré : on sait tout de suite si le mot de passe est le bon.
        $manifeste = json_decode((string) $zip->getFromName('manifest.json'), true) ?: [];
        $motDePasse = config('sauvegardes.mot_de_passe');
        if (($manifeste['chiffree'] ?? false) === true) {
            if ($motDePasse === null) {
                $zip->close();
                throw new RuntimeException('Archive chiffrée : définir SAUVEGARDE_MOT_DE_PASSE pour la lire.');
            }
            $attendue = $manifeste['empreinte_mot_de_passe'] ?? null;
            if ($attendue !== null && $attendue !== self::empreinteMotDePasse((string) $motDePasse)) {
                $zip->close();
                throw new RuntimeException("Mot de passe différent de celui de l'archive (empreinte attendue {$attendue}) : un ancien mot de passe ?");
            }
            $zip->setPassword((string) $motDePasse);
        }
        File::ensureDirectoryExists($vers);
        if (! $zip->extractTo($vers)) {
            $zip->close();
            throw new RuntimeException('Extraction impossible (mot de passe faux ou archive abîmée).');
        }
        $zip->close();
    }

    /** @return list<string> */
    private function fichiersCites(string $connexion): array
    {
        $chemins = [];
        foreach (self::FICHIERS_CITES as [$table, $colonne]) {
            if (Schema::connection($connexion)->hasTable($table)) {
                $chemins = [...$chemins, ...DB::connection($connexion)->table($table)->whereNotNull($colonne)->pluck($colonne)->map(fn ($c) => (string) $c)->all()];
            }
        }

        return array_values(array_unique($chemins));
    }

    /** Supprime les archives trop anciennes, en gardant toujours les 7 plus récentes. */
    private function purger(): void
    {
        $archives = glob(config('sauvegardes.dossier').DIRECTORY_SEPARATOR.'ly-agricole-*.zip') ?: [];
        rsort($archives);
        $limite = Carbon::now()->subDays((int) config('sauvegardes.conserver_jours'))->getTimestamp();

        foreach (array_slice($archives, 7) as $ancienne) {
            if (filemtime($ancienne) < $limite) {
                File::delete($ancienne);
            }
        }
    }
}
