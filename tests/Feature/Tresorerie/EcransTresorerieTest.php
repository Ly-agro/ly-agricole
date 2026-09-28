<?php

namespace Tests\Feature\Tresorerie;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutDepense;
use App\Livewire\Depenses\FormulaireDepense;
use App\Livewire\Depenses\ListeDepenses;
use App\Livewire\Tresorerie\Comptes;
use App\Livewire\Tresorerie\ReleveCompte;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Depense;
use App\Models\Parametre;
use App\Models\User;
use App\Services\Depenses;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EcransTresorerieTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $comptable;

    private User $direction;

    private CompteTresorerie $caisse;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->comptable = User::factory()->role(Role::Comptable)->create(['nom' => 'Adjoua Comptable']);
        $this->direction = User::factory()->role(Role::Direction)->create(['nom' => 'Mariam Direction']);
        $this->caisse = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
    }

    /** @return array<string, array{Role, int, int, int}> [rôle, /tresorerie, /depenses, /depenses/nouvelle] */
    public static function acces(): array
    {
        return [
            'direction' => [Role::Direction, 200, 200, 200],
            'comptable' => [Role::Comptable, 200, 200, 200],
            'agent' => [Role::Agent, 403, 200, 200],
            'agronome' => [Role::Agronome, 403, 403, 403],
            'admin' => [Role::Admin, 403, 403, 403],
            'investisseur' => [Role::Investisseur, 403, 403, 403],
        ];
    }

    #[Test]
    #[DataProvider('acces')]
    public function chacun_n_ouvre_que_ses_ecrans_d_argent(Role $role, int $tresorerie, int $depenses, int $nouvelle): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/tresorerie')->assertStatus($tresorerie);
        $this->get("/tresorerie/comptes/{$this->caisse->id}")->assertStatus($tresorerie);
        $this->get('/depenses')->assertStatus($depenses);
        $this->get('/depenses/nouvelle')->assertStatus($nouvelle);
    }

    #[Test]
    public function une_entree_tapee_avec_des_espaces_est_enregistree_et_le_solde_s_affiche(): void
    {
        $this->actingAs($this->comptable);

        Livewire::test(Comptes::class)
            ->call('ouvrir', 'entree')
            ->set('compteId', (string) $this->caisse->id)
            ->set('nature', 'apport')
            ->set('montant', '21 000 000')
            ->set('libelle', 'Fonds de la campagne')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Opération enregistrée.')
            ->assertSee('21'.self::FINE.'000'.self::FINE.'000 FCFA');

        $this->assertSame(21_000_000, $this->caisse->solde());
    }

    #[Test]
    public function un_montant_ambigu_ou_un_virement_a_decouvert_est_explique(): void
    {
        $this->actingAs($this->comptable);
        $banque = CompteTresorerie::factory()->create();
        Tresorerie::entree($this->caisse, 100_000, NatureMouvement::Apport, Carbon::today(), 'Apport', $this->comptable);

        Livewire::test(Comptes::class)
            ->call('ouvrir', 'entree')
            ->set('compteId', (string) $this->caisse->id)
            ->set('nature', 'apport')
            ->set('montant', '1.500')
            ->set('libelle', 'Ambigu')
            ->call('enregistrer')
            ->assertHasErrors('montant')
            ->assertSee('sans virgule ni point');

        Livewire::test(Comptes::class)
            ->call('ouvrir', 'virement')
            ->set('compteId', (string) $this->caisse->id)
            ->set('compteDestinationId', (string) $banque->id)
            ->set('montant', '150000')
            ->set('libelle', 'Dépôt')
            ->call('enregistrer')
            ->assertHasErrors('montant')
            ->assertSee('Solde insuffisant sur « Caisse centrale »');

        $this->assertSame(100_000, $this->caisse->solde());
    }

    #[Test]
    public function le_releve_montre_le_solde_courant_et_permet_une_contre_passation_motivee(): void
    {
        $this->actingAs($this->comptable);
        $un = Tresorerie::entree($this->caisse, 300_000, NatureMouvement::Apport, Carbon::today(), 'Premier apport', $this->comptable);
        Tresorerie::entree($this->caisse, 200_000, NatureMouvement::Apport, Carbon::today(), 'Apport en double', $this->comptable);

        $releve = Livewire::test(ReleveCompte::class, ['compte' => $this->caisse])
            ->assertSee('500'.self::FINE.'000 FCFA')
            ->call('preparerContrePassation', $un->id + 1)
            ->set('motif', 'non')
            ->call('contrePasser')
            ->assertHasErrors('motif');

        $releve->set('motif', 'Apport saisi deux fois')
            ->call('contrePasser')
            ->assertHasNoErrors()
            ->assertSee('Mouvement contre-passé.')
            ->assertSee('Motif : Apport saisi deux fois');

        $this->assertSame(300_000, $this->caisse->solde());
    }

    #[Test]
    public function saisir_une_depense_exige_un_justificatif_et_l_enregistre_en_prive(): void
    {
        $this->actingAs($this->comptable);
        $categorie = CategorieDepense::query()->create(['nom' => 'Carburant']);

        $ecran = Livewire::test(FormulaireDepense::class)
            ->assertSee('Seuil de validation non défini : toute dépense doit être validée par une autre personne.')
            ->set('categorieId', (string) $categorie->id)
            ->set('compteId', (string) $this->caisse->id)
            ->set('montant', '45 000')
            ->set('beneficiaire', 'Station Total Korhogo')
            ->call('enregistrer')
            ->assertHasErrors(['justificatif' => 'required'])
            ->assertSee('Le justificatif (photo du reçu ou PDF) est obligatoire.');

        $ecran->set('justificatif', UploadedFile::fake()->image('recu.jpg'))
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('depenses'));

        $depense = Depense::firstOrFail();
        $this->assertSame(45_000, $depense->montant_fcfa);
        $this->assertSame(StatutDepense::AValider, $depense->statut);
        Storage::disk('local')->assertExists($depense->justificatif);
        $this->assertStringStartsWith('depenses/justificatifs/', $depense->justificatif);
    }

    #[Test]
    public function l_auteur_ne_voit_pas_de_bouton_valider_sur_sa_depense_et_un_autre_la_valide(): void
    {
        Tresorerie::entree($this->caisse, 1_000_000, NatureMouvement::Apport, Carbon::today(), 'Apport', $this->direction);
        $depense = Depenses::saisir([
            'categorie_id' => CategorieDepense::query()->create(['nom' => 'Transport'])->id,
            'compte_id' => $this->caisse->id,
            'montant_fcfa' => 60_000,
            'date_depense' => Carbon::today(),
            'beneficiaire' => 'Transporteur',
        ], 'depenses/justificatifs/r.jpg', $this->comptable);

        $this->actingAs($this->comptable);
        Livewire::test(ListeDepenses::class)
            ->assertSee('(à valider par un autre)')
            ->assertDontSeeHtml("wire:click=\"valider('{$depense->id}')\"")
            // Même en appelant l'action à la main, le service refuse.
            ->call('valider', $depense->id)
            ->assertHasErrors('action')
            ->assertSee('Vous ne pouvez pas valider votre propre dépense');

        $this->actingAs($this->direction);
        Livewire::test(ListeDepenses::class)
            ->assertSeeHtml("wire:click=\"valider('{$depense->id}')\"")
            ->call('valider', $depense->id)
            ->assertHasNoErrors()
            ->assertSee('Dépense validée et payée.')
            ->assertSee('validée par Mariam Direction');

        $this->assertSame(940_000, $this->caisse->solde());
    }

    #[Test]
    public function un_refus_demande_un_motif(): void
    {
        $depense = Depenses::saisir([
            'categorie_id' => CategorieDepense::query()->create(['nom' => 'Divers'])->id,
            'compte_id' => $this->caisse->id,
            'montant_fcfa' => 10_000,
            'date_depense' => Carbon::today(),
            'beneficiaire' => 'Quelqu\'un',
        ], 'depenses/justificatifs/r.jpg', $this->comptable);

        $this->actingAs($this->direction);
        Livewire::test(ListeDepenses::class)
            ->call('preparerRefus', $depense->id)
            ->call('refuser')
            ->assertHasErrors('motifRefus')
            ->set('motifRefus', 'Reçu illisible')
            ->call('refuser')
            ->assertHasNoErrors()
            ->assertSee('Reçu illisible');

        $this->assertSame(StatutDepense::Refusee, $depense->refresh()->statut);
    }

    #[Test]
    public function un_agent_ne_voit_que_ses_depenses_et_sa_caisse(): void
    {
        Parametre::query()->create(['cle' => CleParametre::SeuilValidationDepense, 'valeur' => '100000']);
        $koffi = User::factory()->role(Role::Agent)->create(['nom' => 'Koffi']);
        $autreAgent = User::factory()->role(Role::Agent)->create();
        $caisseKoffi = CompteTresorerie::factory()->caisseDe($koffi)->create();
        $categorie = CategorieDepense::query()->create(['nom' => 'Manutention']);
        Tresorerie::entree($this->caisse, 1_000_000, NatureMouvement::Apport, Carbon::today(), 'Apport', $this->direction);
        Tresorerie::avanceAgent($this->caisse, $caisseKoffi, 50_000, Carbon::today(), 'Avance', $this->direction);

        $depenseKoffi = Depenses::saisir([
            'categorie_id' => $categorie->id, 'compte_id' => $caisseKoffi->id, 'montant_fcfa' => 8_000,
            'date_depense' => Carbon::today(), 'beneficiaire' => 'Porteurs du marché',
        ], UploadedFile::fake()->image('r.jpg')->store('depenses/justificatifs', 'local'), $koffi);
        Depenses::saisir([
            'categorie_id' => $categorie->id, 'compte_id' => $this->caisse->id, 'montant_fcfa' => 9_000,
            'date_depense' => Carbon::today(), 'beneficiaire' => 'Dépense du bureau',
        ], 'depenses/justificatifs/b.jpg', $this->comptable);

        $this->actingAs($koffi);
        Livewire::test(ListeDepenses::class)->assertSee('Porteurs du marché')->assertDontSee('Dépense du bureau');
        Livewire::test(FormulaireDepense::class)
            ->assertSee($caisseKoffi->nom)
            ->assertDontSeeHtml('>Caisse centrale<');

        $this->assertSame(42_000, $caisseKoffi->solde());
        $this->get(route('depenses.justificatif', $depenseKoffi))->assertOk();

        $this->actingAs($autreAgent)->get(route('depenses.justificatif', $depenseKoffi))->assertForbidden();
        $this->actingAs($this->comptable)->get(route('depenses.justificatif', $depenseKoffi))->assertOk();
    }
}
