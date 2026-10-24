<?php

namespace Database\Factories;

use App\Models\Village;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Village>
 */
class VillageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'nom' => 'Village '.fake()->unique()->lastName(),
            'lat' => null,
            'lng' => null,
            'actif' => true,
        ];
    }
}
