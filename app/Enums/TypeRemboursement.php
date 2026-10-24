<?php

namespace App\Enums;

enum TypeRemboursement: string
{
    case Especes = 'especes';
    /** Kilos livrés, valorisés selon la règle de la question 3. */
    case Nature = 'nature';
    case ContrePassation = 'contre_passation';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::Nature => 'En kilos',
            self::ContrePassation => 'Contre-passation',
        };
    }
}
