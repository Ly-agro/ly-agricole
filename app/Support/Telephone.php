<?php

namespace App\Support;

/**
 * Numéros ivoiriens : 10 chiffres. On accepte les saisies avec espaces, points,
 * tirets et indicatif (+225 ou 00225), et on stocke les 10 chiffres seuls : sans
 * cela, deux saisies du même numéro échapperaient à la détection des doublons.
 */
class Telephone
{
    public const REGLE = 'regex:/^\d{10}$/';

    /** Forme normalisée, ou la saisie nettoyée telle quelle si elle n'a pas 10 chiffres (la validation la refusera). */
    public static function normaliser(?string $saisie): ?string
    {
        if ($saisie === null || trim($saisie) === '') {
            return null;
        }

        $chiffres = preg_replace('/[\s.\-()]/', '', $saisie) ?? '';
        $chiffres = preg_replace('/^(\+|00)225/', '', $chiffres) ?? '';

        return $chiffres;
    }

    public static function afficher(?string $numero): string
    {
        if ($numero === null) {
            return '—';
        }

        return strlen($numero) === 10 ? implode(' ', str_split($numero, 2)) : $numero;
    }
}
