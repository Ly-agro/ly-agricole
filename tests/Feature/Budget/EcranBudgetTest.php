<?php

namespace Tests\Feature\Budget;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\PosteBudget;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Livewire\Budget\SuiviBudget;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\JournalActivite;
use App\Models\LigneBudget;
use App\Models\Parametre;
use App\Models\User;
use App\Services\Budgets;
use App\Services\Depenses;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EcranBudgetTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $direction;

    private Campagne $campagne;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create();
    }

    /** @return array<string, array{Role, int}> */
    public static function acces(): array
    {
        return [
            'direction' => [Role::Direction, 200],
            'comptable' => [Role::Comptable, 200],
            'agent' => [Role::Agent, 403],
            'agronome' => [Role::Agronome, 403],
            'investisseur' => [Role::Investisseur, 403],
            'admin' => [Role::Admin, 403],
        ];
    }

    #[Test]
    #[DataProvider('acces')]
    public function acces_a_l_ecran_budget(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/budget')->assertStatus($attendu);
    }

    #[Test]
    public function la_direction_prevoit_un_poste_depuis_l_ecran(): void
    {
        $transport = CategorieDepense::query()->create(['nom' => 'Transport']);
        $this->actingAs($this->direction);

        Livewire::test(SuiviBudget::class)
            ->assertSet('campagneId', (string) $this->campagne->id)
            ->call('ouvrir')
            ->set('poste', "categorie-{$transport->id}")
            ->set('montant', '1 500 000')
            ->set('note', 'Deux camions')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Budget enregistré.'))
            ->assertSee('1'.self::FINE.'500'.self::FINE.'000 FCFA')
            ->assertSee('Deux camions');

        $this->assertSame(1_500_000, LigneBudget::query()->sole()->montant_fcfa);
    }

    #[Test]
    public function modifier_un_poste_reprend_son_montant(): void
    {
        Budgets::definir($this->campagne, PosteBudget::Achats, null, 17_000_000, $this->direction, 'Hypothèse 40 t');
        $this->actingAs($this->direction);

        Livewire::test(SuiviBudget::class)
            ->call('ouvrir', 'achats')
            ->assertSet('montant', '17 000 000')
            ->assertSet('note', 'Hypothèse 40 t')
            ->set('montant', '18 000 000')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertSame(18_000_000, LigneBudget::query()->sole()->montant_fcfa);
    }

    #[Test]
    public function le_motif_d_une_modification_entre_au_journal(): void
    {
        Budgets::definir($this->campagne, PosteBudget::Achats, null, 17_000_000, $this->direction);
        $this->actingAs($this->direction);

        Livewire::test(SuiviBudget::class)
            ->call('ouvrir')
            ->assertSet('modification', false)
            ->set('poste', 'achats')
            ->assertSet('modification', true)
            ->set('montant', '19 000 000')
            ->set('motif', 'Prix bord-champ relevé à 450 F/kg')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $ligne = LigneBudget::query()->sole();
        $journal = JournalActivite::query()->where('objet_type', 'ligne_budget')->where('objet_id', (string) $ligne->id)
            ->where('action', 'modification')->sole();
        $this->assertSame(17_000_000, (int) $journal->avant['montant_fcfa']);
        $this->assertSame(19_000_000, (int) $journal->apres['montant_fcfa']);
        $this->assertSame('Prix bord-champ relevé à 450 F/kg', $journal->apres['motif_modification']);
    }

    #[Test]
    public function un_montant_ambigu_est_refuse(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(SuiviBudget::class)
            ->call('ouvrir', 'achats')
            ->set('montant', '1.500')
            ->call('enregistrer')
            ->assertHasErrors('montant');

        $this->assertSame(0, LigneBudget::query()->count());
    }

    #[Test]
    public function la_comptabilite_suit_le_budget_sans_pouvoir_le_modifier(): void
    {
        Budgets::definir($this->campagne, PosteBudget::Achats, null, 17_000_000, $this->direction);
        $this->actingAs(User::factory()->role(Role::Comptable)->create());

        Livewire::test(SuiviBudget::class)
            ->assertSee('17'.self::FINE.'000'.self::FINE.'000 FCFA')
            ->assertDontSee('Prévoir un poste')
            ->assertDontSeeHtml('wire:click="ouvrir(')
            ->call('ouvrir', 'achats')
            ->assertForbidden();
    }

    #[Test]
    public function une_campagne_cloturee_n_offre_plus_de_modification(): void
    {
        $this->campagne->update(['statut' => StatutCampagne::Cloturee]);
        $this->actingAs($this->direction);

        Livewire::test(SuiviBudget::class)
            ->assertDontSee('Prévoir un poste')
            ->assertSee('budget figé');
    }

    #[Test]
    public function un_depassement_est_signale(): void
    {
        $transport = CategorieDepense::query()->create(['nom' => 'Transport']);
        Budgets::definir($this->campagne, PosteBudget::Categorie, $transport->id, 50_000, $this->direction);
        $this->actingAs($this->direction);
        Livewire::test(SuiviBudget::class)->assertDontSee('Dépassé de');

        $caisse = CompteTresorerie::factory()->create();
        Tresorerie::entree($caisse, 1_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationDepense], ['valeur' => '1000000']);
        Depenses::saisir([
            'categorie_id' => $transport->id, 'compte_id' => $caisse->id, 'montant_fcfa' => 60_000,
            'date_depense' => Carbon::today(), 'beneficiaire' => 'Transporteur', 'campagne_id' => $this->campagne->id,
        ], 'depenses/justificatifs/recu.jpg', $this->direction);

        Livewire::test(SuiviBudget::class)
            ->assertSee('Dépassé de 10'.self::FINE.'000 FCFA')
            ->assertSee('120,0 %');
    }
}
