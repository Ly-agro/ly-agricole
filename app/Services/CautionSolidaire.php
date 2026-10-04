<?php

namespace App\Services;

use App\Enums\RegleCautionSolidaire;
use App\Enums\StatutPret;
use App\Models\GroupeProducteur;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;

/**
 * Caution solidaire d'un groupe de producteurs (cahier §10 : « baisse des impayés »).
 *
 * Le cahier ne dit pas comment le groupe se porte caution. Ce qui est codé est le minimum
 * lisible, CHOISI par la direction dans les Paramètres (question ouverte n° 37) et inactif tant
 * qu'elle n'a rien choisi : quand un autre membre du groupe a un prêt EN RETARD (même définition
 * que la fiabilité : échéance dépassée, prêt versé et pas soldé), un prêt à un membre est
 * soit signalé, soit refusé. Rien d'autre : pas de prêt ni de remboursement imputé au groupe,
 * pas de montant réclamé à un membre pour un autre.
 *
 * Le message montré à l'agent est GÉNÉRIQUE (jamais le nom ni les montants d'un autre
 * producteur) ; le détail nominatif est réservé à la fiche de groupe de la direction et de la
 * comptabilité.
 */
class CautionSolidaire
{
    public const AUCUN = 'aucun';

    public const AVERTISSEMENT = 'avertissement';

    public const BLOCAGE = 'blocage';

    /**
     * Ce que la règle choisie dit d'un prêt à ce producteur.
     *
     * @return array{niveau: string, message: string|null}
     */
    public static function controle(Producteur $producteur): array
    {
        $regle = Parametre::regleCautionSolidaire();
        if ($regle === RegleCautionSolidaire::Aucune || $producteur->groupe_id === null) {
            return ['niveau' => self::AUCUN, 'message' => null];
        }

        $enRetard = Producteur::query()->where('groupe_id', $producteur->groupe_id)->where('actif', true)
            ->where('id', '!=', $producteur->id)->get()
            ->filter(fn (Producteur $membre) => FiabiliteProducteur::fiche($membre)['synthese']['nb_en_retard'] > 0);

        if ($enRetard->isEmpty()) {
            return ['niveau' => self::AUCUN, 'message' => null];
        }

        $nb = $enRetard->count();
        $texte = ($nb > 1 ? "$nb autres membres du groupe ont" : 'Un autre membre du groupe a').' un prêt en retard (caution solidaire).';

        return $regle === RegleCautionSolidaire::Bloquer
            ? ['niveau' => self::BLOCAGE, 'message' => $texte.' Aucun prêt n\'est possible tant que le retard n\'est pas régularisé.']
            : ['niveau' => self::AVERTISSEMENT, 'message' => $texte.' À signaler à la direction avant de valider.'];
    }

    /**
     * Situation d'un groupe, avec le détail nominatif (direction et comptabilité seulement).
     *
     * @return array{
     *     groupe: GroupeProducteur,
     *     membres: list<array{producteur: Producteur, nb_prets: int, remis: int, rembourse: int, taux_pour_mille: int|null, restant_du: int, nb_en_retard: int, retard_max_jours: int}>,
     *     totaux: array{remis: int, rembourse: int, restant_du: int, taux_pour_mille: int|null, membres_en_retard: int},
     *     regle: RegleCautionSolidaire
     * }
     */
    public static function situation(GroupeProducteur $groupe): array
    {
        $membres = [];
        foreach (Producteur::query()->where('groupe_id', $groupe->id)->orderBy('nom')->orderBy('prenoms')->get() as $producteur) {
            $fiche = FiabiliteProducteur::fiche($producteur);
            $s = $fiche['synthese'];
            $restant = (int) Pret::query()->where('producteur_id', $producteur->id)->where('statut', StatutPret::Decaisse)->get()
                ->sum(fn (Pret $p) => max(0, $p->restantDu()));

            $membres[] = [
                'producteur' => $producteur,
                'nb_prets' => $s['nb_prets'],
                'remis' => $s['total_remis'],
                'rembourse' => $s['total_rembourse'],
                'taux_pour_mille' => $s['taux_pour_mille'],
                'restant_du' => $restant,
                'nb_en_retard' => $s['nb_en_retard'],
                'retard_max_jours' => $s['retard_max_jours'],
            ];
        }

        $remis = (int) array_sum(array_column($membres, 'remis'));
        $rembourse = (int) array_sum(array_column($membres, 'rembourse'));

        return [
            'groupe' => $groupe,
            'membres' => $membres,
            'totaux' => [
                'remis' => $remis,
                'rembourse' => $rembourse,
                'restant_du' => (int) array_sum(array_column($membres, 'restant_du')),
                'taux_pour_mille' => $remis > 0 ? min(1000, intdiv($rembourse * 1000, $remis)) : null,
                'membres_en_retard' => count(array_filter($membres, fn (array $m) => $m['nb_en_retard'] > 0)),
            ],
            'regle' => Parametre::regleCautionSolidaire(),
        ];
    }
}
