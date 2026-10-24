<?php

namespace App\Services\Geo;

/**
 * Plusieurs contours dans un même dessin SVG, avec une projection commune (mêmes
 * échelle et origine pour tous : les distances entre parcelles restent vraies).
 * Pas de fond de carte : rien n'est envoyé à un serveur de cartes.
 */
class CarteSvg
{
    /**
     * @param  array<string, array{type: string, coordinates: array<mixed>}>  $geometries  par clé
     * @return array{largeur: int, hauteur: int, polygones: array<string, list<string>>} attributs `points` par clé
     */
    public static function projeter(array $geometries, int $taille = 600, int $marge = 10): array
    {
        if ($geometries === []) {
            return ['largeur' => $taille, 'hauteur' => $taille, 'polygones' => []];
        }

        $anneaux = [];
        foreach ($geometries as $cle => $geometrie) {
            $polygones = $geometrie['type'] === 'Polygon' ? [$geometrie['coordinates']] : $geometrie['coordinates'];
            $anneaux[$cle] = array_map(fn ($p) => $p[0], $polygones);
        }

        $positions = array_merge(...array_merge(...array_values($anneaux)));
        $lngs = array_column($positions, 0);
        $lats = array_column($positions, 1);
        $minLng = min($lngs);
        $maxLat = max($lats);
        $cosLat = cos(deg2rad((min($lats) + $maxLat) / 2));

        $largeur = max((max($lngs) - $minLng) * $cosLat, 1e-12);
        $hauteur = max($maxLat - min($lats), 1e-12);
        $echelle = ($taille - 2 * $marge) / max($largeur, $hauteur);

        $polygones = [];
        foreach ($anneaux as $cle => $liste) {
            $polygones[$cle] = array_map(fn ($anneau) => implode(' ', array_map(
                fn ($pos) => round($marge + ($pos[0] - $minLng) * $cosLat * $echelle, 1).','.round($marge + ($maxLat - $pos[1]) * $echelle, 1),
                $anneau,
            )), $liste);
        }

        return [
            'largeur' => (int) ceil($marge * 2 + $largeur * $echelle),
            'hauteur' => (int) ceil($marge * 2 + $hauteur * $echelle),
            'polygones' => $polygones,
        ];
    }
}
