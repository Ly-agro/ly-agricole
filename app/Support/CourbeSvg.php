<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Géométrie d'une courbe de prix en escalier (le prix reste le même jusqu'au changement suivant),
 * pour un dessin SVG fait côté serveur, sans bibliothèque. Fonction PURE : elle ne lit rien, elle
 * place des points. Les flottants ne servent qu'à la géométrie (positions à l'écran) : les prix
 * restent des entiers, jamais recalculés ici.
 *
 * Axe vertical : une seule échelle par graphique, graduée en pas « ronds » (10, 25, 50, 100…),
 * avec une marge autour des valeurs ; jamais sous zéro. Axe horizontal : le temps, linéaire, gradué
 * sur des dates rondes (1er janvier, 1er du mois) selon la durée affichée.
 */
class CourbeSvg
{
    public const LARGEUR = 760;

    public const HAUTEUR = 320;

    private const MARGE = ['gauche' => 56, 'droite' => 84, 'haut' => 24, 'bas' => 40];

    private const PAS = [5, 10, 25, 50, 100, 200, 250, 500, 1000, 2500, 5000, 10_000, 50_000, 100_000];

    private const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    /**
     * @param  list<array{date: CarbonInterface, prix: int}>  $points  triés par date croissante, une valeur par date
     * @return array{
     *     largeur: int, hauteur: int, chemin: string, aire: string,
     *     marqueurs: list<array{x: float, y: float, index: int}>,
     *     graduations_y: list<array{y: float, valeur: int}>,
     *     graduations_x: list<array{x: float, date: CarbonInterface, libelle: string}>,
     *     zone: array{gauche: float, droite: float, haut: float, bas: float},
     *     fin: array{x: float, y: float}
     * }|null null s'il n'y a aucun point
     */
    public static function construire(array $points, CarbonInterface $debut, CarbonInterface $fin): ?array
    {
        if ($points === []) {
            return null;
        }

        $g = self::MARGE['gauche'];
        $d = self::LARGEUR - self::MARGE['droite'];
        $h = self::MARGE['haut'];
        $b = self::HAUTEUR - self::MARGE['bas'];

        $t0 = $debut->copy()->startOfDay()->getTimestamp();
        $t1 = max($fin->copy()->startOfDay()->getTimestamp(), $t0 + 86_400);
        $x = fn (CarbonInterface $date) => $g + ($d - $g) * (min(max($date->copy()->startOfDay()->getTimestamp(), $t0), $t1) - $t0) / ($t1 - $t0);

        [$bas, $haut, $pas] = self::echelle(array_column($points, 'prix'));
        $y = fn (int $prix) => $b - ($b - $h) * ($prix - $bas) / ($haut - $bas);

        $chemin = '';
        $marqueurs = [];
        $yPrecedent = null;
        foreach ($points as $i => $p) {
            $px = $x($p['date']);
            $py = $y($p['prix']);
            $chemin .= $yPrecedent === null
                ? sprintf('M%.1f %.1f', $px, $py)
                : sprintf(' H%.1f V%.1f', $px, $py);
            $marqueurs[] = ['x' => round($px, 1), 'y' => round($py, 1), 'index' => $i];
            $yPrecedent = $py;
        }
        // Le dernier prix reste valable jusqu'à la fin de la période.
        $chemin .= sprintf(' H%.1f', $d);

        $graduationsY = [];
        for ($v = $bas; $v <= $haut; $v += $pas) {
            $graduationsY[] = ['y' => round($y($v), 1), 'valeur' => $v];
        }

        $graduationsX = array_map(
            fn (array $t) => ['x' => round($x($t['date']), 1), 'date' => $t['date'], 'libelle' => $t['libelle']],
            self::datesRondes($debut->copy()->startOfDay(), $debut->copy()->startOfDay()->setTimestamp($t1)),
        );

        // Aire sous l'escalier, fermée sur la ligne de base : un lavis, jamais une masse pleine.
        $aire = $chemin.sprintf(' V%.1f H%.1f Z', $b, $marqueurs[0]['x']);

        return [
            'largeur' => self::LARGEUR,
            'hauteur' => self::HAUTEUR,
            'chemin' => $chemin,
            'aire' => $aire,
            'marqueurs' => $marqueurs,
            'graduations_y' => $graduationsY,
            'graduations_x' => $graduationsX,
            'zone' => ['gauche' => (float) $g, 'droite' => (float) $d, 'haut' => (float) $h, 'bas' => (float) $b],
            'fin' => ['x' => (float) $d, 'y' => round($yPrecedent, 1)],
        ];
    }

    /**
     * Graduations du temps sur des dates rondes, 3 à 8 environ : le 1er janvier (« 2024 ») sur
     * plusieurs années, le 1er du mois (« oct. 2025 ») sur quelques mois, sinon des jours réguliers.
     *
     * @return list<array{date: CarbonInterface, libelle: string}>
     */
    public static function datesRondes(CarbonInterface $debut, CarbonInterface $fin): array
    {
        $mois = ($fin->year - $debut->year) * 12 + $fin->month - $debut->month;
        $dates = [];

        if ($mois >= 30) {
            $saut = $mois > 96 ? 2 : 1;
            for ($a = $debut->year + ($debut->dayOfYear > 1 ? 1 : 0); $a <= $fin->year; $a += $saut) {
                $dates[] = ['date' => $debut->copy()->setDate($a, 1, 1), 'libelle' => (string) $a];
            }
        } elseif ($mois >= 3) {
            $saut = $mois <= 7 ? 1 : ($mois <= 14 ? 2 : ($mois <= 21 ? 3 : 6));
            $courant = $debut->copy()->startOfMonth();
            if ($courant->lessThan($debut)) {
                $courant->addMonthNoOverflow();
            }
            for (; $courant->lessThanOrEqualTo($fin); $courant->addMonthsNoOverflow($saut)) {
                $dates[] = ['date' => $courant->copy(), 'libelle' => self::MOIS[$courant->month - 1].' '.$courant->year];
            }
        }

        if (count($dates) < 2) {
            $dates = [];
            $jours = max((int) $debut->diffInDays($fin), 1);
            for ($i = 0; $i <= 4; $i++) {
                $date = $debut->copy()->addDays((int) round($jours * $i / 4));
                $dates[] = ['date' => $date, 'libelle' => $date->format('d/m/y')];
            }
        }

        return $dates;
    }

    /**
     * Bornes et pas ronds pour les prix donnés : {bas, haut, pas}. bas ≥ 0, haut > bas, au plus
     * 6 graduations.
     *
     * @param  list<int>  $prix
     * @return array{0: int, 1: int, 2: int}
     */
    public static function echelle(array $prix): array
    {
        $min = min($prix);
        $max = max($prix);
        // Un prix constant (ou presque) ne doit pas se lire comme une envolée : l'échelle couvre au moins
        // un dixième du prix.
        $ecart = max($max - $min, (int) round($max * 0.1), 1);

        $pas = self::PAS[count(self::PAS) - 1];
        foreach (self::PAS as $candidat) {
            // Avec la marge d'un dixième de chaque côté, l'étendue est de 1,2 × l'écart.
            if (ceil(($ecart * 1.2) / $candidat) <= 5 && $candidat >= $ecart / 20) {
                $pas = $candidat;
                break;
            }
        }

        $marge = max($ecart * 0.1, $pas * 0.5);
        $bas = max(0, (int) (floor(($min - $marge) / $pas) * $pas));
        $haut = (int) (ceil(($max + $marge) / $pas) * $pas);
        if ($haut <= $bas) {
            $haut = $bas + $pas;
        }

        return [$bas, $haut, $pas];
    }
}
