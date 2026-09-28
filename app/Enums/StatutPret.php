<?php

namespace App\Enums;

/**
 * Viendront plus tard : reporte, perte (clôture de campagne, avec validation D6).
 */
enum StatutPret: string
{
    case Demande = 'demande';
    case Valide = 'valide';
    case Refuse = 'refuse';
    /** Tout le montant a été remis au producteur. */
    case Decaisse = 'decaisse';
    /** Tout a été remis et tout a été remboursé. */
    case Solde = 'solde';

    public function libelle(): string
    {
        return match ($this) {
            self::Demande => 'Demande',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
            self::Decaisse => 'Décaissé',
            self::Solde => 'Soldé',
        };
    }
}
