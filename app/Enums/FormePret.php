<?php

namespace App\Enums;

/**
 * Forme du prêt (question ouverte n° 2). Intrants et mixte arrivent avec le stock
 * d'intrants (semaine 5) : refusés d'ici là.
 */
enum FormePret: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case Intrants = 'intrants';
    case Mixte = 'mixte';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::MobileMoney => 'Mobile Money',
            self::Intrants => 'Intrants (semaine 5)',
            self::Mixte => 'Mixte (semaine 5)',
        };
    }

    public function disponible(): bool
    {
        return in_array($this, [self::Especes, self::MobileMoney], true);
    }

    /** @return list<self> */
    public static function disponibles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $f) => $f->disponible()));
    }
}
