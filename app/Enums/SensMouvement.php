<?php

namespace App\Enums;

enum SensMouvement: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';

    public function inverse(): self
    {
        return $this === self::Entree ? self::Sortie : self::Entree;
    }

    public function libelle(): string
    {
        return $this === self::Entree ? 'Entrée' : 'Sortie';
    }
}
