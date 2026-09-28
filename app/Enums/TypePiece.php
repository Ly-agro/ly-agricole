<?php

namespace App\Enums;

enum TypePiece: string
{
    case Cni = 'cni';
    case Passeport = 'passeport';
    case AttestationIdentite = 'attestation_identite';
    case CarteConsulaire = 'carte_consulaire';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Cni => 'Carte nationale d\'identité',
            self::Passeport => 'Passeport',
            self::AttestationIdentite => 'Attestation d\'identité',
            self::CarteConsulaire => 'Carte consulaire',
            self::Autre => 'Autre pièce',
        };
    }
}
