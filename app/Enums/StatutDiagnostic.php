<?php

namespace App\Enums;

/** Cycle d'un diagnostic de photo (skill ly-agricole-ia-conseil, règles 2 et 3). */
enum StatutDiagnostic: string
{
    /** Demandé, le service IA ne l'a pas encore traité (file d'attente). */
    case EnAttente = 'en_attente';
    /** Le modèle propose une maladie au-dessus du seuil : à confirmer par un agronome. */
    case Propose = 'propose';
    /** Pas de modèle, ou confiance trop basse : un humain regarde la photo. */
    case Incertain = 'incertain';
    case Confirme = 'confirme';
    case Corrige = 'corrige';
    /** Service IA injoignable : redemandable. */
    case Erreur = 'erreur';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Propose => 'Proposé (à confirmer)',
            self::Incertain => 'Incertain (avis humain)',
            self::Confirme => 'Confirmé par l\'agronome',
            self::Corrige => 'Corrigé par l\'agronome',
            self::Erreur => 'Service IA indisponible',
        };
    }

    /** Validé par un agronome : seul ce cas sert à l'entraînement. */
    public function valide(): bool
    {
        return $this === self::Confirme || $this === self::Corrige;
    }
}
