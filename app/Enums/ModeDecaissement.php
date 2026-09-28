<?php

namespace App\Enums;

enum ModeDecaissement: string
{
    /** Depuis une caisse ; reçu signé obligatoire. */
    case Especes = 'especes';
    /** Depuis un compte Mobile Money ; référence de la transaction obligatoire. */
    case MobileMoney = 'mobile_money';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::MobileMoney => 'Mobile Money',
        };
    }

    /** @return list<TypeCompte> */
    public function typesDeCompte(): array
    {
        return match ($this) {
            self::Especes => [TypeCompte::Caisse],
            self::MobileMoney => [TypeCompte::Wave, TypeCompte::OrangeMoney, TypeCompte::MtnMomo, TypeCompte::MoovMoney],
        };
    }
}
