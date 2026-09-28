<?php

namespace Database\Factories;

use App\Models\PointCollecte;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointCollecte>
 */
class PointCollecteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Point '.fake()->unique()->lastName(),
            'village_id' => Village::factory(),
            'actif' => true,
        ];
    }
}
