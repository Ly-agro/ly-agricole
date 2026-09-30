<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\OperationRefusee;
use App\Models\Apport;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\MouvementTresorerie;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Apports de campagne (registre immuable 🔒), contrat art. 5 (compte dédié) et art. 9
 * (apport de LY facultatif). L'argent va toujours sur le compte de trésorerie **dédié à
 * la campagne** : un apport sur un compte sans lien avec elle est refusé.
 *
 * Ne calcule PAS le résultat net ni les quotes-parts (contrat art. 10 à 14) : le texte
 * exact de ces articles n'est pas disponible (question ouverte n° 15 bis, 2026-12-12).
 * Ce service ne fait que tracer qui a apporté combien, à quelle campagne.
 */
class Apports
{
    /** @param  int|null  $investisseurId  null = apport de LY elle-même. */
    public static function enregistrer(?int $investisseurId, int $campagneId, int $compteId, int $montant, Carbon $date, User $auteur, ?string $motif = null): Apport
    {
        if (! $auteur->can('gerer-apports')) {
            throw new OperationRefusee('Votre rôle ne permet pas d\'enregistrer un apport.');
        }

        return DB::transaction(function () use ($investisseurId, $campagneId, $compteId, $montant, $date, $auteur, $motif) {
            $campagne = Campagne::query()->findOrFail($campagneId);
            $compte = CompteTresorerie::query()->findOrFail($compteId);

            if ($compte->campagne_id !== $campagne->id) {
                throw new OperationRefusee("Un apport va sur le compte dédié à la campagne {$campagne->code} (contrat art. 5) : "
                    ."« {$compte->nom} » n'est pas rattaché à cette campagne.");
            }
            if ($montant <= 0) {
                throw new OperationRefusee('Le montant apporté doit être supérieur à zéro.');
            }

            $investisseur = null;
            if ($investisseurId !== null) {
                $investisseur = User::query()->findOrFail($investisseurId);
                if (! $investisseur->aLeRole(Role::Investisseur)) {
                    throw new OperationRefusee("{$investisseur->nom} n'a pas le rôle investisseur.");
                }
            }

            // La ligne immuable se crée en un seul appel, mouvement déjà en main : le
            // mouvement ne peut pas référencer l'apport (qui n'existe pas encore), il
            // référence la campagne, seule chose déjà stable à ce stade.
            $mouvement = Tresorerie::enregistrerApport($campagne, $investisseur, $compte, $montant, $date, $auteur);

            return Apport::query()->create([
                'investisseur_id' => $investisseur?->id,
                'campagne_id' => $campagne->id,
                'montant_fcfa' => $montant,
                'date_apport' => $date->toDateString(),
                'motif' => filled($motif) ? trim($motif) : null,
                'mouvement_id' => $mouvement->id,
                'cree_par' => $auteur->id,
            ]);
        });
    }

    /** Annule un apport par une ligne négative ; l'argent ressort aussi de la trésorerie. */
    public static function contrePasser(Apport $apport, string $motif, User $auteur): Apport
    {
        if (! $auteur->can('gerer-apports')) {
            throw new OperationRefusee('Votre rôle ne permet pas de corriger un apport.');
        }
        if (mb_strlen(trim($motif)) < 5) {
            throw new OperationRefusee('Le motif est obligatoire (5 caractères au moins).');
        }

        return DB::transaction(function () use ($apport, $motif, $auteur) {
            $original = Apport::query()->lockForUpdate()->findOrFail($apport->id);
            if (Apport::query()->where('annule_id', $original->id)->exists()) {
                throw new OperationRefusee('Cet apport a déjà été contre-passé.');
            }

            $mouvementInverse = null;
            if ($original->mouvement_id !== null) {
                $mouvementInverse = Tresorerie::contrePasser(
                    MouvementTresorerie::query()->findOrFail($original->mouvement_id), trim($motif), $auteur, depuisOrigine: true,
                )[0];
            }

            return Apport::query()->create([
                'investisseur_id' => $original->investisseur_id,
                'campagne_id' => $original->campagne_id,
                'montant_fcfa' => -$original->montant_fcfa,
                'date_apport' => Carbon::today()->toDateString(),
                'motif' => trim($motif),
                'mouvement_id' => $mouvementInverse?->id,
                'annule_id' => $original->id,
                'cree_par' => $auteur->id,
            ]);
        });
    }

    /**
     * Répartition des apports d'une campagne : chaque investisseur, son apport net (les
     * contre-passations le compensent), et sa part de l'ensemble des apports
     * d'investisseurs (PAS une quote-part du résultat — voir l'avertissement en tête de
     * fichier). Triée par apport décroissant.
     *
     * @return array{parLy: int, parInvestisseurs: int, lignes: Collection<int, array{investisseur: User, montant: int, part_pour_mille: int}>}
     */
    public static function repartition(Campagne $campagne): array
    {
        $parLy = (int) Apport::query()->where('campagne_id', $campagne->id)->whereNull('investisseur_id')->sum('montant_fcfa');

        $parInvestisseur = Apport::query()->where('campagne_id', $campagne->id)->whereNotNull('investisseur_id')
            ->groupBy('investisseur_id')->selectRaw('investisseur_id, SUM(montant_fcfa) AS total')
            ->pluck('total', 'investisseur_id')->map(fn ($t) => (int) $t)->filter(fn (int $t) => $t > 0);

        $total = (int) $parInvestisseur->sum();
        $utilisateurs = User::query()->whereKey($parInvestisseur->keys())->get()->keyBy('id');

        $lignes = $parInvestisseur->map(fn (int $montant, int $id) => [
            'investisseur' => $utilisateurs->get($id) ?? throw new OperationRefusee("Investisseur #$id introuvable."),
            'montant' => $montant,
            // Millièmes plutôt que pourcentage entier : plus précis pour de petits apports.
            'part_pour_mille' => $total > 0 ? intdiv($montant * 1000, $total) : 0,
        ])->sortByDesc('montant')->values();

        return ['parLy' => $parLy, 'parInvestisseurs' => $total, 'lignes' => $lignes];
    }
}
