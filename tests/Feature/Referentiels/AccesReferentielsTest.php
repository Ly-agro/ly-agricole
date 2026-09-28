<?php

namespace Tests\Feature\Referentiels;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccesReferentielsTest extends TestCase
{
    use RefreshDatabase;

    private const SIMPLES = ['zones', 'villages', 'produits', 'magasins', 'points-collecte'];

    /**
     * Matrice proposée (question ouverte n° 19) : ce test la fige ; la changer = le
     * changer lui aussi, en connaissance de cause.
     *
     * @return array<string, array{Role, list<string>}>
     */
    public static function ecransParRole(): array
    {
        return [
            'admin' => [Role::Admin, self::SIMPLES],
            'direction' => [Role::Direction, [...self::SIMPLES, 'campagnes', 'parametres']],
            'comptable' => [Role::Comptable, []],
            'agent' => [Role::Agent, []],
            'agronome' => [Role::Agronome, []],
            'investisseur' => [Role::Investisseur, []],
        ];
    }

    /**
     * @param  list<string>  $autorises
     */
    #[Test]
    #[DataProvider('ecransParRole')]
    public function chaque_role_n_ouvre_que_ses_referentiels(Role $role, array $autorises): void
    {
        $user = User::factory()->role($role)->create();

        foreach ([...self::SIMPLES, 'campagnes', 'parametres'] as $ecran) {
            $this->actingAs($user)
                ->get("/referentiels/$ecran")
                ->assertStatus(in_array($ecran, $autorises, true) ? 200 : 403);
        }

        $tableau = $this->actingAs($user)->get('/tableau-de-bord');
        $lien = 'href="'.route('referentiels').'"';
        $autorises === [] ? $tableau->assertDontSee($lien, false) : $tableau->assertSee($lien, false);

        $entree = $this->actingAs($user)->get('/referentiels');
        $autorises === [] ? $entree->assertForbidden() : $entree->assertRedirect(route('referentiels.zones'));
    }

    #[Test]
    public function les_onglets_ne_montrent_que_les_ecrans_autorises(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create())
            ->get('/referentiels/zones')
            ->assertSee('href="'.route('referentiels.villages').'"', false)
            ->assertDontSee('href="'.route('referentiels.campagnes').'"', false)
            ->assertDontSee('href="'.route('referentiels.parametres').'"', false);

        $this->actingAs(User::factory()->role(Role::Direction)->create())
            ->get('/referentiels/zones')
            ->assertSee('href="'.route('referentiels.campagnes').'"', false)
            ->assertSee('href="'.route('referentiels.parametres').'"', false);
    }
}
