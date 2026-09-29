<?php

namespace Tests\Feature\Resultat;

use App\Enums\Role;
use App\Exceptions\OperationRefusee;
use App\Exceptions\RegistreImmuableException;
use App\Models\Campagne;
use App\Models\Magasin;
use App\Models\User;
use App\Models\ValorisationStock;
use App\Services\PartageResultat;
use App\Services\ResultatCampagne;
use App\Services\ValorisationsStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valorisation du stock invendu (contrat art. 11.3 ; question 32) : saisie par la direction à
 * partir de deux offres écrites, registre immuable, prise en compte dans le résultat, et
 * conditions de clôture d'un résultat définitif.
 */
class ValorisationStockTest extends TestCase
{
    use RefreshDatabase;

    private User $direction;

    private Campagne $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $surcharge
     * @return array<string, mixed>
     */
    private function donnees(array $surcharge = []): array
    {
        return array_merge([
            'poids_g' => 100_000,
            'offre1_fournisseur' => 'Ivoire Export', 'offre1_date' => Carbon::today()->subDays(3), 'offre1_prix_kg_fcfa' => 900,
            'offre2_fournisseur' => 'Usine Korhogo', 'offre2_date' => Carbon::today()->subDays(2), 'offre2_prix_kg_fcfa' => 850,
            'valeur_retenue_fcfa' => 85_000, 'motif' => 'Prix le plus prudent',
        ], $surcharge);
    }

    private function valoriser(array $surcharge = [], ?User $auteur = null): ValorisationStock
    {
        return ValorisationsStock::enregistrer($this->campagne, $this->donnees($surcharge), $auteur ?? $this->direction);
    }

