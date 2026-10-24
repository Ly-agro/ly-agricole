<?php

namespace Tests\Feature\Publications;

use App\Enums\Role;
use App\Models\Campagne;
use App\Models\Produit;
use App\Models\User;
use App\Services\Publications;
use Database\Seeders\CulturesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Évolution publique des prix bord-champ : une courbe par produit, filtrable par campagne et par
 * période, avec un tableau de toutes les valeurs. Ne lit que les prix publiés.
 */
class EvolutionPrixTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $direction;

    private Produit $cacao;

    private Produit $anacarde;

    protected function setUp(): void
    {
        parent::setUp();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->cacao = Produit::query()->create(['code' => 'cacao', 'nom' => 'Cacao', 'actif' => true]);
        $this->anacarde = Produit::query()->create(['code' => 'anacarde', 'nom' => 'Anacarde', 'actif' => true]);
    }

    private function prix(Produit $produit, int $prix, string $date, string $source = 'Communiqué officiel'): void
    {
        Publications::publierPrix($produit, $prix, Carbon::parse($date), $source, null, null, $this->direction);
    }

    private function campagne(Produit $produit, string $code, string $debut, string $fin): Campagne
    {
        return Campagne::factory()->create(['produit_id' => $produit->id, 'code' => $code, 'debut' => $debut, 'fin' => $fin]);
    }

    // --- la série de prix ---------------------------------------------------------------------

    #[Test]
    public function la_serie_donne_chaque_changement_et_son_resume(): void
    {
        $this->prix($this->cacao, 1_800, '2026-01-10');
        $this->prix($this->cacao, 1_200, '2026-03-05');
        $this->prix($this->cacao, 1_400, '2026-06-20');

        $s = Publications::serie($this->cacao, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame([1_800, 1_200, 1_400], array_column($s['points'], 'prix'));
        $this->assertSame(['premier' => 1_800, 'dernier' => 1_400, 'min' => 1_200, 'max' => 1_800, 'variation' => -400, 'changements' => 2], $s['resume']);
    }

    #[Test]
    public function un_prix_deja_en_vigueur_au_debut_de_la_periode_ouvre_la_courbe(): void
    {
        $this->prix($this->cacao, 1_800, '2025-11-01', 'Ancien communiqué');
        $this->prix($this->cacao, 1_200, '2026-03-05');

        $s = Publications::serie($this->cacao, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertCount(2, $s['points']);
        $this->assertTrue($s['points'][0]['report']);
        $this->assertSame('2026-01-01', $s['points'][0]['date']->toDateString());
        $this->assertSame(1_800, $s['points'][0]['prix']);
        $this->assertSame('Ancien communiqué', $s['points'][0]['source']);
        $this->assertFalse($s['points'][1]['report']);
    }

    #[Test]
    public function un_changement_le_jour_du_debut_ne_double_pas_le_point_de_report(): void
    {
        $this->prix($this->cacao, 1_800, '2025-11-01');
        $this->prix($this->cacao, 1_200, '2026-01-01');

        $s = Publications::serie($this->cacao, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame([1_200], array_column($s['points'], 'prix'));
        $this->assertFalse($s['points'][0]['report']);
    }

    #[Test]
    public function les_prix_apres_la_fin_de_la_periode_sont_ignores_et_une_periode_vide_donne_null(): void
    {
        $this->prix($this->cacao, 1_800, '2026-01-10');
        $this->prix($this->cacao, 1_200, '2026-09-01');

        $s = Publications::serie($this->cacao, Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30'));
        $this->assertSame([1_800], array_column($s['points'], 'prix'));

        $vide = Publications::serie($this->anacarde, Carbon::parse('2026-01-01'), Carbon::parse('2026-06-30'));
        $this->assertSame([], $vide['points']);
        $this->assertNull($vide['resume']);
    }

    #[Test]
    public function une_periode_a_l_envers_est_remise_dans_l_ordre(): void
    {
        $this->prix($this->cacao, 1_800, '2026-01-10');

        $s = Publications::serie($this->cacao, Carbon::parse('2026-12-31'), Carbon::parse('2026-01-01'));

        $this->assertSame('2026-01-01', $s['debut']->toDateString());
        $this->assertSame('2026-12-31', $s['fin']->toDateString());
    }

    #[Test]
    public function la_serie_garde_la_valeur_la_plus_recemment_saisie_pour_une_meme_date(): void
    {
        $this->prix($this->cacao, 1_800, '2026-01-10');
        $this->prix($this->cacao, 1_850, '2026-01-10', 'Correction');

        $s = Publications::serie($this->cacao);

        $this->assertSame([1_850], array_column($s['points'], 'prix'));
        $this->assertSame('Correction', $s['points'][0]['source']);
    }

    // --- campagne par campagne ----------------------------------------------------------------

    #[Test]
    public function le_resume_par_campagne_ne_compte_que_les_campagnes_du_produit_commencees(): void
    {
        $this->prix($this->cacao, 2_800, '1990-10-05');
        $this->prix($this->cacao, 1_200, '1991-04-01');
        $c1 = $this->campagne($this->cacao, '1990-1991', '1990-10-01', '1991-09-30');
        $this->campagne($this->anacarde, '1990-1991', '1990-10-01', '1991-09-30'); // autre produit
        $this->campagne($this->cacao, '2090-2091', '2090-10-01', '2091-09-30'); // future

        $lignes = Publications::parCampagne($this->cacao);

        $this->assertCount(1, $lignes);
        $this->assertSame($c1->id, $lignes[0]['campagne']->id);
        $this->assertSame(['premier' => 2_800, 'dernier' => 1_200, 'min' => 1_200, 'max' => 2_800, 'variation' => -1_600, 'changements' => 1], $lignes[0]['resume']);
    }

    #[Test]
    public function on_rend_les_sept_dernieres_campagnes_avec_l_ecart_depuis_la_precedente(): void
    {
        // Neuf campagnes 1980-1988, prix 1000, 1100, … 1800 : chacune démarre avec son prix.
        for ($i = 0; $i < 9; $i++) {
            $an = 1980 + $i;
            $this->campagne($this->cacao, $an.'-'.($an + 1), $an.'-10-01', ($an + 1).'-09-30');
            $this->prix($this->cacao, 1_000 + 100 * $i, $an.'-10-01');
        }

        $lignes = Publications::parCampagne($this->cacao);

        $this->assertCount(7, $lignes);
        $this->assertSame('1988-1989', $lignes[0]['campagne']->code);
        $this->assertSame(100, $lignes[0]['depuis_precedente']);
        // La plus ancienne des sept a encore une précédente (la huitième) : l'écart est connu.
        $this->assertSame(100, $lignes[6]['depuis_precedente']);
        $this->assertCount(1, Publications::parCampagne($this->cacao, 1));
    }

    #[Test]
    public function le_tableau_d_ensemble_liste_toutes_les_cultures_actives_sur_les_sept_dernieres_campagnes(): void
    {
        $this->seed(CulturesSeeder::class);
        // 9 campagnes de cacao ; seules les 7 plus récentes sont des colonnes.
        $cacao = Produit::query()->where('code', 'cacao')->firstOrFail();
        for ($i = 0; $i < 9; $i++) {
            $an = 1980 + $i;
            $this->campagne($cacao, $an.'-'.($an + 1), $an.'-10-01', ($an + 1).'-09-30');
            $this->prix($cacao, 1_000 + 100 * $i, $an.'-10-01');
        }

        $t = Publications::tableauCampagnes();

        $this->assertSame(['1982-1983', '1983-1984', '1984-1985', '1985-1986', '1986-1987', '1987-1988', '1988-1989'], $t['campagnes']);
        $this->assertCount(Produit::query()->where('actif', true)->count(), $t['lignes']);
        $this->assertGreaterThanOrEqual(35, count($t['lignes']));
        $ligne = collect($t['lignes'])->first(fn (array $l) => $l['produit']->id === $cacao->id);
        $this->assertTrue($ligne['connu']);
        $this->assertSame(['prix' => 1_800, 'ecart' => 100], $ligne['cases']['1988-1989']);
        $manioc = collect($t['lignes'])->first(fn (array $l) => $l['produit']->code === 'manioc');
        $this->assertFalse($manioc['connu']);
        $this->assertSame([null], array_values(array_unique($manioc['cases'], SORT_REGULAR)));
    }

    #[Test]
    public function la_page_affiche_le_tableau_des_cultures_avec_les_cases_vides_sans_prix_invente(): void
    {
        $this->seed(CulturesSeeder::class);
        $cacao = Produit::query()->where('code', 'cacao')->firstOrFail();
        $this->campagne($cacao, '1990-1991', '1990-10-01', '1991-09-30');
        $this->prix($cacao, 2_800, '1990-10-05');
        $this->campagne($cacao, '1991-1992', '1991-10-01', '1992-09-30');
        $this->prix($cacao, 1_200, '1991-10-05');

        $page = $this->get('/prix')->assertOk()->assertSee('1990-1991')->assertSee('1991-1992')
            ->assertSee('Manioc')->assertSee('2'.self::FINE.'800')->assertSee('1'.self::FINE.'200')
            ->assertSee('1'.self::FINE.'600')->getContent();

        $this->assertStringContainsString('▼', (string) $page);
        $this->assertGreaterThanOrEqual(35, substr_count((string) $page, '<th scope="row"'));
    }

    #[Test]
    public function la_toute_premiere_campagne_connue_n_a_pas_d_ecart_depuis_la_precedente(): void
    {
        $this->campagne($this->cacao, '1990-1991', '1990-10-01', '1991-09-30');
        $this->prix($this->cacao, 1_200, '1990-10-01');

        $this->assertNull(Publications::parCampagne($this->cacao)[0]['depuis_precedente']);
    }

    #[Test]
    public function une_campagne_sans_aucun_prix_connu_n_apparait_pas(): void
    {
        $this->prix($this->cacao, 1_200, '1995-01-01');
        $this->campagne($this->cacao, '1990-1991', '1990-10-01', '1991-09-30'); // avant tout prix publié

        $this->assertSame([], Publications::parCampagne($this->cacao));
    }

    // --- la page publique ---------------------------------------------------------------------

    #[Test]
    public function la_page_sans_prix_le_dit(): void
    {
        $this->get('/prix')->assertOk()->assertSee('Les prix, campagne après campagne')->assertSee('Aucun prix publié pour le moment');
    }

    #[Test]
    public function la_page_est_publique_et_dessine_une_courbe_avec_son_tableau_et_ses_libelles(): void
    {
        $this->prix($this->cacao, 1_800, '2026-01-10', 'Communiqué de janvier');
        $this->prix($this->cacao, 1_200, '2026-03-05', 'Communiqué de mars');

        $page = (string) $this->get('/prix')->assertOk()
            ->assertSee('Cacao')->assertSee('1'.self::FINE.'800')->assertSee('1'.self::FINE.'200')
            ->assertSee('Voir les valeurs en tableau')->assertSee('Communiqué de mars')->assertSee('▼')
            ->assertSeeHtml('role="img"')->assertSeeHtml('aria-label="Évolution du prix Cacao')
            ->assertSeeHtml('<path d="M')->assertSee('Ces prix sont indicatifs')->getContent();

        // Une marque cliquable / focalisable par valeur, avec sa date, son prix et sa source.
        $this->assertSame(2, substr_count($page, 'data-prix='));
        $this->assertStringContainsString('tabindex="0"', $page);
    }

    #[Test]
    public function un_graphique_par_produit_et_le_filtre_produit_n_en_garde_qu_un(): void
    {
        $this->prix($this->cacao, 1_200, '2026-03-05');
        $this->prix($this->anacarde, 400, '2026-02-06');

        $this->get('/prix')->assertOk()->assertSee('Cacao')->assertSee('Anacarde');
        $tous = (string) $this->get('/prix')->getContent();
        $this->assertSame(2, substr_count($tous, ' data-courbe>'));

        $un = (string) $this->get('/prix?produit='.$this->anacarde->id)->assertOk()->assertSee('Anacarde')->getContent();
        $this->assertSame(1, substr_count($un, ' data-courbe>'));
        $this->assertStringNotContainsString('aria-label="Évolution du prix Cacao', $un);
    }

    #[Test]
    public function le_filtre_par_campagne_limite_la_courbe_aux_dates_de_la_campagne(): void
    {
        $this->prix($this->cacao, 2_800, '1990-06-01');
        $this->prix($this->cacao, 1_500, '1991-01-15');
        $this->prix($this->cacao, 1_200, '1992-06-01');
        $c = $this->campagne($this->cacao, '1990-1991', '1990-10-01', '1991-09-30');

        $page = (string) $this->get('/prix?periode=campagne-'.$c->id)->assertOk()
            ->assertSee('du 01/10/1990 au 30/09/1991')->getContent();

        // Prix déjà en vigueur au début (2 800) + le changement du 15/01/1991 (1 500) ; pas celui de 1992.
        $this->assertSame(2, substr_count($page, 'data-prix='));
        $this->assertStringContainsString('(déjà en vigueur)', $page);
        $this->assertStringNotContainsString('01/06/1992', $page);
    }

    #[Test]
    public function la_periode_personnalisee_et_les_derniers_mois_marchent_et_une_valeur_invalide_revient_a_tout(): void
    {
        $this->prix($this->cacao, 1_800, Carbon::today()->subMonths(20)->toDateString());
        $this->prix($this->cacao, 1_200, Carbon::today()->subMonths(2)->toDateString());

        $this->get('/prix?periode=6m')->assertOk()->assertSee('6 derniers mois');
        $six = (string) $this->get('/prix?periode=6m')->getContent();
        $this->assertSame(2, substr_count($six, 'data-prix='), 'Le prix de 20 mois est repris comme « déjà en vigueur » au début de la période.');

        $perso = (string) $this->get('/prix?periode=perso&du='.Carbon::today()->subMonths(3)->toDateString().'&au='.Carbon::today()->toDateString())->assertOk()->getContent();
        $this->assertSame(2, substr_count($perso, 'data-prix='));

        foreach (['periode=n-importe-quoi', 'periode=campagne-999999', 'periode=perso&du=pas-une-date', 'periode=campagne-abc', 'produit=zzz'] as $mauvais) {
            $this->get('/prix?'.$mauvais)->assertOk()->assertSee('Cacao');
        }
    }

    #[Test]
    public function une_campagne_qui_n_a_pas_commence_n_est_pas_proposee_dans_les_filtres(): void
    {
        $this->prix($this->cacao, 1_200, '2026-03-05');
        $this->campagne($this->cacao, '2090-2091', '2090-10-01', '2091-09-30');
        $passee = $this->campagne($this->cacao, '1990-1991', '1990-10-01', '1991-09-30');

        $page = (string) $this->get('/prix')->assertOk()->getContent();

        $this->assertStringContainsString('value="campagne-'.$passee->id.'"', $page);
        $this->assertStringNotContainsString('2090-2091', $page);
        $this->get('/prix?periode=campagne-'.Campagne::query()->where('code', '2090-2091')->value('id'))->assertOk()->assertSee('Toute la période');
    }

    #[Test]
    public function la_page_montre_le_tableau_campagne_par_campagne(): void
    {
        $this->prix($this->cacao, 2_800, '1990-10-05');
        $this->prix($this->cacao, 1_200, '1991-04-01');
        $this->campagne($this->cacao, '1990-1991', '1990-10-01', '1991-09-30');

        $this->get('/prix')->assertOk()->assertSee('campagnes connues, une à une')->assertSee('1990-1991')->assertSee('1'.self::FINE.'600');
    }

    #[Test]
    public function un_prix_du_futur_ou_de_la_periode_hors_donnees_ne_casse_pas_la_page(): void
    {
        $this->prix($this->cacao, 1_200, '2026-03-05');

        $this->get('/prix?periode=perso&du=2000-01-01&au=2000-12-31')->assertOk()->assertSee('Aucun prix publié sur cette période');
        $this->get('/prix?periode=perso&du=2999-01-01&au=2999-12-31')->assertOk();
    }

    #[Test]
    public function la_vitrine_renvoie_vers_l_evolution_des_prix(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Voir l\'évolution des prix');

        $this->prix($this->cacao, 1_200, '2026-03-05');

        $this->get('/')->assertOk()->assertSeeHtml('href="'.route('prix.evolution').'"');
    }

    #[Test]
    public function la_page_ne_contient_que_des_prix_publies_pas_le_prix_officiel_de_campagne(): void
    {
        $this->prix($this->cacao, 1_200, '2026-03-05');
        Campagne::factory()->create(['produit_id' => $this->cacao->id, 'code' => '1990-1991', 'prix_officiel_kg_fcfa' => 987_654]);

        $this->assertStringNotContainsString('987', (string) $this->get('/prix')->assertOk()->getContent());
    }
}
