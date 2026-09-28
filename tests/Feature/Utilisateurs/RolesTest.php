<?php

namespace Tests\Feature\Utilisateurs;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{Role}> */
    public static function rolesSansGestionDesComptes(): array
    {
        return [
            'direction' => [Role::Direction],
            'comptable' => [Role::Comptable],
            'agent' => [Role::Agent],
            'agronome' => [Role::Agronome],
            'investisseur' => [Role::Investisseur],
        ];
    }

    #[Test]
    public function l_admin_voit_et_ouvre_la_gestion_des_utilisateurs(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)->get('/tableau-de-bord')
            ->assertOk()
            ->assertSee('href="'.route('utilisateurs').'"', false);

        $this->actingAs($admin)->get('/utilisateurs')->assertOk();
    }

    #[Test]
    #[DataProvider('rolesSansGestionDesComptes')]
    public function les_autres_roles_ne_voient_ni_n_ouvrent_la_gestion_des_utilisateurs(Role $role): void
    {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->get('/tableau-de-bord')
            ->assertOk()
            ->assertSee($role->libelle())
            ->assertDontSee('href="'.route('utilisateurs').'"', false);

        $this->actingAs($user)->get('/utilisateurs')
            ->assertForbidden()
            ->assertSee('Accès refusé')
            ->assertSee('Votre rôle ne donne pas accès à cette page.');
    }

    #[Test]
    public function un_compte_sans_role_n_a_aucun_droit_et_le_sait(): void
    {
        $user = User::factory()->role(null)->create();

        $this->assertFalse($user->can('gerer-utilisateurs'));

        $this->actingAs($user)->get('/tableau-de-bord')
            ->assertOk()
            ->assertSee('Aucun rôle ne vous est attribué');

        $this->actingAs($user)->get('/utilisateurs')->assertForbidden();
    }

    #[Test]
    public function un_compte_desactive_pendant_sa_session_est_deconnecte(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/tableau-de-bord')->assertOk();

        $user->update(['actif' => false]);

        $this->get('/tableau-de-bord')
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors(['email' => 'Ce compte a été désactivé.']);

        $this->assertGuest();
    }

    #[Test]
    public function le_seeder_de_developpement_cree_un_compte_par_role(): void
    {
        $this->seed();

        foreach (Role::cases() as $role) {
            $this->assertDatabaseHas('users', [
                'email' => $role->value.'@ly-agricole.test',
                'role' => $role->value,
                'actif' => true,
            ]);
        }
    }
}
