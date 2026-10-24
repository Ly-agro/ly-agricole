<?php

namespace Tests\Feature\Tresorerie;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutDepense;
use App\Exceptions\OperationRefusee;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\MouvementTresorerie;
use App\Models\Parametre;
use App\Models\User;
use App\Services\Depenses;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DepensesTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;

    private User $direction;

    private CompteTresorerie $caisse;

    private CategorieDepense $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->caisse = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
        $this->transport = CategorieDepense::query()->create(['nom' => 'Transport']);
        Tresorerie::entree($this->caisse, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Apport', $this->direction);
    }

    private function seuil(?int $montant): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationDepense], ['valeur' => $montant === null ? null : (string) $montant]);
    }

    /** @param  array<string, mixed>  $surcharge */
    private function saisir(int $montant, User $auteur, array $surcharge = []): Depense
    {
        return Depenses::saisir(array_merge([
            'categorie_id' => $this->transport->id,
            'compte_id' => $this->caisse->id,
            'montant_fcfa' => $montant,
            'date_depense' => Carbon::today(),
            'beneficiaire' => 'Transporteur Kouamé',
        ], $surcharge), 'depenses/justificatifs/recu.jpg', $auteur);
    }

    #[Test]
    public function sans_seuil_defini_toute_depense_attend_une_validation(): void
    {
        $this->assertNull(Parametre::entier(CleParametre::SeuilValidationDepense));

        $depense = $this->saisir(1_000, $this->comptable);

        $this->assertSame(StatutDepense::AValider, $depense->statut);
        $this->assertNull($depense->mouvement_id);
        $this->assertSame(5_000_000, $this->caisse->solde());
    }

    #[Test]
    public function sous_le_seuil_la_depense_est_payee_tout_de_suite(): void
    {
        $this->seuil(50_000);

        $depense = $this->saisir(50_000, $this->comptable);

        $this->assertSame(StatutDepense::Payee, $depense->statut);
        $this->assertNull($depense->valide_par);
        $this->assertSame(4_950_000, $this->caisse->solde());
        $mouvement = MouvementTresorerie::query()->findOrFail($depense->mouvement_id);
        $this->assertSame(NatureMouvement::Depense, $mouvement->nature);
        $this->assertSame('depense', $mouvement->source_type);
        $this->assertSame($depense->id, $mouvement->source_id);
    }

    #[Test]
    public function au_dessus_du_seuil_l_auteur_ne_peut_pas_valider_sa_propre_depense(): void
    {
        // Livrable de la semaine 3 et invariant 5 : valide_par ≠ cree_par.
        $this->seuil(50_000);
        $depense = $this->saisir(50_001, $this->comptable);
        $this->assertSame(StatutDepense::AValider, $depense->statut);

        try {
            Depenses::valider($depense, $this->comptable);
            $this->fail('L\'auteur a validé sa propre dépense.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('votre propre dépense', $e->getMessage());
        }

        $this->assertSame(StatutDepense::AValider, $depense->refresh()->statut);
        $this->assertSame(5_000_000, $this->caisse->solde());

        Depenses::valider($depense, $this->direction);

        $depense->refresh();
        $this->assertSame(StatutDepense::Payee, $depense->statut);
        $this->assertSame($this->direction->id, $depense->valide_par);
        $this->assertNotSame($depense->cree_par, $depense->valide_par);
        $this->assertSame(4_949_999, $this->caisse->solde());
    }

    #[Test]
    public function l_auteur_ne_peut_pas_non_plus_refuser_sa_propre_depense(): void
    {
        $depense = $this->saisir(10_000, $this->comptable);

        $this->expectException(OperationRefusee::class);
        Depenses::refuser($depense, $this->comptable, 'Je me ravise');
    }

    #[Test]
    public function un_refus_ne_sort_pas_d_argent_et_garde_son_motif(): void
    {
        $depense = $this->saisir(200_000, $this->comptable);

        Depenses::refuser($depense, $this->direction, 'Pas de devis joint');

        $depense->refresh();
        $this->assertSame(StatutDepense::Refusee, $depense->statut);
        $this->assertSame('Pas de devis joint', $depense->motif_refus);
        $this->assertSame(5_000_000, $this->caisse->solde());
        $this->expectException(OperationRefusee::class);
        Depenses::valider($depense, $this->direction);
    }

    #[Test]
    public function une_depense_ne_se_paie_pas_deux_fois(): void
    {
        $depense = $this->saisir(100_000, $this->comptable);
        Depenses::valider($depense, $this->direction);

        try {
            Depenses::valider($depense, User::factory()->role(Role::Comptable)->create());
            $this->fail('Payée deux fois.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('n\'est plus à valider', $e->getMessage());
        }

        $this->assertSame(4_900_000, $this->caisse->solde());
    }

    #[Test]
    public function la_validation_est_refusee_si_le_compte_n_a_plus_assez(): void
    {
        $depense = $this->saisir(4_000_000, $this->comptable);
        $banque = CompteTresorerie::factory()->create();
        Tresorerie::virement($this->caisse, $banque, 3_000_000, Carbon::today(), 'Dépôt', $this->direction);

        try {
            Depenses::valider($depense, $this->direction);
            $this->fail('Payée à découvert.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('Solde insuffisant', $e->getMessage());
        }

        $this->assertSame(StatutDepense::AValider, $depense->refresh()->statut);
        $this->assertSame(2_000_000, $this->caisse->solde());
    }

    #[Test]
    public function un_validateur_doit_en_avoir_le_droit(): void
    {
        $depense = $this->saisir(10_000, $this->comptable);
        $agent = User::factory()->role(Role::Agent)->create();

        $this->expectException(OperationRefusee::class);
        Depenses::valider($depense, $agent);
    }

    #[Test]
    public function une_charge_exclue_par_l_article_10_3_est_bloquee_sur_les_fonds_de_campagne(): void
    {
        $campagne = Campagne::factory()->create();
        $compteCampagne = CompteTresorerie::factory()->create(['campagne_id' => $campagne->id]);
        Tresorerie::entree($compteCampagne, 1_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds investisseurs', $this->direction);
        $exclue = CategorieDepense::query()->create(['nom' => 'Frais personnels', 'exclue_fonds_campagne' => true]);

        foreach ([
            ['compte_id' => $compteCampagne->id],
            ['campagne_id' => $campagne->id],
        ] as $surcharge) {
            try {
                $this->saisir(10_000, $this->comptable, ['categorie_id' => $exclue->id] + $surcharge);
                $this->fail('Charge exclue acceptée.');
            } catch (OperationRefusee $e) {
                $this->assertStringContainsString('art. 10.3', $e->getMessage());
            }
        }

        // Hors campagne, la même catégorie reste possible.
        $this->assertSame(StatutDepense::AValider, $this->saisir(10_000, $this->comptable, ['categorie_id' => $exclue->id])->statut);
        $this->assertSame(1, Depense::count());
    }

    #[Test]
    public function un_agent_ne_paie_que_depuis_sa_propre_caisse(): void
    {
        $this->seuil(100_000);
        $agent = User::factory()->role(Role::Agent)->create();
        $sienne = CompteTresorerie::factory()->caisseDe($agent)->create();
        Tresorerie::avanceAgent($this->caisse, $sienne, 300_000, Carbon::today(), 'Avance', $this->direction);

        try {
            $this->saisir(5_000, $agent);
            $this->fail('Un agent a payé depuis la caisse centrale.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('sa propre caisse', $e->getMessage());
        }

        $depense = $this->saisir(5_000, $agent, ['compte_id' => $sienne->id]);
        $this->assertSame(StatutDepense::Payee, $depense->statut);
        // Reste à justifier par l'agent = solde de sa caisse.
        $this->assertSame(295_000, $sienne->solde());
    }

    #[Test]
    public function contre_passer_le_paiement_annule_la_depense(): void
    {
        $this->seuil(1_000_000);
        $depense = $this->saisir(80_000, $this->comptable);

        Tresorerie::contrePasser(MouvementTresorerie::query()->findOrFail($depense->mouvement_id), 'Payée sur le mauvais compte', $this->direction);

        $this->assertSame(StatutDepense::Annulee, $depense->refresh()->statut);
        $this->assertSame(5_000_000, $this->caisse->solde());
    }
}
