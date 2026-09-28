<?php

namespace App\Enums;

/**
 * Viendront avec les remboursements (semaine 6) : en_cours, solde, reporte, perte.
 */
enum StatutPret: string
{
    case Demande = 'demande';
    case Valide = 'valide';
    case Refuse = 'refuse';
    /** Tout le montant a été versé. */
    case Decaisse = 'decaisse';

    public function libelle(): string
    {
        return match ($this) {
            self::Demande => 'Demande',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
            self::Decaisse => 'Décaissé',
        };
    }
}
