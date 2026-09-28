<?php

namespace App\Enums;

enum StatutDepense: string
{
    /** Au-dessus du seuil (ou seuil non défini) : attend une autre personne que l'auteur. */
    case AValider = 'a_valider';
    /** Payée : un mouvement de sortie existe. */
    case Payee = 'payee';
    case Refusee = 'refusee';
    /** Paiement contre-passé. */
    case Annulee = 'annulee';

    public function libelle(): string
    {
        return match ($this) {
            self::AValider => 'À valider',
            self::Payee => 'Payée',
            self::Refusee => 'Refusée',
            self::Annulee => 'Annulée',
        };
    }
}
