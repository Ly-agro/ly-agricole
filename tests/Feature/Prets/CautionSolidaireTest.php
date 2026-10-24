<?php

namespace Tests\Feature\Prets;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\RegleCautionSolidaire;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Exceptions\OperationRefusee;
use App\Livewire\Fiabilite\ListeGroupes;
use App\Livewire\Fiabilite\SituationGroupe;
use App\Livewire\Prets\FormulairePret;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\GroupeProducteur;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Services\CautionSolidaire;
use App\Services\Prets;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Caution solidaire d'un groupe (cahier §10, question 37) : inactive tant que la direction
 * n'a rien choisi ; « avertir » signale, « bloquer » refuse un prêt quand un AUTRE membre du
 * groupe a un prêt en retard.
 */
class CautionSolidaireTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private CompteTresorerie $caisse;

    private GroupeProducteur $groupe;

    private Producteur $awa;

    private Producteur $issa;

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

        $this->groupe = GroupeProducteur::factory()->create(['nom' => 'Groupe Espoir']);
        $this->awa = Producteur::factory()->create(['nom' => 'Koné', 'prenoms' => 'Awa', 'village_id' => $this->groupe->village_id, 'groupe_id' => $this->groupe->id]);
        $this->issa = Producteur::factory()->create(['nom' => 'Ouattara', 'prenoms' => 'Issa', 'village_id' => $this->groupe->village_id, 'groupe_id' => $this->groupe->id]);
    }

    private function regle(RegleCautionSolidaire $regle): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::CautionSolidaire], ['valeur' => $regle->value]);
    }

    /** Prêt versé à `$producteur`, échéance dans un mois. */
    private function pretVerse(Producteur $producteur, int $montant = 300_000): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => $producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => $montant,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonth(),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, ['compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => $montant, 'date' => Carbon::today()], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    /** Fait passer l'échéance du prêt d'Awa : elle est en retard. */
    private function awaEnRetard(): void
    {
        $this->pretVerse($this->awa);
        Carbon::setTestNow(Carbon::today()->addMonths(2));
    }

    private function demandeIssa(): Pret
    {
        return Prets::demander([
            'producteur_id' => $this->issa->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => 200_000,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(3),
        ], $this->agent);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function sans_choix_de_la_direction_aucune_regle_ne_s_applique(): void
    {
        $this->assertSame(RegleCautionSolidaire::Aucune, Parametre::regleCautionSolidaire());
        $this->awaEnRetard();

        $this->assertSame(CautionSolidaire::AUCUN, CautionSolidaire::controle($this->issa)['niveau']);
        $this->assertSame($this->issa->id, $this->demandeIssa()->producteur_id);
    }

    #[Test]
    public function avertir_signale_sans_rien_refuser_et_sans_nommer_personne(): void
    {
        $this->regle(RegleCautionSolidaire::Avertir);
        $this->awaEnRetard();

        $controle = CautionSolidaire::controle($this->issa);

        $this->assertSame(CautionSolidaire::AVERTISSEMENT, $controle['niveau']);
        $this->assertStringContainsString('Un autre membre du groupe a un prêt en retard', $controle['message']);
        $this->assertStringNotContainsString('Awa', $controle['message']);
        $this->assertStringNotContainsString('Koné', $controle['message']);
        $this->assertNotNull($this->demandeIssa());
    }

    #[Test]
    public function bloquer_refuse_la_demande_avec_un_message_generique(): void
    {
        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->awaEnRetard();

        try {
            $this->demandeIssa();
            $this->fail('La demande aurait dû être refusée.');
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString('caution solidaire', $e->getMessage());
            $this->assertStringNotContainsString('Awa', $e->getMessage());
        }
        $this->assertSame(1, Pret::query()->count(), 'Seul le prêt d\'Awa existe.');
    }

    #[Test]
    public function bloquer_refuse_aussi_la_validation_d_une_demande_faite_avant_le_retard(): void
    {
        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->pretVerse($this->awa);
        $demande = $this->demandeIssa(); // faite avant le retard d'Awa
        Carbon::setTestNow(Carbon::today()->addMonths(2)); // Awa passe en retard

        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessage('caution solidaire');

        Prets::valider($demande, $this->direction);
    }

    #[Test]
    public function un_prêt_a_l_heure_ou_soldé_ne_declenche_rien(): void
    {
        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->pretVerse($this->awa); // pas encore échu

        $this->assertSame(CautionSolidaire::AUCUN, CautionSolidaire::controle($this->issa)['niveau']);
        $this->assertNotNull($this->demandeIssa());
    }

    #[Test]
    public function son_propre_retard_ne_le_bloque_pas_par_la_caution_du_groupe(): void
    {
        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->awaEnRetard();

        // La règle vise les AUTRES membres : Awa n'est pas bloquée par son propre retard.
        $this->assertSame(CautionSolidaire::AUCUN, CautionSolidaire::controle($this->awa)['niveau']);
    }

    #[Test]
    public function un_producteur_sans_groupe_ou_d_un_autre_groupe_n_est_pas_concerne(): void
    {
        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->awaEnRetard();
        $sansGroupe = Producteur::factory()->create();
        $autreGroupe = Producteur::factory()->create(['groupe_id' => GroupeProducteur::factory()->create()->id]);

        $this->assertSame(CautionSolidaire::AUCUN, CautionSolidaire::controle($sansGroupe)['niveau']);
        $this->assertSame(CautionSolidaire::AUCUN, CautionSolidaire::controle($autreGroupe)['niveau']);
    }

    #[Test]
    public function le_message_compte_les_membres_en_retard(): void
    {
        $this->regle(RegleCautionSolidaire::Avertir);
        $troisieme = Producteur::factory()->create(['village_id' => $this->groupe->village_id, 'groupe_id' => $this->groupe->id]);
        $this->pretVerse($this->awa);
        $this->pretVerse($troisieme);
        Carbon::setTestNow(Carbon::today()->addMonths(2));

        $this->assertStringContainsString('2 autres membres du groupe ont un prêt en retard', CautionSolidaire::controle($this->issa)['message']);
    }

    #[Test]
    public function la_situation_du_groupe_totalise_les_membres(): void
    {
        $this->pretVerse($this->awa, 300_000);
        $this->pretVerse($this->issa, 200_000);

        $s = CautionSolidaire::situation($this->groupe);

        $this->assertCount(2, $s['membres']);
        $this->assertSame(500_000, $s['totaux']['remis']);
        $this->assertSame(500_000, $s['totaux']['restant_du']);
        $this->assertSame(0, $s['totaux']['rembourse']);
        $this->assertSame(0, $s['totaux']['membres_en_retard']);
        $this->assertSame(RegleCautionSolidaire::Aucune, $s['regle']);
    }

    #[Test]
    public function le_parametre_est_un_choix_de_la_direction(): void
    {
        $this->assertTrue(CleParametre::CautionSolidaire->estUnChoix());
        $this->assertSame(['aucune', 'avertir', 'bloquer'], array_keys(CleParametre::CautionSolidaire->options()));

        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->assertSame(RegleCautionSolidaire::Bloquer, Parametre::regleCautionSolidaire());

        Parametre::query()->where('cle', CleParametre::CautionSolidaire)->update(['valeur' => 'n-importe-quoi']);
        $this->assertSame(RegleCautionSolidaire::Aucune, Parametre::regleCautionSolidaire(), 'Une valeur inconnue ne bloque rien.');
    }

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'comptable' => [Role::Comptable, 200], 'agent' => [Role::Agent, 403],
            'agronome' => [Role::Agronome, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function les_ecrans_de_groupes_suivent_le_role(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/fiabilite/groupes')->assertStatus($attendu);
        $this->get('/fiabilite/groupes/'.$this->groupe->id)->assertStatus($attendu);
    }

    #[Test]
    public function les_ecrans_montrent_groupes_membres_et_retards(): void
    {
        $this->pretVerse($this->awa);
        Carbon::setTestNow(Carbon::today()->addMonths(2));

        $this->actingAs($this->direction);
        Livewire::test(ListeGroupes::class)->assertSee('Groupe Espoir');
        Livewire::test(SituationGroupe::class, ['groupe' => $this->groupe])
            ->assertSee('Groupe Espoir')->assertSee('Awa Koné')->assertSee('Issa Ouattara')
            ->assertSee('Membres en retard')->assertSee('Pas de caution solidaire');
    }

    #[Test]
    public function le_formulaire_de_pret_previent_l_agent_sans_nommer_le_membre_en_retard(): void
    {
        $this->regle(RegleCautionSolidaire::Bloquer);
        $this->awaEnRetard();

        $this->actingAs($this->agent);
        Livewire::test(FormulairePret::class)->set('producteurId', $this->issa->id)
            ->assertSee('Un autre membre du groupe a un prêt en retard')
            ->assertDontSee('Awa Koné');
    }
}
