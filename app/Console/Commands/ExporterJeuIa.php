<?php

namespace App\Console\Commands;

use App\Enums\StatutDiagnostic;
use App\Models\Diagnostic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Jeu d'entraînement du modèle de vision (skill ly-agricole-ia-conseil, boucle
 * d'apprentissage) : SEULEMENT les photos confirmées ou corrigées par un agronome.
 *
 * dossier/<culture>/<classe>/<photo>.jpg + manifeste.csv. Pas de nom de producteur, pas de
 * position GPS : une photo et sa classe suffisent à entraîner. Chaque photo a une place
 * FIXE (entraînement ou test), tirée de son identifiant : le jeu de test ne change pas d'un
 * export à l'autre, et un nouveau modèle se compare à l'ancien sur les mêmes photos.
 */
class ExporterJeuIa extends Command
{
    protected $signature = 'ia:exporter-jeu {dossier : dossier de sortie (vidé puis rempli)}
        {--avec-provisoires : ajouter les annotations provisoires (pas un agronome ; jamais au jeu de test)}';

    protected $description = 'Exporter les photos validées par un agronome pour entraîner le modèle de vision';

    /** Une photo sur dix va au jeu de test. */
    public const PART_TEST = 10;

    public function handle(): int
    {
        $dossier = rtrim((string) $this->argument('dossier'), '/\\');
        $provisoires = (bool) $this->option('avec-provisoires');
        $diagnostics = Diagnostic::query()->with('photo', 'visite.parcelle.produit')
            ->where(fn ($q) => $q->whereIn('statut', [StatutDiagnostic::Confirme, StatutDiagnostic::Corrige])
                ->when($provisoires, fn ($q) => $q->orWhere(fn ($p) => $p->whereIn('statut', [StatutDiagnostic::Propose, StatutDiagnostic::Incertain])
                    ->whereNotNull('annotation_classe'))))
            ->orderBy('id')->get();

        if ($diagnostics->isEmpty()) {
            $this->warn($provisoires
                ? 'Aucune photo validée ni annotée : rien à exporter.'
                : 'Aucune photo validée par un agronome : rien à exporter (et rien à entraîner). Les annotations provisoires s\'ajoutent avec --avec-provisoires.');

            return self::SUCCESS;
        }
        if ($provisoires) {
            $this->warn('Annotations PROVISOIRES incluses (pas un agronome) : réservées à l\'entraînement, jamais au jeu de test ; un modèle qui en dépend reste « incertain ».');
        }

        File::deleteDirectory($dossier);
        File::ensureDirectoryExists($dossier);
        $manifeste = fopen($dossier.DIRECTORY_SEPARATOR.'manifeste.csv', 'w');
        if ($manifeste === false) {
            $this->error("Impossible d'écrire dans « {$dossier} ».");

            return self::FAILURE;
        }
        fputcsv($manifeste, ['fichier', 'culture', 'classe', 'jeu', 'statut', 'valide_le', 'source'], ';', '"', '');

        $parClasse = [];
        $manquantes = 0;
        foreach ($diagnostics as $d) {
            $contenu = Storage::disk('local')->get($d->photo->chemin);
            if ($contenu === null) {
                $manquantes++;

                continue;
            }
            $valide = $d->statut->valide();
            $culture = Str::slug($d->visite->parcelle->produit->nom ?? 'inconnue');
            $classe = Str::slug((string) ($valide ? $d->classe_retenue : $d->annotation_classe));
            // Le jeu de test ne contient que des validations d'agronome (et reste fixe).
            $jeu = $valide && hexdec(substr(md5($d->photo_id), 0, 6)) % self::PART_TEST === 0 ? 'test' : 'entrainement';
            $relatif = "{$culture}/{$classe}/{$d->photo_id}.jpg";

            File::ensureDirectoryExists(dirname($dossier.'/'.$relatif));
            File::put($dossier.'/'.$relatif, $contenu);
            fputcsv($manifeste, [$relatif, $culture, $classe, $jeu, $d->statut->value, ($valide ? $d->valide_at : $d->annotation_at)?->toDateString(),
                $valide ? 'agronome' : 'provisoire:'.$d->annotation_source], ';', '"', '');
            $parClasse["{$culture}/{$classe}"][$jeu] = ($parClasse["{$culture}/{$classe}"][$jeu] ?? 0) + 1;
        }
        fclose($manifeste);

        $this->table(['Classe', 'Entraînement', 'Test'], collect($parClasse)->map(fn (array $n, string $c) => [$c, $n['entrainement'] ?? 0, $n['test'] ?? 0])->values()->all());
        if ($manquantes > 0) {
            $this->warn("{$manquantes} photo(s) introuvable(s) sur le disque, ignorée(s).");
        }
        $this->info("Jeu exporté dans {$dossier} (manifeste.csv). Une classe avec peu de photos de test se juge mal : regarder la matrice de confusion par classe.");

        return self::SUCCESS;
    }
}
