<?php

namespace App\Enums;

/**
enum StatutCampagne: string
{
    case Preparation = 'preparation';
    case Ouverte = 'ouverte';
    case Suspendue = 'suspendue';
    case Cloturee = 'cloturee';

    public function libelle(): string
    {
        return match ($this) {
            self::Preparation => 'En préparation',
            self::Ouverte => 'Ouverte',
            self::Suspendue => 'Suspendue',
            self::Cloturee => 'Clôturée',
        };
    }
}
