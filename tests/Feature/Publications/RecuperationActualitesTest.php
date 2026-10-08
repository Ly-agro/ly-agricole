<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Exceptions\OperationRefusee;
use App\Livewire\Publications\GestionPublications;
use App\Models\Actualite;
use App\Models\SourceActualites;
use App\Models\User;
use App\Services\RecuperationActualites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Actualités récupérées sur internet : flux RSS / Atom, toujours en brouillon à relire, extrait et
 * lien seulement, aucune adresse interne appelée, aucun doublon.
 */
class RecuperationActualitesTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://8.8.8.8/flux.xml';

    private User $direction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->direction = User::factory()->role(Role::Direction)->create();
    }

    private function rss(string $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Flux</title>'.$items.'</channel></rss>';
    }

    private function item(string $titre, string $lien, string $description = 'Le prix bord-champ de la campagne est annoncé.', string $date = 'Tue, 01 Sep 2026 08:00:00 GMT'): string
    {
        return '<item><title>'.$titre.'</title><link>'.$lien.'</link><description><![CDATA['.$description.']]></description><pubDate>'.$date.'</pubDate></item>';
    }

    private function source(): SourceActualites
    {
        return RecuperationActualites::ajouterSource('Presse agricole', self::URL, $this->direction);
    }

    #[Test]
    public function un_flux_rss_donne_des_brouillons_a_relire_jamais_publies(): void
    {
        Http::fake([self::URL => Http::response($this->rss($this->item('Cacao : le prix', 'https://exemple.ci/a1')))]);

        $r = RecuperationActualites::recuperer($this->source(), $this->direction);

        $this->assertSame(['nouveaux' => 1, 'ignores' => 0], $r);
        $a = Actualite::query()->firstOrFail();
        $this->assertFalse($a->publie);
        $this->assertNull($a->publie_le);
        $this->assertSame('externe', $a->origine);
        $this->assertSame('Presse agricole', $a->source_nom);
        $this->assertSame('https://exemple.ci/a1', $a->lien_source);
        $this->assertSame('2026-09-01', $a->date_source?->toDateString());
        $this->assertCount(0, Actualite::query()->visibles()->get());
    }

    #[Test]
    public function la_meme_actualite_n_est_jamais_importee_deux_fois(): void
    {
        Http::fake([self::URL => Http::response($this->rss($this->item('Cacao', 'https://exemple.ci/a1').$this->item('Café', 'https://exemple.ci/a2')))]);
        $source = $this->source();

        RecuperationActualites::recuperer($source, $this->direction);
        $r = RecuperationActualites::recuperer($source, $this->direction);

        $this->assertSame(['nouveaux' => 0, 'ignores' => 2], $r);
        $this->assertSame(2, Actualite::query()->count());
    }

    #[Test]
    public function une_actualite_relue_et_modifiee_n_est_pas_reimportee_ni_ecrasee(): void
    {
        Http::fake([self::URL => Http::response($this->rss($this->item('Titre d\'origine', 'https://exemple.ci/a1')))]);
        $source = $this->source();
        RecuperationActualites::recuperer($source, $this->direction);
        Actualite::query()->firstOrFail()->update(['titre' => 'Titre corrigé par la direction']);

        RecuperationActualites::recuperer($source, $this->direction);

        $this->assertSame(1, Actualite::query()->count());
        $this->assertSame('Titre corrigé par la direction', Actualite::query()->firstOrFail()->titre);
    }

    #[Test]
    public function le_html_est_retire_et_l_extrait_est_court(): void
    {
        $long = '<p>Début <b>gras</b> &amp; suite</p> '.str_repeat('mot ', 300);
        Http::fake([self::URL => Http::response($this->rss($this->item('Un &lt;i&gt;titre&lt;/i&gt;', 'https://exemple.ci/a1', $long)))]);

        RecuperationActualites::recuperer($this->source(), $this->direction);

        $a = Actualite::query()->firstOrFail();
        $this->assertSame('Un titre', $a->titre);
        $this->assertStringStartsWith('Début gras & suite', $a->contenu);
        $this->assertStringNotContainsString('<', $a->contenu);
        $this->assertLessThanOrEqual(RecuperationActualites::EXTRAIT_MAX, mb_strlen($a->contenu));
    }

    #[Test]
    public function un_flux_atom_est_lu(): void
    {
        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><entry><title>Karité</title>'
            .'<link rel="alternate" href="https://exemple.ci/k1"/><updated>2026-08-15T10:00:00Z</updated>'
            .'<summary>Le prix du karité pour la campagne.</summary></entry></feed>';
        Http::fake([self::URL => Http::response($atom)]);

        $r = RecuperationActualites::recuperer($this->source(), $this->direction);

        $this->assertSame(1, $r['nouveaux']);
        $this->assertSame('https://exemple.ci/k1', Actualite::query()->firstOrFail()->lien_source);
    }

    #[Test]
    public function un_element_sans_lien_web_valide_est_ignore(): void
    {
        $items = $this->item('Piégé', 'javascript:alert(1)').$this->item('Sans titre', 'https://exemple.ci/x', 'x', 'Tue, 01 Sep 2026 08:00:00 GMT')
            .$this->item('Bon', 'https://exemple.ci/bon');
        Http::fake([self::URL => Http::response($this->rss($items))]);

        RecuperationActualites::recuperer($this->source(), $this->direction);

        $this->assertSame(['Sans titre', 'Bon'], Actualite::query()->orderBy('id')->pluck('titre')->all());
    }

    #[Test]
    public function un_flux_avec_declaration_d_entites_est_refuse(): void
    {
        $piege = '<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]><rss><channel><item><title>&x;</title><link>https://exemple.ci/a</link></item></channel></rss>';
        Http::fake([self::URL => Http::response($piege)]);
        $source = $this->source();

        $this->expectException(OperationRefusee::class);
        try {
            RecuperationActualites::recuperer($source, $this->direction);
        } finally {
            $this->assertSame(0, Actualite::query()->count());
            $this->assertSame('erreur', $source->refresh()->dernier_statut);
        }
    }

    #[Test]
    public function une_source_en_panne_est_signalee_sans_bloquer_les_autres(): void
    {
        RecuperationActualites::ajouterSource('En panne', 'https://8.8.4.4/panne.xml', $this->direction);
        $this->source();
        Http::fake([
            'https://8.8.4.4/*' => Http::response('erreur', 500),
            self::URL => Http::response($this->rss($this->item('Cacao', 'https://exemple.ci/a1'))),
        ]);

        $r = RecuperationActualites::toutesLesSources();

        $this->assertSame(['nouveaux' => 1, 'erreurs' => 1], $r);
        $panne = SourceActualites::query()->where('nom', 'En panne')->firstOrFail();
        $this->assertSame('erreur', $panne->dernier_statut);
        $this->assertStringContainsString('500', (string) $panne->dernier_message);
    }

    #[Test]
    public function une_source_desactivee_n_est_pas_lue(): void
    {
        $source = $this->source();
        $source->update(['actif' => false]);
        Http::fake();

        $r = RecuperationActualites::toutesLesSources();

        $this->assertSame(['nouveaux' => 0, 'erreurs' => 0], $r);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string}> */
    public static function adressesRefusees(): array
    {
        return [
            'localhost' => ['http://localhost/flux'],
            'boucle locale' => ['http://127.0.0.1/flux'],
            'réseau privé' => ['http://192.168.1.10/flux'],
            'réseau privé 10' => ['http://10.0.0.5/flux'],
            'métadonnées cloud' => ['http://169.254.169.254/latest'],
            'ipv6 local' => ['http://[::1]/flux'],
            'fichier' => ['file:///etc/passwd'],
            'ftp' => ['ftp://exemple.ci/flux'],
            'sans schéma' => ['exemple.ci/flux'],
        ];
    }

    #[Test]
    #[DataProvider('adressesRefusees')]
    public function les_adresses_internes_ou_non_web_sont_refusees_et_rien_n_est_appele(string $url): void
    {
        Http::fake();

        try {
            RecuperationActualites::ajouterSource('Piège', $url, $this->direction);
            $this->fail('Adresse acceptée : '.$url);
        } catch (OperationRefusee) {
            $this->assertSame(0, SourceActualites::query()->count());
        }
        $this->expectException(OperationRefusee::class);
        RecuperationActualites::lire($url);
    }

    #[Test]
    public function seule_la_direction_ajoute_une_source(): void
    {
        $this->expectException(OperationRefusee::class);

        RecuperationActualites::ajouterSource('X', self::URL, User::factory()->role(Role::Comptable)->create());
    }

    #[Test]
    public function la_commande_planifiee_recupere_en_brouillon(): void
    {
        $this->source();
        Http::fake([self::URL => Http::response($this->rss($this->item('Cacao', 'https://exemple.ci/a1')))]);

        $this->artisan('vitrine:actualites')->expectsOutputToContain('1 nouveau(x) brouillon(s)')->assertSuccessful();

        $this->assertSame(1, Actualite::query()->where('publie', false)->count());
    }

    // --- bureau et vitrine ----------------------------------------------------------------------

    #[Test]
    public function la_direction_ajoute_et_recupere_depuis_l_ecran(): void
    {
        Http::fake([self::URL => Http::response($this->rss($this->item('Cacao', 'https://exemple.ci/a1')))]);

        Livewire::actingAs($this->direction)->test(GestionPublications::class)
            ->set('onglet', 'sources')
            ->set('fluxNom', 'Presse agricole')->set('fluxUrl', self::URL)->call('ajouterSource')
            ->assertHasNoErrors()->assertSee('Presse agricole')
            ->call('recupererTout')->assertSet('statut', fn ($s) => str_contains((string) $s, '1 nouvelle(s) actualité(s) en brouillon'));

        $this->assertSame(1, Actualite::query()->count());
    }

    #[Test]
    public function l_ecran_refuse_une_adresse_interne_avec_un_message(): void
    {
        Livewire::actingAs($this->direction)->test(GestionPublications::class)
            ->set('onglet', 'sources')
            ->set('fluxNom', 'Piège')->set('fluxUrl', 'http://127.0.0.1/x')->call('ajouterSource')
            ->assertHasErrors('fluxUrl');

        $this->assertSame(0, SourceActualites::query()->count());
    }

    #[Test]
    public function un_brouillon_recupere_est_invisible_puis_apparait_avec_sa_source_une_fois_publie(): void
    {
        Http::fake([self::URL => Http::response($this->rss($this->item('Cacao : prix annoncé', 'https://exemple.ci/a1')))]);
        RecuperationActualites::recuperer($this->source(), $this->direction);
        $a = Actualite::query()->firstOrFail();

        $this->get(route('actualites'))->assertOk()->assertDontSee('Cacao : prix annoncé');
        $this->get(route('actualites.voir', $a))->assertNotFound();

        Livewire::actingAs($this->direction)->test(GestionPublications::class)->call('basculerPublication', $a->id);

        $this->get(route('actualites.voir', $a))->assertOk()
            ->assertSee('Cacao : prix annoncé')->assertSee('Presse agricole')
            ->assertSeeHtml('href="https://exemple.ci/a1"')->assertSeeHtml('rel="noopener noreferrer nofollow"');
    }
}
