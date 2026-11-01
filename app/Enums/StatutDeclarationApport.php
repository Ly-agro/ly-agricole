<?php

namespace App\Enums;

enum StatutDeclarationApport: string
{
    case EnAttente = 'en_attente';
    case Confirmee = 'confirmee';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de confirmation',
            self::Confirmee => 'Confirmée',
            self::Refusee => 'Refusée',
        };
    }
}
