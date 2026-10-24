<?php

namespace App\Enums;

/**
 * Le calcul automatique de la commission des pisteurs est ACTIVÉ PAR LA DIRECTION (question 6,
 * réponse : « prévoir le champ dès maintenant, calcul automatique activé ultérieurement »).
 * Tant qu'elle n'a rien choisi : aucun calcul, la commission d'un achat reste vide.
 */
enum CalculCommissionPisteur: string
{
    case Aucun = 'aucun';
    case Automatique = 'automatique';

    public function libelle(): string
    {
        return match ($this) {
            self::Aucun => 'Pas de calcul (commission laissée vide)',
            self::Automatique => 'Calculer la commission de chaque achat d\'un pisteur qui a une règle',
        };
    }
}
