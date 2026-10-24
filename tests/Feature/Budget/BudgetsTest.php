<?php

namespace Tests\Feature\Budget;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\PosteBudget;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\TypeFournisseur;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\JournalActivite;
use App\Models\LigneBudget;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Achats;
use App\Services\Budgets;
use App\Services\Depenses;
use App\Services\Prets;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BudgetsTest extends TestCase
{
    use RefreshDatabase;

    private User $direction;

    private User $comptable;

    private User $agent;

    private Campagne $campagne;

    private CompteTresorerie $caisse;

    private CategorieDepense $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->caisse = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
        $this->transport = CategorieDepense::query()->create(['nom' => 'Transport']);
        Tresorerie::entree($this->caisse, 30_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
    }

    private function seuil(CleParametre $cle, int $valeur): void
    {
        Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => (string) $valeur]);
    }

    private function refusAttendu(callable $tentative, string $message): void
    {
        try {
            $tentative();
            $this->fail("Aucun refus, « $message » attendu.");
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    private function depenser(int $montant, ?int $campagneId = null, ?CategorieDepense $categorie = null): void
    {
        Depenses::saisir([
            'categorie_id' => ($categorie ?? $this->transport)->id,
            'compte_id' => $this->caisse->id,
            'montant_fcfa' => $montant,
            'date_depense' => Carbon::today(),
            'beneficiaire' => 'Transporteur',
            'campagne_id' => $campagneId ?? $this->campagne->id,
        ], 'depenses/justificatifs/recu.jpg', $this->comptable);
    }

    /** @return array<string, mixed> */
    private function ligneSuivi(string $cle): array
    {
        $ligne = Budgets::suivi($this->campagne)['lignes']->firstWhere('cle', $cle);
        $this->assertNotNull($ligne, "Ligne « $cle » absente du suivi.");

        return $ligne;
    }

    #[Test]
    public function la_direction_prevoit_un_poste_puis_le_modifie_sans_creer_de_doublon(): void
    {
        Budgets::definir($this->campagne, PosteBudget::Categorie, $this->transport->id, 500_000, $this->direction, 'Camions');
        Budgets::definir($this->campagne, PosteBudget::Categorie, $this->transport->id, 650_000, $this->direction);
        Budgets::definir($this->campagne, PosteBudget::Achats, null, 17_000_000, $this->direction);
        Budgets::definir($this->campagne, PosteBudget::Achats, null, 18_000_000, $this->direction);

        $this->assertSame(2, LigneBudget::query()->count());
        $ligne = LigneBudget::query()->where('categorie_id', $this->transport->id)->sole();
        $this->assertSame(650_000, $ligne->montant_fcfa);
        $this->assertSame($this->direction->id, $ligne->modifie_par);
        $this->assertSame(18_000_000, LigneBudget::query()->where('poste', 'achats')->sole()->montant_fcfa);

        // L'ancien montant reste lisible au journal.
        $modification = JournalActivite::query()->where('objet_type', 'ligne_budget')->where('objet_id', (string) $ligne->id)
            ->where('action', 'modification')->sole();
        $this->assertSame(500_000, (int) $modification->avant['montant_fcfa']);
    }

    #[Test]
    public function seule_la_direction_fixe_le_budget(): void
    {
        foreach ([$this->comptable, $this->agent, User::factory()->role(Role::Admin)->create()] as $auteur) {
            $this->refusAttendu(fn () => Budgets::definir($this->campagne, PosteBudget::Achats, null, 1_000, $auteur), 'ne permet pas');
        }
        $this->assertSame(0, LigneBudget::query()->count());
    }

    #[Test]
    public function une_campagne_cloturee_a_un_budget_fige(): void
    {
        $this->campagne->update(['statut' => StatutCampagne::Cloturee]);

        $this->refusAttendu(fn () => Budgets::definir($this->campagne, PosteBudget::Achats, null, 1_000, $this->direction), 'clôturée');
    }

    #[Test]
    public function une_categorie_exclue_des_fonds_de_campagne_ne_se_budgete_pas(): void
    {
        $exclue = CategorieDepense::query()->create(['nom' => 'Frais personnels', 'exclue_fonds_campagne' => true]);

        $this->refusAttendu(fn () => Budgets::definir($this->campagne, PosteBudget::Categorie, $exclue->id, 1_000, $this->direction), 'art. 10.3');
        $this->refusAttendu(fn () => Budgets::definir($this->campagne, PosteBudget::Categorie, null, 1_000, $this->direction), 'catégorie');
        $this->refusAttendu(fn () => Budgets::definir($this->campagne, PosteBudget::Achats, null, -1, $this->direction), 'négatif');
    }

    #[Test]
    public function le_reel_des_depenses_compte_les_payees_de_la_campagne_et_montre_celles_en_attente(): void
    {
        $this->seuil(CleParametre::SeuilValidationDepense, 100_000);
        Budgets::definir($this->campagne, PosteBudget::Categorie, $this->transport->id, 100_000, $this->direction);

        $this->depenser(60_000);                     // payée tout de suite
        $this->depenser(70_000);                     // payée : le poste est dépassé
        $this->depenser(250_000);                    // au-dessus du seuil : à valider
        $autre = Campagne::factory()->create();
        $this->depenser(40_000, $autre->id);         // autre campagne : ne compte pas ici

        $ligne = $this->ligneSuivi("categorie-{$this->transport->id}");
        $this->assertSame(100_000, $ligne['prevu']);
        $this->assertSame(130_000, $ligne['reel']);
        $this->assertSame(250_000, $ligne['en_attente']);
        $this->assertSame(-30_000, $ligne['ecart']);
        $this->assertSame(1300, $ligne['consomme_pour_mille']);
    }

    #[Test]
    public function une_categorie_depensee_sans_budget_apparait_en_non_prevu(): void
    {
        $this->seuil(CleParametre::SeuilValidationDepense, 1_000_000);
        $carburant = CategorieDepense::query()->create(['nom' => 'Carburant']);
        $this->depenser(45_000, categorie: $carburant);

        $ligne = $this->ligneSuivi("categorie-{$carburant->id}");
        $this->assertNull($ligne['prevu']);
        $this->assertNull($ligne['ecart']);
        $this->assertSame(45_000, $ligne['reel']);
    }

    #[Test]
    public function le_reel_des_achats_est_l_argent_paye_pas_la_part_retenue_sur_un_pret(): void
    {
        $this->seuil(CleParametre::SeuilValidationAchat, 10_000_000);
        $lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => 'ouvert', 'cree_par' => $this->comptable->id,
        ]);

        // 500 kg à 425 F/kg = 212 500 FCFA, payés en espèces.
        Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $lot->id,
            'fournisseur_type' => TypeFournisseur::Producteur, 'producteur_id' => Producteur::factory()->create()->id,
            'date_achat' => now()->subDay(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'prix_kg_fcfa' => 425, 'compte_id' => $this->caisse->id,
        ], $this->comptable);

        $ligne = $this->ligneSuivi('achats');
        $this->assertNull($ligne['prevu']);
        $this->assertSame(212_500, $ligne['reel']);
    }

    #[Test]
    public function le_reel_des_prets_est_l_argent_decaisse_hors_contre_passations(): void
    {
        $this->seuil(CleParametre::SeuilValidationPret, 10_000_000);
        Budgets::definir($this->campagne, PosteBudget::Prets, null, 5_000_000, $this->direction);

        $decaisser = function (int $montant) {
            $pret = Prets::valider(Prets::demander([
                'producteur_id' => Producteur::factory()->create()->id,
                'campagne_id' => $this->campagne->id,
                'montant_fcfa' => $montant,
                'forme' => FormePret::Especes,
                'echeance' => Carbon::today()->addMonths(6),
            ], $this->agent), $this->direction);

            return Prets::decaisser($pret, [
                'compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes,
                'montant_fcfa' => $montant, 'date' => Carbon::today(),
            ], $this->comptable, 'prets/recus/recu.jpg');
        };

        $decaisser(2_000_000);
        $errone = $decaisser(1_500_000);
        Tresorerie::contrePasser($errone->mouvement, 'Versé au mauvais producteur', $this->comptable);

        $ligne = $this->ligneSuivi('prets');
        $this->assertSame(2_000_000, $ligne['reel']);
        $this->assertSame(3_000_000, $ligne['ecart']);
        $this->assertSame(400, $ligne['consomme_pour_mille']);
    }

    #[Test]
    public function les_totaux_additionnent_les_postes_et_ignorent_le_non_prevu(): void
    {
        $this->seuil(CleParametre::SeuilValidationDepense, 1_000_000);
        Budgets::definir($this->campagne, PosteBudget::Achats, null, 3_000_000, $this->direction);
        Budgets::definir($this->campagne, PosteBudget::Categorie, $this->transport->id, 200_000, $this->direction);
        $this->depenser(80_000);
        $this->depenser(20_000, categorie: CategorieDepense::query()->create(['nom' => 'Divers']));

        $suivi = Budgets::suivi($this->campagne);
        $this->assertSame(3_200_000, $suivi['prevu']);
        $this->assertSame(100_000, $suivi['reel']);
        $this->assertSame(0, $suivi['en_attente']);
    }
}
