<?php

namespace Database\Factories;

use App\Models\Magasin;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Magasin>
 */
class MagasinFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Magasin '.fake()->unique()->lastName(),
            'village_id' => Village::factory(),
            'capacite_g' => null,
            'actif' => true,
        ];
    }
}
