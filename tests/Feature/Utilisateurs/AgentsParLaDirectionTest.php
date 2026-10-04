<?php

namespace Tests\Feature\Utilisateurs;

use App\Enums\Role;
use App\Livewire\Utilisateurs\GestionUtilisateurs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** La direction crée, modifie et désactive les comptes d'agents, et rien d'autre. */
class AgentsParLaDirectionTest extends TestCase
{
    use RefreshDatabase;

    private User $direction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->direction = User::factory()->role(Role::Direction)->create();
    }

    #[Test]
    public function la_direction_voit_le_lien_agents_et_ouvre_l_ecran(): void
    {
        $this->actingAs($this->direction)->get('/tableau-de-bord')
            ->assertOk()->assertSee('href="'.route('utilisateurs').'"', false);

        $this->actingAs($this->direction)->get('/utilisateurs')
            ->assertOk()->assertSee('Comptes des agents')->assertSee('Nouvel agent');
    }

    #[Test]
    public function la_direction_cree_un_compte_agent(): void
    {
        Livewire::actingAs($this->direction)->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->assertSet('role', Role::Agent->value)
            ->set('nom', 'Kouassi Agent')
            ->set('email', 'kouassi@ly-agricole.test')
            ->set('motDePasse', 'motdepasse-long')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'kouassi@ly-agricole.test', 'role' => Role::Agent->value, 'actif' => true]);
    }

    #[Test]
    public function la_direction_ne_peut_pas_creer_un_autre_role(): void
    {
        Livewire::actingAs($this->direction)->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->set('nom', 'Faux comptable')
            ->set('email', 'faux@ly-agricole.test')
            ->set('role', Role::Comptable->value)
            ->set('motDePasse', 'motdepasse-long')
            ->call('enregistrer')
            ->assertHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'faux@ly-agricole.test']);
    }

    #[Test]
    public function la_direction_ne_voit_ni_ne_modifie_les_comptes_qui_ne_sont_pas_des_agents(): void
    {
        $agent = User::factory()->role(Role::Agent)->create(['nom' => 'Agent Visible']);
        $comptable = User::factory()->role(Role::Comptable)->create(['nom' => 'Comptable Cache']);

        $composant = Livewire::actingAs($this->direction)->test(GestionUtilisateurs::class)
            ->assertSee('Agent Visible')->assertDontSee('Comptable Cache');

        $composant->call('modifier', $comptable->id)->assertForbidden();

        // Même en forçant l'identifiant du formulaire, on ne change pas un comptable.
        Livewire::actingAs($this->direction)->test(GestionUtilisateurs::class)
            ->set('editionId', $comptable->id)
            ->set('nom', 'Pirate')->set('email', $comptable->email)->set('role', Role::Agent->value)
            ->call('enregistrer')
            ->assertForbidden();
        $this->assertSame('Comptable Cache', $comptable->fresh()->nom);

        Livewire::actingAs($this->direction)->test(GestionUtilisateurs::class)
            ->call('modifier', $agent->id)->set('actif', false)->call('enregistrer')->assertHasNoErrors();
        $this->assertFalse($agent->fresh()->actif);
    }

    #[Test]
    public function l_admin_garde_tous_les_roles(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();

        Livewire::actingAs($admin)->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->set('nom', 'Nouvelle comptable')
            ->set('email', 'compta2@ly-agricole.test')
            ->set('role', Role::Comptable->value)
            ->set('motDePasse', 'motdepasse-long')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'compta2@ly-agricole.test', 'role' => Role::Comptable->value]);
    }
}
