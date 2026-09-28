<?php

namespace App\Enums;

/**
 * Forme du prêt (question ouverte n° 2). Un prêt mixte n'enregistre que son total :
 * la répartition argent / intrants n'est pas fixée d'avance.
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
            self::Intrants => 'Intrants',
            self::Mixte => 'Mixte (argent + intrants)',
        };
    }

    /** Le prêt peut-il être versé (en partie) avec ce mode d'argent ? */
    public function accepteArgent(ModeDecaissement $mode): bool
    {
        return match ($this) {
            self::Especes => $mode === ModeDecaissement::Especes,
            self::MobileMoney => $mode === ModeDecaissement::MobileMoney,
            self::Intrants => false,
            self::Mixte => true,
        };
    }

    public function accepteIntrants(): bool
    {
        return in_array($this, [self::Intrants, self::Mixte], true);
    }
}
