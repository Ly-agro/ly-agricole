<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Un tableau de rapport, rendu tel quel à l'écran, en PDF et en fichier pour Excel.
 * Les cellules gardent leur valeur brute (FCFA entiers, grammes) : la mise en forme se
 * fait au rendu, jamais dans les chiffres (D4).
 */
final class Tableau
{
    public const TEXTE = 'texte';

    public const FCFA = 'fcfa';

    public const KG = 'kg';

    public const POUR_MILLE = 'pour_mille';

    public const DATE = 'date';

    public const NOMBRE = 'nombre';

    /**
     * @param  list<array{0: string, 1: string}>  $colonnes  [libellé, type]
     * @param  list<list<int|string|Carbon|null>>  $lignes
     * @param  list<int|string|null>|null  $totaux
     */
    public function __construct(
        public readonly string $titre,
        public readonly array $colonnes,
        public readonly array $lignes,
        public readonly ?array $totaux = null,
        public readonly ?string $note = null,
    ) {}

    /**
     * Somme d'une colonne numérique (entiers).
     *
     * @param  list<list<int|string|Carbon|null>>  $lignes
     */
    public static function somme(array $lignes, int $colonne): int
    {
        return array_sum(array_map(fn (array $l) => (int) ($l[$colonne] ?? 0), $lignes));
    }

    /** Cellule mise en forme pour l'écran et le PDF. */
    public static function afficher(mixed $valeur, string $type): string
    {
        if ($valeur === null || $valeur === '') {
            return '—';
        }

        return match ($type) {
            self::FCFA => Format::fcfa((int) $valeur),
            self::KG => Format::kg((int) $valeur),
            self::POUR_MILLE => self::pourMille((int) $valeur),
            self::DATE => $valeur instanceof Carbon ? $valeur->format('d/m/Y') : (string) $valeur,
            self::NOMBRE => number_format((int) $valeur, 0, ',', "\u{202F}"),
            default => (string) $valeur,
        };
    }

    /**
     * Cellule pour Excel : nombres sans séparateur de milliers (additionnables), kilos
     * avec la virgule décimale française, pour mille en pourcentage à 1 décimale.
     */
    public static function brut(mixed $valeur, string $type): string
    {
        if ($valeur === null) {
            return '';
        }

        return match ($type) {
            self::FCFA, self::NOMBRE => (string) (int) $valeur,
            self::KG => Mesure::versSaisie((int) $valeur, 3),
            self::POUR_MILLE => self::pourMille((int) $valeur, false),
            self::DATE => $valeur instanceof Carbon ? $valeur->format('d/m/Y') : (string) $valeur,
            default => (string) $valeur,
        };
    }

    /** 52 ‰ → « 5,2 % », en entiers. */
    private static function pourMille(int $valeur, bool $unite = true): string
    {
        $signe = $valeur < 0 ? '-' : '';
        $absolu = abs($valeur);

        return $signe.intdiv($absolu, 10).','.($absolu % 10).($unite ? ' %' : '');
    }
}
