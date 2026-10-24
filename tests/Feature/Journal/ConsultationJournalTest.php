<?php

namespace Tests\Feature\Journal;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Livewire\Journal\ConsultationJournal;
use App\Models\JournalActivite;
use App\Models\User;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationJournalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{Role, bool}> */
    public static function accesParRole(): array
    {
        return [
            'admin' => [Role::Admin, true],
            'direction' => [Role::Direction, true],
            'comptable' => [Role::Comptable, false],
            'agent' => [Role::Agent, false],
            'agronome' => [Role::Agronome, false],
            'investisseur' => [Role::Investisseur, false],
        ];
    }

    #[Test]
    #[DataProvider('accesParRole')]
    public function seuls_l_admin_et_la_direction_voient_le_journal(Role $role, bool $autorise): void
    {
        $user = User::factory()->role($role)->create();

        $tableau = $this->actingAs($user)->get('/tableau-de-bord');
        $lien = 'href="'.route('journal').'"';
        $autorise ? $tableau->assertSee($lien, false) : $tableau->assertDontSee($lien, false);

        $this->actingAs($user)->get('/journal')->assertStatus($autorise ? 200 : 403);
    }

    #[Test]
    public function le_journal_affiche_qui_a_fait_quoi_avec_le_detail(): void
    {
        $direction = User::factory()->role(Role::Direction)->create(['nom' => 'Mariam Direction']);
        $agent = User::factory()->create(['nom' => 'Ancien Nom']);

        $this->actingAs($direction);
        $agent->update(['nom' => 'Nouveau Nom']);

        Livewire::test(ConsultationJournal::class)
            ->assertSee('Mariam Direction')
            ->assertSee('Modification')
            ->assertSee('user n° '.$agent->id)
            ->assertSeeHtml('<span class="text-stone-500 line-through">Ancien Nom</span>')
            ->assertSeeHtml('<span>Nouveau Nom</span>');
    }

    #[Test]
    public function les_valeurs_sont_affichees_lisiblement(): void
    {
        $this->assertSame('Koffi', JournalActivite::valeurLisible('Koffi'));
        $this->assertSame('oui', JournalActivite::valeurLisible(true));
        $this->assertSame('non', JournalActivite::valeurLisible(false));
        $this->assertSame('(vide)', JournalActivite::valeurLisible(null));
        $this->assertSame('(vide)', JournalActivite::valeurLisible(''));
        $this->assertSame('3000000', JournalActivite::valeurLisible(3000000));
    }

    #[Test]
    public function les_filtres_par_action_et_par_utilisateur_fonctionnent(): void
    {
        $admin = User::factory()->role(Role::Admin)->create(['nom' => 'Awa Admin']);
        $autre = User::factory()->create(['nom' => 'Bakary Agent']);

        Journal::enregistrer(ActionJournal::Connexion, userId: $admin->id, apres: ['repere' => 'connexion-awa']);
        Journal::enregistrer(ActionJournal::Connexion, userId: $autre->id, apres: ['repere' => 'connexion-bakary']);
        Journal::enregistrer(ActionJournal::Deconnexion, userId: $admin->id, apres: ['repere' => 'deconnexion-awa']);

        $ecran = Livewire::actingAs($admin)->test(ConsultationJournal::class);

        $ecran->set('filtreAction', 'connexion')
            ->assertSee('connexion-awa')
            ->assertSee('connexion-bakary')
            ->assertDontSee('deconnexion-awa');

        $ecran->set('filtreUtilisateur', (string) $admin->id)
            ->assertSee('connexion-awa')
            ->assertDontSee('connexion-bakary')
            ->assertDontSee('deconnexion-awa');
    }

    #[Test]
    public function un_filtre_invalide_est_refuse_sans_erreur_serveur(): void
    {
        Livewire::actingAs(User::factory()->role(Role::Admin)->create())
            ->test(ConsultationJournal::class)
            ->set('filtreAction', 'nimportequoi')
            ->assertHasErrors('filtreAction')
            ->assertOk();
    }

    #[Test]
    public function un_utilisateur_retrograde_perd_l_acces_au_journal_deja_ouvert(): void
    {
        $user = User::factory()->role(Role::Direction)->create();
        $ecran = Livewire::actingAs($user)->test(ConsultationJournal::class);

        $user->update(['role' => Role::Agent]);

        $ecran->set('filtreAction', 'connexion')->assertForbidden();
    }
}
