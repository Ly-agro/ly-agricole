<?php

namespace Tests\Feature\Producteurs;

use App\Enums\Role;
use App\Livewire\Producteurs\FormulaireParcelle;
use App\Models\JournalActivite;
use App\Models\Parcelle;
use App\Models\Producteur;
use App\Models\User;
use Database\Factories\ParcelleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParcellesTest extends TestCase
{
    use RefreshDatabase;

    private Producteur $producteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->role(Role::Agent)->create());
        $this->producteur = Producteur::factory()->create();
    }

    #[Test]
    public function un_contour_colle_donne_la_surface_calculee_et_enregistree(): void
    {
        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur])
            ->set('texteContour', (string) json_encode(ParcelleFactory::geometrieCarre(100)))
            ->call('lireTexte')
            ->assertHasNoErrors()
            ->assertSee('1,00 ha')
            ->set('nom', 'Champ du marigot')
            ->set('nbArbres', '120')
            ->call('enregistrer')
            ->assertRedirect(route('producteurs.fiche', $this->producteur));

        $parcelle = Parcelle::firstOrFail();
        $this->assertEqualsWithDelta(10_000, $parcelle->surface_m2, 10);
        $this->assertSame(120, $parcelle->nb_arbres);
        $this->assertSame('Polygon', $parcelle->contour['type'] ?? null);

        $ligne = JournalActivite::query()->where('objet_type', 'parcelle')->firstOrFail();
        $this->assertSame($parcelle->surface_m2, $ligne->apres['surface_m2'] ?? null);
    }

    #[Test]
    public function un_fichier_geojson_s_importe(): void
    {
        $contenu = (string) json_encode(['type' => 'Feature', 'properties' => [], 'geometry' => ParcelleFactory::geometrieCarre(200)]);

        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur])
            ->set('fichierContour', UploadedFile::fake()->createWithContent('parcelle.geojson', $contenu))
            ->assertHasNoErrors()
            ->assertSee('4,00 ha');
    }

    #[Test]
    public function un_mauvais_fichier_ou_un_contour_faux_est_explique(): void
    {
        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur])
            ->set('fichierContour', UploadedFile::fake()->createWithContent('parcelle.kml', '<kml/>'))
            ->assertHasErrors('fichierContour')
            ->assertSee('Choisir un fichier .geojson ou .json.');

        $inverse = ['type' => 'Polygon', 'coordinates' => [array_map(fn ($p) => [$p[1], $p[0]], ParcelleFactory::geometrieCarre(100)['coordinates'][0])]];
        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur])
            ->set('texteContour', (string) json_encode($inverse))
            ->call('lireTexte')
            ->assertHasErrors('texteContour')
            ->assertSee('latitude et longitude semblent inversées')
            ->assertSee('Surface non relevée');
    }

    #[Test]
    public function sans_contour_la_surface_reste_non_relevee(): void
    {
        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur])
            ->set('nom', 'Champ sans relevé')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertNull(Parcelle::firstOrFail()->surface_m2);
        $this->get(route('producteurs.fiche', $this->producteur))->assertSee('Non relevée');
    }

    #[Test]
    public function la_surface_et_le_contour_ne_peuvent_pas_etre_imposes_par_le_navigateur(): void
    {
        $ecran = Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur]);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $ecran->set('contour', ParcelleFactory::geometrieCarre(1000));
    }

    #[Test]
    public function surface_m2_n_est_pas_affectable_en_masse(): void
    {
        $parcelle = Parcelle::factory()->create(['producteur_id' => $this->producteur->id]);
        $parcelle->update(['surface_m2' => 999_999]);

        $this->assertNull($parcelle->refresh()->surface_m2);
    }

    #[Test]
    public function modifier_la_description_garde_le_contour_et_la_surface(): void
    {
        $parcelle = Parcelle::factory()->carre(100)->create(['producteur_id' => $this->producteur->id, 'nom' => 'Ancienne']);
        $surface = $parcelle->surface_m2;

        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur, 'parcelle' => $parcelle])
            ->assertSee('1,00 ha')
            ->set('nom', 'Nouvelle')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $parcelle->refresh();
        $this->assertSame('Nouvelle', $parcelle->nom);
        $this->assertSame($surface, $parcelle->surface_m2);
        $this->assertSame(['nom' => 'Ancienne'], JournalActivite::query()->latest('id')->firstOrFail()->avant);
    }

    #[Test]
    public function la_parcelle_d_un_autre_producteur_n_est_pas_accessible_par_son_adresse(): void
    {
        $ailleurs = Parcelle::factory()->create();

        $this->get("/producteurs/{$this->producteur->id}/parcelles/{$ailleurs->id}/modifier")->assertNotFound();
    }

    #[Test]
    public function deux_parcelles_d_un_meme_producteur_ne_portent_pas_le_meme_nom(): void
    {
        Parcelle::factory()->create(['producteur_id' => $this->producteur->id, 'nom' => 'Champ A']);

        Livewire::test(FormulaireParcelle::class, ['producteur' => $this->producteur])
            ->set('nom', 'Champ A')
            ->call('enregistrer')
            ->assertHasErrors(['nom' => 'unique']);
    }

    #[Test]
    public function la_fiche_totalise_la_surface_relevee(): void
    {
        Parcelle::factory()->carre(100)->create(['producteur_id' => $this->producteur->id]);
        Parcelle::factory()->carre(200)->create(['producteur_id' => $this->producteur->id]);
        Parcelle::factory()->create(['producteur_id' => $this->producteur->id]);

        $this->get(route('producteurs.fiche', $this->producteur))
            ->assertSee('(3 ; 5,00 ha relevés)');
    }
}
