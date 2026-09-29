<?php

namespace App\Services;

use App\Enums\StatutAchat;
use App\Enums\StatutVente;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rapports du contrat de campagne (art. 18). Aujourd'hui : le POINT D'ÉTAPE (art. 18.1,
 * « au plus tard le 30 avril 2027 ») — volumes achetés et vendus, montants engagés,
 * trésorerie disponible, principaux événements. Le rapport final (art. 18.2) attend les
 * réponses à la question ouverte n° 32 (perte supérieure aux fonds, stock invendu).
 *
 * Tout est relu dans les registres au moment de l'édition, en entiers (D4) : le document
 * dit « état au JJ/MM/AAAA » et ne prétend pas à un arrêté comptable. Il ne contient NI
 * résultat net NI quote-part (le contrat ne les demande pas dans la note d'étape, et un
 * résultat provisoire lu comme définitif tromperait les investisseurs), NI donnée
 * personnelle de producteur. Les « principaux événements » sont écrits par la direction :
 * ils ne sont jamais devinés.
 */
class RapportCampagne
{
    public const MAX_EVENEMENTS = 3000;

    /**
     * @return array{
     *     campagne: Campagne,
     *     etat_au: Carbon,
     *     fonds: array{investisseurs: int, nb_investisseurs: int, ly: int, total: int},
     *     volumes: array{achete_g: int, nb_achats: int, vendu_g: int, nb_ventes: int, stock_g: int},
     *     engage: array{achats: int, depenses: list<array{categorie: string, montant: int}>, total_depenses: int, avances_decaissees: int, avances_non_remboursees: int},
     *     ventes: array{facture: int, encaisse: int, reste: int},
     *     tresorerie: array{comptes: list<array{nom: string, solde: int}>, total: int},
     *     evenements: string
     * }
     */
    public static function pointEtape(Campagne $campagne, string $evenements = '', ?Carbon $etatAu = null): array
    {
        $campagne->loadMissing('produit');
        $resultat = ResultatCampagne::etat($campagne);
        $apports = Apports::repartition($campagne);

        $achats = DB::table('achats')->where('campagne_id', $campagne->id)->where('statut', StatutAchat::Valide->value)
            ->selectRaw('COUNT(*) AS nb, COALESCE(SUM(poids_net_g), 0) AS grammes')->first();
        $ventes = DB::table('ventes')->where('campagne_id', $campagne->id)->where('statut', StatutVente::Valide->value)
            ->selectRaw('COUNT(*) AS nb, COALESCE(SUM(poids_net_g), 0) AS grammes, COALESCE(SUM(montant_fcfa), 0) AS montant')->first();

        $facture = (int) ($ventes->montant ?? 0);
        $comptes = CompteTresorerie::query()->where('campagne_id', $campagne->id)->where('actif', true)->orderBy('nom')->get()
            ->map(fn (CompteTresorerie $c) => ['nom' => $c->nom, 'solde' => $c->solde()])->all();

        return [
            'campagne' => $campagne,
            'etat_au' => $etatAu ?? Carbon::now(),
            'fonds' => [
                'investisseurs' => $apports['parInvestisseurs'],
                'nb_investisseurs' => $apports['lignes']->count(),
                'ly' => $apports['parLy'],
                'total' => $apports['parInvestisseurs'] + $apports['parLy'],
            ],
            'volumes' => [
                'achete_g' => (int) ($achats->grammes ?? 0),
                'nb_achats' => (int) ($achats->nb ?? 0),
                'vendu_g' => (int) ($ventes->grammes ?? 0),
                'nb_ventes' => (int) ($ventes->nb ?? 0),
                'stock_g' => $resultat['info']['stock_invendu_g'],
            ],
            'engage' => [
                'achats' => $resultat['charges']['achats'],
                'depenses' => $resultat['charges']['depenses'],
                'total_depenses' => (int) array_sum(array_column($resultat['charges']['depenses'], 'montant')),
                'avances_decaissees' => (int) DB::table('decaissements')->join('prets', 'prets.id', '=', 'decaissements.pret_id')
                    ->where('prets.campagne_id', $campagne->id)->sum('decaissements.montant_fcfa'),
                'avances_non_remboursees' => $resultat['info']['avances_non_remboursees'],
            ],
            'ventes' => [
                'facture' => $facture,
                'encaisse' => $resultat['recettes'],
                'reste' => max(0, $facture - $resultat['recettes']),
            ],
            'tresorerie' => [
                'comptes' => $comptes,
                'total' => (int) array_sum(array_column($comptes, 'solde')),
            ],
            'evenements' => mb_substr(trim($evenements), 0, self::MAX_EVENEMENTS),
        ];
    }

    public static function pointEtapePdf(Campagne $campagne, string $evenements = ''): Response
    {
        $donnees = self::pointEtape($campagne, $evenements);

        return Pdf::loadView('rapport-campagne.point-etape', $donnees)
            ->setPaper('a4')->setOption('isFontSubsettingEnabled', true)
            ->stream('point-etape-'.$campagne->code.'.pdf');
    }
}
