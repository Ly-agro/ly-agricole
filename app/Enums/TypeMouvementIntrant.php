<?php

namespace App\Enums;

enum TypeMouvementIntrant: string
{
    case Entree = 'entree';
    /** Remise à crédit à un producteur, rattachée à son prêt. */
    case Distribution = 'distribution';
    case Perte = 'perte';
    case Ajustement = 'ajustement';
    case ContrePassation = 'contre_passation';

    public function libelle(): string
    {
        return match ($this) {
            self::Entree => 'Entrée en stock',
            self::Distribution => 'Distribution à crédit',
            self::Perte => 'Perte',
            self::Ajustement => 'Ajustement d\'inventaire',
            self::ContrePassation => 'Contre-passation',
        };
    }
}
