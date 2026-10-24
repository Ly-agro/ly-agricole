<?php

namespace App\Support;

use Closure;

/**
 * Lecture d'un montant saisi en FCFA entiers (D4). Les espaces (y compris insécables)
 * séparent les milliers et sont acceptés ; la virgule et le point sont REFUSÉS : « 1.500 »
 * peut vouloir dire 1 500 ou 1,5 — un montant ambigu ne doit pas être deviné.
 */
class Montant
{
    public const MAX = 999_999_999_999;

    public static function depuisSaisie(?string $saisie): ?int
    {
        $propre = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $saisie) ?? '';

        if ($propre === '' || ! ctype_digit($propre) || strlen($propre) > 12) {
            return null;
        }

        return (int) $propre;
    }

    /**
     * Règle de validation Laravel : montant entier strictement positif.
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    public static function regle(): Closure
    {
        return function (string $attribut, mixed $valeur, Closure $echec) {
            $montant = self::depuisSaisie(is_scalar($valeur) ? (string) $valeur : null);

            if ($montant === null) {
                $echec('Le montant doit être un nombre entier de FCFA, sans virgule ni point (ex. 1 500 000).');
            } elseif ($montant <= 0 || $montant > self::MAX) {
                $echec('Le montant doit être supérieur à zéro.');
            }
        };
    }
}
