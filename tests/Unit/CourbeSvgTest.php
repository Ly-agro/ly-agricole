<?php

namespace Tests\Unit;

use App\Support\CourbeSvg;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Géométrie d'une courbe de prix en escalier : positions à l'écran, jamais de prix recalculé. */
class CourbeSvgTest extends TestCase
{
    private function jour(string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay();
    }

    #[Test]
    public function sans_point_pas_de_courbe(): void
    {
        $this->assertNull(CourbeSvg::construire([], $this->jour('2026-01-01'), $this->jour('2026-12-31')));
    }

    /** @return array<string, array{list<int>, int, int, int}> */
    public static function echelles(): array
    {
        return [
            'écart moyen' => [[1_200, 1_850], 1_000, 2_000, 200],
            'même prix' => [[1_200], 1_150, 1_250, 50],
            'petits prix' => [[250, 400], 200, 450, 50],
            'grand écart' => [[100, 2_800], 0, 4_000, 1_000],
            'très proche' => [[1_200, 1_205], 1_150, 1_250, 50],
        ];
    }

    /**
     * @param  list<int>  $prix
     */
    #[Test]
    #[DataProvider('echelles')]
    public function l_echelle_est_ronde_englobe_les_prix_et_ne_descend_pas_sous_zero(array $prix, int $bas, int $haut, int $pas): void
    {
        [$b, $h, $p] = CourbeSvg::echelle($prix);

        $this->assertSame([$bas, $haut, $pas], [$b, $h, $p]);
        $this->assertLessThanOrEqual(min($prix), $b);
        $this->assertGreaterThanOrEqual(max($prix), $h);
        $this->assertGreaterThanOrEqual(0, $b);
        $this->assertSame(0, $b % $p);
        $this->assertSame(0, $h % $p);
        $this->assertLessThanOrEqual(7, intdiv($h - $b, $p) + 1, 'Au plus quelques graduations.');
    }

    #[Test]
    public function l_echelle_ne_va_jamais_sous_zero_pour_de_petits_prix(): void
    {
        [$bas] = CourbeSvg::echelle([3, 8]);

        $this->assertSame(0, $bas);
    }

    #[Test]
    public function la_courbe_est_un_escalier_qui_se_prolonge_jusqu_a_la_fin(): void
    {
        $c = CourbeSvg::construire([
            ['date' => $this->jour('2026-01-01'), 'prix' => 1_800],
            ['date' => $this->jour('2026-07-01'), 'prix' => 1_200],
        ], $this->jour('2026-01-01'), $this->jour('2026-12-31'));

        $this->assertNotNull($c);
        $this->assertMatchesRegularExpression('/^M[\d.]+ [\d.]+ H[\d.]+ V[\d.]+ H[\d.]+$/', $c['chemin']);
        $this->assertCount(2, $c['marqueurs']);
        // Le premier point est au bord gauche de la zone, à la date de début.
        $this->assertSame(68.0, $c['marqueurs'][0]['x']);
        // Un prix plus bas se dessine plus bas à l'écran (y plus grand).
        $this->assertGreaterThan($c['marqueurs'][0]['y'], $c['marqueurs'][1]['y']);
        // La ligne va jusqu'au bord droit de la zone, au niveau du dernier prix.
        $this->assertSame($c['zone']['droite'], $c['fin']['x']);
        $this->assertSame($c['marqueurs'][1]['y'], $c['fin']['y']);
    }

    #[Test]
    public function tous_les_marqueurs_restent_dans_la_zone_de_dessin(): void
    {
        $c = CourbeSvg::construire([
            ['date' => $this->jour('2025-12-01'), 'prix' => 100], // avant le début : ramené au bord
            ['date' => $this->jour('2026-06-15'), 'prix' => 2_800],
            ['date' => $this->jour('2027-03-01'), 'prix' => 1_400], // après la fin : ramené au bord
        ], $this->jour('2026-01-01'), $this->jour('2026-12-31'));

        foreach ($c['marqueurs'] as $m) {
            $this->assertGreaterThanOrEqual($c['zone']['gauche'], $m['x']);
            $this->assertLessThanOrEqual($c['zone']['droite'], $m['x']);
            $this->assertGreaterThanOrEqual($c['zone']['haut'], $m['y']);
            $this->assertLessThanOrEqual($c['zone']['bas'], $m['y']);
        }
    }

    #[Test]
    public function un_seul_prix_et_une_seule_date_ne_cassent_rien(): void
    {
        $jour = $this->jour('2026-05-05');

        $c = CourbeSvg::construire([['date' => $jour, 'prix' => 1_200]], $jour, $jour);

        $this->assertNotNull($c);
        $this->assertCount(1, $c['marqueurs']);
        $this->assertNotContains('NAN', explode(' ', $c['chemin']));
        $this->assertStringNotContainsString('NAN', $c['chemin']);
        $this->assertStringNotContainsString('INF', $c['chemin']);
    }

    #[Test]
    public function les_graduations_encadrent_les_prix_et_le_temps_est_reparti_regulierement(): void
    {
        $c = CourbeSvg::construire([
            ['date' => $this->jour('2026-01-01'), 'prix' => 1_200],
            ['date' => $this->jour('2026-04-01'), 'prix' => 1_850],
        ], $this->jour('2026-01-01'), $this->jour('2026-12-31'));

        $valeurs = array_column($c['graduations_y'], 'valeur');
        $this->assertLessThanOrEqual(1_200, min($valeurs));
        $this->assertGreaterThanOrEqual(1_850, max($valeurs));
        $this->assertCount(6, $c['graduations_x']);
        $this->assertSame('2026-01-01', $c['graduations_x'][0]['date']->toDateString());
        $this->assertSame('2026-12-31', $c['graduations_x'][5]['date']->toDateString());
        // Un prix plus haut est plus haut à l'écran : les graduations descendent quand la valeur monte.
        $ys = array_column($c['graduations_y'], 'y');
        $this->assertSame($ys, collect($ys)->sortDesc()->values()->all());
    }
}
