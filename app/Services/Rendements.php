<?php

namespace App\Services;

use App\Enums\StatutAchat;
use App\Enums\StatutPret;
use App\Models\Campagne;
use App\Models\Parcelle;
use App\Models\Producteur;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rendement réel d'une campagne (cahier §3 : « kg livrés ÷ hectares financés »),
 * producteur par producteur. Lecture seule, recalculé depuis les registres, entiers
 * uniquement (kg/ha arrondi au kg le plus proche, jamais de flottant).
 *
 * - Hectares financés : parcelles rattachées aux prêts accordés de la campagne, chacune
 *   comptée une seule fois même si deux prêts la financent. Surface relevée
 *   seulement : un producteur dont aucune parcelle financée n'a de contour n'a PAS de
 *   rendement (il est listé à part, jamais classé avec un rendement inventé).
 * - Kilos livrés : poids net des achats validés du producteur sur la campagne, prêt
 *   ou non (question ouverte n° 31 : ne compter que les kilos rendus en remboursement ?).
 */
class Rendements
{
    /**
     * @return array{
     *     classes: Collection<int, array{producteur: Producteur, surface_m2: int, grammes: int, kg_par_ha: int, rang: int, groupe: string}>,
     *     sansSurface: Collection<int, array{producteur: Producteur, grammes: int}>,
     *     moyenneKgParHa: int|null
     * }
     */
    public static function classement(Campagne $campagne): array
    {
        $parcelles = self::parcellesFinancees($campagne);
        $livre = self::grammesLivres($campagne);

        $producteurs = Producteur::query()
            ->whereIn('id', $parcelles->keys()->merge($livre->keys())->unique()->all())
            ->get()->keyBy('id');

        $classes = collect();
        $sansSurface = collect();
        foreach ($producteurs as $id => $producteur) {
            $surface = $parcelles->get($id, 0);
            $grammes = (int) $livre->get($id, 0);
            if ($surface <= 0) {
                if ($grammes > 0 && $parcelles->has($id)) {
                    $sansSurface->push(['producteur' => $producteur, 'grammes' => $grammes]);
                }

                continue;
            }
            $classes->push([
                'producteur' => $producteur,
                'surface_m2' => $surface,
                'grammes' => $grammes,
                'kg_par_ha' => self::kgParHa($grammes, $surface),
            ]);
        }

        $tries = $classes->sortByDesc('kg_par_ha')->values();
        $n = $tries->count();
        // 20 % de chaque bout, au moins 1 dès que le classement compte 5 producteurs ou plus.
        $bout = $n >= 5 ? intdiv($n, 5) : 0;
        $classement = collect();
        foreach ($tries as $i => $ligne) {
            $groupe = $bout > 0 && $i < $bout ? 'meilleurs' : ($bout > 0 && $i >= $n - $bout ? 'moins_bons' : 'milieu');
            $classement->push([...$ligne, 'rang' => $i + 1, 'groupe' => $groupe]);
        }

        $surfaceTotale = (int) $classes->sum('surface_m2');

        return [
            'classes' => $classement,
            'sansSurface' => $sansSurface->values(),
            // Moyenne pondérée par la surface (total kg ÷ total ha), pas moyenne des moyennes.
            'moyenneKgParHa' => $surfaceTotale > 0 ? self::kgParHa((int) $classes->sum('grammes'), $surfaceTotale) : null,
        ];
    }

    /**
     * Parcelles financées de la campagne qui ont un contour, chacune avec le rendement
     * de son producteur : les kilos sont pesés par producteur, pas par parcelle, donc
     * deux parcelles du même producteur portent le même chiffre. Sans rendement
     * (`kg_par_ha` null), la parcelle est grise sur la carte, pas à zéro.
     *
     * `classe` : 0 (plus faible) à 4 (plus fort), par cinquièmes égaux de l'écart
     * entre le plus faible et le plus fort rendement (entiers) ; null si pas de rendement.
     *
     * @return array{
     *     parcelles: list<array{id: string, nom: string, producteur: string, geometrie: array{type: string, coordinates: array<mixed>}, kg_par_ha: int|null, classe: int|null}>,
     *     bornes: list<int>|null
     * }
     */
    public static function carte(Campagne $campagne): array
    {
        $rendements = self::classement($campagne)['classes']->mapWithKeys(fn (array $l) => [$l['producteur']->id => $l['kg_par_ha']]);
        $min = $rendements->isEmpty() ? null : (int) $rendements->min();
        $max = $rendements->isEmpty() ? null : (int) $rendements->max();

        $ids = DB::table('pret_parcelle')
            ->join('prets', 'prets.id', '=', 'pret_parcelle.pret_id')
            ->where('prets.campagne_id', $campagne->id)
            ->whereIn('prets.statut', [StatutPret::Valide->value, StatutPret::Decaisse->value, StatutPret::Solde->value])
            ->pluck('pret_parcelle.parcelle_id')->unique()->all();

        $parcelles = [];
        foreach (Parcelle::query()->with('producteur')->whereIn('id', $ids)->whereNotNull('contour')->orderBy('nom')->get() as $parcelle) {
            /** @var array{type: string, coordinates: array<mixed>} $geometrie */
            $geometrie = $parcelle->contour;
            $kg = $rendements->get($parcelle->producteur_id);
            $parcelles[] = [
                'id' => $parcelle->id,
                'nom' => $parcelle->nom,
                'producteur' => $parcelle->producteur->nom,
                'geometrie' => $geometrie,
                'kg_par_ha' => $kg,
                'classe' => $kg === null || $min === null || $max === null ? null : ($max === $min ? 2 : min(4, intdiv(($kg - $min) * 5, $max - $min))),
            ];
        }

        $bornes = null;
        if ($min !== null && $max !== null && $max !== $min) {
            $bornes = array_map(fn (int $i) => $min + intdiv(($max - $min) * $i, 5), range(0, 5));
        }

        return ['parcelles' => $parcelles, 'bornes' => $bornes];
    }

