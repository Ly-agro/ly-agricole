<?php

namespace App\Enums;

/** « Transformé » viendra avec la transformation (karité, décorticage). */
enum StatutLot: string
{
    case Ouvert = 'ouvert';
    case Ferme = 'ferme';
    /** Mis automatiquement quand une vente vide le lot (stock = 0, tous magasins). */
    case Vendu = 'vendu';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert (reçoit des achats)',
            self::Ferme => 'Fermé',
            self::Vendu => 'Vendu',
        };
    }
}
