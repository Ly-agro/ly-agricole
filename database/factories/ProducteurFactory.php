<?php

namespace Database\Factories;

use App\Models\Producteur;
use App\Models\User;
use App\Models\Village;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producteur>
 */
class ProducteurFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'sexe' => fake()->randomElement(['F', 'M']),
            'annee_naissance' => fake()->numberBetween(1950, 2005),
            'telephone' => fake()->unique()->numerify('07########'),
            'numero_mobile_money' => null,
            'operateur_mm' => null,
            'piece_type' => null,
            'piece_numero' => null,
            'village_id' => Village::factory(),
            'consentement_at' => now(),
            'consentement_par' => User::factory(),
            'cree_par' => fn (array $attributs) => $attributs['consentement_par'],
            'actif' => true,
        ];
    }
}
