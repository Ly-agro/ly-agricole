<?php

namespace App\Enums;

enum StatutVente: string
{
    /** Au-dessus du seuil (ou seuil non défini) : le stock ne sort pas avant validation. */
    case AValider = 'a_valider';
    case Valide = 'valide';
    case Refuse = 'refuse';

    public function libelle(): string
    {
        return match ($this) {
            self::AValider => 'À valider',
            self::Valide => 'Validée',
            self::Refuse => 'Refusée',
        };
    }
}
