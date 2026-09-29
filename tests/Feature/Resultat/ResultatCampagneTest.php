<?php

namespace Tests\Feature\Resultat;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeAcheteur;
use App\Enums\TypeFournisseur;
use App\Livewire\Resultat\ResultatDeCampagne;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Encaissement;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Vente;
use App\Services\Achats;
use App\Services\Apports;
use App\Services\Depenses;
use App\Services\Encaissements;
use App\Services\Prets;
use App\Services\ResultatCampagne;
use App\Services\Tresorerie;
use App\Services\Ventes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Résultat net provisoire (contrat art. 10 et 11) lu dans les registres, puis son
 * partage (art. 12 à 14) sur l'écran de la direction.
 */
class ResultatCampagneTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    private CompteTresorerie $compteDedie;

    private User $fondsA;

    private User $fondsB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->fondsA = User::factory()->role(Role::Investisseur)->create(['nom' => 'Fonds A']);
        $this->fondsB = User::factory()->role(Role::Investisseur)->create(['nom' => 'Fonds B']);
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        $this->compteDedie = CompteTresorerie::factory()->create(['campagne_id' => $this->campagne->id, 'nom' => 'Fonds campagne']);
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance achats', $this->direction);
        foreach ([CleParametre::SeuilValidationAchat, CleParametre::SeuilValidationVente, CleParametre::SeuilValidationDepense, CleParametre::SeuilValidationPret] as $cle) {
            Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => '1000000000']);
        }
    }

    /** Apports de l'exemple de l'art. 14 : 2 M + 8 M d'investisseurs = 10 M ; LY 2,5 M. */
    private function apports(): void
    {
        Apports::enregistrer($this->fondsA->id, $this->campagne->id, $this->compteDedie->id, 2_000_000, Carbon::today(), $this->direction);
        Apports::enregistrer($this->fondsB->id, $this->campagne->id, $this->compteDedie->id, 8_000_000, Carbon::today(), $this->direction);
        Apports::enregistrer(null, $this->campagne->id, $this->compteDedie->id, 2_500_000, Carbon::today(), $this->direction);
    }

    private function acheter(int $netG = 500_000, int $prix = 425): void
    {
        Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id,
            'fournisseur_type' => TypeFournisseur::Producteur, 'producteur_id' => Producteur::factory()->create()->id,
            'date_achat' => now()->subDay(), 'poids_brut_g' => $netG + 5_000, 'tare_g' => 5_000,
            'prix_kg_fcfa' => $prix, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);
    }

    private function vendre(int $netG = 400_000, int $prix = 900): Vente
    {
        return Ventes::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'type_acheteur' => TypeAcheteur::Exportateur,
            'acheteur_nom' => 'Ivoire Export SA', 'date_vente' => now()->subMinute(), 'poids_net_g' => $netG, 'prix_kg_fcfa' => $prix,
        ], $this->direction);
    }

    private function encaisser(Vente $vente, int $montant): Encaissement
    {
        return Encaissements::encaisser($vente, $this->compteDedie, $montant, Carbon::today(), $this->comptable);
    }

    private function depenser(int $montant, string $categorie = 'Transport'): void
    {
        Depenses::saisir([
            'categorie_id' => CategorieDepense::query()->firstOrCreate(['nom' => $categorie])->id,
            'compte_id' => $this->caisseAgent->id, 'montant_fcfa' => $montant, 'date_depense' => Carbon::today(),
            'beneficiaire' => 'Transporteur', 'campagne_id' => $this->campagne->id,
        ], 'depenses/j.jpg', $this->agent);
    }

    /** Scénario chiffré : 500 kg achetés à 425 (212 500), 400 kg vendus à 900 (360 000) encaissés, 30 000 de transport. */
    private function scenario(): void
    {
        $this->acheter();
        $this->encaisser($this->vendre(), 360_000);
        $this->depenser(30_000);
    }

    #[Test]
    public function le_resultat_est_recettes_moins_achats_moins_depenses(): void
    {
        $this->scenario();

        $etat = ResultatCampagne::etat($this->campagne);

        $this->assertSame(360_000, $etat['recettes']);
        $this->assertSame(212_500, $etat['charges']['achats']);
        $this->assertSame([['categorie' => 'Transport', 'montant' => 30_000]], $etat['charges']['depenses']);
        $this->assertSame(242_500, $etat['charges']['total']);
        $this->assertSame(0, $etat['valeur_stock_invendu']);
        $this->assertSame(117_500, $etat['resultat_net']);
        $this->assertSame(100_000, $etat['info']['stock_invendu_g']);
    }

    #[Test]
    public function la_valeur_du_stock_invendu_est_ajoutee_quand_la_direction_la_donne(): void
    {
        $this->scenario();

        $etat = ResultatCampagne::etat($this->campagne, 100_000);

        $this->assertSame(100_000, $etat['valeur_stock_invendu']);
        $this->assertSame(217_500, $etat['resultat_net']);
    }

    #[Test]
    public function seules_les_recettes_encaissees_comptent_et_une_contre_passation_les_retire(): void
    {
        $vente = $this->vendreEnRemplissant();
        $this->assertSame(0, ResultatCampagne::etat($this->campagne)['recettes'], 'Vente non encaissée : pas de recette (art. 10.1).');

        $encaissement = $this->encaisser($vente, 100_000);
        $this->assertSame(100_000, ResultatCampagne::etat($this->campagne)['recettes']);

        Encaissements::contrePasser($encaissement, 'Erreur de saisie du montant', $this->comptable);
        $this->assertSame(0, ResultatCampagne::etat($this->campagne)['recettes']);
    }

    private function vendreEnRemplissant(): Vente
    {
        $this->acheter();

        return $this->vendre();
    }

    #[Test]
    public function un_achat_ou_une_depense_pas_encore_valides_ne_sont_pas_des_charges(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '1000']);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationDepense], ['valeur' => '1000']);

        $this->acheter();
        $this->depenser(30_000);

        $etat = ResultatCampagne::etat($this->campagne);
        $this->assertSame(0, $etat['charges']['achats']);
        $this->assertSame([], $etat['charges']['depenses']);
        $this->assertSame(0, $etat['resultat_net']);
    }

    #[Test]
    public function une_categorie_exclue_par_l_article_10_3_n_est_jamais_comptee(): void
    {
        $exclue = CategorieDepense::query()->create(['nom' => 'Frais de structure', 'exclue_fonds_campagne' => true]);
        // Le service refuse cette saisie ; on force la ligne pour prouver que le calcul l'ignorerait quand même.
        DB::table('depenses')->insert([
            'id' => (string) Str::uuid7(), 'categorie_id' => $exclue->id, 'compte_id' => $this->caisseAgent->id,
            'montant_fcfa' => 999_999, 'date_depense' => today()->toDateString(), 'beneficiaire' => 'Propriétaire', 'justificatif' => 'x.jpg',
            'campagne_id' => $this->campagne->id, 'statut' => 'payee', 'cree_par' => $this->agent->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, ResultatCampagne::etat($this->campagne)['charges']['total']);
    }

    #[Test]
    public function une_autre_campagne_n_est_pas_melangee(): void
    {
        $this->scenario();
        $autre = Campagne::factory()->create();

        $etat = ResultatCampagne::etat($autre);

        $this->assertSame(0, $etat['recettes']);
        $this->assertSame(0, $etat['charges']['total']);
        $this->assertSame(0, $etat['info']['stock_invendu_g']);
    }

    #[Test]
    public function les_avances_non_remboursees_sortent_en_information_sans_toucher_au_resultat(): void
    {
        $centrale = CompteTresorerie::query()->whereNull('titulaire_id')->whereNull('campagne_id')->firstOrFail();
        $pret = Prets::demander([
            'producteur_id' => Producteur::factory()->create()->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => 300_000,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, ['compte_id' => $centrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => 300_000, 'date' => Carbon::today()], $this->comptable, 'prets/recus/r.jpg');

        $etat = ResultatCampagne::etat($this->campagne);

        $this->assertSame(300_000, $etat['info']['avances_non_remboursees']);
        $this->assertSame(0, $etat['resultat_net']);
    }

    #[Test]
    public function l_ecran_montre_le_partage_de_l_exemple_de_l_article_14_a_partir_des_registres(): void
    {
        $this->apports();
        $this->scenario(); // résultat net de 117 500

        $this->actingAs($this->direction);
        // 40 % de 117 500 = 47 000 ; LY : 70 500 ; Fonds A (20 %) : 9 400 ⇒ 2 009 400.
        Livewire::test(ResultatDeCampagne::class)
            ->assertSee('Résultat provisoire')
            ->assertSee('117'.self::FINE.'500'.' FCFA')
            ->assertSee('47'.self::FINE.'000'.' FCFA')
            ->assertSee('70'.self::FINE.'500'.' FCFA')
            ->assertSee('Fonds A')
            ->assertSee('2'.self::FINE.'009'.self::FINE.'400'.' FCFA');
    }

    #[Test]
    public function l_ecran_prend_la_valeur_du_stock_et_refuse_un_montant_ambigu(): void
    {
        $this->apports();
        $this->scenario();
        $this->actingAs($this->direction);

        Livewire::test(ResultatDeCampagne::class)->set('valeurStock', '100 000')
            ->assertSee('217'.self::FINE.'500'.' FCFA');
        Livewire::test(ResultatDeCampagne::class)->set('valeurStock', '1.500')
            ->assertSee('sans virgule ni point');
    }

    #[Test]
    public function une_perte_se_partage_et_la_faute_de_gestion_la_met_sur_ly(): void
    {
        $this->apports();
        $this->acheter(); // 212 500 de charges, aucune recette : résultat = − 212 500

        $this->actingAs($this->direction);
        // Investisseurs : 212 500 × 10 M ÷ 12,5 M = 170 000 ; LY 42 500 ; Fonds A (20 %) : 34 000 ⇒ 1 966 000.
        Livewire::test(ResultatDeCampagne::class)
            ->assertSee('Part de perte des investisseurs')
            ->assertSee('170'.self::FINE.'000'.' FCFA')
            ->assertSee('1'.self::FINE.'966'.self::FINE.'000'.' FCFA')
            ->set('fauteLy', true)
            ->assertSee('212'.self::FINE.'500'.' FCFA') // toute la perte chez LY
            ->assertSee('2'.self::FINE.'000'.self::FINE.'000'.' FCFA'); // capital intégralement rendu
    }

    #[Test]
    public function sans_apport_d_investisseur_l_ecran_l_explique(): void
    {
        $this->scenario();
        $this->actingAs($this->direction);

        Livewire::test(ResultatDeCampagne::class)->assertSee('Aucun apport')->assertSeeHtml("Enregistrez d'abord les apports");
    }

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'comptable' => [Role::Comptable, 200], 'agent' => [Role::Agent, 403],
            'agronome' => [Role::Agronome, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function l_acces_suit_le_role(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/resultat')->assertStatus($attendu);
    }
}
