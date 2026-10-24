<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Exceptions\OperationRefusee;
use App\Exceptions\RegistreImmuableException;
use App\Livewire\Publications\GestionPublications;
use App\Models\Actualite;
use App\Models\Campagne;
use App\Models\PrixMarche;
use App\Models\Produit;
use App\Models\User;
use App\Services\Publications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vitrine publique : prix bord-champ (café, cacao, anacarde, autres) et actualités, saisis par la
 * direction. Un prix est une information datée et sourcée ; jamais le prix officiel d'une campagne.
 */
class PublicationsTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $direction;

    private Produit $cacao;

    private Produit $cafe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->cacao = Produit::query()->create(['code' => 'cacao', 'nom' => 'Cacao', 'actif' => true]);
        $this->cafe = Produit::query()->create(['code' => 'cafe', 'nom' => 'Café', 'actif' => true]);
    }

    private function prix(Produit $produit, int $prix, ?Carbon $date = null, string $source = 'Communiqué officiel', ?string $url = null, ?User $auteur = null): PrixMarche
    {
        return Publications::publierPrix($produit, $prix, $date ?? Carbon::today(), $source, $url, null, $auteur ?? $this->direction);
    }

    // --- prix ---------------------------------------------------------------------------------

    #[Test]
    public function un_prix_est_publie_avec_sa_source_sa_date_et_son_auteur(): void
    {
        $p = $this->prix($this->cacao, 1_800, Carbon::today()->subDays(2), 'Communiqué du Conseil Café-Cacao', 'https://exemple.ci/communique');

        $this->assertSame(1_800, $p->prix_kg_fcfa);
        $this->assertSame('Communiqué du Conseil Café-Cacao', $p->source);
        $this->assertSame('https://exemple.ci/communique', $p->source_url);
        $this->assertSame($this->direction->id, $p->cree_par);
        $this->assertTrue($p->date_effet->isSameDay(Carbon::today()->subDays(2)));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function refus(): array
    {
        return [
            'prix nul' => [['prix' => 0], 'supérieur à zéro'],
            'prix négatif' => [['prix' => -5], 'supérieur à zéro'],
            'prix aberrant' => [['prix' => 2_000_000], 'supérieur à zéro'],
            'source vide' => [['source' => '  '], 'source du prix est obligatoire'],
            'source trop courte' => [['source' => 'ab'], 'source du prix est obligatoire'],
            'lien javascript' => [['url' => 'javascript:alert(1)'], 'http://'],
            'lien sans schéma' => [['url' => 'exemple.ci/x'], 'http://'],
            'date future' => [['date' => 'demain'], 'futur'],
        ];
    }

    /**
     * @param  array<string, mixed>  $surcharge
     */
    #[Test]
    #[DataProvider('refus')]
    public function une_saisie_invalide_est_refusee_avec_un_motif(array $surcharge, string $extrait): void
    {
        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($extrait, '/').'/u');

        Publications::publierPrix(
            $this->cacao, $surcharge['prix'] ?? 1_800,
            ($surcharge['date'] ?? null) === 'demain' ? Carbon::tomorrow() : Carbon::today(),
            $surcharge['source'] ?? 'Communiqué', $surcharge['url'] ?? null, null, $this->direction,
        );
    }

    #[Test]
    public function un_produit_desactive_ne_peut_pas_recevoir_de_prix(): void
    {
        $this->cafe->update(['actif' => false]);

        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessage('désactivé');

        $this->prix($this->cafe, 1_000);
    }

    #[Test]
    public function seule_la_direction_publie(): void
    {
        foreach ([Role::Comptable, Role::Agent, Role::Agronome, Role::Investisseur, Role::Admin] as $role) {
            try {
                $this->prix($this->cacao, 1_800, null, 'Communiqué', null, User::factory()->role($role)->create());
                $this->fail("Le rôle {$role->value} n'aurait pas dû pouvoir publier.");
            } catch (OperationRefusee $e) {
                $this->assertStringContainsString('direction', $e->getMessage());
            }
        }
        $this->assertSame(0, PrixMarche::query()->count());
    }

    #[Test]
    public function un_prix_ne_se_corrige_pas_on_en_publie_un_nouveau(): void
    {
        $ancien = $this->prix($this->cacao, 1_800, Carbon::today()->subDays(10));
        $nouveau = $this->prix($this->cacao, 1_850, Carbon::today());

        $courants = Publications::prixCourants();

        $this->assertCount(1, $courants);
        $this->assertSame($nouveau->id, $courants[0]['prix']->id);
        $this->assertSame($ancien->id, $courants[0]['precedent']->id);
        $this->assertSame(50, $courants[0]['ecart']);
        $this->assertCount(2, Publications::historiquePrix($this->cacao));

        try {
            $ancien->update(['prix_kg_fcfa' => 1]);
            $this->fail('La modification aurait dû être refusée.');
        } catch (RegistreImmuableException) {
            $this->assertSame(1_800, $ancien->refresh()->prix_kg_fcfa);
        }
    }

    #[Test]
    public function le_prix_en_vigueur_suit_la_date_d_effet_pas_l_ordre_de_saisie(): void
    {
        // Saisi en dernier, mais plus ancien : il n'est pas « en vigueur ».
        $recent = $this->prix($this->cacao, 1_900, Carbon::today());
        $this->prix($this->cacao, 1_500, Carbon::today()->subMonth());

        $this->assertSame($recent->id, Publications::prixCourants()[0]['prix']->id);
    }

    #[Test]
    public function chaque_produit_a_son_prix_et_l_ecart_est_signe(): void
    {
        $this->prix($this->cacao, 1_800, Carbon::today()->subDays(5));
        $this->prix($this->cacao, 1_750, Carbon::today());
        $this->prix($this->cafe, 1_200);

        $courants = Publications::prixCourants()->keyBy(fn ($l) => $l['produit']->code);

        $this->assertSame(['cacao', 'cafe'], $courants->keys()->all());
        $this->assertSame(-50, $courants['cacao']['ecart']);
        $this->assertNull($courants['cafe']['ecart'], 'Premier prix d\'un produit : pas d\'écart.');
    }

    // --- actualités ---------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $d
     */
    private function actu(array $d = [], ?Actualite $existante = null): Actualite
    {
        return Publications::enregistrerActualite(array_merge([
            'titre' => 'Ouverture de la campagne', 'contenu' => 'La campagne d\'anacarde ouvre bientôt.', 'publie' => true,
        ], $d), $this->direction, $existante);
    }

    #[Test]
    public function une_actualite_publiee_prend_la_date_du_jour_et_un_brouillon_n_en_a_pas(): void
    {
        $publiee = $this->actu();
        $brouillon = $this->actu(['titre' => 'À venir', 'publie' => false]);

        $this->assertTrue($publiee->publie_le->isToday());
        $this->assertNull($brouillon->publie_le);
        $this->assertSame([$publiee->id], Publications::actualitesVisibles()->pluck('id')->all());
    }

    #[Test]
    public function une_actualite_deja_publiee_garde_sa_date_et_peut_etre_retiree_puis_remise(): void
    {
        $a = $this->actu(['publie_le' => Carbon::today()->subDays(3)]);
        $this->actu(['titre' => 'Titre corrigé'], $a);

        $this->assertSame('Titre corrigé', $a->refresh()->titre);
        $this->assertTrue($a->publie_le->isSameDay(Carbon::today()->subDays(3)));

        $this->actu(['publie' => false], $a);
        $this->assertCount(0, Publications::actualitesVisibles());
    }

    #[Test]
    public function une_actualite_datee_du_futur_n_est_pas_visible(): void
    {
        $this->actu(['publie_le' => Carbon::tomorrow()]);

        $this->assertCount(0, Publications::actualitesVisibles());
    }

    #[Test]
    public function une_actualite_trop_courte_ou_sans_titre_est_refusee(): void
    {
        foreach ([['titre' => ' '], ['contenu' => 'court']] as $mauvais) {
            try {
                $this->actu($mauvais);
                $this->fail('Aurait dû être refusé.');
            } catch (OperationRefusee) {
                $this->assertSame(0, Actualite::query()->count());
            }
        }
    }

    // --- la vitrine publique ------------------------------------------------------------------

    #[Test]
    public function la_vitrine_sans_rien_de_publie_le_dit_et_reste_sans_aucun_montant(): void
    {
        $page = $this->get('/')->assertOk()->assertSee('Aucun prix publié pour le moment')->assertSee('Aucune actualité pour le moment')->getContent();

        $this->assertStringNotContainsString('FCFA', strip_tags((string) $page));
    }

    #[Test]
    public function la_vitrine_affiche_les_prix_avec_source_date_et_ecart_sans_authentification(): void
    {
        $this->prix($this->cacao, 1_800, Carbon::today()->subDays(5), 'Ancien communiqué');
        $this->prix($this->cacao, 1_850, Carbon::today(), 'Communiqué du Conseil Café-Cacao', 'https://exemple.ci/c');

        $this->get('/')->assertOk()
            ->assertSee('Cacao')->assertSee('1'.self::FINE.'850')->assertSee('FCFA / kg')
            ->assertSee('Communiqué du Conseil Café-Cacao')->assertSee('+50')
            ->assertSee('Ils ne remplacent pas le prix officiel')
            ->assertSeeHtml('href="https://exemple.ci/c"')->assertSeeHtml('rel="noopener noreferrer"');
    }

    #[Test]
    public function la_vitrine_ne_montre_aucun_montant_en_dehors_de_la_section_des_prix_ni_le_prix_officiel_de_campagne(): void
    {
        $campagne = Campagne::factory()->create(['prix_officiel_kg_fcfa' => 987_654]);
        $this->prix($this->cacao, 1_850);

        $page = (string) $this->get('/')->assertOk()->getContent();
        $horsPrix = preg_replace('#<section id="prix".*?</section>#s', '', $page);

        $this->assertStringNotContainsString('FCFA', strip_tags((string) $horsPrix));
        $this->assertStringNotContainsString('987', $page, 'Le prix officiel d\'une campagne n\'est jamais publié tel quel.');
        $this->assertNotNull($campagne->id);
    }

    #[Test]
    public function la_vitrine_liste_les_dernieres_actualites_publiees_seulement(): void
    {
        $this->actu(['titre' => 'Visible un']);
        $this->actu(['titre' => 'Brouillon caché', 'publie' => false]);
        $this->actu(['titre' => 'Futur caché', 'publie_le' => Carbon::tomorrow()]);

        $this->get('/')->assertOk()->assertSee('Visible un')->assertDontSee('Brouillon caché')->assertDontSee('Futur caché')->assertSee('Toutes les actualités');
    }

    #[Test]
    public function les_pages_d_actualites_sont_publiques_et_les_brouillons_n_existent_pas(): void
    {
        $visible = $this->actu(['titre' => 'Bonne nouvelle', 'contenu' => "Première ligne.\nSeconde ligne de la nouvelle."]);
        $brouillon = $this->actu(['titre' => 'Secret', 'publie' => false]);
        $futur = $this->actu(['titre' => 'Demain', 'publie_le' => Carbon::tomorrow()]);

        $this->get('/actualites')->assertOk()->assertSee('Bonne nouvelle')->assertDontSee('Secret')->assertDontSee('Demain');
        $this->get('/actualites/'.$visible->id)->assertOk()->assertSee('Bonne nouvelle')->assertSee('Seconde ligne de la nouvelle.');
        $this->get('/actualites/'.$brouillon->id)->assertNotFound();
        $this->get('/actualites/'.$futur->id)->assertNotFound();
        $this->get('/actualites/999999')->assertNotFound();
    }

    #[Test]
    public function le_texte_d_une_actualite_est_echappe_jamais_du_html(): void
    {
        $a = $this->actu(['titre' => '<script>alert(1)</script>Titre', 'contenu' => 'Texte avec <b>gras</b> et <img src=x onerror=alert(1)> fin.']);

        $page = (string) $this->get('/actualites/'.$a->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringNotContainsString('<img src=x', $page);
        $this->assertStringContainsString('&lt;b&gt;gras&lt;/b&gt;', $page);
    }

    #[Test]
    public function les_liens_du_menu_public_pointent_vers_l_accueil_depuis_les_actualites(): void
    {
        $this->get('/actualites')->assertOk()->assertSeeHtml('href="'.route('accueil').'#prix"')->assertSeeHtml('href="'.route('accueil').'#actualites"');
        $this->get('/')->assertOk()->assertSeeHtml('href="#prix"')->assertSeeHtml('href="#actualites"');
    }

    // --- l'écran de la direction --------------------------------------------------------------

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'admin' => [Role::Admin, 403], 'comptable' => [Role::Comptable, 403], 'agent' => [Role::Agent, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function l_ecran_de_gestion_suit_le_role(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/publications')->assertStatus($attendu);
    }

    #[Test]
    public function la_direction_publie_un_prix_depuis_l_ecran_et_l_historique_reste(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(GestionPublications::class)
            ->set('produitId', (string) $this->cacao->id)->set('prixKg', '1 800')->set('source', 'Communiqué du Conseil')
            ->call('publierPrix')->assertHasNoErrors()->assertSet('statut', fn ($s) => str_contains((string) $s, 'Prix publié'))
            ->set('prixKg', '1850')->set('source', 'Nouveau communiqué')->call('publierPrix')->assertHasNoErrors()
            ->assertSee('Historique')->assertSee('Communiqué du Conseil');

        $this->assertSame(2, PrixMarche::query()->count());
    }

    #[Test]
    public function l_ecran_refuse_un_prix_decimal_une_source_absente_et_un_lien_dangereux(): void
    {
        $this->actingAs($this->direction);
        $ecran = fn () => Livewire::test(GestionPublications::class)->set('produitId', (string) $this->cacao->id)->set('prixKg', '1800')->set('source', 'Communiqué');

        $ecran()->set('prixKg', '1800,5')->call('publierPrix')->assertHasErrors(['prixKg']);
        $ecran()->set('source', '')->call('publierPrix')->assertHasErrors(['source']);
        $ecran()->set('sourceUrl', 'javascript:alert(1)')->call('publierPrix')->assertHasErrors(['prixKg']);
        $this->assertSame(0, PrixMarche::query()->count());
    }

    #[Test]
    public function l_ecran_reprend_le_prix_officiel_de_la_campagne_ouverte_comme_point_de_depart_seulement(): void
    {
        Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['produit_id' => $this->cacao->id, 'code' => '2030-2031', 'prix_officiel_kg_fcfa' => 1_000]);
        $this->actingAs($this->direction);

        Livewire::test(GestionPublications::class)->set('produitId', (string) $this->cacao->id)
            ->call('reprendrePrixCampagne')->assertSet('prixKg', '1000')->assertSet('source', 'Prix officiel de la campagne 2030-2031');

        $this->assertSame(0, PrixMarche::query()->count(), 'Reprendre pré-remplit le formulaire, rien n\'est publié tout seul.');
    }

    #[Test]
    public function la_direction_ecrit_un_brouillon_le_publie_puis_le_retire(): void
    {
        $this->actingAs($this->direction);

        $ecran = Livewire::test(GestionPublications::class)->set('onglet', 'actualites')
            ->call('nouvelleActualite')->set('titre', 'Mon actualité')->set('contenu', 'Un texte assez long pour être publié.')
            ->call('enregistrerActualite')->assertHasNoErrors()->assertSet('statut', fn ($s) => str_contains((string) $s, 'Brouillon enregistré'));

        $a = Actualite::query()->sole();
        $this->assertFalse($a->publie);
        $this->get('/actualites/'.$a->id)->assertNotFound();

        $ecran->call('basculerPublication', $a->id)->assertSet('statut', fn ($s) => str_contains((string) $s, 'Actualité publiée'));
        $this->get('/actualites/'.$a->id)->assertOk();

        $ecran->call('basculerPublication', $a->id)->assertSet('statut', fn ($s) => str_contains((string) $s, 'retirée de la vitrine'));
        $this->get('/actualites/'.$a->id)->assertNotFound();
    }
}