    #[Test]
    public function la_direction_enregistre_deux_offres_et_la_valeur_retenue(): void
    {
        $v = $this->valoriser();

        $this->assertSame(85_000, $v->valeur_retenue_fcfa);
        $this->assertSame('Ivoire Export', $v->offre1_fournisseur);
        $this->assertSame(850, $v->offre2_prix_kg_fcfa);
        $this->assertSame($this->direction->id, $v->cree_par);
        $this->assertSame('Prix le plus prudent', $v->motif);
        // Aide à la décision, pas la valeur retenue : 100 kg × 900 = 90 000 ; × 850 = 85 000.
        $this->assertSame(['offre1' => 90_000, 'offre2' => 85_000], ValorisationsStock::valeursDesOffres($v));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function refus(): array
    {
        return [
            'même fournisseur (casse et espaces ignorés)' => [['offre2_fournisseur' => '  ivoire EXPORT '], 'deux fournisseurs différents'],
            'fournisseur vide' => [['offre1_fournisseur' => '  '], 'nommer son fournisseur'],
            'prix nul' => [['offre2_prix_kg_fcfa' => 0], 'supérieur à zéro'],
            'poids nul' => [['poids_g' => 0], 'supérieur à zéro'],
            'valeur négative' => [['valeur_retenue_fcfa' => -1], 'positif ou nul'],
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

        $this->valoriser($surcharge);
    }

    #[Test]
    public function une_offre_datee_du_futur_est_refusee(): void
    {
        $this->expectException(OperationRefusee::class);

        $this->valoriser(['offre1_date' => Carbon::tomorrow()]);
    }

    #[Test]
    public function seule_la_direction_valorise(): void
    {
        foreach ([Role::Comptable, Role::Agent, Role::Investisseur, Role::Agronome] as $role) {
            try {
                $this->valoriser([], User::factory()->role($role)->create());
                $this->fail("Le rôle {$role->value} n'aurait pas dû pouvoir valoriser.");
            } catch (OperationRefusee $e) {
                $this->assertStringContainsString('direction', $e->getMessage());
            }
        }
        $this->assertSame(0, ValorisationStock::query()->count());
    }

    #[Test]
    public function une_valorisation_ne_se_modifie_ni_ne_se_supprime(): void
    {
        $v = $this->valoriser();

        try {
            $v->update(['valeur_retenue_fcfa' => 1]);
            $this->fail('La modification aurait dû être refusée.');
        } catch (RegistreImmuableException) {
            $this->assertSame(85_000, $v->refresh()->valeur_retenue_fcfa);
        }
        $this->expectException(RegistreImmuableException::class);
        $v->delete();
    }

    #[Test]
    public function la_plus_recente_fait_foi_et_l_historique_reste(): void
    {
        $this->valoriser(['valeur_retenue_fcfa' => 85_000]);
        $seconde = $this->valoriser(['valeur_retenue_fcfa' => 70_000]);

        $this->assertSame($seconde->id, ValorisationsStock::courante($this->campagne)->id);
        $this->assertSame(2, ValorisationStock::query()->count());
        $this->assertNull(ValorisationsStock::courante(Campagne::factory()->create()));
    }

    #[Test]
    public function le_resultat_prend_la_valorisation_enregistree_et_zero_sans_elle(): void
    {
        $this->assertSame(0, ResultatCampagne::etat($this->campagne)['valeur_stock_invendu']);

        $this->valoriser(['valeur_retenue_fcfa' => 85_000]);
        $etat = ResultatCampagne::etat($this->campagne);

        $this->assertSame(85_000, $etat['valeur_stock_invendu']);
        $this->assertSame(85_000, $etat['resultat_net']);
        $this->assertNotNull($etat['valorisation']);
        // Une valeur donnée explicitement (simulation) l'emporte, sans rien enregistrer.
        $this->assertSame(1_000, ResultatCampagne::etat($this->campagne, 1_000)['valeur_stock_invendu']);
        $this->assertSame(1, ValorisationStock::query()->count());
    }

    /** Ajoute `$grammes` de stock invendu à la campagne (mouvement brut : on teste le calcul, pas la saisie). */
    private function ajouterStock(int $grammes): void
    {
        $magasin = Magasin::factory()->create();
        $lot = DB::table('lots')->insertGetId([
            'code' => 'L-'.uniqid(), 'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => $magasin->id, 'statut' => 'ouvert', 'cree_par' => $this->direction->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('mouvements_stock')->insert([
            'lot_id' => $lot, 'magasin_id' => $magasin->id, 'type' => 'entree_achat', 'grammes' => $grammes,
            'date_mouvement' => today()->toDateString(), 'cree_par' => $this->direction->id, 'created_at' => now(),
        ]);
    }

    #[Test]
    public function un_stock_invendu_non_valorise_empeche_un_resultat_definitif(): void
    {
        $this->ajouterStock(100_000);
        $etat = ResultatCampagne::etat($this->campagne);

        $conditions = collect(ResultatCampagne::conditionsDeCloture($etat, null))->keyBy('code');

        $this->assertFalse($conditions['stock_valorise']['ok']);
        $this->assertStringContainsString('pas valorisé', $conditions['stock_valorise']['libelle']);

        $this->valoriser();
        $conditions = collect(ResultatCampagne::conditionsDeCloture(ResultatCampagne::etat($this->campagne), null))->keyBy('code');
        $this->assertTrue($conditions['stock_valorise']['ok']);
    }

    #[Test]
    public function sans_stock_invendu_rien_a_valoriser(): void
    {
        $conditions = collect(ResultatCampagne::conditionsDeCloture(ResultatCampagne::etat($this->campagne), null))->keyBy('code');

        $this->assertTrue($conditions['stock_valorise']['ok']);
    }

    #[Test]
    public function une_perte_non_imputee_empeche_un_resultat_definitif_et_le_libelle_le_dit(): void
    {
        $partage = PartageResultat::calculer(-20_000_000, [1 => 2_000_000], 500_000);

        $conditions = collect(ResultatCampagne::conditionsDeCloture(ResultatCampagne::etat($this->campagne), $partage))->keyBy('code');

        $this->assertFalse($conditions['perte_imputee']['ok']);
        $this->assertStringContainsString('Perte non imputée — traitement à décider', $conditions['perte_imputee']['libelle']);
    }

    #[Test]
    public function les_avances_sont_toujours_presentees_a_part(): void
    {
        $conditions = collect(ResultatCampagne::conditionsDeCloture(ResultatCampagne::etat($this->campagne), null))->keyBy('code');

        $this->assertTrue($conditions['avances_presentees']['ok']);
    }
}
