<?php

namespace App\Enums;

/**
 * Qui achète le lot (cahier §7, stade Revente). Texte libre pour le nom : pas de table
 * dédiée tant qu'aucune donnée propre à l'acheteur (commission, contrat cadre) n'est
 * demandée — contrairement aux pisteurs, qui ont la leur pour leur commission.
 */
enum TypeAcheteur: string
{
    case Exportateur = 'exportateur';
    case Grossiste = 'grossiste';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Exportateur => 'Exportateur',
            self::Grossiste => 'Grossiste',
            self::Autre => 'Autre',
        };
    }
}
