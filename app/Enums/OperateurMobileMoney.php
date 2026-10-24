<?php

namespace App\Enums;

enum OperateurMobileMoney: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';

    public function libelle(): string
    {
        return match ($this) {
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
        };
    }
}
