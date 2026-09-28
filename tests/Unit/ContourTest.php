<?php

namespace Tests\Unit;

use App\Services\Geo\Contour;
use Database\Factories\ParcelleFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContourTest extends TestCase
{
    private function json(mixed $valeur): string
    {
        return (string) json_encode($valeur);
    }

    #[Test]
    public function un_carre_de_100_m_de_cote_fait_un_hectare(): void
    {
        $surface = Contour::depuisGeojson($this->json(ParcelleFactory::geometrieCarre(100)))->surfaceM2();

        // Carré construit à partir des distances : l'écart ne vient que de la courbure.
        $this->assertEqualsWithDelta(10_000, $surface, 10);
    }

    #[Test]
    public function la_surface_ne_depend_pas_du_sens_de_parcours(): void
    {
        $geometrie = ParcelleFactory::geometrieCarre(250);
        $inverse = ['type' => 'Polygon', 'coordinates' => [array_reverse($geometrie['coordinates'][0])]];

        $this->assertSame(
            Contour::depuisGeometrie($geometrie)->surfaceM2(),
            Contour::depuisGeometrie($inverse)->surfaceM2(),
        );
    }

    #[Test]
    public function un_trou_se_retranche_et_un_multipolygone_s_additionne(): void
    {
        $grand = ParcelleFactory::geometrieCarre(200, 9.45, -5.63)['coordinates'][0];
        $trou = ParcelleFactory::geometrieCarre(100, 9.4502, -5.6298)['coordinates'][0];
        $autre = ParcelleFactory::geometrieCarre(100, 9.50, -5.70)['coordinates'][0];

        $avecTrou = Contour::depuisGeometrie(['type' => 'Polygon', 'coordinates' => [$grand, $trou]])->surfaceM2();
        $this->assertEqualsWithDelta(30_000, $avecTrou, 30);

        $multi = Contour::depuisGeometrie(['type' => 'MultiPolygon', 'coordinates' => [[$grand], [$autre]]])->surfaceM2();
        $this->assertEqualsWithDelta(50_000, $multi, 50);
    }

    #[Test]
    public function une_feature_ou_une_collection_d_une_seule_parcelle_est_acceptee(): void
    {
        $geometrie = ParcelleFactory::geometrieCarre(100);
        $feature = ['type' => 'Feature', 'properties' => ['nom' => 'Champ'], 'geometry' => $geometrie];

        $this->assertSame($geometrie, Contour::depuisGeojson($this->json($feature))->geometrie);
        $this->assertSame($geometrie, Contour::depuisGeojson($this->json(['type' => 'FeatureCollection', 'features' => [$feature]]))->geometrie);
    }

    /** @return array<string, array{string, string}> */
    public static function contoursRefuses(): array
    {
        $carre = ParcelleFactory::geometrieCarre(100);
        $anneau = $carre['coordinates'][0];
        $inverse = array_map(fn ($p) => [$p[1], $p[0]], $anneau);
        $ouvert = array_slice($anneau, 0, 4);
        $feature = ['type' => 'Feature', 'geometry' => $carre];

        return [
            'pas du JSON' => ['<kml>…</kml>', 'pas du GeoJSON lisible'],
            'une ligne' => [(string) json_encode(['type' => 'LineString', 'coordinates' => $anneau]), 'type trouvé : LineString'],
            'deux parcelles' => [(string) json_encode(['type' => 'FeatureCollection', 'features' => [$feature, $feature]]), 'une seule parcelle (il en contient 2)'],
            'anneau ouvert' => [(string) json_encode(['type' => 'Polygon', 'coordinates' => [$ouvert]]), 'pas fermé'],
            'trop peu de points' => [(string) json_encode(['type' => 'Polygon', 'coordinates' => [[$anneau[0], $anneau[1], $anneau[0]]]]), 'au moins 3 sommets'],
            'lat et lng inversées' => [(string) json_encode(['type' => 'Polygon', 'coordinates' => [$inverse]]), 'latitude et longitude semblent inversées'],
            'hors de Côte d\'Ivoire' => [(string) json_encode(ParcelleFactory::geometrieCarre(100, 48.85, 2.35)), 'hors de Côte d\'Ivoire'],
            'surface nulle' => [(string) json_encode(['type' => 'Polygon', 'coordinates' => [[$anneau[0], $anneau[1], $anneau[1], $anneau[0]]]]), 'surface nulle'],
        ];
    }

    #[Test]
    #[DataProvider('contoursRefuses')]
    public function un_contour_invalide_est_refuse_avec_un_message_lisible(string $texte, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        Contour::depuisGeojson($texte);
    }

    #[Test]
    public function le_dessin_svg_tient_dans_le_cadre(): void
    {
        $points = Contour::depuisGeometrie(ParcelleFactory::geometrieCarre(100))->pointsSvg(200, 10);

        $this->assertCount(1, $points);
        foreach (explode(' ', $points[0]) as $point) {
            [$x, $y] = array_map('floatval', explode(',', $point));
            $this->assertGreaterThanOrEqual(9.9, $x);
            $this->assertLessThanOrEqual(190.1, $x);
            $this->assertGreaterThanOrEqual(9.9, $y);
            $this->assertLessThanOrEqual(190.1, $y);
        }
    }
}
