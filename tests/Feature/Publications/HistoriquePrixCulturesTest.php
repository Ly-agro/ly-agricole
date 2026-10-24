<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\PrixMarche;
use App\Models\User;
use App\Services\Publications;
use Database\Seeders\CulturesSeeder;
use Database\Seeders\HistoriquePrixCulturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Prix relevés des autres cultures (coton, hévéa, ANADER…) : sourcés, sans doublon, une case par année de campagne. */
class HistoriquePrixCulturesTest extends TestCase
{
    use RefreshDatabase;

    private function charger(): array
    {
        User::factory()->role(Role::Direction)->create();
        $this->seed(CulturesSeeder::class);

        return (new HistoriquePrixCulturesSeeder)->run();
    }

    #[Test]
    public function chaque_prix_a_une_source_un_lien_et_les_campagnes_sont_cloturees(): void
    {
        $resultat = $this->charger();

        $attendus = array_sum(array_map('count', HistoriquePrixCulturesSeeder::donnees()));
        $this->assertSame($attendus, $resultat['prix']);
        $this->assertSame($attendus, PrixMarche::query()->count());
        $this->assertSame(0, PrixMarche::query()->whereNull('source_url')->count());
        $this->assertSame(0, Campagne::query()->where('statut', '!=', StatutCampagne::Cloturee)->count());
        $this->assertNull(Campagne::query()->whereNotNull('prix_officiel_kg_fcfa')->first());
    }

    #[Test]
    public function relancer_ne_cree_rien_de_plus(): void
    {
        $this->charger();

        $this->assertSame(['campagnes' => 0, 'prix' => 0], (new HistoriquePrixCulturesSeeder)->run());
    }

    #[Test]
    public function le_tableau_range_chaque_prix_dans_son_annee_de_campagne(): void
    {
        $this->charger();

        $t = Publications::tableauCampagnes();
        $ligne = fn (string $code) => collect($t['lignes'])->first(fn (array $l) => $l['produit']->code === $code);

        $this->assertSame(['2019-2020', '2020-2021', '2021-2022', '2022-2023', '2023-2024', '2024-2025', '2025-2026'], $t['campagnes']);
        $this->assertSame(409, $ligne('hevea')['cases']['2025-2026']['prix']);
        $this->assertSame(11, $ligne('hevea')['cases']['2025-2026']['ecart']);
        // Tomate ANADER 2023 → campagne 2022-2023 ; rien de publié après.
        $this->assertSame(473, $ligne('tomate')['cases']['2022-2023']['prix']);
        $this->assertNull($ligne('tomate')['cases']['2023-2024']);
        // Karité annoncé en août 2025 : campagne 2024-2025.
        $this->assertSame(250, $ligne('karite')['cases']['2024-2025']['prix']);
        $this->assertSame(10, $ligne('coton')['cases']['2022-2023']['ecart']);
        // Aucune source : ligne vide, jamais un prix inventé.
        $this->assertFalse($ligne('sesame')['connu']);
    }
}
