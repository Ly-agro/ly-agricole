<?php

namespace Tests\Feature\Api;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutDepense;
use App\Enums\StatutLot;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Parcelle;
use App\Models\PhotoTerrain;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Tresorerie;
use Database\Factories\ParcelleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Semaine 9 : parcelle relevée au GPS, photos à part, dépense terrain.
 * Livrable : une parcelle relevée en marchant, surface affichée au bureau.
 */
class TerrainSemaine9Test extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $direction;

    private Producteur $producteur;

    private CompteTresorerie $caisseAgent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->producteur = Producteur::factory()->create();
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 1_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 100_000, Carbon::today(), 'Avance', $this->direction);
        Sanctum::actingAs($this->agent);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array{uuid: string, statut: string, motif?: string}
     */
    private function operation(string $type, array $donnees, ?string $uuid = null): array
    {
        return $this->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => [
            ['uuid' => $uuid ?? (string) Str::uuid7(), 'type' => $type, 'cree_at' => now()->toIso8601String(), 'donnees' => $donnees],
        ]])->assertOk()->json('resultats.0');
    }

    private function envoyerPhoto(string $uuid, ?UploadedFile $fichier = null): TestResponse
    {
        return $this->post('/api/photos', [
            'uuid' => $uuid, 'appareil_id' => 'tel', 'prise_at' => now()->toIso8601String(), 'lat' => 9.45, 'lng' => -5.63,
            'fichier' => $fichier ?? UploadedFile::fake()->image('p.jpg', 800, 600),
        ], ['Accept' => 'application/json']);
    }

    #[Test]
    public function une_parcelle_relevee_en_marchant_a_sa_surface_calculee_et_affichee_au_bureau(): void
    {
        $uuid = (string) Str::uuid7();
        // Carré de 100 m de côté = 1 ha. La surface envoyée par le téléphone est ignorée.
        $r = $this->operation('parcelle', [
            'producteur_id' => $this->producteur->id, 'nom' => 'Champ du bas',
            'contour' => ParcelleFactory::geometrieCarre(100), 'surface_m2' => 999_999,
        ], $uuid);

        $this->assertSame('accepte', $r['statut']);
        $parcelle = Parcelle::query()->findOrFail($uuid);
        $this->assertSame('gps', $parcelle->contour_origine);
        $this->assertEqualsWithDelta(10_000, $parcelle->surface_m2, 5);

        // Au bureau, sur la fiche du producteur.
        $this->actingAs($this->direction)->get(route('producteurs.fiche', $this->producteur))
            ->assertOk()->assertSee('Champ du bas')->assertSee('1,00 ha')->assertSee('relevé GPS en marchant');

        // Renvoi : rien en double.
        Sanctum::actingAs($this->agent);
        $this->assertSame('deja_recu', $this->operation('parcelle', ['producteur_id' => $this->producteur->id, 'nom' => 'Champ du bas',
            'contour' => ParcelleFactory::geometrieCarre(100)], $uuid)['statut']);
        $this->assertSame(1, Parcelle::query()->count());
    }

    #[Test]
    public function un_contour_gps_invalide_est_rejete_avec_son_motif(): void
    {
        $horsCi = ParcelleFactory::geometrieCarre(100, lat: 48.85, lng: 2.35);
        $ouvert = ParcelleFactory::geometrieCarre(100);
        array_pop($ouvert['coordinates'][0]);

        $r1 = $this->operation('parcelle', ['producteur_id' => $this->producteur->id, 'nom' => 'A', 'contour' => $horsCi]);
        $r2 = $this->operation('parcelle', ['producteur_id' => $this->producteur->id, 'nom' => 'B', 'contour' => $ouvert]);

        $this->assertSame('rejete', $r1['statut']);
        $this->assertStringContainsString('hors de Côte d\'Ivoire', $r1['motif']);
        $this->assertSame('rejete', $r2['statut']);
        $this->assertStringContainsString('Contour GPS refusé', $r2['motif']);
        $this->assertSame(0, Parcelle::query()->count());
    }

    #[Test]
    public function un_contour_importe_au_bureau_est_marque_import(): void
    {
        $parcelle = Parcelle::factory()->create(['contour' => ParcelleFactory::geometrieCarre(50)]);

        $this->assertSame('import', $parcelle->contour_origine);
    }

    #[Test]
    public function une_photo_est_recue_une_seule_fois_et_une_non_image_est_refusee(): void
    {
        $uuid = (string) Str::uuid7();

        $this->envoyerPhoto($uuid)->assertCreated()->assertJsonPath('statut', 'accepte');
        $this->envoyerPhoto($uuid)->assertOk()->assertJsonPath('statut', 'deja_recu');

        $photo = PhotoTerrain::query()->sole();
        Storage::disk('local')->assertExists($photo->chemin);
        $this->assertSame('9.4500000', $photo->lat);

        $this->envoyerPhoto((string) Str::uuid7(), UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))->assertUnprocessable();
    }

    #[Test]
    public function une_depense_terrain_a_pour_justificatif_la_photo_envoyee_avant(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationDepense], ['valeur' => '50000']);
        $categorie = CategorieDepense::query()->create(['nom' => 'Carburant', 'exclue_fonds_campagne' => false, 'actif' => true]);
        $photo = (string) Str::uuid7();
        $donnees = ['categorie_id' => $categorie->id, 'compte_id' => $this->caisseAgent->id, 'montant_fcfa' => 15_000,
            'date_depense' => today()->toDateString(), 'beneficiaire' => 'Station Total Korhogo', 'justificatif_photo' => $photo];

        // Photo pas encore arrivée : rejet, renvoyable.
        $uuid = (string) Str::uuid7();
        $r = $this->operation('depense', $donnees, $uuid);
        $this->assertSame('rejete', $r['statut']);
        $this->assertStringContainsString('pas encore arrivée', $r['motif']);

        $this->envoyerPhoto($photo)->assertCreated();
        $this->assertSame('accepte', $this->operation('depense', $donnees, $uuid)['statut']);

        $depense = Depense::query()->findOrFail($uuid);
        $this->assertSame(PhotoTerrain::query()->findOrFail($photo)->chemin, $depense->justificatif);
        // Sous le seuil : payée depuis la caisse de l'agent.
        $this->assertSame(StatutDepense::Payee, $depense->statut);
        $this->assertSame(85_000, $this->caisseAgent->solde());
    }

    #[Test]
    public function une_depense_avec_la_photo_d_un_autre_ou_un_montant_non_entier_est_rejetee(): void
    {
        $categorie = CategorieDepense::query()->create(['nom' => 'Carburant', 'exclue_fonds_campagne' => false, 'actif' => true]);
        $photo = (string) Str::uuid7();
        Sanctum::actingAs(User::factory()->role(Role::Agent)->create());
        $this->envoyerPhoto($photo)->assertCreated();
        Sanctum::actingAs($this->agent);

        $base = ['categorie_id' => $categorie->id, 'compte_id' => $this->caisseAgent->id, 'montant_fcfa' => 15_000,
            'date_depense' => today()->toDateString(), 'beneficiaire' => 'X', 'justificatif_photo' => $photo];

        $this->assertStringContainsString('autre utilisateur', $this->operation('depense', $base)['motif']);
        $this->assertSame('rejete', $this->operation('depense', ['montant_fcfa' => 15_000.5] + $base)['statut']);
        $this->assertSame(0, Depense::query()->count());
    }

    #[Test]
    public function la_photo_de_pesee_peut_arriver_apres_l_achat(): void
    {
        $campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create();
        $lot = Lot::query()->create(['produit_id' => $campagne->produit_id, 'campagne_id' => $campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->direction->id]);
        $photo = (string) Str::uuid7();

        $r = $this->operation('achat', [
            'campagne_id' => $campagne->id, 'lot_id' => $lot->id, 'compte_id' => $this->caisseAgent->id,
            'fournisseur_type' => 'producteur', 'producteur_id' => $this->producteur->id,
            'date_achat' => now()->subMinute()->toIso8601String(), 'poids_brut_g' => 50_000, 'tare_g' => 0, 'prix_kg_fcfa' => 400,
            'photo_pesee' => $photo,
        ]);
        $this->assertSame('accepte', $r['statut']);
        $this->actingAs($this->direction)->get('/achats')->assertSee('photo attendue')->assertDontSee('Photo pesée');

        Sanctum::actingAs($this->agent);
        $this->envoyerPhoto($photo)->assertCreated();
        $this->assertSame($photo, Achat::query()->sole()->photoPesee?->id);
        $this->actingAs($this->direction)->get('/achats')->assertSee('Photo pesée');
        $this->actingAs($this->direction)->get(route('photos-terrain', $photo))->assertOk();
        $this->actingAs(User::factory()->role(Role::Investisseur)->create())->get(route('photos-terrain', $photo))->assertForbidden();
    }

    #[Test]
    public function les_referentiels_contiennent_les_categories_de_depense(): void
    {
        CategorieDepense::query()->create(['nom' => 'Carburant', 'exclue_fonds_campagne' => false, 'actif' => true]);

        $this->getJson('/api/referentiels')->assertOk()->assertJsonPath('categories_depense.0.nom', 'Carburant');
    }
}
