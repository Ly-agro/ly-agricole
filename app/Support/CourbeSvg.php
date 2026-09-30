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
 * avec une marge autour des valeurs ; jamais sous zéro. Axe horizontal : le temps, linéaire.
 */
class CourbeSvg
{
    public const LARGEUR = 760;

    public const HAUTEUR = 300;

    private const MARGE = ['gauche' => 68, 'droite' => 112, 'haut' => 18, 'bas' => 38];

    private const PAS = [5, 10, 25, 50, 100, 200, 250, 500, 1000, 2500, 5000, 10_000, 50_000, 100_000];

    /**
     * @param  list<array{date: CarbonInterface, prix: int}>  $points  triés par date croissante, une valeur par date
     * @return array{
     *     largeur: int, hauteur: int, chemin: string,
     *     marqueurs: list<array{x: float, y: float, index: int}>,
     *     graduations_y: list<array{y: float, valeur: int}>,
     *     graduations_x: list<array{x: float, date: CarbonInterface}>,
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

        $graduationsX = [];
        $nb = 5;
        for ($i = 0; $i <= $nb; $i++) {
            $date = $debut->copy()->startOfDay()->addSeconds((int) round(($t1 - $t0) * $i / $nb));
            $graduationsX[] = ['x' => round($g + ($d - $g) * $i / $nb, 1), 'date' => $date];
        }

        return [
            'largeur' => self::LARGEUR,
            'hauteur' => self::HAUTEUR,
            'chemin' => $chemin,
            'marqueurs' => $marqueurs,
            'graduations_y' => $graduationsY,
            'graduations_x' => $graduationsX,
            'zone' => ['gauche' => (float) $g, 'droite' => (float) $d, 'haut' => (float) $h, 'bas' => (float) $b],
            'fin' => ['x' => (float) $d, 'y' => round($yPrecedent, 1)],
        ];
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
