<?php

namespace App\Enums;

/**
 * Paramètres connus du code. Aucune valeur par défaut : un seuil non défini ne doit
 * jamais valoir « pas de contrôle ». Le code qui lit un seuil absent doit exiger la
 * validation (voir docs/QUESTIONS_OUVERTES.md, n° 5).
 */
enum CleParametre: string
{
    case SeuilValidationDepense = 'seuil_validation_depense_fcfa';
    case SeuilValidationAchat = 'seuil_validation_achat_fcfa';
    case SeuilValidationPret = 'seuil_validation_pret_fcfa';

    public function libelle(): string
    {
        return match ($this) {
            self::SeuilValidationDepense => 'Seuil de validation des dépenses',
            self::SeuilValidationAchat => 'Seuil de validation des achats',
            self::SeuilValidationPret => 'Seuil de validation des prêts',
        };
    }

    public function aide(): string
    {
        return match ($this) {
            self::SeuilValidationDepense => 'Au-dessus de ce montant, une dépense doit être validée par une autre personne que son auteur.',
            self::SeuilValidationAchat => 'Au-dessus de ce montant, un achat doit être validé par une autre personne que son auteur.',
            self::SeuilValidationPret => 'Au-dessus de ce montant, un prêt demande une double validation.',
        };
    }

    /** Tous les paramètres actuels sont des montants en FCFA entiers. */
    public function unite(): string
    {
        return 'FCFA';
    }
}
