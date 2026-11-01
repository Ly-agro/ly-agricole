<?php

namespace Tests\Feature\Referentiels;

use App\Enums\ActionJournal;
use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Livewire\Referentiels\Parametres;
use App\Models\CompteTresorerie;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\User;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ParametresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->role(Role::Direction)->create());
    }

    #[Test]
    public function au_depart_aucun_seuil_n_est_defini_et_cela_se_voit(): void
    {
        foreach (CleParametre::cases() as $cle) {
            $this->assertNull(Parametre::entier($cle));
        }

        Livewire::test(Parametres::class)
            ->assertSee('Seuil de validation des dépenses')
            ->assertSee('Non défini');
    }

    #[Test]
    public function la_direction_fixe_un_seuil_en_fcfa_et_c_est_journalise(): void
    {
        Livewire::test(Parametres::class)
            ->call('modifier', CleParametre::SeuilValidationDepense->value)
            ->set('valeur', '500000')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Seuil de validation des dépenses : enregistré.'))
            ->assertSee("500\u{202F}000 FCFA");

        $this->assertSame(500_000, Parametre::entier(CleParametre::SeuilValidationDepense));
        $this->assertNull(Parametre::entier(CleParametre::SeuilValidationAchat));

        $ligne = JournalActivite::query()->where('objet_type', 'parametre')->latest('id')->firstOrFail();
        $this->assertSame(ActionJournal::Creation, $ligne->action);
        $this->assertSame('500000', $ligne->apres['valeur'] ?? null);
    }

    #[Test]
    public function changer_puis_vider_un_seuil_garde_la_trace_de_chaque_valeur(): void
    {
        $ecran = Livewire::test(Parametres::class);
        foreach (['500000', '750000', ''] as $valeur) {
            $ecran->call('modifier', CleParametre::SeuilValidationPret->value)
                ->set('valeur', $valeur)
                ->call('enregistrer')
                ->assertHasNoErrors();
        }

        $this->assertNull(Parametre::entier(CleParametre::SeuilValidationPret));

        $historique = JournalActivite::query()->where('objet_type', 'parametre')->orderBy('id')->get();
        $this->assertCount(3, $historique);
        $this->assertSame(['valeur' => '500000'], $historique[1]->avant);
        $this->assertSame(['valeur' => '750000'], $historique[1]->apres);
        $this->assertSame(['valeur' => null], $historique[2]->apres);
    }

    #[Test]
    public function un_seuil_decimal_ou_negatif_est_refuse(): void
    {
        foreach (['1000.5' => 'integer', '-1' => 'min'] as $saisie => $regle) {
            Livewire::test(Parametres::class)
                ->call('modifier', CleParametre::SeuilValidationAchat->value)
                ->set('valeur', $saisie)
                ->call('enregistrer')
                ->assertHasErrors(['valeur' => $regle]);
        }

        $this->assertNull(Parametre::entier(CleParametre::SeuilValidationAchat));
    }

    #[Test]
    public function une_cle_inconnue_est_refusee(): void
    {
        Livewire::test(Parametres::class)
            ->set('edition', 'seuil_invente')
            ->set('valeur', '1')
            ->call('enregistrer')
            ->assertHasErrors('edition');

        $this->assertSame(0, Parametre::count());
    }

    #[Test]
    public function l_admin_ne_touche_pas_aux_seuils(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        Livewire::test(Parametres::class)->assertForbidden();
    }

    #[Test]
    public function la_direction_ferme_un_compte_vide_et_conserve_son_historique(): void
    {
        $compte = CompteTresorerie::factory()->create(['nom' => 'Caisse à fermer']);
        $direction = auth()->user();
        Tresorerie::entree($compte, 10_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $direction);
        Tresorerie::retirer($compte, 10_000, Carbon::today(), 'Retrait', $direction);

        $this->assertSame(0, $compte->solde());
        Livewire::test(Parametres::class)
            ->call('preparerActionCompte', $compte->id, 'fermer')
            ->assertSee('son historique sera conservé')
            ->call('confirmerActionCompte')
            ->assertHasNoErrors()
            ->assertSet('statut', 'Compte « Caisse à fermer » fermé ; son historique est conservé.');

        $this->assertFalse($compte->refresh()->actif);
        $this->assertSame(2, $compte->mouvements()->count());
    }

    #[Test]
    public function la_direction_peut_supprimer_un_compte_jamais_utilise(): void
    {
        $compte = CompteTresorerie::factory()->create(['nom' => 'Compte inutilisé']);

        Livewire::test(Parametres::class)
            ->call('preparerActionCompte', $compte->id, 'supprimer')
            ->assertSee('suppression définitive')
            ->call('confirmerActionCompte')
            ->assertHasNoErrors()
            ->assertSet('statut', 'Compte « Compte inutilisé » supprimé.');

        $this->assertDatabaseMissing('comptes_tresorerie', ['id' => $compte->id]);
        $this->assertDatabaseHas('journal_activite', [
            'objet_type' => 'compte_tresorerie',
            'objet_id' => (string) $compte->id,
            'action' => ActionJournal::Suppression->value,
        ]);
    }

    #[Test]
    public function un_compte_avec_historique_ne_peut_pas_etre_supprime_meme_si_son_solde_est_nul(): void
    {
        $compte = CompteTresorerie::factory()->create();
        $direction = auth()->user();
        Tresorerie::entree($compte, 5_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $direction);
        Tresorerie::retirer($compte, 5_000, Carbon::today(), 'Retrait', $direction);

        Livewire::test(Parametres::class)
            ->call('preparerActionCompte', $compte->id, 'supprimer')
            ->assertHasErrors('actionCompte')
            ->assertSee('fermez-le pour le conserver');

        $this->assertNotNull($compte->fresh());
        $this->assertTrue($compte->refresh()->actif);
    }

    #[Test]
    public function un_compte_non_vide_ne_peut_pas_etre_ferme(): void
    {
        $compte = CompteTresorerie::factory()->create();
        Tresorerie::entree($compte, 1_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', auth()->user());

        Livewire::test(Parametres::class)
            ->call('preparerActionCompte', $compte->id, 'fermer')
            ->assertHasErrors('actionCompte')
            ->assertSee('solde est nul');

        $this->assertTrue($compte->refresh()->actif);
    }

    #[Test]
    public function seul_le_role_direction_peut_gerer_les_comptes_depuis_les_parametres(): void
    {
        $this->actingAs(User::factory()->role(Role::Comptable)->create());

        Livewire::test(Parametres::class)->assertForbidden();
    }
}
