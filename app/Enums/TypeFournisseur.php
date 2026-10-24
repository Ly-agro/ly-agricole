<?php

namespace App\Enums;

enum TypeFournisseur: string
{
    case Producteur = 'producteur';
    /** Pisteur qui vend ce qu'il a collecté ; sa commission attend la question 6. */
    case Pisteur = 'pisteur';
    case Cooperative = 'cooperative';

    public function libelle(): string
    {
        return match ($this) {
            self::Producteur => 'Producteur',
            self::Pisteur => 'Pisteur',
            self::Cooperative => 'Coopérative',
        };
    }
}
