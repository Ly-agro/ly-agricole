<?php

namespace Tests\Feature\Referentiels;

use App\Enums\ActionJournal;
use App\Enums\CleParametre;
use App\Enums\Role;
use App\Livewire\Referentiels\Parametres;
use App\Models\JournalActivite;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Seuil de validation des dépenses : enregistré.')
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
}
