<?php

namespace App\Services;

use App\Enums\StatutAchat;
use App\Enums\StatutDepense;
use App\Enums\StatutPret;
use App\Models\Campagne;
use App\Models\Pret;
use Illuminate\Support\Facades\DB;

/**
 * Résultat net PROVISOIRE d'une campagne, lu dans les registres (contrat art. 10 et 11) :
 *
 *   résultat net = recettes encaissées + valeur du stock invendu − charges justifiées
 *
 * - Recettes (art. 10.1 : « effectivement encaissées ») : encaissements des ventes de la
 *   campagne, contre-passations comprises (elles sont négatives).
 * - Charges (art. 10.1 et 10.2) : coût d'achat des achats VALIDÉS + dépenses PAYÉES
 *   rattachées à la campagne, par catégorie. Une catégorie « exclue des fonds de campagne »
 *   (art. 10.3) n'est jamais comptée, même si une dépense s'y était glissée.
 * - Valeur du stock invendu (art. 11.3) : elle n'est PAS calculée ici. Elle dépend d'une
 *   décision de LY (vente avant le 30/09/2027 ou reprise) et de deux offres de prix écrites :
 *   la direction la donne en paramètre ; 0 par défaut (jamais devinée).
 *
 * Deux choses sont volontairement HORS du résultat et sortent seulement en information
 * (question ouverte n° 32) : les avances aux producteurs non remboursées (créances, pas des
 * charges dans le texte du contrat) et le stock invendu en kilos.
 *
 * Lecture seule, entiers uniquement (D4). Ce n'est PAS le rapport final de l'art. 18 :
 * tant que la campagne n'est pas close, ces chiffres bougent avec chaque opération.
 */
class ResultatCampagne
{
    /**
     * @return array{
     *     recettes: int,
     *     charges: array{achats: int, depenses: list<array{categorie: string, montant: int}>, total: int},
     *     valeur_stock_invendu: int,
     *     resultat_net: int,
     *     info: array{avances_non_remboursees: int, stock_invendu_g: int}
     * }
     */
    public static function etat(Campagne $campagne, int $valeurStockInvendu = 0): array
    {
        $recettes = (int) DB::table('encaissements')
            ->join('ventes', 'ventes.id', '=', 'encaissements.vente_id')
            ->where('ventes.campagne_id', $campagne->id)
            ->sum('encaissements.montant_fcfa');

        $achats = (int) DB::table('achats')
            ->where('campagne_id', $campagne->id)
            ->where('statut', StatutAchat::Valide->value)
            ->sum('montant_fcfa');

        $depenses = DB::table('depenses')
            ->join('categories_depense', 'categories_depense.id', '=', 'depenses.categorie_id')
            ->where('depenses.campagne_id', $campagne->id)
            ->where('depenses.statut', StatutDepense::Payee->value)
            ->where('categories_depense.exclue_fonds_campagne', false)
            ->groupBy('categories_depense.id', 'categories_depense.nom')
            ->orderBy('categories_depense.nom')
            ->selectRaw('categories_depense.nom AS categorie, SUM(depenses.montant_fcfa) AS montant')
            ->get()
            ->map(fn ($l) => ['categorie' => (string) $l->categorie, 'montant' => (int) $l->montant])
            ->all();

        $totalCharges = $achats + array_sum(array_column($depenses, 'montant'));

        return [
            'recettes' => $recettes,
            'charges' => ['achats' => $achats, 'depenses' => $depenses, 'total' => $totalCharges],
            'valeur_stock_invendu' => $valeurStockInvendu,
            'resultat_net' => $recettes + $valeurStockInvendu - $totalCharges,
            'info' => [
                'avances_non_remboursees' => self::avancesNonRemboursees($campagne),
                'stock_invendu_g' => (int) DB::table('mouvements_stock')
                    ->join('lots', 'lots.id', '=', 'mouvements_stock.lot_id')
                    ->where('lots.campagne_id', $campagne->id)
                    ->sum('mouvements_stock.grammes'),
            ],
        ];
    }

    /** Restant dû des prêts accordés de la campagne (jamais négatif par prêt). */
    private static function avancesNonRemboursees(Campagne $campagne): int
    {
        return Pret::query()->where('campagne_id', $campagne->id)
            ->whereIn('statut', [StatutPret::Valide, StatutPret::Decaisse])
            ->get()->sum(fn (Pret $p) => max(0, $p->restantDu()));
    }
}
