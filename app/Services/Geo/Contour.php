<?php

namespace App\Services\Geo;

use InvalidArgumentException;

/**
 * Contour de parcelle en GeoJSON (WGS84, [longitude, latitude]) : lecture stricte et
 * surface calculée sur la sphère — la même méthode que les outils de cartographie
 * (turf.js, geojson-area). La surface n'est jamais saisie (docs/MODELE_DE_DONNEES.md).
 *
 * Les calculs géométriques sont en flottants : la règle « jamais de float » (D4) vaut
 * pour l'argent et les poids. La surface est arrondie au m² une seule fois, puis
 * stockée en entier.
 */
class Contour
{
    /** Rayon équatorial WGS84, en mètres (valeur de turf.js). */
    private const RAYON_TERRE = 6378137.0;

    /**
     * Côte d'Ivoire avec une marge (lat. ≈ 4,35 à 10,74 ; long. ≈ −8,60 à −2,49) : sert à
     * repérer une latitude et une longitude inversées, erreur fréquente à l'export.
     */
    private const LAT_MIN = 4.0;

    private const LAT_MAX = 11.5;

    private const LNG_MIN = -9.0;

    private const LNG_MAX = -2.0;

    /**
     * @param  array{type: string, coordinates: array<mixed>}  $geometrie  Polygon ou MultiPolygon
     */
    private function __construct(public readonly array $geometrie) {}

    /**
     * Lit un fichier ou un texte GeoJSON : Polygon, MultiPolygon, Feature, ou
     * FeatureCollection d'une seule parcelle.
     *
     * @throws InvalidArgumentException avec un message lisible par l'utilisateur
     */
    public static function depuisGeojson(string $texte): self
    {
        try {
            $donnees = json_decode($texte, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Ce fichier n\'est pas du GeoJSON lisible.');
        }

        if (! is_array($donnees)) {
            throw new InvalidArgumentException('Ce fichier n\'est pas du GeoJSON lisible.');
        }

        $geometrie = self::extraireGeometrie($donnees);
        self::verifier($geometrie);

        return new self(['type' => $geometrie['type'], 'coordinates' => $geometrie['coordinates']]);
    }

    /**
     * @param  array{type: string, coordinates: array<mixed>}  $geometrie
     */
    public static function depuisGeometrie(array $geometrie): self
    {
        self::verifier($geometrie);

        return new self($geometrie);
    }

    /** Surface en m², arrondie une seule fois. */
    public function surfaceM2(): int
    {
        $polygones = $this->geometrie['type'] === 'Polygon'
            ? [$this->geometrie['coordinates']]
            : $this->geometrie['coordinates'];

        $total = 0.0;
        foreach ($polygones as $anneaux) {
            foreach ($anneaux as $i => $anneau) {
                // Le premier anneau est l'extérieur ; les suivants sont des trous.
                $aire = abs(self::aireAnneau($anneau));
                $total += $i === 0 ? $aire : -$aire;
            }
        }

        return (int) round(max($total, 0.0));
    }

    /**
     * Anneaux extérieurs projetés dans un carré de `$taille` px, pour un dessin SVG sans
     * fond de carte (pas de réseau, rien n'est envoyé à un serveur de cartes).
     *
     * @return list<string> un attribut `points` SVG par polygone
     */
    public function pointsSvg(int $taille = 200, int $marge = 10): array
    {
        $polygones = $this->geometrie['type'] === 'Polygon'
            ? [$this->geometrie['coordinates']]
            : $this->geometrie['coordinates'];

        $positions = array_merge(...array_map(fn ($p) => $p[0], $polygones));
        $lngs = array_column($positions, 0);
        $lats = array_column($positions, 1);
        $cosLat = cos(deg2rad((min($lats) + max($lats)) / 2));

        $largeur = max((max($lngs) - min($lngs)) * $cosLat, 1e-12);
        $hauteur = max(max($lats) - min($lats), 1e-12);
        $echelle = ($taille - 2 * $marge) / max($largeur, $hauteur);

        return array_map(function ($polygone) use ($lngs, $lats, $cosLat, $echelle, $marge) {
            return implode(' ', array_map(function ($pos) use ($lngs, $lats, $cosLat, $echelle, $marge) {
                $x = $marge + ($pos[0] - min($lngs)) * $cosLat * $echelle;
                $y = $marge + (max($lats) - $pos[1]) * $echelle;

                return round($x, 1).','.round($y, 1);
            }, $polygone[0]));
        }, $polygones);
    }

