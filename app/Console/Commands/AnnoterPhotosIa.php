<?php

namespace App\Console\Commands;

use App\Enums\StatutDiagnostic;
use App\Exceptions\OperationRefusee;
use App\Models\Diagnostic;
use App\Services\Ia\Diagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Annotation PROVISOIRE des photos en attendant un agronome (réponse 55) :
 *
 *   php artisan ia:annoter                       liste les photos à annoter (chemin du fichier,
 *                                                culture, observation de la visite)
 *   php artisan ia:annoter 12 anthracnose --note="taches brunes en bordure"
 *
 * Utilisé pendant une session Claude : la session regarde la photo et propose une classe.
 * Ce n'est PAS une validation (Claude n'est pas agronome) : le statut ne change pas.
 */
class AnnoterPhotosIa extends Command
{
    protected $signature = 'ia:annoter {diagnostic? : numéro du diagnostic} {classe? : maladie, ravageur ou « sain »}
        {--note= : ce qui justifie la classe} {--source=claude : qui annote} {--limite=20 : photos listées}';

    protected $description = 'Lister ou annoter provisoirement les photos en attente d\'un agronome';

    public function handle(): int
    {
        if ($this->argument('diagnostic') === null) {
            return $this->lister();
        }

        $d = Diagnostic::query()->find((int) $this->argument('diagnostic'));
        if ($d === null) {
            $this->error('Diagnostic introuvable.');

            return self::FAILURE;
        }
        try {
            Diagnostics::annoterProvisoirement($d, (string) $this->argument('classe'), (string) $this->option('source'), $this->option('note'));
        } catch (OperationRefusee $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info("Diagnostic {$d->id} : annotation provisoire « {$d->annotation_classe} » ({$d->annotation_source}). Statut inchangé : {$d->statut->libelle()}.");

        return self::SUCCESS;
    }

    private function lister(): int
    {
        $a = Diagnostic::query()->with('photo', 'visite.parcelle.produit')
            ->whereIn('statut', [StatutDiagnostic::Propose, StatutDiagnostic::Incertain])
            ->whereNull('annotation_classe')->orderBy('id')->limit((int) $this->option('limite'))->get();

        if ($a->isEmpty()) {
            $this->info('Aucune photo à annoter.');

            return self::SUCCESS;
        }
        // Pas de nom de producteur : la photo, la culture et l'observation suffisent.
        $this->table(['N°', 'Culture', 'Proposition du modèle', 'Observation de la visite', 'Fichier'], $a->map(fn (Diagnostic $d) => [
            $d->id,
            $d->visite->parcelle->produit->nom ?? '?',
            $d->classe_proposee ?? '—',
            mb_strimwidth((string) $d->visite->observations, 0, 60, '…'),
            Storage::disk('local')->path($d->photo->chemin),
        ])->all());

        return self::SUCCESS;
    }
}
