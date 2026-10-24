<?php

namespace Database\Factories;

use App\Models\GroupeProducteur;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupeProducteur>
 */
class GroupeProducteurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Groupe '.fake()->unique()->lastName(),
            'village_id' => Village::factory(),
            'responsable_id' => null,
            'actif' => true,
        ];
    }
}
