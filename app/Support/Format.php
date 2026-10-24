<?php

namespace App\Support;

/**
 * Affichage des montants et des poids. Le stockage reste en FCFA entiers et en
 * grammes (D4) ; on ne convertit qu'ici, à l'affichage.
 * (Pas de `Number::format` : l'extension intl manque sur le poste de dev.)
 */
class Format
{
    /** Espace fine insécable, séparateur des milliers en français. */
    private const MILLIERS = "\u{202F}";

    /** Entier avec séparateur des milliers, sans unité. */
    public static function entier(int $n): string
    {
        return number_format($n, 0, ',', self::MILLIERS);
    }

    public static function fcfa(?int $montant): string
    {
        return $montant === null ? '—' : number_format($montant, 0, ',', self::MILLIERS).' FCFA';
    }

    /**
     * m² → hectares à 2 décimales (1 ha = 10 000 m²), arrondi au centième, en entiers.
     */
    public static function hectares(?int $m2): string
    {
        if ($m2 === null) {
            return '—';
        }

        $centiemes = intdiv($m2 + 50, 100);

        return number_format(intdiv($centiemes, 100), 0, ',', self::MILLIERS)
            .','.str_pad((string) ($centiemes % 100), 2, '0', STR_PAD_LEFT).' ha';
    }

    /**
     * Grammes → kg, sans arrondi caché : les grammes restants s'affichent en décimales.
     */
    public static function kg(?int $grammes): string
    {
        if ($grammes === null) {
            return '—';
        }

        // Calcul en entiers seulement (règle « jamais de float » pour les poids).
        $signe = $grammes < 0 ? '-' : '';
        $absolu = abs($grammes);
        $kg = number_format(intdiv($absolu, 1000), 0, ',', self::MILLIERS);
        $reste = $absolu % 1000;

        return $signe.$kg.($reste === 0 ? '' : ','.str_pad((string) $reste, 3, '0', STR_PAD_LEFT)).' kg';
    }
}
