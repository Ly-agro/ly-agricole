<?php

namespace Tests\Feature\Producteurs;

use App\Enums\Role;
use App\Livewire\Producteurs\Groupes;
use App\Models\GroupeProducteur;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->role(Role::Agent)->create());
    }

    #[Test]
    public function un_groupe_se_cree_avec_un_responsable_du_village(): void
    {
        $village = Village::factory()->create();
        $awa = Producteur::factory()->create(['nom' => 'Coulibaly', 'prenoms' => 'Awa', 'village_id' => $village->id]);

        Livewire::test(Groupes::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Groupe des femmes')
            ->set('donnees.village_id', (string) $village->id)
            ->assertSee('Coulibaly Awa')
            ->set('donnees.responsable_id', $awa->id)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Groupe Groupe des femmes : créé(e).'));

        $groupe = GroupeProducteur::firstOrFail();
        $this->assertSame($awa->id, $groupe->responsable_id);
    }

    #[Test]
    public function le_responsable_d_un_autre_village_est_refuse(): void
    {
        $village = Village::factory()->create();
        $ailleurs = Producteur::factory()->create();

        Livewire::test(Groupes::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Groupe A')
            ->set('donnees.village_id', (string) $village->id)
            ->set('donnees.responsable_id', $ailleurs->id)
            ->call('enregistrer')
            ->assertHasErrors(['donnees.responsable_id' => 'exists']);

        $this->assertSame(0, GroupeProducteur::count());
    }

    #[Test]
    public function la_liste_compte_les_membres_et_n_affiche_pas_les_onglets_des_referentiels(): void
    {
        $groupe = GroupeProducteur::factory()->create(['nom' => 'Groupe B']);
        Producteur::factory()->count(3)->create(['village_id' => $groupe->village_id, 'groupe_id' => $groupe->id]);

        $page = $this->get('/producteurs/groupes')->assertOk();
        $page->assertDontSee('href="'.route('referentiels.zones').'"', false);

        Livewire::test(Groupes::class)->assertSeeHtml('<td class="px-4 py-3">3</td>');
    }

    #[Test]
    public function le_comptable_voit_les_producteurs_mais_ne_gere_pas_les_groupes(): void
    {
        $this->actingAs(User::factory()->role(Role::Comptable)->create());

        $this->get('/producteurs/groupes')->assertForbidden();
    }
}
