<?php

namespace App\Support;

use Closure;

/**
 * Lecture d'une mesure décimale saisie (poids en kg, humidité en %, KOR en lbs) vers un
 * ENTIER dans l'unité de stockage, sans jamais passer par un float (D4) :
 * « 500,250 » kg avec 3 décimales → 500 250 g ; « 8,5 » % avec 1 → 85 ‰.
 * Virgule ou point acceptés comme séparateur décimal ; espaces ignorés. Plus de
 * décimales que prévu = refus (on ne devine pas un arrondi).
 */
class Mesure
{
    public static function depuisSaisie(?string $saisie, int $decimales): ?int
    {
        $propre = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $saisie) ?? '';
        $propre = str_replace(',', '.', $propre);

        if (! preg_match('/^(\d{1,12})(?:\.(\d+))?$/', $propre, $m)) {
            return null;
        }

        $fraction = $m[2] ?? '';
        if (strlen($fraction) > $decimales) {
            return null;
        }

        return (int) ($m[1].str_pad($fraction, $decimales, '0'));
    }

    /**
     * Règle de validation Laravel.
     *
     * @return Closure(string, mixed, Closure(string): mixed): void
     */
    public static function regle(int $decimales, string $exemple): Closure
    {
        return function (string $attribut, mixed $valeur, Closure $echec) use ($decimales, $exemple) {
            if (self::depuisSaisie(is_scalar($valeur) ? (string) $valeur : null, $decimales) === null) {
                $echec("Nombre attendu, au plus {$decimales} chiffre(s) après la virgule (ex. {$exemple}).");
            }
        };
    }

    /** Entier stocké → texte à décimales, pour réafficher une saisie. */
    public static function versSaisie(?int $valeur, int $decimales): string
    {
        if ($valeur === null) {
            return '';
        }
        $diviseur = 10 ** $decimales;
        $fraction = $decimales === 0 ? '' : ','.str_pad((string) ($valeur % $diviseur), $decimales, '0', STR_PAD_LEFT);

        return intdiv($valeur, $diviseur).$fraction;
    }
}