    /**
     * @param  array<mixed>  $donnees
     * @return array{type: string, coordinates: array<mixed>}
     */
    private static function extraireGeometrie(array $donnees): array
    {
        $type = $donnees['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $features = $donnees['features'] ?? [];
            if (! is_array($features) || count($features) !== 1) {
                throw new InvalidArgumentException('Le fichier doit contenir une seule parcelle (il en contient '.(is_array($features) ? count($features) : 0).').');
            }

            return self::extraireGeometrie($features[0]);
        }

        if ($type === 'Feature') {
            return self::extraireGeometrie(is_array($donnees['geometry'] ?? null) ? $donnees['geometry'] : []);
        }

        if (in_array($type, ['Polygon', 'MultiPolygon'], true) && is_array($donnees['coordinates'] ?? null)) {
            return ['type' => $type, 'coordinates' => $donnees['coordinates']];
        }

        throw new InvalidArgumentException('Le contour doit être un polygone (type trouvé : '.(is_string($type) ? $type : 'aucun').').');
    }

    /**
     * @param  array{type: string, coordinates: array<mixed>}  $geometrie
     */
    private static function verifier(array $geometrie): void
    {
        $polygones = $geometrie['type'] === 'Polygon' ? [$geometrie['coordinates']] : $geometrie['coordinates'];

        if ($polygones === []) {
            throw new InvalidArgumentException('Le contour est vide.');
        }

        foreach ($polygones as $anneaux) {
            if (! is_array($anneaux) || $anneaux === []) {
                throw new InvalidArgumentException('Le contour est vide.');
            }

            foreach ($anneaux as $anneau) {
                if (! is_array($anneau) || count($anneau) < 4) {
                    throw new InvalidArgumentException('Un contour doit avoir au moins 3 sommets (4 positions, la dernière égale à la première).');
                }

                foreach ($anneau as $position) {
                    if (! is_array($position) || count($position) < 2 || ! is_numeric($position[0]) || ! is_numeric($position[1])) {
                        throw new InvalidArgumentException('Une position du contour n\'est pas une paire [longitude, latitude].');
                    }

                    [$lng, $lat] = [(float) $position[0], (float) $position[1]];
                    if ($lat < self::LAT_MIN || $lat > self::LAT_MAX || $lng < self::LNG_MIN || $lng > self::LNG_MAX) {
                        $inverse = $lng >= self::LAT_MIN && $lng <= self::LAT_MAX && $lat >= self::LNG_MIN && $lat <= self::LNG_MAX;
                        throw new InvalidArgumentException($inverse
                            ? 'Le contour tombe hors de Côte d\'Ivoire : latitude et longitude semblent inversées (GeoJSON attend [longitude, latitude]).'
                            : "Le contour tombe hors de Côte d'Ivoire (point {$lng}, {$lat}).");
                    }
                }

                if ($anneau[0][0] != end($anneau)[0] || $anneau[0][1] != end($anneau)[1]) {
                    throw new InvalidArgumentException('Le contour n\'est pas fermé : la dernière position doit être égale à la première.');
                }
            }
        }

        if ((new self($geometrie))->surfaceM2() === 0) {
            throw new InvalidArgumentException('Ce contour a une surface nulle.');
        }
    }

    /**
     * Aire signée d'un anneau sur la sphère (Chamberlain & Duquette, « Some algorithms
     * for polygons on a sphere », JPL 2007 — méthode de turf.js).
     *
     * @param  array<int, array<int, float|int>>  $anneau
     */
    private static function aireAnneau(array $anneau): float
    {
        $n = count($anneau);
        if ($n <= 2) {
            return 0.0;
        }

        $somme = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $bas = $anneau[$i];
            $milieu = $anneau[($i + 1) % $n];
            $haut = $anneau[($i + 2) % $n];
            $somme += (deg2rad((float) $haut[0]) - deg2rad((float) $bas[0])) * sin(deg2rad((float) $milieu[1]));
        }

        return $somme * self::RAYON_TERRE * self::RAYON_TERRE / 2;
    }
}
