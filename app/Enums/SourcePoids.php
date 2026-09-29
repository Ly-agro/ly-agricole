<?php

namespace App\Enums;

/**
 * D'où vient le poids brut d'un achat : lu sur la balance Bluetooth, ou tapé à la main.
 * Ce n'est pas un contrôle qui bloque, c'est une TRACE : un poids tapé à la main alors qu'une
 * balance existe se voit, se compte et se discute (cahier §10 : « poids sans saisie manuelle »).
 * Un achat d'avant cette fonction (ou d'une appli qui ne l'envoie pas) n'a pas de source (null).
 */
enum SourcePoids: string
{
    case Manuel = 'manuel';
    case Balance = 'balance';

    public function libelle(): string
    {
        return match ($this) {
            self::Manuel => 'Pesée saisie à la main',
            self::Balance => 'Pesée lue sur la balance',
        };
    }
}
