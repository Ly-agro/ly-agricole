<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Http\Controllers\VitrineController;
use App\Models\Campagne;
use App\Models\Produit;
use App\Models\User;
use App\Services\Publications;
use Database\Seeders\CulturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Vitrine : quelques prix en avant, les autres repliés ; page « Voir tous » : recherche et filtres immédiats. */
class PrixVitrineTest extends TestCase
{
    use RefreshDatabase;

    private User $direction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->seed(CulturesSeeder::class);
    }

    private function prix(string $code, int $prix): void
    {
        Publications::publierPrix(Produit::query()->where('code', $code)->firstOrFail(), $prix, Carbon::parse('2026-01-10'), 'Source de test', null, null, $this->direction);
    }

    #[Test]
    public function la_vitrine_met_les_filieres_de_ly_en_avant_et_replie_les_autres_prix(): void
    {
        foreach (['ananas' => 300, 'mais' => 250, 'riz' => 200, 'manioc' => 150, 'coton' => 310, 'soja' => 270, 'anacarde' => 400, 'karite' => 250] as $code => $p) {
            $this->prix($code, $p);
        }

        $page = $this->get('/')->assertOk();

        $enAvant = $page->viewData('prixEnAvant')->map(fn (array $l) => $l['produit']->code)->all();
        $this->assertCount(VitrineController::PRIX_EN_AVANT, $enAvant);
        $this->assertSame(['anacarde', 'karite'], array_slice($enAvant, 0, 2));
        $this->assertCount(2, $page->viewData('prixAutres'));
        $page->assertSee('Afficher les 2 autres cultures')->assertSee('Voir tous les prix et les courbes');
    }

    #[Test]
    public function peu_de_prix_pas_de_bouton_pour_les_autres(): void
    {
        $this->prix('anacarde', 400);

        $this->get('/')->assertOk()->assertDontSee('data-autres-prix', false);
    }

    #[Test]
    public function voir_tous_offre_la_recherche_et_filtre_sans_bouton(): void
    {
        $this->prix('anacarde', 400);
        Campagne::factory()->create(['produit_id' => Produit::query()->where('code', 'anacarde')->value('id'), 'code' => '2026', 'debut' => '2026-01-01', 'fin' => '2026-09-30']);

        $this->get('/prix')->assertOk()
            ->assertSee('data-recherche-culture', false)
            ->assertSee('data-culture="Anacarde"', false)
            ->assertSee('data-filtres-prix', false)
            ->assertSee('<noscript><button type="submit"', false);
    }

    #[Test]
    public function avec_un_produit_choisi_seules_ses_campagnes_sont_proposees(): void
    {
        $anacarde = Produit::query()->where('code', 'anacarde')->firstOrFail();
        $cacao = Produit::query()->where('code', 'cacao')->firstOrFail();
        $this->prix('anacarde', 400);
        $this->prix('cacao', 1_800);
        Campagne::factory()->create(['produit_id' => $anacarde->id, 'code' => '2025', 'debut' => '2025-02-01', 'fin' => '2025-09-30']);
        Campagne::factory()->create(['produit_id' => $cacao->id, 'code' => '2025-2026', 'debut' => '2025-10-01', 'fin' => '2026-09-30']);

        $this->get('/prix?produit='.$anacarde->id)->assertOk()
            ->assertSee('Campagne Anacarde 2025')
            ->assertDontSee('Campagne Cacao 2025-2026');

        $this->get('/prix')->assertOk()->assertSee('Campagne Cacao 2025-2026');
    }
}
