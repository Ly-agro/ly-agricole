<?php

namespace Database\Factories;

use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campagne>
 */
class CampagneFactory extends Factory
{
    public function definition(): array
    {
        $annee = fake()->unique()->numberBetween(2030, 2099);

        return [
            'produit_id' => Produit::factory(),
            'code' => $annee.'-'.($annee + 1),
            'debut' => "$annee-12-01",
            'fin' => ($annee + 1).'-09-30',
            'statut' => StatutCampagne::Preparation,
            'prix_officiel_kg_fcfa' => null,
        ];
    }

    public function statut(StatutCampagne $statut): static
    {
        return $this->state(fn (array $attributes) => ['statut' => $statut]);
    }
}
