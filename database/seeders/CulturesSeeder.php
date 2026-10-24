<?php

namespace Database\Seeders;

use App\Models\Produit;
use Illuminate\Database\Seeder;

/**
 * Cultures courantes de Côte d'Ivoire, pour que la vitrine et les référentiels affichent déjà une
 * liste large. Sans doublon : relancer ne change rien. Aucun prix ici (un prix se publie avec sa source).
 */
class CulturesSeeder extends Seeder
{
    /** @var array<string, string> */
    public const CULTURES = [
        'cacao' => 'Cacao', 'cafe' => 'Café', 'anacarde' => 'Anacarde', 'karite' => 'Karité',
        'tomate' => 'Tomate', 'hevea' => 'Hévéa (caoutchouc)', 'palmier-huile' => 'Palmier à huile',
        'coton' => 'Coton', 'mais' => 'Maïs', 'riz' => 'Riz', 'manioc' => 'Manioc', 'igname' => 'Igname',
        'banane-plantain' => 'Banane plantain', 'banane-dessert' => 'Banane dessert', 'ananas' => 'Ananas',
        'mangue' => 'Mangue', 'arachide' => 'Arachide', 'soja' => 'Soja', 'sesame' => 'Sésame',
        'piment' => 'Piment', 'gombo' => 'Gombo', 'aubergine' => 'Aubergine', 'oignon' => 'Oignon',
        'cola' => 'Noix de cola', 'gingembre' => 'Gingembre', 'hibiscus' => 'Hibiscus (bissap)',
        'sucre' => 'Canne à sucre', 'patate-douce' => 'Patate douce', 'taro' => 'Taro', 'papaye' => 'Papaye',
        'poivre' => 'Poivre', 'niebe' => 'Niébé (haricot)', 'fonio' => 'Fonio', 'sorgho' => 'Sorgho',
        'mil' => 'Mil', 'concombre' => 'Concombre', 'chou' => 'Chou',
    ];

    public function run(): void
    {
        foreach (self::CULTURES as $code => $nom) {
            Produit::query()->firstOrCreate(['code' => $code], ['nom' => $nom, 'actif' => true]);
        }
    }
}
