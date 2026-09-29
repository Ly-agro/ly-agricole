<?php

namespace Tests\Feature\Prets;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Livewire\Fiabilite\FicheFiabilite;
use App\Livewire\Fiabilite\ListeFiabilite;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Services\FiabiliteProducteur;
use App\Services\Prets;
use App\Services\Remboursements;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Note de fiabilité (cahier §10) : historique objectif de remboursement et plafond PROPOSÉ,
 * jamais une décision. Règle : le plus gros prêt soldé à l'échéance ou avant.
 */
class FiabiliteProducteurTest extends TestCase
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

    /** Prêt versé en espèces, échéance dans `$mois` mois (par rapport à aujourd'hui). */
    private function pret(int $montant, int $mois = 5, ?Producteur $producteur = null): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => ($producteur ?? $this->producteur)->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => $montant,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths($mois),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, ['compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => $montant, 'date' => Carbon::today()], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    private function rembourser(Pret $pret, int $montant, ?Carbon $date = null): void
    {
        Remboursements::especes($pret, $this->caisse, $montant, $date ?? Carbon::today(), $this->comptable);
    }

    #[Test]
    public function sans_pret_verse_rien_n_est_propose(): void
    {
        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(0, $f['synthese']['nb_prets']);
        $this->assertNull($f['synthese']['taux_pour_mille']);
        $this->assertNull($f['plafond_propose']);
        $this->assertStringContainsString('pas d\'historique', $f['raison']);
    }

    #[Test]
    public function une_demande_ou_un_pret_pas_verse_ne_compte_pas(): void
    {
        Prets::demander([
            'producteur_id' => $this->producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => 300_000,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5),
        ], $this->agent);

        $this->assertSame(0, FiabiliteProducteur::fiche($this->producteur)['synthese']['nb_prets']);
    }

    #[Test]
    public function un_pret_solde_a_temps_donne_son_montant_comme_plafond(): void
    {
        $pret = $this->pret(300_000);
        $this->rembourser($pret, 300_000);

        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(1, $f['synthese']['nb_prets']);
        $this->assertSame(1000, $f['synthese']['taux_pour_mille']);
        $this->assertSame(1, $f['synthese']['nb_soldes_a_temps']);
        $this->assertTrue($f['prets'][0]['a_temps']);
        $this->assertSame(0, $f['prets'][0]['retard_jours']);
        $this->assertSame(300_000, $f['plafond_propose']);
        $this->assertStringContainsString('Aucune augmentation', $f['raison']);
    }

    #[Test]
    public function le_plafond_est_le_plus_gros_pret_solde_a_temps(): void
    {
        $this->rembourser($petit = $this->pret(200_000), 200_000);
        $this->rembourser($gros = $this->pret(500_000), 500_000);

        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(2, $f['synthese']['nb_soldes_a_temps']);
        $this->assertSame(700_000, $f['synthese']['total_rembourse']);
        $this->assertSame(500_000, $f['plafond_propose']);
        $this->assertNotSame($petit->id, $gros->id);
    }

    #[Test]
    public function un_pret_rembourse_a_moitie_et_pas_echu_ne_donne_pas_de_plafond(): void
    {
        $this->rembourser($this->pret(300_000), 150_000);

        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(500, $f['synthese']['taux_pour_mille']);
        $this->assertSame(0, $f['synthese']['nb_en_retard']);
        $this->assertNull($f['plafond_propose']);
        $this->assertStringContainsString('Aucun prêt soldé', $f['raison']);
        $this->assertFalse($f['prets'][0]['solde']);
    }

    #[Test]
    public function un_pret_solde_apres_l_echeance_n_est_pas_a_temps(): void
    {
        $pret = $this->pret(300_000, mois: 1);
        $apres = Carbon::today()->addMonth()->addDays(12);
        Carbon::setTestNow($apres);
        $this->rembourser($pret, 300_000, $apres);
        Carbon::setTestNow();

        $f = FiabiliteProducteur::fiche($this->producteur, $apres);

        $this->assertTrue($f['prets'][0]['solde']);
        $this->assertSame(12, $f['prets'][0]['retard_jours']);
        $this->assertFalse($f['prets'][0]['a_temps']);
        $this->assertSame(0, $f['synthese']['nb_soldes_a_temps']);
        $this->assertSame(12, $f['synthese']['retard_max_jours']);
        $this->assertNull($f['plafond_propose']);
    }

    #[Test]
    public function un_pret_en_retard_bloque_toute_proposition_meme_avec_un_bon_historique(): void
    {
        $this->rembourser($this->pret(200_000), 200_000);
        $enRetard = $this->pret(400_000, mois: 1);
        $plusTard = $enRetard->echeance->copy()->addDays(10);

        $f = FiabiliteProducteur::fiche($this->producteur, $plusTard);

        $this->assertSame(1, $f['synthese']['nb_en_retard']);
        $this->assertSame(10, $f['synthese']['retard_max_jours']);
        $this->assertNull($f['plafond_propose']);
        $this->assertStringContainsString('en retard', $f['raison']);
    }

    #[Test]
    public function un_pret_en_cours_pas_encore_echu_n_est_pas_un_retard(): void
    {
        $this->pret(400_000, mois: 3);

        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(0, $f['synthese']['nb_en_retard']);
        $this->assertSame(0, $f['synthese']['retard_max_jours']);
    }

    #[Test]
    public function le_plafond_par_producteur_des_parametres_borne_la_proposition(): void
    {
        $this->rembourser($this->pret(500_000), 500_000);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::PlafondPretProducteur], ['valeur' => '400000']);

        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(400_000, $f['plafond_propose']);
        $this->assertStringContainsString('plafond par producteur', $f['raison']);
    }

    #[Test]
    public function un_remboursement_contre_passe_ne_compte_plus(): void
    {
        $pret = $this->pret(300_000);
        $this->rembourser($pret, 300_000);
        Remboursements::contrePasser($pret->remboursements()->sole(), 'Erreur de saisie du montant', $this->comptable);

        $f = FiabiliteProducteur::fiche($this->producteur);

        $this->assertSame(0, $f['synthese']['taux_pour_mille']);
        $this->assertFalse($f['prets'][0]['solde']);
        $this->assertNull($f['plafond_propose']);
    }

    #[Test]
    public function un_autre_producteur_n_est_pas_melange(): void
    {
        $this->rembourser($this->pret(300_000), 300_000);
        $autre = Producteur::factory()->create();

        $this->assertSame(0, FiabiliteProducteur::fiche($autre)['synthese']['nb_prets']);
    }

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'comptable' => [Role::Comptable, 200], 'agent' => [Role::Agent, 403],
            'agronome' => [Role::Agronome, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function les_ecrans_suivent_le_role(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/fiabilite')->assertStatus($attendu);
        $this->get('/fiabilite/'.$this->producteur->id)->assertStatus($attendu);
    }

    #[Test]
    public function la_liste_ne_montre_que_les_producteurs_avec_un_pret_verse_et_se_filtre(): void
    {
        $this->rembourser($this->pret(300_000), 300_000);
        $ouattara = Producteur::factory()->create(['nom' => 'Ouattara', 'prenoms' => 'Issa']);
        $this->pret(200_000, 5, $ouattara);
        Producteur::factory()->create(['nom' => 'Sansprêt', 'prenoms' => 'Zoe']);

        $this->actingAs($this->direction);
        Livewire::test(ListeFiabilite::class)
            ->assertSee('Koné')->assertSee('Ouattara')->assertDontSee('Sansprêt')
            ->set('recherche', 'Ouattara')->assertSee('Ouattara')->assertDontSee('Koné');
    }

    #[Test]
    public function la_fiche_dit_que_la_direction_decide_et_donne_le_plafond(): void
    {
        $this->rembourser($this->pret(300_000), 300_000);
        $f = "\u{202F}";

        $this->actingAs($this->direction);
        Livewire::test(FicheFiabilite::class, ['producteur' => $this->producteur])
            ->assertSee('Awa Koné')
            ->assertSee('La direction décide')
            ->assertSee('300'.$f.'000 FCFA')
            ->assertSee('Soldé à temps')
            ->assertSee('100,0 %');
    }
}
