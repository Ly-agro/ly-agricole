<?php

namespace App\Enums;

/** Sert aux indicateurs d'impact (femmes, jeunes) du cahier des charges §10. */
enum Sexe: string
{
    case Femme = 'F';
    case Homme = 'M';

    public function libelle(): string
    {
        return match ($this) {
            self::Femme => 'Femme',
            self::Homme => 'Homme',
        };
    }
}
