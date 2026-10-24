<?php

namespace Tests\Feature\Referentiels;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Livewire\Referentiels\Campagnes;
use App\Models\Campagne;
use App\Models\JournalActivite;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CampagnesTest extends TestCase
{
    use RefreshDatabase;

    private Produit $anacarde;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->role(Role::Direction)->create());
        $this->anacarde = Produit::factory()->create(['code' => 'anacarde', 'nom' => 'Anacarde']);
    }

    /** @param  array<string, mixed>  $surcharge */
    private function creer(array $surcharge = []): Testable
    {
        $ecran = Livewire::test(Campagnes::class)->call('nouveau');

        foreach (array_merge([
            'produit_id' => $this->anacarde->id,
            'code' => '2026-2027',
            'debut' => '2026-12-21',
            'fin' => '2027-09-30',
            'prix_officiel_kg_fcfa' => '',
        ], $surcharge) as $champ => $valeur) {
            $ecran->set("donnees.$champ", $valeur);
        }

        return $ecran->call('enregistrer');
    }

    #[Test]
    public function une_campagne_se_cree_en_preparation_sans_prix_officiel(): void
    {
        $this->creer()
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Campagne Anacarde 2026-2027 : créé(e).'))
            ->assertSee('Non annoncé')
            ->assertSee('En préparation');

        $campagne = Campagne::firstOrFail();
        $this->assertSame(StatutCampagne::Preparation, $campagne->statut);
        $this->assertNull($campagne->prix_officiel_kg_fcfa);
        $this->assertSame('2026-12-21', $campagne->debut->format('Y-m-d'));
    }

    #[Test]
    public function le_prix_officiel_est_un_entier_de_fcfa(): void
    {
        $this->creer(['prix_officiel_kg_fcfa' => '275.5'])
            ->assertHasErrors(['donnees.prix_officiel_kg_fcfa' => 'integer'])
            ->assertSee('Le champ prix officiel bord-champ (fcfa/kg) doit être un nombre entier.');

        $this->creer(['prix_officiel_kg_fcfa' => '0'])
            ->assertHasErrors(['donnees.prix_officiel_kg_fcfa' => 'min']);

        $this->creer(['prix_officiel_kg_fcfa' => '425'])->assertHasNoErrors();

        $this->assertSame(425, Campagne::firstOrFail()->prix_officiel_kg_fcfa);
    }

    #[Test]
    public function la_fin_ne_precede_pas_le_debut_et_le_code_a_le_bon_format(): void
    {
        $this->creer(['debut' => '2027-01-10', 'fin' => '2026-12-01', 'code' => '2026/27'])
            ->assertHasErrors(['donnees.fin' => 'after_or_equal', 'donnees.code' => 'regex'])
            ->assertSee('Le champ fin doit être une date égale ou postérieure à début.');

        $this->assertSame(0, Campagne::count());
    }

    #[Test]
    public function le_meme_code_est_permis_pour_deux_produits_mais_pas_pour_un_seul(): void
    {
        $karite = Produit::factory()->create(['nom' => 'Karité']);
        Campagne::factory()->create(['produit_id' => $this->anacarde->id, 'code' => '2026-2027']);

        $this->creer()->assertHasErrors(['donnees.code' => 'unique']);
        $this->creer(['produit_id' => $karite->id])->assertHasNoErrors();
    }

    #[Test]
    public function ouvrir_une_campagne_est_journalise(): void
    {
        $campagne = Campagne::factory()->create(['produit_id' => $this->anacarde->id]);

        Livewire::test(Campagnes::class)
            ->assertSee('Ouvrir')
            ->call('ouvrir', $campagne->id)
            ->assertHasNoErrors()
            ->assertSee('Ouverte');

        $this->assertSame(StatutCampagne::Ouverte, $campagne->refresh()->statut);

        $ligne = JournalActivite::query()->where('objet_type', 'campagne')->latest('id')->firstOrFail();
        $this->assertSame(ActionJournal::Modification, $ligne->action);
        $this->assertSame(['statut' => 'preparation'], $ligne->avant);
        $this->assertSame(['statut' => 'ouverte'], $ligne->apres);
    }

    #[Test]
    public function une_seule_campagne_ouverte_par_produit(): void
    {
        Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['produit_id' => $this->anacarde->id, 'code' => '2025-2026']);
        $suivante = Campagne::factory()->create(['produit_id' => $this->anacarde->id, 'code' => '2026-2027']);
        $autreProduit = Campagne::factory()->create();

        Livewire::test(Campagnes::class)
            ->call('ouvrir', $suivante->id)
            ->assertHasErrors('ligne')
            ->assertHasErrors('ligne');

        $this->assertSame(StatutCampagne::Preparation, $suivante->refresh()->statut);

        Livewire::test(Campagnes::class)->call('ouvrir', $autreProduit->id)->assertHasNoErrors();
        $this->assertSame(StatutCampagne::Ouverte, $autreProduit->refresh()->statut);
    }

    #[Test]
    public function on_n_ouvre_pas_deux_fois_la_meme_campagne(): void
    {
        $campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create();

        Livewire::test(Campagnes::class)
            ->assertDontSee('>Ouvrir<', false)
            ->call('ouvrir', $campagne->id)
            ->assertHasErrors('ligne');
    }

    #[Test]
    public function le_produit_d_une_campagne_ouverte_ne_change_plus_mais_son_prix_si(): void
    {
        $campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['produit_id' => $this->anacarde->id]);
        $autre = Produit::factory()->create();

        Livewire::test(Campagnes::class)
            ->call('modifier', $campagne->id)
            ->set('donnees.produit_id', $autre->id)
            ->call('enregistrer')
            ->assertHasErrors('donnees.produit_id');

        Livewire::test(Campagnes::class)
            ->call('modifier', $campagne->id)
            ->set('donnees.prix_officiel_kg_fcfa', '425')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $campagne->refresh();
        $this->assertSame($this->anacarde->id, $campagne->produit_id);
        $this->assertSame(425, $campagne->prix_officiel_kg_fcfa);
    }

    #[Test]
    public function une_campagne_cloturee_ne_se_modifie_plus(): void
    {
        $campagne = Campagne::factory()->statut(StatutCampagne::Cloturee)->create();

        Livewire::test(Campagnes::class)
            ->assertSee('Clôturée')
            ->assertDontSee('Modifier')
            ->call('modifier', $campagne->id)
            ->assertForbidden();
    }

    #[Test]
    public function l_admin_ne_gere_pas_les_campagnes(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        Livewire::test(Campagnes::class)->assertForbidden();
    }
}
