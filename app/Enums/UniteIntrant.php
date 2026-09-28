<?php

namespace App\Enums;

/**
 * Unités de conditionnement, comptées en entiers. Pas de « kg » en vrac : un poids se
 * stocke en grammes (D4) ; le poids d'un sac figure dans le nom de l'intrant.
 */
enum UniteIntrant: string
{
    case Sac = 'sac';
    case Bidon = 'bidon';
    case Piece = 'piece';
    case Rouleau = 'rouleau';

    public function libelle(int $quantite = 1): string
    {
        $mot = match ($this) {
            self::Sac => 'sac',
            self::Bidon => 'bidon',
            self::Piece => 'pièce',
            self::Rouleau => 'rouleau',
        };

        return abs($quantite) > 1 ? ($this === self::Rouleau ? 'rouleaux' : $mot.'s') : $mot;
    }
}
