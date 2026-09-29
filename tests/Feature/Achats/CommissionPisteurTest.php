<?php

namespace Tests\Feature\Achats;

use App\Enums\CalculCommissionPisteur;
use App\Enums\CleParametre;
use App\Enums\ModeCommission;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutAchat;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Livewire\Achats\ListeAchats;
use App\Livewire\Referentiels\Pisteurs;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Pisteur;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Achats;
use App\Services\CommissionsPisteur;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Commission des pisteurs (question 6) : la règle de chaque pisteur est vide tant que la direction ne
 * la choisit pas ; le calcul automatique est activé par la direction ; la commission est une somme
 * DUE (achats validés), jamais un paiement.
 */
class CommissionPisteurTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->direction->id,
        ]);
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance achats', $this->direction);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '5000000']);
    }

    private function calcul(CalculCommissionPisteur $mode): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::CalculCommissionPisteur], ['valeur' => $mode->value]);
    }

    private function pisteur(?ModeCommission $mode, ?int $valeur, string $nom = 'Pisteur Traoré'): Pisteur
    {
        return Pisteur::query()->create(['nom' => $nom, 'commission_mode' => $mode, 'commission_valeur' => $valeur]);
    }

    /** @param  array<string, mixed>  $surcharge */
    private function acheterAuPisteur(Pisteur $pisteur, array $surcharge = []): Achat
    {
        return Achats::enregistrer(array_merge([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Pisteur,
            'pisteur_id' => $pisteur->id, 'date_achat' => now()->subMinute(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $surcharge), $this->agent);
    }

    #[Test]
    public function la_commission_par_kilo_se_calcule_en_entiers_au_plus_proche(): void
    {
        $p = $this->pisteur(ModeCommission::ParKg, 15);

        $this->assertSame(7_500, CommissionsPisteur::selonRegle($p, 500_000, 212_500));
        // 333 g × 15 FCFA/kg = 4,995 FCFA → 5.
        $this->assertSame(5, CommissionsPisteur::selonRegle($p, 333, 141));
        // 1 g × 15 = 0,015 → 0 ; 34 g × 15 = 0,51 → 1.
        $this->assertSame(0, CommissionsPisteur::selonRegle($p, 1, 0));
        $this->assertSame(1, CommissionsPisteur::selonRegle($p, 34, 0));
    }

    #[Test]
    public function la_commission_en_pour_mille_du_montant(): void
    {
        $p = $this->pisteur(ModeCommission::Pourcent, 20); // 2 %

        $this->assertSame(4_250, CommissionsPisteur::selonRegle($p, 500_000, 212_500));
        // 2 % de 25 FCFA = 0,5 → 1 (moitié vers le haut) ; 2 % de 24 = 0,48 → 0.
        $this->assertSame(1, CommissionsPisteur::selonRegle($p, 0, 25));
        $this->assertSame(0, CommissionsPisteur::selonRegle($p, 0, 24));
    }

    #[Test]
    public function un_montant_de_plusieurs_milliards_reste_exact(): void
    {
        $p = $this->pisteur(ModeCommission::Pourcent, 25); // 2,5 %

        $this->assertSame(75_000_000, CommissionsPisteur::selonRegle($p, 0, 3_000_000_000));
    }

    #[Test]
    public function sans_regle_complete_pas_de_commission(): void
    {
        $this->calcul(CalculCommissionPisteur::Automatique);

        $this->assertNull(CommissionsPisteur::calculer($this->pisteur(null, null), 500_000, 212_500));
        $this->assertNull(CommissionsPisteur::calculer($this->pisteur(ModeCommission::ParKg, null, 'Mode seul'), 500_000, 212_500));
        $this->assertNull(CommissionsPisteur::calculer(null, 500_000, 212_500));
    }

    #[Test]
    public function tant_que_la_direction_n_a_pas_active_le_calcul_l_achat_n_a_pas_de_commission(): void
    {
        $this->assertSame(CalculCommissionPisteur::Aucun, Parametre::calculCommissionPisteur());
        $p = $this->pisteur(ModeCommission::ParKg, 15);

        $this->assertNull($this->acheterAuPisteur($p)->refresh()->commission_pisteur_fcfa);

        $this->calcul(CalculCommissionPisteur::Aucun);
        $this->assertNull($this->acheterAuPisteur($p)->refresh()->commission_pisteur_fcfa);
    }

    #[Test]
    public function calcul_active_l_achat_au_pisteur_recoit_sa_commission(): void
    {
        $this->calcul(CalculCommissionPisteur::Automatique);
        $p = $this->pisteur(ModeCommission::ParKg, 15);

        $achat = $this->acheterAuPisteur($p)->refresh();

        // 500 kg nets × 15 FCFA/kg.
        $this->assertSame(7_500, $achat->commission_pisteur_fcfa);
        // La commission ne change ni le poids ni le montant payé au vendeur.
        $this->assertSame(212_500, $achat->montant_fcfa);
    }

    #[Test]
    public function seul_un_achat_dont_le_vendeur_est_un_pisteur_porte_une_commission(): void
    {
        $this->calcul(CalculCommissionPisteur::Automatique);
        $this->pisteur(ModeCommission::ParKg, 15);

        $achat = Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => Producteur::factory()->create()->id, 'date_achat' => now()->subMinute(), 'poids_brut_g' => 505_000,
            'tare_g' => 5_000, 'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);

        $this->assertNull($achat->refresh()->commission_pisteur_fcfa);
    }

    #[Test]
    public function la_commission_due_ne_compte_que_les_achats_valides(): void
    {
        $this->calcul(CalculCommissionPisteur::Automatique);
        $p = $this->pisteur(ModeCommission::ParKg, 15);
        $this->acheterAuPisteur($p); // validé (sous le seuil)
        // Un achat au-dessus du seuil reste « à valider » : sa commission n'est pas encore due.
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '1000']);
        $enAttente = $this->acheterAuPisteur($p);

        $this->assertSame(StatutAchat::AValider, $enAttente->refresh()->statut);
        $this->assertSame(7_500, CommissionsPisteur::due($p));
    }

    #[Test]
    public function l_ecran_des_pisteurs_enregistre_une_regle_complete(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(Pisteurs::class)->call('nouveau')
            ->set('donnees.nom', 'Pisteur Koné')->set('donnees.commission_mode', 'par_kg')->set('donnees.commission_valeur', '15')
            ->call('enregistrer')->assertHasNoErrors();

        $p = Pisteur::query()->where('nom', 'Pisteur Koné')->firstOrFail();
        $this->assertSame(ModeCommission::ParKg, $p->commission_mode);
        $this->assertSame(15, $p->commission_valeur);
    }

    #[Test]
    public function l_ecran_refuse_un_mode_sans_valeur_une_valeur_sans_mode_un_decimal_et_plus_de_100_pour_cent(): void
    {
        $this->actingAs($this->direction);
        $ecran = fn (array $d) => tap(Livewire::test(Pisteurs::class)->call('nouveau')->set('donnees.nom', 'Test '.random_int(1, 9999)), function ($e) use ($d) {
            foreach ($d as $champ => $valeur) {
                $e->set('donnees.'.$champ, $valeur);
            }
            $e->call('enregistrer');
        });

        $ecran(['commission_mode' => 'par_kg'])->assertHasErrors(['donnees.commission_valeur']);
        $ecran(['commission_valeur' => '15'])->assertHasErrors(['donnees.commission_valeur']);
        $ecran(['commission_mode' => 'par_kg', 'commission_valeur' => '15,5'])->assertHasErrors(['donnees.commission_valeur']);
        $ecran(['commission_mode' => 'pourcent', 'commission_valeur' => '1001'])->assertHasErrors(['donnees.commission_valeur']);
        $ecran(['commission_mode' => 'pourcent', 'commission_valeur' => '1000'])->assertHasNoErrors();
        $this->assertSame(1, Pisteur::query()->count());
    }

    #[Test]
    public function l_ecran_affiche_la_regle_et_la_commission_due(): void
    {
        $this->calcul(CalculCommissionPisteur::Automatique);
        $p = $this->pisteur(ModeCommission::ParKg, 15, 'Pisteur Affiché');
        $this->pisteur(null, null, 'Pisteur Sans Règle');
        $this->acheterAuPisteur($p);

        $this->actingAs($this->direction);
        Livewire::test(Pisteurs::class)
            ->assertSee('Pisteur Affiché')->assertSee('15 FCFA / kg')->assertSee('Non définie')
            ->assertSee('7'."\u{202F}".'500 FCFA');
    }

    #[Test]
    public function la_liste_des_achats_montre_la_commission_due(): void
    {
        $this->calcul(CalculCommissionPisteur::Automatique);
        $this->acheterAuPisteur($this->pisteur(ModeCommission::ParKg, 15));

        $this->actingAs($this->direction);
        Livewire::test(ListeAchats::class)->assertSee('commission due')->assertSee('7'."\u{202F}".'500 FCFA');
    }

    #[Test]
    public function le_parametre_est_un_choix_et_une_valeur_inconnue_ne_calcule_rien(): void
    {
        $this->assertTrue(CleParametre::CalculCommissionPisteur->estUnChoix());
        $this->assertSame(['aucun', 'automatique'], array_keys(CleParametre::CalculCommissionPisteur->options()));

        Parametre::query()->updateOrCreate(['cle' => CleParametre::CalculCommissionPisteur], ['valeur' => 'peut-etre']);
        $this->assertSame(CalculCommissionPisteur::Aucun, Parametre::calculCommissionPisteur());
    }
}
