<?php

namespace Database\Factories;

use App\Models\Produit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
{
    public function definition(): array
    {
        $nom = fake()->unique()->word();

        return [
            'code' => 'produit-'.$nom,
            'nom' => ucfirst($nom),
            'actif' => true,
        ];
    }
}
