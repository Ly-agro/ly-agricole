<?php

namespace App\Enums;

/**
 * Question ouverte n° 3 : à quel prix valoriser des kilos livrés en remboursement ?
 * La direction choisit dans les Paramètres ; tant qu'elle n'a pas choisi, aucun achat
 * ne rembourse un prêt (le code ne tranche pas à sa place).
 */
enum RegleValorisationNature: string
{
    /** Prix payé ce jour-là pour l'achat. */
    case PrixAchat = 'prix_achat';
    /** Prix de référence fixé dans le prêt au moment de la demande. */
    case PrixReferencePret = 'prix_reference_pret';

    public function libelle(): string
    {
        return match ($this) {
            self::PrixAchat => 'Prix de l\'achat du jour',
            self::PrixReferencePret => 'Prix de référence fixé dans le prêt',
        };
    }
}
