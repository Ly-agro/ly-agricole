<?php

namespace App\Enums;

enum ActionJournal: string
{
    case Creation = 'creation';
    case Modification = 'modification';
    case Suppression = 'suppression';
    case Connexion = 'connexion';
    case Deconnexion = 'deconnexion';
    case EchecConnexion = 'echec_connexion';
    case BlocageConnexion = 'blocage_connexion';
    case DoublonConfirme = 'doublon_confirme';
    case ImpressionCarte = 'impression_carte';
    case RevocationAppareil = 'revocation_appareil';

    public function libelle(): string
    {
        return match ($this) {
            self::RevocationAppareil => 'Appareil coupé',
            self::ImpressionCarte => 'Impression de carte',
            self::DoublonConfirme => 'Doublon possible confirmé',
            self::Creation => 'Création',
            self::Modification => 'Modification',
            self::Suppression => 'Suppression',
            self::Connexion => 'Connexion',
            self::Deconnexion => 'Déconnexion',
            self::EchecConnexion => 'Échec de connexion',
            self::BlocageConnexion => 'Connexion bloquée (trop d\'essais)',
        };
    }
}
