<?php

namespace App\Enums;

/**
 * Pourquoi l'argent a bougé. Viendront : décaissement de prêt (sem. 4), achat (sem. 6),
 * remboursement en espèces, encaissement de vente (phase 2).
 */
enum NatureMouvement: string
{
    case Apport = 'apport';
    case AutreEntree = 'autre_entree';
    case Virement = 'virement';
    case AvanceAgent = 'avance_agent';
    case Depense = 'depense';
    case DecaissementPret = 'decaissement_pret';
    case ContrePassation = 'contre_passation';

    public function libelle(): string
    {
        return match ($this) {
            self::Apport => 'Apport de fonds',
            self::AutreEntree => 'Autre entrée',
            self::Virement => 'Virement interne',
            self::AvanceAgent => 'Avance à un agent',
            self::Depense => 'Dépense',
            self::DecaissementPret => 'Décaissement de prêt',
            self::ContrePassation => 'Contre-passation',
        };
    }

    /**
     * Natures qu'on saisit à la main comme simple entrée d'argent.
     *
     * @return list<self>
     */
    public static function entreesManuelles(): array
    {
        return [self::Apport, self::AutreEntree];
    }
}
