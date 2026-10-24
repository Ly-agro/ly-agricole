<?php

namespace Database\Factories;

use App\Models\Parcelle;
use App\Models\Producteur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Parcelle>
 */
class ParcelleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'producteur_id' => Producteur::factory(),
            'nom' => 'Parcelle '.fake()->unique()->word(),
            'contour' => null,
            'produit_id' => null,
            'cree_par' => User::factory(),
            'actif' => true,
        ];
    }

    /** Carré d'environ `$cote` mètres autour de Korhogo (9,45° N ; 5,63° O). */
    public function carre(int $cote = 100): static
    {
        return $this->state(fn () => ['contour' => self::geometrieCarre($cote)]);
    }

    /** @return array{type: string, coordinates: array<mixed>} */
    public static function geometrieCarre(int $cote, float $lat = 9.45, float $lng = -5.63): array
    {
        $dLat = $cote / 6378137.0 * 180 / M_PI;
        $dLng = $dLat / cos(deg2rad($lat));

        return ['type' => 'Polygon', 'coordinates' => [[
            [$lng, $lat], [$lng + $dLng, $lat], [$lng + $dLng, $lat + $dLat], [$lng, $lat + $dLat], [$lng, $lat],
        ]]];
    }
}
