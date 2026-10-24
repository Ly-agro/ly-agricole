<?php

namespace App\Services;

use App\Enums\CalculCommissionPisteur;
use App\Enums\ModeCommission;
use App\Enums\StatutAchat;
use App\Models\Achat;
use App\Models\Parametre;
use App\Models\Pisteur;

/**
 * Commission d'un pisteur (question ouverte n° 6 : « combien, par kg ou en %, payée quand ? »).
 * Ce qui est codé est ce qui est décidé, rien de plus :
 *  - chaque pisteur a SA règle (FCFA par kilo, ou pour mille du montant), vide tant que la
 *    direction ne l'a pas choisie ;
 *  - le calcul automatique est ACTIVÉ PAR LA DIRECTION (Paramètres) : sans lui, la commission
 *    d'un achat reste vide (la décision est : « champ prévu, calcul activé ultérieurement ») ;
 *  - la commission calculée est une somme DUE au pisteur : son PAIEMENT (quand, comment) n'est pas
 *    défini, et elle n'entre pas dans le résultat de la campagne tant qu'elle n'est pas payée par
 *    une dépense (contrat art. 10.2 : « commissions commerciales »).
 *
 * Entiers uniquement (D4) ; arrondi au franc le plus proche, une seule fois.
 */
class CommissionsPisteur
{
    /**
     * Commission due sur un achat, ou null si le calcul n'est pas activé ou si le pisteur n'a pas de
     * règle complète.
     */
    public static function calculer(?Pisteur $pisteur, int $poidsNetG, int $montantFcfa): ?int
    {
        if ($pisteur === null || Parametre::calculCommissionPisteur() !== CalculCommissionPisteur::Automatique) {
            return null;
        }

        return self::selonRegle($pisteur, $poidsNetG, $montantFcfa);
    }

    /** Ce que donnerait la règle du pisteur, calcul activé ou non (aperçu). null = pas de règle complète. */
    public static function selonRegle(Pisteur $pisteur, int $poidsNetG, int $montantFcfa): ?int
    {
        $valeur = $pisteur->commission_valeur;
        if ($pisteur->commission_mode === null || $valeur === null) {
            return null;
        }

        return match ($pisteur->commission_mode) {
            // grammes × FCFA/kg ÷ 1000, arrondi au plus proche.
            ModeCommission::ParKg => intdiv($poidsNetG * $valeur * 2 + 1000, 2000),
            // montant × ‰ ÷ 1000, arrondi au plus proche.
            ModeCommission::Pourcent => intdiv($montantFcfa * $valeur * 2 + 1000, 2000),
        };
    }

    /** Total DÛ à un pisteur : commissions des achats VALIDÉS (jamais ceux à valider ou refusés). */
    public static function due(Pisteur $pisteur): int
    {
        return (int) Achat::query()->where('pisteur_id', $pisteur->id)->where('statut', StatutAchat::Valide)->sum('commission_pisteur_fcfa');
    }
}
