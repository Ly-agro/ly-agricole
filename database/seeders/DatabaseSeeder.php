<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Produit;
use App\Models\User;
use App\Models\Village;
use App\Models\Zone;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de développement uniquement : jamais en production.
     * Un compte par rôle : `<role>@ly-agricole.test`, mot de passe de la fabrique.
     * Aucun prix officiel ni seuil : ce sont des décisions du responsable projet.
     */
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            User::factory()->role($role)->create([
                'nom' => $role->libelle().' (dév.)',
                'email' => $role->value.'@ly-agricole.test',
            ]);
        }

        // Produits cités par le cahier des charges.
        foreach (['anacarde' => 'Anacarde', 'karite' => 'Karité', 'tomate' => 'Tomate'] as $code => $nom) {
            Produit::create(['code' => $code, 'nom' => $nom]);
        }
        // Et la liste large des cultures courantes (sans doublon avec les trois ci-dessus).
        $this->call(CulturesSeeder::class);

        // Lieux fictifs, pour essayer les écrans (les vraies zones : question ouverte n° 1).
        $zone = Zone::create(['nom' => 'Zone de test']);
        Village::create(['zone_id' => $zone->id, 'nom' => 'Village de test']);
    }
}
