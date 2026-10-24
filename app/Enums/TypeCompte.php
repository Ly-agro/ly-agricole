<?php

namespace App\Enums;

enum TypeCompte: string
{
    case Caisse = 'caisse';
    case Banque = 'banque';
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';

    public function libelle(): string
    {
        return match ($this) {
            self::Caisse => 'Caisse',
            self::Banque => 'Banque',
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
        };
    }
}
