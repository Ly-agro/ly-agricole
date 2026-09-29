<?php

namespace App\Enums;

/**
 * Pratiques constatées lors d'une visite (cahier §4). Liste volontairement commune à
 * tous les produits (anacarde, karité, tomate…) : elle sert à comparer les parcelles
 * les plus et les moins productives. À faire valider par l'agronome (question 30).
 */
enum PratiqueCulturale: string
{
    case Debroussaillage = 'debroussaillage';
    case PareFeu = 'pare_feu';
    case Taille = 'taille';
    case Fertilisation = 'fertilisation';
    case Traitement = 'traitement';
    case Irrigation = 'irrigation';
    case RecolteEnCours = 'recolte_en_cours';
    case Sechage = 'sechage';

    public function libelle(): string
    {
        return match ($this) {
            self::Debroussaillage => 'Débroussaillage',
            self::PareFeu => 'Pare-feu',
            self::Taille => 'Taille',
            self::Fertilisation => 'Engrais / fumure',
            self::Traitement => 'Traitement',
            self::Irrigation => 'Arrosage / irrigation',
            self::RecolteEnCours => 'Récolte en cours',
            self::Sechage => 'Séchage',
        };
    }
}
