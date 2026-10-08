<?php

namespace Tests\Feature\Referentiels;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Livewire\Referentiels\Magasins;
use App\Livewire\Referentiels\PointsCollecte;
use App\Livewire\Referentiels\Produits;
use App\Livewire\Referentiels\Villages;
use App\Livewire\Referentiels\Zones;
use App\Models\JournalActivite;
use App\Models\Magasin;
use App\Models\PointCollecte;
use App\Models\User;
use App\Models\Village;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReferentielsSimplesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->actingAs($this->admin);
    }

    #[Test]
    public function creer_une_zone_la_journalise_au_nom_de_son_auteur(): void
    {
        Livewire::test(Zones::class)
            ->call('nouveau')
            ->assertSet('donnees.actif', true)
            ->set('donnees.nom', 'Korhogo')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Zone Korhogo : créé(e).'));

        $zone = Zone::where('nom', 'Korhogo')->firstOrFail();
        $this->assertTrue($zone->actif);

        $ligne = JournalActivite::query()->where('objet_type', 'zone')->latest('id')->firstOrFail();
        $this->assertSame(ActionJournal::Creation, $ligne->action);
        $this->assertSame($this->admin->id, $ligne->user_id);
        $this->assertSame((string) $zone->id, $ligne->objet_id);
    }

    #[Test]
    public function une_zone_se_desactive_et_n_a_pas_de_bouton_supprimer(): void
    {
        $zone = Zone::factory()->create(['nom' => 'Ancienne zone']);

        Livewire::test(Zones::class)
            ->assertDontSee('Supprimer')
            ->call('modifier', $zone->id)
            ->set('donnees.actif', false)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Désactivée');

        $this->assertFalse($zone->refresh()->actif);
        $this->assertSame(['actif' => false], JournalActivite::query()->latest('id')->firstOrFail()->apres);
    }

    #[Test]
    public function deux_zones_ne_portent_pas_le_meme_nom(): void
    {
        Zone::factory()->create(['nom' => 'Korhogo']);

        Livewire::test(Zones::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Korhogo')
            ->call('enregistrer')
            ->assertHasErrors(['donnees.nom' => 'unique'])
            ->assertSee('Cette valeur de nom est déjà utilisée.');
    }

    #[Test]
    public function un_meme_nom_de_village_est_permis_dans_deux_zones_mais_pas_dans_la_meme(): void
    {
        $zoneA = Zone::factory()->create();
        $zoneB = Zone::factory()->create();
        Village::factory()->create(['zone_id' => $zoneA->id, 'nom' => 'Kanakono']);

        Livewire::test(Villages::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Kanakono')
            ->set('donnees.zone_id', $zoneA->id)
            ->call('enregistrer')
            ->assertHasErrors(['donnees.nom' => 'unique']);

        Livewire::test(Villages::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Kanakono')
            ->set('donnees.zone_id', $zoneB->id)
            ->set('donnees.lat', '9.4580')
            ->set('donnees.lng', '-5.6290')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $village = Village::where('zone_id', $zoneB->id)->firstOrFail();
        $this->assertSame('9.4580000', $village->lat);
        $this->assertSame('-5.6290000', $village->lng);
    }

    #[Test]
    public function les_coordonnees_hors_limites_sont_refusees_et_vides_acceptees(): void
    {
        $zone = Zone::factory()->create();

        Livewire::test(Villages::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Village perdu')
            ->set('donnees.zone_id', $zone->id)
            ->set('donnees.lat', '95')
            ->set('donnees.lng', 'abc')
            ->call('enregistrer')
            ->assertHasErrors(['donnees.lat' => 'between', 'donnees.lng' => 'numeric'])
            ->assertSee('Le champ latitude doit être compris entre -90 et 90.');

        Livewire::test(Villages::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Village sans GPS')
            ->set('donnees.zone_id', $zone->id)
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull(Village::where('nom', 'Village sans GPS')->firstOrFail()->lat);
    }

    #[Test]
    public function un_village_exige_une_zone_existante(): void
    {
        Livewire::test(Villages::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Orphelin')
            ->set('donnees.zone_id', 999)
            ->call('enregistrer')
            ->assertHasErrors(['donnees.zone_id' => 'exists']);
    }

    #[Test]
    public function le_code_produit_est_en_minuscules_sans_espaces(): void
    {
        Livewire::test(Produits::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Karité')
            ->set('donnees.code', 'Karité bio')
            ->call('enregistrer')
            ->assertHasErrors(['donnees.code' => 'regex']);

        Livewire::test(Produits::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Karité')
            ->set('donnees.code', 'karite')
            ->call('enregistrer')
            ->assertHasNoErrors();
    }

    #[Test]
    public function la_capacite_d_un_magasin_se_saisit_en_kg_et_se_stocke_en_grammes(): void
    {
        $village = Village::factory()->create();

        Livewire::test(Magasins::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Magasin central')
            ->set('donnees.village_id', $village->id)
            ->set('donnees.capacite_kg', '250000')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee("250\u{202F}000 kg");

        $magasin = Magasin::where('nom', 'Magasin central')->firstOrFail();
        $this->assertSame(250_000_000, $magasin->capacite_g);

        Livewire::test(Magasins::class)
            ->call('modifier', $magasin->id)
            ->assertSet('donnees.capacite_kg', 250_000);
    }

    #[Test]
    public function une_capacite_decimale_ou_negative_est_refusee(): void
    {
        $village = Village::factory()->create();

        foreach (['12.5' => 'integer', '-1' => 'min'] as $saisie => $regle) {
            Livewire::test(Magasins::class)
                ->call('nouveau')
                ->set('donnees.nom', 'Magasin '.$saisie)
                ->set('donnees.village_id', $village->id)
                ->set('donnees.capacite_kg', $saisie)
                ->call('enregistrer')
                ->assertHasErrors(['donnees.capacite_kg' => $regle]);
        }

        $this->assertSame(0, Magasin::count());
    }

    #[Test]
    public function un_point_de_collecte_est_rattache_a_un_village(): void
    {
        $village = Village::factory()->create(['nom' => 'Sinématiali']);

        Livewire::test(PointsCollecte::class)
            ->call('nouveau')
            ->set('donnees.nom', 'Marché')
            ->set('donnees.village_id', $village->id)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Sinématiali');

        $this->assertSame($village->id, PointCollecte::where('nom', 'Marché')->firstOrFail()->village_id);
    }

    #[Test]
    public function l_onglet_de_la_page_est_marque_actif(): void
    {
        $page = $this->get('/referentiels/villages')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route('referentiels.villages'), '#').'"\s+class="[^"]*border-emerald-700#',
            (string) $page,
        );
        $this->assertDoesNotMatchRegularExpression(
            '#href="'.preg_quote(route('referentiels.zones'), '#').'"\s+class="[^"]*border-emerald-700#',
            (string) $page,
        );
    }

    #[Test]
    public function un_retrograde_perd_l_acces_a_un_ecran_deja_ouvert(): void
    {
        $user = User::factory()->role(Role::Direction)->create();
        $this->actingAs($user);
        $ecran = Livewire::test(Zones::class);

        $user->update(['role' => Role::Agent]);

        $ecran->call('nouveau')->assertForbidden();
    }
}