    /**
     * Évolution d'un producteur d'une campagne à l'autre : une ligne par campagne où il a
     * un rendement, de la plus ancienne à la plus récente. L'écart (`ecart_kg_par_ha`)
     * se mesure à la campagne précédente **du même produit** : le rendement d'un produit
     * ne se compare pas à celui d'un autre. `null` pour la première campagne d'un produit.
     *
     * @return list<array{campagne: Campagne, surface_m2: int, grammes: int, kg_par_ha: int, ecart_kg_par_ha: int|null}>
     */
    public static function evolution(Producteur $producteur): array
    {
        $lignes = [];
        $dernierParProduit = [];
        foreach (Campagne::query()->with('produit')->orderBy('debut')->orderBy('id')->get() as $campagne) {
            $ligne = self::classement($campagne)['classes']->first(fn (array $l) => $l['producteur']->id === $producteur->id);
            if ($ligne === null) {
                continue;
            }
            $precedent = $dernierParProduit[$campagne->produit_id] ?? null;
            $lignes[] = [
                'campagne' => $campagne,
                'surface_m2' => $ligne['surface_m2'],
                'grammes' => $ligne['grammes'],
                'kg_par_ha' => $ligne['kg_par_ha'],
                'ecart_kg_par_ha' => $precedent === null ? null : $ligne['kg_par_ha'] - $precedent,
            ];
            $dernierParProduit[$campagne->produit_id] = $ligne['kg_par_ha'];
        }

        return $lignes;
    }

    /** kg/ha = grammes ÷ 1000 ÷ (m² ÷ 10 000) = grammes × 10 ÷ m², arrondi au plus proche. */
    public static function kgParHa(int $grammes, int $surfaceM2): int
    {
        return intdiv($grammes * 20 + $surfaceM2, 2 * $surfaceM2);
    }

    /** @return Collection<string, int> m² financés par producteur (0 si aucun contour relevé) */
    private static function parcellesFinancees(Campagne $campagne): Collection
    {
        $couples = DB::table('pret_parcelle')
            ->join('prets', 'prets.id', '=', 'pret_parcelle.pret_id')
            ->where('prets.campagne_id', $campagne->id)
            ->whereIn('prets.statut', [StatutPret::Valide->value, StatutPret::Decaisse->value, StatutPret::Solde->value])
            ->select('prets.producteur_id', 'pret_parcelle.parcelle_id')->distinct()->get();

        $surfaces = Parcelle::query()->whereIn('id', $couples->pluck('parcelle_id')->unique()->all())
            ->pluck('surface_m2', 'id');

        $parProducteur = collect();
        foreach ($couples as $c) {
            $parProducteur[$c->producteur_id] = ($parProducteur[$c->producteur_id] ?? 0) + (int) ($surfaces[$c->parcelle_id] ?? 0);
        }

        return $parProducteur;
    }

    /** @return Collection<string, int> grammes nets livrés (achats validés) par producteur */
    private static function grammesLivres(Campagne $campagne): Collection
    {
        return DB::table('achats')
            ->where('campagne_id', $campagne->id)
            ->where('statut', StatutAchat::Valide->value)
            ->whereNotNull('producteur_id')
            ->groupBy('producteur_id')
            ->selectRaw('producteur_id, SUM(poids_net_g) as total')
            ->pluck('total', 'producteur_id')
            ->map(fn ($v) => (int) $v);
    }
}
