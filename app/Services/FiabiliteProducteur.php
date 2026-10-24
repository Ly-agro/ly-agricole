<?php

namespace App\Services;

use App\Enums\CleParametre;
use App\Enums\StatutPret;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use Illuminate\Support\Carbon;

/**
 * Fiabilité d'un producteur (cahier §10) : l'HISTORIQUE OBJECTIF de ses prêts et un plafond
 * PROPOSÉ pour la campagne suivante. La direction décide ; ce service n'accorde ni ne
 * refuse rien, et ne classe personne (pas de note, pas de « bon » ou « mauvais » payeur).
 *
 * Règle de proposition, volontairement prudente et sans coefficient inventé (question
 * ouverte n° 35) : le plafond proposé est **le plus gros prêt que le producteur a soldé à
 * l'échéance ou avant**, borné par le plafond par producteur des Paramètres s'il est défini.
 * Rien n'est proposé s'il n'y a pas d'historique, si aucun prêt n'a été soldé à temps, ou si un
 * prêt est aujourd'hui en retard. Aucune augmentation n'est proposée : c'est un choix de la
 * direction.
 *
 * Lecture seule, entiers uniquement (D4). Taux en pour mille (1 000 = tout remboursé).
 * Un prêt « décaissé » ou « soldé » compte ; les demandes, refus et prêts validés mais pas
 * encore versés n'ont rien remis au producteur.
 */
class FiabiliteProducteur
{
    /**
     * @return array{
     *     producteur: Producteur,
     *     prets: list<array{pret: Pret, remis: int, rembourse: int, taux_pour_mille: int, solde: bool, date_solde: Carbon|null, retard_jours: int, a_temps: bool}>,
     *     synthese: array{nb_prets: int, total_remis: int, total_rembourse: int, taux_pour_mille: int|null, nb_soldes_a_temps: int, nb_en_retard: int, retard_max_jours: int, plus_gros_solde_a_temps: int|null},
     *     plafond_propose: int|null,
     *     raison: string
     * }
     */
    public static function fiche(Producteur $producteur, ?Carbon $aujourdhui = null): array
    {
        $aujourdhui = ($aujourdhui ?? Carbon::today())->copy()->startOfDay();

        $prets = [];
        foreach (Pret::query()->with('campagne.produit')->where('producteur_id', $producteur->id)
            ->whereIn('statut', [StatutPret::Decaisse, StatutPret::Solde])->orderBy('echeance')->get() as $pret) {
            $remis = $pret->montantRemis();
            $rembourse = $pret->montantRembourse();
            $solde = $pret->statut === StatutPret::Solde;
            $dateSolde = $solde ? self::dateSolde($pret) : null;
            // Retard : soldé = jours entre l'échéance et le dernier remboursement ; pas soldé = jours d'échéance dépassée.
            $retard = $solde
                ? ($dateSolde !== null ? max(0, (int) $pret->echeance->startOfDay()->diffInDays($dateSolde->copy()->startOfDay(), false)) : 0)
                : max(0, (int) $pret->echeance->startOfDay()->diffInDays($aujourdhui, false));

            $prets[] = [
                'pret' => $pret,
                'remis' => $remis,
                'rembourse' => $rembourse,
                'taux_pour_mille' => $remis > 0 ? min(1000, intdiv(max(0, $rembourse) * 1000, $remis)) : 0,
                'solde' => $solde,
                'date_solde' => $dateSolde,
                'retard_jours' => $retard,
                'a_temps' => $solde && $retard === 0,
            ];
        }

        $totalRemis = (int) array_sum(array_column($prets, 'remis'));
        $totalRembourse = (int) array_sum(array_map(fn (array $p) => max(0, $p['rembourse']), $prets));
        $soldesATemps = array_filter($prets, fn (array $p) => $p['a_temps']);
        $enRetard = array_filter($prets, fn (array $p) => ! $p['solde'] && $p['retard_jours'] > 0);
        $plusGros = $soldesATemps === [] ? null : (int) max(array_column($soldesATemps, 'remis'));

        $synthese = [
            'nb_prets' => count($prets),
            'total_remis' => $totalRemis,
            'total_rembourse' => $totalRembourse,
            'taux_pour_mille' => $totalRemis > 0 ? min(1000, intdiv($totalRembourse * 1000, $totalRemis)) : null,
            'nb_soldes_a_temps' => count($soldesATemps),
            'nb_en_retard' => count($enRetard),
            'retard_max_jours' => (int) max([0, ...array_column($prets, 'retard_jours')]),
            'plus_gros_solde_a_temps' => $plusGros,
        ];

        [$plafond, $raison] = self::proposer($synthese);

        return ['producteur' => $producteur, 'prets' => $prets, 'synthese' => $synthese, 'plafond_propose' => $plafond, 'raison' => $raison];
    }

    /**
     * @param  array{nb_prets: int, nb_en_retard: int, plus_gros_solde_a_temps: int|null}  $synthese
     * @return array{0: int|null, 1: string}
     */
    private static function proposer(array $synthese): array
    {
        if ($synthese['nb_prets'] === 0) {
            return [null, 'Aucun prêt versé : pas d\'historique sur lequel s\'appuyer.'];
        }
        if ($synthese['nb_en_retard'] > 0) {
            return [null, 'Un prêt est en retard : aucun plafond n\'est proposé tant qu\'il n\'est pas régularisé.'];
        }
        if ($synthese['plus_gros_solde_a_temps'] === null) {
            return [null, 'Aucun prêt soldé à l\'échéance ou avant : pas d\'historique de remboursement complet.'];
        }

        $plafond = $synthese['plus_gros_solde_a_temps'];
        $limite = Parametre::entier(CleParametre::PlafondPretProducteur);
        if ($limite !== null && $plafond > $limite) {
            return [$limite, 'Le plus gros prêt soldé à temps dépasse le plafond par producteur des Paramètres : le plafond des Paramètres s\'applique.'];
        }

        return [$plafond, 'Le plus gros prêt soldé à l\'échéance ou avant. Aucune augmentation n\'est proposée : la direction décide.'];
    }

    /** Date du dernier remboursement encaissé (les remboursements contre-passés ne comptent pas). */
    private static function dateSolde(Pret $pret): ?Carbon
    {
        $date = $pret->remboursements()->where('montant_fcfa', '>', 0)->whereDoesntHave('contrePassation')->max('date_remboursement');

        return $date === null ? null : Carbon::parse((string) $date);
    }
}
