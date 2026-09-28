<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\Connexion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_page_de_connexion_s_affiche(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertSeeLivewire(Connexion::class)
            ->assertSee('Se connecter');
    }

    #[Test]
    public function la_racine_renvoie_un_visiteur_vers_la_connexion(): void
    {
        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSeeLivewire(Connexion::class);
    }

    #[Test]
    public function un_visiteur_ne_voit_pas_le_tableau_de_bord(): void
    {
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
    }

    #[Test]
    public function un_utilisateur_actif_se_connecte_et_arrive_au_tableau_de_bord(): void
    {
        $user = User::factory()->create(['email' => 'agent@ly-agricole.test']);

        Livewire::test(Connexion::class)
            ->set('email', 'agent@ly-agricole.test')
            ->set('password', 'password')
            ->call('connecter')
            ->assertHasNoErrors()
            ->assertRedirect('/tableau-de-bord');

        $this->assertAuthenticatedAs($user);

        $this->get('/tableau-de-bord')->assertOk()->assertSee($user->nom);
    }

    #[Test]
    public function un_mauvais_mot_de_passe_est_refuse_en_francais(): void
    {
        User::factory()->create(['email' => 'agent@ly-agricole.test']);

        Livewire::test(Connexion::class)
            ->set('email', 'agent@ly-agricole.test')
            ->set('password', 'faux')
            ->call('connecter')
            ->assertHasErrors('email')
            ->assertSee('Adresse e-mail ou mot de passe incorrect.')
            ->assertSet('password', '');

        $this->assertGuest();
    }

    #[Test]
    public function un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->inactif()->create(['email' => 'ancien@ly-agricole.test']);

        Livewire::test(Connexion::class)
            ->set('email', 'ancien@ly-agricole.test')
            ->set('password', 'password')
            ->call('connecter')
            ->assertHasErrors('email')
            // Même message qu'un mauvais mot de passe : on ne révèle pas que le compte existe.
            ->assertSee('Adresse e-mail ou mot de passe incorrect.');

        $this->assertGuest();
    }

    #[Test]
    public function les_champs_vides_sont_signales_en_francais(): void
    {
        Livewire::test(Connexion::class)
            ->call('connecter')
            ->assertHasErrors(['email' => 'required', 'password' => 'required'])
            ->assertSee('Le champ adresse e-mail est obligatoire.')
            ->assertSee('Le champ mot de passe est obligatoire.');
    }

    #[Test]
    public function apres_cinq_echecs_meme_le_bon_mot_de_passe_est_bloque(): void
    {
        User::factory()->create(['email' => 'agent@ly-agricole.test']);

        $composant = Livewire::test(Connexion::class)->set('email', 'agent@ly-agricole.test');

        for ($i = 0; $i < Connexion::TENTATIVES_MAX; $i++) {
            $composant->set('password', 'faux')->call('connecter');
        }

        $composant->set('password', 'password')
            ->call('connecter')
            ->assertHasErrors('email')
            ->assertSee('Trop de tentatives de connexion.');

        $this->assertGuest();
    }

    #[Test]
    public function une_connexion_reussie_remet_le_compteur_d_echecs_a_zero(): void
    {
        User::factory()->create(['email' => 'agent@ly-agricole.test']);

        $composant = Livewire::test(Connexion::class)->set('email', 'agent@ly-agricole.test');
        $composant->set('password', 'faux')->call('connecter');
        $composant->set('password', 'password')->call('connecter')->assertHasNoErrors();

        $this->assertSame(0, RateLimiter::attempts('agent@ly-agricole.test|127.0.0.1'));
    }

    #[Test]
    public function un_utilisateur_connecte_qui_ouvre_la_connexion_va_au_tableau_de_bord(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/connexion')
            ->assertRedirect('/tableau-de-bord');
    }

    #[Test]
    public function la_deconnexion_ferme_la_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/deconnexion')
            ->assertRedirect('/connexion');

        $this->assertGuest();
    }
}
