<?php

namespace Tests\Feature\Rendements;

use App\Enums\Role;
use App\Enums\StatutAchat;
use App\Enums\StatutCampagne;
use App\Livewire\Rendements\ClassementRendements;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Magasin;
use App\Models\Parcelle;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Rendements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RendementsTest extends TestCase
{
    use RefreshDatabase;

    private Campagne $campagne;

    protected function setUp(): void
    {
        parent::setUp();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create();
    }

    /** Prêt accordé (inséré directement : on teste le calcul, pas l'octroi) avec ses parcelles. */
    private function financer(Producteur $producteur, Parcelle ...$parcelles): string
    {
        $auteur = User::factory()->create();
        $id = (string) Str::uuid7();
        DB::table('prets')->insert([
            'id' => $id, 'reference' => 'P-'.Str::random(8), 'producteur_id' => $producteur->id, 'campagne_id' => $this->campagne->id,
            'montant_fcfa' => 100_000, 'forme' => 'especes', 'echeance' => now()->addMonths(3)->toDateString(),
            'statut' => 'decaisse', 'validations_requises' => 1, 'cree_par' => $auteur->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($parcelles as $p) {
            DB::table('pret_parcelle')->insert(['pret_id' => $id, 'parcelle_id' => $p->id]);
        }

        return $id;
    }

    /** Un carré de 100 m sur 100 m : environ un hectare, la surface exacte vient du contour. */
    private function hectare(Producteur $producteur): Parcelle
    {
        return Parcelle::factory()->carre(100)->create(['producteur_id' => $producteur->id]);
    }

    private function livrer(Producteur $producteur, int $grammes, StatutAchat $statut = StatutAchat::Valide): void
    {
        $auteur = User::factory()->create();
        $lot = DB::table('lots')->value('id') ?? DB::table('lots')->insertGetId([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'code' => 'L-'.Str::random(6), 'magasin_id' => Magasin::factory()->create()->id, 'statut' => 'ouvert',
            'cree_par' => $auteur->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $compte = CompteTresorerie::query()->value('id') ?? CompteTresorerie::factory()->create()->id;
        DB::table('achats')->insert([
            'id' => (string) Str::uuid7(), 'reference' => 'A-'.Str::random(8), 'campagne_id' => $this->campagne->id,
            'lot_id' => $lot, 'fournisseur_type' => 'producteur', 'producteur_id' => $producteur->id,
            'date_achat' => now(), 'poids_brut_g' => $grammes, 'tare_g' => 0, 'poids_net_g' => $grammes,
            'prix_kg_fcfa' => 400, 'montant_fcfa' => intdiv($grammes * 400, 1000), 'grammes_rembourses' => 0,
            'montant_especes_fcfa' => 0, 'compte_id' => $compte, 'statut' => $statut->value,
            'cree_par' => $auteur->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function le_rendement_est_des_kilos_livres_par_hectare_finance_en_entiers(): void
    {
        $p = Producteur::factory()->create();
        $this->financer($p, $this->hectare($p));
        $this->livrer($p, 800_000);

        $r = Rendements::classement($this->campagne);

        $this->assertCount(1, $r['classes']);
        $this->assertEqualsWithDelta(800, $r['classes'][0]['kg_par_ha'], 3);
        $this->assertSame($r['classes'][0]['kg_par_ha'], $r['moyenneKgParHa']);
    }

    #[Test]
    public function l_arrondi_kg_par_ha_se_fait_en_entiers_au_plus_proche(): void
    {
        $this->assertSame(800, Rendements::kgParHa(800_000, 10_000));
        $this->assertSame(1_000, Rendements::kgParHa(500_000, 5_000));
        // 1 g sur 3 m² : 3,33 kg/ha → 3 ; 2 g sur 3 m² : 6,67 → 7.
        $this->assertSame(3, Rendements::kgParHa(1, 3));
        $this->assertSame(7, Rendements::kgParHa(2, 3));
        // Au-delà de 2^31 g, sans perte.
        $this->assertSame(3_000_000_000, Rendements::kgParHa(3_000_000_000_000, 10_000));
    }

    #[Test]
    public function seuls_les_achats_valides_comptent(): void
    {
        $p = Producteur::factory()->create();
        $this->financer($p, $this->hectare($p));
        $this->livrer($p, 400_000);
        $this->livrer($p, 900_000, StatutAchat::AValider);
        $this->livrer($p, 900_000, StatutAchat::Refuse);

        $this->assertSame(400_000, Rendements::classement($this->campagne)['classes'][0]['grammes']);
    }

    #[Test]
    public function une_parcelle_financee_par_deux_prets_n_est_comptee_qu_une_fois(): void
    {
        $p = Producteur::factory()->create();
        $parcelle = $this->hectare($p);
        $this->financer($p, $parcelle);
        $this->financer($p, $parcelle);
        $this->livrer($p, 500_000);

        $this->assertSame($parcelle->surface_m2, Rendements::classement($this->campagne)['classes'][0]['surface_m2']);
    }

    #[Test]
    public function sans_contour_releve_pas_de_rendement_invente(): void
    {
        $p = Producteur::factory()->create();
        $this->financer($p, Parcelle::factory()->create(['producteur_id' => $p->id, 'contour' => null]));
        $this->livrer($p, 300_000);

        $r = Rendements::classement($this->campagne);

        $this->assertTrue($r['classes']->isEmpty());
        $this->assertCount(1, $r['sansSurface']);
        $this->assertSame(300_000, $r['sansSurface'][0]['grammes']);
        $this->assertNull($r['moyenneKgParHa']);
    }

    #[Test]
    public function un_producteur_sans_pret_n_est_pas_classe(): void
    {
        $p = Producteur::factory()->create();
        $this->livrer($p, 300_000);

        $r = Rendements::classement($this->campagne);

        $this->assertTrue($r['classes']->isEmpty());
        $this->assertTrue($r['sansSurface']->isEmpty());
    }

    #[Test]
    public function classement_et_20_pour_cent_de_chaque_bout(): void
    {
        foreach ([100, 200, 300, 400, 500, 600, 700, 800, 900, 1000] as $kg) {
            $p = Producteur::factory()->create();
            $this->financer($p, $this->hectare($p));
            $this->livrer($p, $kg * 1000);
        }

        $classes = Rendements::classement($this->campagne)['classes'];

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9, 10], $classes->pluck('rang')->all());
        $this->assertSame(['meilleurs', 'meilleurs'], $classes->take(2)->pluck('groupe')->all());
        $this->assertSame(['moins_bons', 'moins_bons'], $classes->take(-2)->pluck('groupe')->all());
        $this->assertSame(6, $classes->where('groupe', 'milieu')->count());
    }

    #[Test]
    public function sous_cinq_producteurs_aucun_groupe_n_est_etiquete(): void
    {
        foreach ([100, 200, 300] as $kg) {
            $p = Producteur::factory()->create();
            $this->financer($p, $this->hectare($p));
            $this->livrer($p, $kg * 1000);
        }

        $groupes = Rendements::classement($this->campagne)['classes']->pluck('groupe')->unique()->all();

        $this->assertSame(['milieu'], $groupes);
    }

    #[Test]
    public function la_moyenne_est_ponderee_par_la_surface(): void
    {
        $petit = Producteur::factory()->create();
        $this->financer($petit, $this->hectare($petit));
        $this->livrer($petit, 1_000_000);
        $grand = Producteur::factory()->create();
        $this->financer($grand, $this->hectare($grand), $this->hectare($grand), $this->hectare($grand));
        $this->livrer($grand, 1_000_000);

        // 2 000 kg sur environ 4 ha = environ 500 kg/ha, pas la moyenne simple (environ 667).
        $this->assertEqualsWithDelta(500, Rendements::classement($this->campagne)['moyenneKgParHa'], 5);
    }

    /** @return array<string, array{Role, bool}> */
    public static function roles(): array
    {
        return [
            'direction' => [Role::Direction, true], 'comptable' => [Role::Comptable, true],
            'agent' => [Role::Agent, false], 'investisseur' => [Role::Investisseur, false],
        ];
    }

    #[Test]
    #[DataProvider('roles')]
    public function l_acces_suit_le_role(Role $role, bool $autorise): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/rendements')->assertStatus($autorise ? 200 : 403);
    }

    #[Test]
    public function l_ecran_affiche_le_classement_et_l_avertissement_sans_surface(): void
    {
        $p = Producteur::factory()->create(['nom' => 'Kone Classe']);
        $this->financer($p, $this->hectare($p));
        $this->livrer($p, 800_000);
        $q = Producteur::factory()->create(['nom' => 'Sans Contour']);
        $this->financer($q, Parcelle::factory()->create(['producteur_id' => $q->id, 'contour' => null]));
        $this->livrer($q, 100_000);

        $this->actingAs(User::factory()->role(Role::Direction)->create());
        Livewire::test(ClassementRendements::class)
            ->assertSee('Kone Classe')->assertSee('kg/ha')
            ->assertSee('Sans Contour')->assertSee('aucune parcelle financée avec contour relevé');
    }
}
