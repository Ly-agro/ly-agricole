<?php

namespace Tests\Feature\Utilisateurs;

use App\Enums\Role;
use App\Livewire\Auth\Connexion;
use App\Livewire\Utilisateurs\GestionUtilisateurs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GestionUtilisateursTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->role(Role::Admin)->create(['nom' => 'Awa Admin']);
    }

    #[Test]
    public function l_admin_cree_un_compte_qui_peut_ensuite_se_connecter(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->set('nom', 'Koffi Agent')
            ->set('email', 'koffi@ly-agricole.test')
            ->set('telephone', '0701020304')
            ->set('role', 'agent')
            ->set('motDePasse', 'motdepasse-solide')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('editionId', null)
            ->assertSee('Compte de Koffi Agent créé.')
            ->assertSee('Koffi Agent');

        $koffi = User::where('email', 'koffi@ly-agricole.test')->firstOrFail();
        $this->assertSame(Role::Agent, $koffi->role);
        $this->assertTrue($koffi->actif);
        $this->assertTrue(Hash::check('motdepasse-solide', $koffi->password));

        Auth::logout();
        Livewire::test(Connexion::class)
            ->set('email', 'koffi@ly-agricole.test')
            ->set('password', 'motdepasse-solide')
            ->call('connecter')
            ->assertHasNoErrors();
        $this->assertAuthenticatedAs($koffi);
    }

    #[Test]
    public function la_creation_exige_rôle_mot_de_passe_et_e_mail_unique(): void
    {
        User::factory()->create(['email' => 'pris@ly-agricole.test']);

        Livewire::actingAs($this->admin)
            ->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->set('nom', 'Doublon')
            ->set('email', 'pris@ly-agricole.test')
            ->set('motDePasse', 'court')
            ->call('enregistrer')
            ->assertHasErrors(['email' => 'unique', 'role' => 'required', 'motDePasse' => 'min'])
            ->assertSee('Cette valeur de adresse e-mail est déjà utilisée.')
            ->assertSee('Le champ rôle est obligatoire.')
            ->assertSee('Le champ mot de passe doit contenir au moins 8 caractères.');

        $this->assertDatabaseMissing('users', ['nom' => 'Doublon']);
    }

    #[Test]
    public function un_role_inconnu_est_refuse(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->set('nom', 'Pirate')
            ->set('email', 'pirate@ly-agricole.test')
            ->set('role', 'superadmin')
            ->set('motDePasse', 'motdepasse-solide')
            ->call('enregistrer')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'pirate@ly-agricole.test']);
    }

    #[Test]
    public function modifier_sans_mot_de_passe_garde_l_ancien(): void
    {
        $agent = User::factory()->create(['nom' => 'Ancien Nom']);
        $ancienHash = $agent->password;

        Livewire::actingAs($this->admin)
            ->test(GestionUtilisateurs::class)
            ->call('modifier', $agent->id)
            ->assertSet('nom', 'Ancien Nom')
            ->assertSet('motDePasse', '')
            ->set('nom', 'Nouveau Nom')
            ->set('role', 'comptable')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Compte de Nouveau Nom modifié.');

        $agent->refresh();
        $this->assertSame('Nouveau Nom', $agent->nom);
        $this->assertSame(Role::Comptable, $agent->role);
        $this->assertSame($ancienHash, $agent->password);
    }

    #[Test]
    public function l_admin_desactive_un_compte_qui_ne_peut_plus_se_connecter(): void
    {
        $agent = User::factory()->create(['email' => 'parti@ly-agricole.test']);

        Livewire::actingAs($this->admin)
            ->test(GestionUtilisateurs::class)
            ->call('modifier', $agent->id)
            ->set('actif', false)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Désactivé');

        $this->assertFalse($agent->refresh()->actif);

        Auth::logout();
        Livewire::test(Connexion::class)
            ->set('email', 'parti@ly-agricole.test')
            ->set('password', 'password')
            ->call('connecter')
            ->assertHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function l_admin_ne_peut_ni_se_desactiver_ni_changer_son_propre_role(): void
    {
        $composant = Livewire::actingAs($this->admin)
            ->test(GestionUtilisateurs::class)
            ->call('modifier', $this->admin->id);

        $composant->set('actif', false)
            ->call('enregistrer')
            ->assertHasErrors('actif')
            ->assertSee('Vous ne pouvez pas désactiver votre propre compte.');

        $composant->set('actif', true)
            ->set('role', 'direction')
            ->call('enregistrer')
            ->assertHasErrors('role')
            ->assertSee('Vous ne pouvez pas changer votre propre rôle.');

        $this->admin->refresh();
        $this->assertTrue($this->admin->actif);
        $this->assertSame(Role::Admin, $this->admin->role);
    }

    #[Test]
    public function un_non_admin_ne_peut_pas_monter_le_composant(): void
    {
        // La direction l'ouvre pour les agents (AgentsParLaDirectionTest) ; le comptable, non.
        Livewire::actingAs(User::factory()->role(Role::Comptable)->create())
            ->test(GestionUtilisateurs::class)
            ->assertForbidden();
    }

    #[Test]
    public function un_admin_retrograde_perd_l_acces_aux_actions_du_composant_deja_ouvert(): void
    {
        $cible = User::factory()->create();

        // Un écran neuf par action : après un 403, l'objet de test Livewire ne peut plus
        // rejouer d'appel.
        foreach (['nouveau' => [], 'modifier' => [$cible->id], 'enregistrer' => []] as $action => $parametres) {
            $autreAdmin = User::factory()->role(Role::Admin)->create();
            $composant = Livewire::actingAs($autreAdmin)->test(GestionUtilisateurs::class);

            // Rétrogradé par un autre admin pendant que son écran est ouvert.
            $autreAdmin->update(['role' => Role::Agent]);

            $composant->call($action, ...$parametres)->assertForbidden();
        }
    }
}
