<?php

namespace App\Enums;

/**
 * Rôles du back-office (cahier des charges §2). Un utilisateur a un seul rôle.
 * Le producteur n'est pas un utilisateur : il reçoit des SMS, il ne se connecte pas.
 */
enum Role: string
{
    case Admin = 'admin';
    case Direction = 'direction';
    case Comptable = 'comptable';
    case Agent = 'agent';
    case Agronome = 'agronome';
    case Investisseur = 'investisseur';

    public function libelle(): string
    {
        return match ($this) {
            self::Admin => 'Administrateur',
            self::Direction => 'Direction',
            self::Comptable => 'Comptable',
            self::Agent => 'Agent de terrain',
            self::Agronome => 'Agronome',
            self::Investisseur => 'Investisseur',
        };
    }
}
