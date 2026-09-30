<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Models\Produit;
use App\Models\User;
use App\Services\Publications;
use Database\Seeders\CulturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** La vitrine affiche déjà une grande liste de cultures ; un prix n'est jamais inventé. */
class CulturesVitrineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_liste_des_cultures_est_large_sans_doublon_et_relancable(): void
    {
        $this->seed(CulturesSeeder::class);
        $this->seed(CulturesSeeder::class);

        $this->assertSame(count(CulturesSeeder::CULTURES), Produit::query()->count());
        $this->assertGreaterThanOrEqual(25, Produit::query()->count());
    }

    #[Test]
    public function la_vitrine_montre_les_cultures_sans_prix_avec_prix_a_venir_et_aucun_chiffre(): void
    {
        $this->seed(CulturesSeeder::class);

        $this->get('/')->assertOk()->assertSee('Autres cultures suivies')
            ->assertSee('Manioc')->assertSee('Cacao')->assertSee('Aucun prix publié pour le moment');
    }

    #[Test]
    public function une_culture_avec_prix_quitte_la_liste_des_cultures_sans_prix(): void
    {
        $this->seed(CulturesSeeder::class);
        $direction = User::factory()->role(Role::Direction)->create();
        Publications::publierPrix(Produit::query()->where('code', 'mais')->firstOrFail(), 250, Carbon::today(), 'Marché', null, null, $direction);

        $page = $this->get('/')->assertOk()->getContent();
        $liste = substr((string) $page, (int) strpos((string) $page, 'Autres cultures suivies'));

        $this->assertStringContainsString('Manioc', $liste);
        $this->assertStringNotContainsString('Maïs', $liste);
    }
}
