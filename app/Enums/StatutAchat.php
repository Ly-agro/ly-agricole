<?php

namespace App\Enums;

enum StatutAchat: string
{
    /** Au-dessus du seuil (ou seuil non défini) : ni stock, ni paiement, ni remboursement avant validation. */
    case AValider = 'a_valider';
    case Valide = 'valide';
    case Refuse = 'refuse';
    /** Annulé par la direction : effets contre-passés (stock, remboursement, caisse), l'achat reste visible. */
    case Annule = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::AValider => 'À valider',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
            self::Annule => 'Annulé',
        };
    }
}
