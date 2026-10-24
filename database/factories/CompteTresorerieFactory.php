<?php

namespace Database\Factories;

use App\Enums\TypeCompte;
use App\Models\CompteTresorerie;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompteTresorerie>
 */
class CompteTresorerieFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nom' => 'Compte '.fake()->unique()->lastName(),
            'type' => TypeCompte::Caisse,
            'titulaire_id' => null,
            'campagne_id' => null,
            'actif' => true,
        ];
    }

    public function caisseDe(User $agent): static
    {
        return $this->state(fn () => ['nom' => 'Caisse '.$agent->nom, 'type' => TypeCompte::Caisse, 'titulaire_id' => $agent->id]);
    }
}
