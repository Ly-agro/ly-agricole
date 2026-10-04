<?php

namespace App\Enums;

/**
 * Caution solidaire d'un groupe de producteurs (cahier §10 : « baisse des impayés »).
 * Le cahier ne dit pas COMMENT le groupe se porte caution : la direction choisit dans les
 * Paramètres (question ouverte n° 37). Tant qu'elle n'a pas choisi, il n'y a AUCUNE règle
 * de groupe : le comportement des prêts ne change pas.
 */
enum RegleCautionSolidaire: string
{
    /** Pas de règle de groupe (valeur tant que rien n'est choisi). */
    case Aucune = 'aucune';
    /** Un prêt à un membre est signalé si un autre membre du groupe est en retard, sans rien bloquer. */
    case Avertir = 'avertir';
    /** Un prêt à un membre est refusé tant qu'un autre membre du groupe a un prêt en retard. */
    case Bloquer = 'bloquer';

    public function libelle(): string
    {
        return match ($this) {
            self::Aucune => 'Pas de caution solidaire',
            self::Avertir => 'Signaler un prêt quand un autre membre du groupe est en retard',
            self::Bloquer => 'Refuser un prêt tant qu\'un autre membre du groupe est en retard',
        };
    }
}
