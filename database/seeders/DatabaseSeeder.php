<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données de développement uniquement : jamais en production.
     */
    public function run(): void
    {
        User::factory()->create([
            'nom' => 'Admin Développement',
            'email' => 'admin@ly-agricole.test',
        ]);
    }
}
