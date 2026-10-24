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

    // La liste « Autres cultures suivies » a été retirée de la vitrine (choix du 2026-10-01) :
    // les cultures défilent dans le bandeau, et un prix n'est jamais inventé.
    #[Test]
    public function la_vitrine_nomme_les_cultures_sans_inventer_de_prix(): void
    {
        $this->seed(CulturesSeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Manioc')->assertSee('Cacao')->assertSee('Aucun prix publié pour le moment')
            ->assertDontSee('FCFA / kg');
    }

    #[Test]
    public function un_prix_publie_apparait_avec_sa_source(): void
    {
        $this->seed(CulturesSeeder::class);
        $direction = User::factory()->role(Role::Direction)->create();
        Publications::publierPrix(Produit::query()->where('code', 'mais')->firstOrFail(), 250, Carbon::today(), 'Marché de Bouaké', null, null, $direction);

        $this->get('/')->assertOk()->assertSee('Maïs')->assertSee('Marché de Bouaké')->assertSee('FCFA / kg');
    }
}
