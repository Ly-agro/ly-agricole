<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\PrixMarche;
use App\Models\User;
use App\Services\Publications;
use Database\Seeders\CulturesSeeder;
use Database\Seeders\HistoriquePrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Prix des campagnes passées relevés dans la presse : sourcés, sans doublon, jamais des campagnes de travail. */
class HistoriquePrixTest extends TestCase
{
    use RefreshDatabase;

    private function charger(): array
    {
        User::factory()->role(Role::Direction)->create();
        $this->seed(CulturesSeeder::class);

        return (new HistoriquePrixSeeder)->run();
    }

    #[Test]
    public function chaque_prix_a_une_source_un_lien_et_les_campagnes_sont_cloturees(): void
    {
        $this->charger();

        $this->assertGreaterThan(20, PrixMarche::query()->count());
        $this->assertSame(0, PrixMarche::query()->whereNull('source_url')->count());
        $this->assertSame(0, Campagne::query()->where('statut', '!=', StatutCampagne::Cloturee)->count());
        $this->assertNull(Campagne::query()->whereNotNull('prix_officiel_kg_fcfa')->first(), 'Un prix relevé n\'est jamais un prix officiel de campagne.');
    }

    #[Test]
    public function relancer_ne_cree_rien_de_plus(): void
    {
        $premier = $this->charger();
        $second = (new HistoriquePrixSeeder)->run();

        $this->assertGreaterThan(0, $premier['prix']);
        $this->assertSame(['campagnes' => 0, 'prix' => 0], $second);
    }

    #[Test]
    public function le_tableau_regroupe_cacao_cafe_et_anacarde_par_annee_de_campagne(): void
    {
        $this->charger();

        $t = Publications::tableauCampagnes();

        $this->assertLessThanOrEqual(7, count($t['campagnes']));
        $this->assertContains('2023-2024', $t['campagnes']);
        $this->assertContains('2025-2026', $t['campagnes']);
        $ligne = fn (string $code) => collect($t['lignes'])->first(fn (array $l) => $l['produit']->code === $code);

        // Le cacao 2023-2024 finit à 1 500 (intermédiaire) ; la campagne précédente finissait à 900 : +600.
        $this->assertSame(1_500, $ligne('cacao')['cases']['2023-2024']['prix']);
        $this->assertSame(600, $ligne('cacao')['cases']['2023-2024']['ecart']);
        // L'anacarde de février 2024 (275) tombe dans l'année 2023-2024 ; celle de février 2026 (400) dans 2025-2026.
        $this->assertSame(275, $ligne('anacarde')['cases']['2023-2024']['prix']);
        $this->assertSame(400, $ligne('anacarde')['cases']['2025-2026']['prix']);
        $this->assertSame(-25, $ligne('anacarde')['cases']['2025-2026']['ecart'] ?? null);
    }
}
