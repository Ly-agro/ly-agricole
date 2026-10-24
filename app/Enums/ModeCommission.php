<?php

namespace App\Enums;

/**
 * Comment on calcule la commission d'un pisteur (question ouverte n° 6 : « combien, par kg ou en %,
 * payée quand ? »). Chaque pisteur a SA règle, vide tant que la direction ne l'a pas choisie.
 */
enum ModeCommission: string
{
    /** Un nombre entier de FCFA par kilo net acheté. */
    case ParKg = 'par_kg';
    /** Une part du montant de l'achat, en pour mille entier (20 ‰ = 2 %). */
    case Pourcent = 'pourcent';

    public function libelle(): string
    {
        return match ($this) {
            self::ParKg => 'FCFA par kilo',
            self::Pourcent => '% du montant de l\'achat',
        };
    }
}
