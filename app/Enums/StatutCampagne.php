<?php

namespace App\Enums;

/**
 * La clôture (`Cloturee`) viendra avec le calcul du résultat et une validation par
 * une autre personne (D6) : aucun écran n'y mène encore.
 */
enum StatutCampagne: string
{
    case Preparation = 'preparation';
    case Ouverte = 'ouverte';
    case Cloturee = 'cloturee';

    public function libelle(): string
    {
        return match ($this) {
            self::Preparation => 'En préparation',
            self::Ouverte => 'Ouverte',
            self::Cloturee => 'Clôturée',
        };
    }
}
