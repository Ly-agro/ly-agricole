<?php

namespace Tests\Feature\Prets;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Exceptions\OperationRefusee;
use App\Exceptions\RegistreImmuableException;
use App\Livewire\Fiabilite\FicheFiabilite;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\DecisionPlafond;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Services\DecisionsPlafond;
use App\Services\Prets;
use App\Services\Remboursements;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Décision de la direction sur le plafond d'un producteur (question 35) : le logiciel propose,
 * la direction décide, tout est tracé (proposition, données, date du calcul, décision, auteur).
 */
class DecisionPlafondTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private CompteTresorerie $caisse;

    private Producteur $producteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->caisse = CompteTresorerie::factory()->create();
        Tresorerie::entree($this->caisse, 50_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationPret], ['valeur' => '1000000000']);
        $this->producteur = Producteur::factory()->create(['nom' => 'Koné', 'prenoms' => 'Awa']);
    }

    /** Prêt de 300 000 versé puis soldé à temps : le logiciel proposera 300 000. */
    private function preterEtSolder(): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => $this->producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => 300_000,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, ['compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => 300_000, 'date' => Carbon::today()], $this->comptable, 'prets/recus/r.jpg');
        Remboursements::especes($pret->refresh(), $this->caisse, 300_000, Carbon::today(), $this->comptable);

        return $pret->refresh();
    }

    #[Test]
    public function la_direction_confirme_la_proposition_sans_motif_et_tout_est_garde(): void
    {
        $pret = $this->preterEtSolder();

        $d = DecisionsPlafond::enregistrer($this->producteur, 300_000, null, $this->campagne, $this->direction);

        $this->assertSame(300_000, $d->plafond_propose_fcfa);
        $this->assertSame(300_000, $d->plafond_retenu_fcfa);
        $this->assertNull($d->motif);
        $this->assertSame($this->direction->id, $d->cree_par);
        $this->assertSame($this->campagne->id, $d->campagne_id);
        $this->assertNotEmpty($d->raison_proposition);
        $this->assertNotNull($d->calcule_at);
        // Les données utilisées sont figées : synthèse et détail des prêts.
        $this->assertSame(1, $d->donnees['synthese']['nb_prets']);
        $this->assertSame($pret->reference, $d->donnees['prets'][0]['reference']);
        $this->assertTrue($d->donnees['prets'][0]['a_temps']);
        $this->assertSame(300_000, $d->donnees['prets'][0]['remis_fcfa']);
    }

    #[Test]
    public function decider_autre_chose_que_la_proposition_exige_un_motif(): void
    {
        $this->preterEtSolder();

        try {
            DecisionsPlafond::enregistrer($this->producteur, 400_000, null, null, $this->direction);
            $this->fail('Un motif aurait dû être exigé.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('diffère de la proposition', $e->getMessage());
        }
        try {
            DecisionsPlafond::enregistrer($this->producteur, 400_000, 'ok', null, $this->direction);
            $this->fail('Un motif trop court aurait dû être refusé.');
        } catch (OperationRefusee) {
            $this->assertSame(0, DecisionPlafond::query()->count());
        }

        $d = DecisionsPlafond::enregistrer($this->producteur, 400_000, 'Bonne récolte, garant connu', null, $this->direction);
        $this->assertSame(400_000, $d->plafond_retenu_fcfa);
        $this->assertSame(300_000, $d->plafond_propose_fcfa, 'La proposition du logiciel est gardée à côté de la décision.');
    }

    #[Test]
    public function sans_proposition_un_plafond_exige_un_motif_et_zero_est_permis(): void
    {
        // Aucun historique : le logiciel ne propose rien.
        try {
            DecisionsPlafond::enregistrer($this->producteur, 150_000, null, null, $this->direction);
            $this->fail('Un motif aurait dû être exigé.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('Aucun plafond n\'est proposé', $e->getMessage());
        }

        $d = DecisionsPlafond::enregistrer($this->producteur, 150_000, 'Premier prêt, petit montant', null, $this->direction);
        $this->assertNull($d->plafond_propose_fcfa);
        $this->assertSame(150_000, $d->plafond_retenu_fcfa);
    }

    #[Test]
    public function le_plafond_des_parametres_borne_la_decision(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::PlafondPretProducteur], ['valeur' => '400000']);

        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessage('plafond par producteur des Paramètres');

        DecisionsPlafond::enregistrer($this->producteur, 500_000, 'Décision exceptionnelle', null, $this->direction);
    }

    #[Test]
    public function un_montant_negatif_est_refuse(): void
    {
        $this->expectException(OperationRefusee::class);

        DecisionsPlafond::enregistrer($this->producteur, -1, 'Motif suffisant', null, $this->direction);
    }

    #[Test]
    public function seule_la_direction_decide(): void
    {
        foreach ([Role::Comptable, Role::Agent, Role::Investisseur, Role::Agronome] as $role) {
            try {
                DecisionsPlafond::enregistrer($this->producteur, 0, 'Motif suffisant', null, User::factory()->role($role)->create());
                $this->fail("Le rôle {$role->value} n'aurait pas dû pouvoir décider.");
            } catch (OperationRefusee $e) {
                $this->assertStringContainsString('direction', $e->getMessage());
            }
        }
        $this->assertSame(0, DecisionPlafond::query()->count());
    }

    #[Test]
    public function une_decision_est_immuable_et_la_plus_recente_est_en_tete(): void
    {
        $premiere = DecisionsPlafond::enregistrer($this->producteur, 0, 'Pas de nouveau prêt', null, $this->direction);
        $seconde = DecisionsPlafond::enregistrer($this->producteur, 100_000, 'Situation régularisée', null, $this->direction);

        $historique = DecisionsPlafond::historique($this->producteur);

        $this->assertSame([$seconde->id, $premiere->id], $historique->pluck('id')->all());
        $this->expectException(RegistreImmuableException::class);
        $premiere->update(['plafond_retenu_fcfa' => 999]);
    }

    #[Test]
    public function la_fiche_montre_le_formulaire_a_la_direction_seule_et_l_historique_a_tous_les_lecteurs(): void
    {
        $this->preterEtSolder();
        DecisionsPlafond::enregistrer($this->producteur, 300_000, null, null, $this->direction);

        $this->actingAs($this->comptable);
        Livewire::test(FicheFiabilite::class, ['producteur' => $this->producteur])
            ->assertSee('Décision de la direction')->assertSee('en vigueur')->assertSee('Règle provisoire')
            ->assertDontSee('Enregistrer une décision')
            ->call('ouvrirDecision')->assertForbidden();

        $this->actingAs($this->direction);
        Livewire::test(FicheFiabilite::class, ['producteur' => $this->producteur])
            ->assertSee('Enregistrer une décision')
            ->call('ouvrirDecision')->assertSet('plafondRetenu', '300000')
            ->set('plafondRetenu', '0')->set('motifDecision', 'Retard récurrent')
            ->call('decider')->assertHasNoErrors()->assertSee('Décision enregistrée');

        $this->assertSame(0, DecisionsPlafond::historique($this->producteur)->first()->plafond_retenu_fcfa);
    }

    #[Test]
    public function la_fiche_refuse_une_decision_sans_motif_avec_un_message(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(FicheFiabilite::class, ['producteur' => $this->producteur])
            ->call('ouvrirDecision')->set('plafondRetenu', '150000')->call('decider')
            ->assertHasErrors(['plafondRetenu']);
        $this->assertSame(0, DecisionPlafond::query()->count());
    }
}
