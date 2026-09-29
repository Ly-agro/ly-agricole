<?php

namespace App\Enums;

/** « Vendu » et « transformé » viendront avec les reventes (phase 2). */
enum StatutLot: string
{
    case Ouvert = 'ouvert';
    case Ferme = 'ferme';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert (reçoit des achats)',
            self::Ferme => 'Fermé',
        };
    }
}
