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
    case PlafondPretProducteur = 'plafond_pret_producteur_fcfa';
    case PlafondPretHectare = 'plafond_pret_hectare_fcfa';

    public function libelle(): string
    {
        return match ($this) {
            self::SeuilValidationDepense => 'Seuil de validation des dépenses',
            self::SeuilValidationAchat => 'Seuil de validation des achats',
            self::SeuilValidationPret => 'Seuil de double validation des prêts',
            self::PlafondPretProducteur => 'Plafond de prêt par producteur et par campagne',
            self::PlafondPretHectare => 'Plafond de prêt par hectare financé',
        };
    }

    public function aide(): string
    {
        return match ($this) {
            self::SeuilValidationDepense => 'Au-dessus de ce montant, une dépense doit être validée par une autre personne que son auteur.',
            self::SeuilValidationAchat => 'Au-dessus de ce montant, un achat doit être validé par une autre personne que son auteur.',
            self::SeuilValidationPret => 'Tout prêt est validé par une autre personne que son auteur ; au-dessus de ce montant (ou tant qu\'il n\'est pas défini), par deux.',
            self::PlafondPretProducteur => 'Total des prêts d\'un producteur sur une campagne. Non défini : pas de plafond automatique (la validation reste obligatoire).',
            self::PlafondPretHectare => 'Montant maximal par hectare de parcelles financées (surfaces relevées). Non défini : pas de plafond automatique.',
        };
    }

    /** Tous les paramètres actuels sont des montants en FCFA entiers. */
    public function unite(): string
    {
        return $this === self::PlafondPretHectare ? 'FCFA / ha' : 'FCFA';
    }
}
