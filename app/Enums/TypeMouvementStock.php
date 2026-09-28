<?php

namespace App\Enums;

enum TypeMouvementStock: string
{
    case EntreeAchat = 'entree_achat';
    case TransfertSortie = 'transfert_sortie';
    case TransfertEntree = 'transfert_entree';
    case Perte = 'perte';
    case AjustementInventaire = 'ajustement_inventaire';
    case ContrePassation = 'contre_passation';

    public function libelle(): string
    {
        return match ($this) {
            self::EntreeAchat => 'Entrée (achat)',
            self::TransfertSortie => 'Transfert (sortie)',
            self::TransfertEntree => 'Transfert (entrée)',
            self::Perte => 'Perte',
            self::AjustementInventaire => 'Ajustement d\'inventaire',
            self::ContrePassation => 'Contre-passation',
        };
    }
}
