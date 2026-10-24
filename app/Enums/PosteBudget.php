<?php

namespace App\Enums;

/**
 * Poste d'un budget de campagne. Les dépenses se budgètent par catégorie ; les achats
 * et les prêts, qui ne passent pas par les dépenses, ont chacun leur poste.
 */
enum PosteBudget: string
{
    /** Argent payé aux fournisseurs (la part retenue sur un prêt n'est pas de l'argent sorti). */
    case Achats = 'achats';
    /** Argent versé aux producteurs au titre des prêts (décaissements). */
    case Prets = 'prets';
    case Categorie = 'categorie';

    public function libelle(): string
    {
        return match ($this) {
            self::Achats => 'Achats de produit (argent payé)',
            self::Prets => 'Prêts aux producteurs (argent versé)',
            self::Categorie => 'Dépenses',
        };
    }
}
