<?php

namespace App\Support;

/**
 * Petits calculs pour les graphiques SVG, en entiers (pas de float sur l'argent).
 */
class Graphique
{
    /** 1 250 000 → « 1,25 M », 45 000 → « 45 k ». Sert aux graduations. */
    public static function court(int $montant): string
    {
        if ($montant >= 1_000_000) {
            $dixiemes = intdiv($montant * 10 + 500_000, 1_000_000);

            return ($dixiemes % 10 === 0 ? (string) intdiv($dixiemes, 10) : intdiv($dixiemes, 10).','.($dixiemes % 10)).' M';
        }

        return $montant >= 1_000 ? intdiv($montant + 500, 1_000).' k' : (string) $montant;
    }

    /** Plafond « rond » de l'axe : 1, 2, 2,5 ou 5 × 10^n au-dessus de la valeur. */
    public static function plafond(int $max): int
    {
        if ($max <= 0) {
            return 1;
        }

        $puissance = 1;
        while ($puissance * 10 <= $max) {
            $puissance *= 10;
        }

        foreach ([1, 2, 5, 10] as $pas) {
            if ($pas * $puissance >= $max) {
                return $pas * $puissance;
            }
        }

        return 10 * $puissance;
    }
}
