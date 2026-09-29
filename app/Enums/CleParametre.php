<?php

namespace App\Enums;

/**
 * Paramètres connus du code. Aucune valeur par défaut : un seuil non défini ne doit
 * jamais valoir « pas de contrôle ». Le code qui lit un seuil absent doit exiger la
 * validation (voir docs/QUESTIONS_OUVERTES.md, n° 5). Une règle non choisie bloque ce
 * qui en dépend, au lieu d'être tranchée par le code.
 */
enum CleParametre: string
{
    case SeuilValidationDepense = 'seuil_validation_depense_fcfa';
    case SeuilValidationAchat = 'seuil_validation_achat_fcfa';
    case SeuilValidationVente = 'seuil_validation_vente_fcfa';
    case SeuilValidationPret = 'seuil_validation_pret_fcfa';
    case PlafondPretProducteur = 'plafond_pret_producteur_fcfa';
    case PlafondPretHectare = 'plafond_pret_hectare_fcfa';
    case RegleRemboursementNature = 'regle_remboursement_nature';

    public function libelle(): string
    {
        return match ($this) {
            self::SeuilValidationDepense => 'Seuil de validation des dépenses',
            self::SeuilValidationAchat => 'Seuil de validation des achats',
            self::SeuilValidationVente => 'Seuil de validation des ventes',
            self::SeuilValidationPret => 'Seuil de double validation des prêts',
            self::PlafondPretProducteur => 'Plafond de prêt par producteur et par campagne',
            self::PlafondPretHectare => 'Plafond de prêt par hectare financé',
            self::RegleRemboursementNature => 'Valorisation des remboursements en kilos',
        };
    }

    public function aide(): string
    {
        return match ($this) {
            self::SeuilValidationDepense => 'Au-dessus de ce montant, une dépense doit être validée par une autre personne que son auteur.',
            self::SeuilValidationAchat => 'Au-dessus de ce montant (ou tant qu\'il n\'est pas défini), un achat doit être validé par une autre personne que son auteur.',
            self::SeuilValidationVente => 'Au-dessus de ce montant (ou tant qu\'il n\'est pas défini), une vente doit être validée par une autre personne que son auteur avant que le stock ne sorte.',
            self::SeuilValidationPret => 'Tout prêt est validé par une autre personne que son auteur ; au-dessus de ce montant (ou tant qu\'il n\'est pas défini), par deux.',
            self::PlafondPretProducteur => 'Total des prêts d\'un producteur sur une campagne. Non défini : pas de plafond automatique (la validation reste obligatoire).',
            self::PlafondPretHectare => 'Montant maximal par hectare de parcelles financées (surfaces relevées). Non défini : pas de plafond automatique.',
            self::RegleRemboursementNature => 'Prix appliqué aux kilos livrés en remboursement d\'un prêt (question 3). Tant qu\'il n\'est pas choisi, un achat ne peut pas rembourser un prêt.',
        };
    }

    /** Un choix dans une liste (et non un montant). */
    public function estUnChoix(): bool
    {
        return $this === self::RegleRemboursementNature;
    }

    /**
     * Valeurs possibles d'un paramètre à choix : valeur => libellé.
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return match ($this) {
            self::RegleRemboursementNature => collect(RegleValorisationNature::cases())
                ->mapWithKeys(fn (RegleValorisationNature $r) => [$r->value => $r->libelle()])->all(),
            default => [],
        };
    }

    public function unite(): string
    {
        return match ($this) {
            self::PlafondPretHectare => 'FCFA / ha',
            self::RegleRemboursementNature => '',
            default => 'FCFA',
        };
    }
}
