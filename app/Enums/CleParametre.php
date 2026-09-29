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
    case SeuilAlerteEcartPoids = 'seuil_alerte_ecart_poids_pour_mille';
    case CautionSolidaire = 'regle_caution_solidaire';

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
            self::SeuilAlerteEcartPoids => 'Seuil d\'alerte d\'écart de poids d\'un lot',
            self::CautionSolidaire => 'Caution solidaire des groupes de producteurs',
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
            self::SeuilAlerteEcartPoids => 'Écart (séchage, pertes, inventaire) au-delà duquel un lot est signalé dans les alertes, en pour mille des kilos achetés (50 = 5 %). Non défini : tous les lots avec un écart sont signalés.',
            self::CautionSolidaire => 'Ce que fait un prêt à un membre d\'un groupe quand un autre membre du même groupe a un prêt en retard (question 37). Non défini : aucune règle de groupe, les prêts ne changent pas.',
        };
    }

    /** Un choix dans une liste (et non un montant). */
    public function estUnChoix(): bool
    {
        return $this === self::RegleRemboursementNature || $this === self::CautionSolidaire;
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
            self::CautionSolidaire => collect(RegleCautionSolidaire::cases())
                ->mapWithKeys(fn (RegleCautionSolidaire $r) => [$r->value => $r->libelle()])->all(),
            default => [],
        };
    }

    public function unite(): string
    {
        return match ($this) {
            self::PlafondPretHectare => 'FCFA / ha',
            self::SeuilAlerteEcartPoids => '‰',
            self::RegleRemboursementNature, self::CautionSolidaire => '',
            default => 'FCFA',
        };
    }
}
