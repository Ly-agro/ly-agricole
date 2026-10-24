<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Livewire\Visites\ListeVisites;
use App\Models\OperationRecue;
use App\Models\Parcelle;
use App\Models\PhotoTerrain;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Visite;
use Database\Factories\ParcelleFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Visites de parcelle (phase 2, cahier §4) : fiche saisie hors ligne, photos envoyées à
 * part, idempotence par UUID, liste au bureau.
 */
class VisitesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Parcelle $parcelle;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->parcelle = Parcelle::factory()->create(['nom' => 'Verger du marigot']);
        Sanctum::actingAs($this->agent);
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array{uuid: string, statut: string, motif?: string}>
     */
    private function envoyer(array $operations): array
    {
        return $this->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => $operations])->assertOk()->json('resultats');
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function op(string $type, array $donnees, ?string $uuid = null): array
    {
        return ['uuid' => $uuid ?? (string) Str::uuid7(), 'type' => $type, 'cree_at' => now()->toIso8601String(), 'donnees' => $donnees];
    }

    /** @param  array<string, mixed>  $surcharge */
    private function visite(array $surcharge = [], ?string $uuid = null): array
    {
        return $this->op('visite', array_merge([
            'parcelle_id' => $this->parcelle->id,
            'date_visite' => now()->toDateString(),
            'lat' => 9.4512,
            'lng' => -5.6301,
            'pratiques' => ['debroussaillage', 'pare_feu'],
            'observations' => 'Pare-feu fait sur trois côtés. Quelques feuilles tachées.',
        ], $surcharge), $uuid);
    }

    private function envoyerPhoto(string $uuid): TestResponse
    {
        return $this->post('/api/photos', [
            'uuid' => $uuid, 'appareil_id' => 'tel', 'prise_at' => now()->toIso8601String(), 'lat' => 9.45, 'lng' => -5.63,
            'fichier' => UploadedFile::fake()->image('p.jpg', 800, 600),
        ], ['Accept' => 'application/json']);
    }

    #[Test]
    public function une_visite_avec_photos_est_enregistree_une_seule_fois(): void
    {
        $photos = [(string) Str::uuid7(), (string) Str::uuid7()];
        foreach ($photos as $p) {
            $this->envoyerPhoto($p)->assertSuccessful();
        }
        $uuid = (string) Str::uuid7();

        $this->assertSame('accepte', $this->envoyer([$this->visite(['photos' => $photos], $uuid)])[0]['statut']);
        // Réponse perdue, même envoi : rien en double.
        $this->assertSame('deja_recu', $this->envoyer([$this->visite(['photos' => $photos], $uuid)])[0]['statut']);

        $visite = Visite::query()->sole();
        $this->assertSame($uuid, $visite->id);
        $this->assertSame($this->parcelle->id, $visite->parcelle_id);
        $this->assertSame(['debroussaillage', 'pare_feu'], $visite->pratiques);
        $this->assertSame($this->agent->id, $visite->cree_par);
        $this->assertNotNull($visite->cree_at);
        $this->assertEqualsCanonicalizing($photos, $visite->photos()->pluck('id')->all());
    }

    #[Test]
    public function une_photo_pas_encore_arrivee_fait_rejeter_la_visite_qui_se_renvoie_ensuite(): void
    {
        $photo = (string) Str::uuid7();
        $uuid = (string) Str::uuid7();

        $r = $this->envoyer([$this->visite(['photos' => [$photo]], $uuid)])[0];
        $this->assertSame('rejete', $r['statut']);
        $this->assertStringContainsString('pas encore arrivée', $r['motif'] ?? '');
        $this->assertSame(0, Visite::query()->count());

        // La photo part, la même opération (même UUID) est renvoyée : acceptée.
        $this->envoyerPhoto($photo)->assertSuccessful();
        $this->assertSame('accepte', $this->envoyer([$this->visite(['photos' => [$photo]], $uuid)])[0]['statut']);
    }

    #[Test]
    public function la_photo_d_un_autre_utilisateur_est_refusee(): void
    {
        $photo = (string) Str::uuid7();
        Sanctum::actingAs(User::factory()->role(Role::Agent)->create());
        $this->envoyerPhoto($photo)->assertSuccessful();
        Sanctum::actingAs($this->agent);

        $r = $this->envoyer([$this->visite(['photos' => [$photo]])])[0];
        $this->assertSame('rejete', $r['statut']);
        $this->assertStringContainsString('autre utilisateur', $r['motif'] ?? '');
    }

    #[Test]
    public function une_fiche_vide_une_date_future_ou_une_pratique_inconnue_sont_rejetees(): void
    {
        $r = $this->envoyer([
            $this->visite(['pratiques' => [], 'observations' => '  ']),
            $this->visite(['date_visite' => now()->addDay()->toDateString()]),
            $this->visite(['pratiques' => ['danse_de_la_pluie']]),
            $this->visite(['parcelle_id' => (string) Str::uuid7()]),
            $this->visite(), // les refus ne bloquent pas les autres
        ]);

        $this->assertSame(['rejete', 'rejete', 'rejete', 'rejete', 'accepte'], array_column($r, 'statut'));
        $this->assertStringContainsString('vide', $r[0]['motif'] ?? '');
        $this->assertStringContainsString('futur', $r[1]['motif'] ?? '');
        $this->assertStringContainsString('Pratique inconnue', $r[2]['motif'] ?? '');
        $this->assertStringContainsString('Parcelle introuvable', $r[3]['motif'] ?? '');
        $this->assertSame(1, Visite::query()->count());
    }

    #[Test]
    public function une_parcelle_relevee_dans_le_meme_envoi_peut_etre_visitee(): void
    {
        $producteur = Producteur::factory()->create();
        $parcelle = (string) Str::uuid7();

        $r = $this->envoyer([
            $this->op('parcelle', ['producteur_id' => $producteur->id, 'nom' => 'Nouveau champ', 'contour' => ParcelleFactory::geometrieCarre(80)], $parcelle),
            $this->visite(['parcelle_id' => $parcelle]),
        ]);

        $this->assertSame(['accepte', 'accepte'], array_column($r, 'statut'));
        $this->assertSame($parcelle, Visite::query()->sole()->parcelle_id);
    }

    #[Test]
    public function l_agronome_saisit_des_visites_mais_pas_d_achat_ni_de_comptable_sur_le_terrain(): void
    {
        Sanctum::actingAs(User::factory()->role(Role::Agronome)->create());
        $this->getJson('/api/referentiels')->assertOk()->assertJsonFragment(['nom' => 'Verger du marigot']);
        $this->assertSame('accepte', $this->envoyer([$this->visite()])[0]['statut']);

        Sanctum::actingAs(User::factory()->role(Role::Comptable)->create());
        $r = $this->envoyer([$this->visite()])[0];
        $this->assertSame('rejete', $r['statut']);
        $this->assertStringContainsString('ne permet pas', $r['motif'] ?? '');
        $this->assertSame(1, OperationRecue::query()->where('type', 'visite')->where('statut', OperationRecue::ACCEPTE)->count());
    }

    #[Test]
    public function les_referentiels_envoient_les_parcelles_sans_leur_contour(): void
    {
        $parcelles = $this->getJson('/api/referentiels')->assertOk()->json('parcelles');

        $this->assertCount(1, $parcelles);
        $this->assertSame($this->parcelle->id, $parcelles[0]['id']);
        $this->assertArrayNotHasKey('contour', $parcelles[0]);
        $this->assertArrayHasKey('surface_m2', $parcelles[0]);
    }

    /** @return array<string, array{Role, int}> */
    public static function acces(): array
    {
        return [
            'direction' => [Role::Direction, 200],
            'agent' => [Role::Agent, 200],
            'comptable' => [Role::Comptable, 200],
            'agronome' => [Role::Agronome, 200],
            'investisseur' => [Role::Investisseur, 403],
            'admin' => [Role::Admin, 403],
        ];
    }

    #[Test]
    #[DataProvider('acces')]
    public function acces_a_la_liste_des_visites(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create())->get('/visites')->assertStatus($attendu);
    }

    #[Test]
    public function la_liste_montre_la_visite_et_filtre_par_pratique(): void
    {
        $photo = (string) Str::uuid7();
        $this->envoyerPhoto($photo)->assertSuccessful();
        $this->envoyer([$this->visite(['photos' => [$photo]])]);
        $this->envoyer([$this->visite(['pratiques' => ['taille'], 'observations' => 'Taille sanitaire faite.'])]);

        $this->actingAs(User::factory()->role(Role::Agronome)->create());
        Livewire::test(ListeVisites::class)
            ->assertSee('Verger du marigot')
            ->assertSee('Pare-feu')
            ->assertSee('Quelques feuilles tachées.')
            ->assertSee('Taille sanitaire faite.')
            ->assertSeeHtml(route('photos-terrain', $photo))
            ->set('pratique', 'taille')
            ->assertSee('Taille sanitaire faite.')
            ->assertDontSee('Quelques feuilles tachées.');
    }

    #[Test]
    public function la_fiche_du_producteur_montre_la_derniere_visite_de_chaque_parcelle(): void
    {
        $jamais = Parcelle::factory()->create(['producteur_id' => $this->parcelle->producteur_id, 'nom' => 'Jachère']);
        $this->envoyer([
            $this->visite(['date_visite' => '2026-09-20']),
            $this->visite(['date_visite' => '2026-09-27']),
        ]);

        $this->actingAs(User::factory()->role(Role::Agronome)->create())
            ->get(route('producteurs.fiche', $this->parcelle->producteur_id))
            ->assertOk()
            ->assertSee('27/09/2026')
            ->assertDontSee('20/09/2026')
            ->assertSee('2 visite(s)')
            ->assertSee($jamais->nom)
            ->assertSee('Jamais');
    }

    #[Test]
    public function l_agronome_voit_une_photo_de_visite_mais_pas_une_photo_de_pesee(): void
    {
        $deVisite = (string) Str::uuid7();
        $dePesee = (string) Str::uuid7();
        $this->envoyerPhoto($deVisite)->assertSuccessful();
        $this->envoyerPhoto($dePesee)->assertSuccessful();
        $this->envoyer([$this->visite(['photos' => [$deVisite]])]);

        $this->actingAs(User::factory()->role(Role::Agronome)->create());
        $this->get(route('photos-terrain', PhotoTerrain::query()->findOrFail($deVisite)))->assertOk();
        $this->get(route('photos-terrain', PhotoTerrain::query()->findOrFail($dePesee)))->assertForbidden();
    }
}
