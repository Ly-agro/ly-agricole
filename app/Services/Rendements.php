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
 *   ou non (question ouverte n° 21 : ne compter que les kilos rendus en remboursement ?).
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
