<?php

namespace App\Http\Controllers\Api;

use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\StatutPret;
use App\Http\Controllers\Controller;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\GroupeProducteur;
use App\Models\Lot;
use App\Models\Pisteur;
use App\Models\PointCollecte;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\Produit;
use App\Models\User;
use App\Models\Village;
use App\Services\Depenses;
use App\Services\Synchronisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Échanges avec l'appli terrain (skill terrain-hors-ligne).
 */
class TerrainController extends Controller
{
    /**
     * Référentiels à garder sur le téléphone. `depuis` (horodatage rendu par l'appel
     * précédent) ne renvoie que ce qui a changé ; les désactivés reviennent avec
     * `actif = false` pour être retirés. Les prêts en cours sont toujours envoyés en
     * entier : leur restant dû change à chaque remboursement.
     */
    public function referentiels(Request $request): JsonResponse
    {
        $request->validate(['depuis' => ['nullable', 'date']]);
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->can('saisir-achats') || $user->can('gerer-producteurs'), 403);

        // Pris AVANT les lectures : une modification pendant la lecture reviendra au
        // prochain appel plutôt que d'être perdue.
        $horodatage = now();
        $depuis = $request->filled('depuis') ? Carbon::parse((string) $request->input('depuis')) : null;

        return response()->json([
            'horodatage' => $horodatage->toIso8601String(),
            'complet' => $depuis === null,
            'villages' => $this->delta(Village::query(), $depuis)->get(['id', 'zone_id', 'nom', 'actif']),
            'groupes' => $this->delta(GroupeProducteur::query(), $depuis)->get(['id', 'village_id', 'nom', 'actif']),
            'produits' => $this->delta(Produit::query(), $depuis)->get(['id', 'code', 'nom', 'actif']),
            'campagnes' => $this->delta(Campagne::query(), $depuis)->get(['id', 'produit_id', 'code', 'debut', 'fin', 'statut', 'prix_officiel_kg_fcfa'])
                ->map(fn (Campagne $c) => $c->toArray() + ['actif' => $c->statut === StatutCampagne::Ouverte]),
            'lots' => $this->delta(Lot::query(), $depuis)->get(['id', 'code', 'produit_id', 'campagne_id', 'magasin_id', 'statut'])
                ->map(fn (Lot $l) => $l->toArray() + ['actif' => $l->statut === StatutLot::Ouvert]),
            'points_collecte' => $this->delta(PointCollecte::query(), $depuis)->get(['id', 'village_id', 'nom', 'actif']),
            'categories_depense' => $this->delta(CategorieDepense::query(), $depuis)->get(['id', 'nom', 'exclue_fonds_campagne', 'actif']),
            'pisteurs' => $this->delta(Pisteur::query(), $depuis)->get(['id', 'nom', 'telephone', 'actif']),
            // Seulement les comptes d'où cet utilisateur peut payer (un agent : sa caisse).
            'comptes' => CompteTresorerie::query()->where('actif', true)->orderBy('nom')->get(['id', 'nom', 'type', 'titulaire_id', 'actif'])
                ->filter(fn (CompteTresorerie $c) => Depenses::peutPayerDepuis($user, $c))->values(),
            'producteurs' => $this->delta(Producteur::query(), $depuis)->get(['id', 'code', 'nom', 'prenoms', 'telephone', 'village_id', 'groupe_id', 'actif']),
            'prets_en_cours' => Pret::query()->whereIn('statut', [StatutPret::Valide, StatutPret::Decaisse])->get()
                ->map(fn (Pret $p) => [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'producteur_id' => $p->producteur_id,
                    'campagne_id' => $p->campagne_id,
                    'restant_du_fcfa' => $p->restantDu(),
                ])->values(),
        ]);
    }

    public function synchroniser(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'appareil_id' => ['required', 'string', 'max:100'],
            'operations' => ['present', 'array', 'list', 'max:'.Synchronisation::MAX_OPERATIONS],
            'operations.*' => ['array'],
        ]);
        /** @var User $user */
        $user = $request->user();

        /** @var list<array<string, mixed>> $operations */
        $operations = $donnees['operations'];

        return response()->json(Synchronisation::recevoir($donnees['appareil_id'], $operations, $user));
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $requete
     * @return Builder<T>
     */
    private function delta(Builder $requete, ?Carbon $depuis): Builder
    {
        return $depuis === null ? $requete : $requete->where('updated_at', '>', $depuis);
    }
}
