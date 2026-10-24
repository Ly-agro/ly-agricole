<?php

namespace Tests\Feature\Journal;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Livewire\Auth\Connexion;
use App\Livewire\Utilisateurs\GestionUtilisateurs;
use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class JournalActiviteTest extends TestCase
{
    use RefreshDatabase;

    private function derniere(): JournalActivite
    {
        return JournalActivite::query()->latest('id')->firstOrFail();
    }

    #[Test]
    public function la_creation_d_un_compte_par_l_admin_est_journalisee_sans_le_mot_de_passe(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();
        $depart = JournalActivite::count();

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('nouveau')
            ->set('nom', 'Koffi Agent')
            ->set('email', 'koffi@ly-agricole.test')
            ->set('role', 'agent')
            ->set('motDePasse', 'motdepasse-secret')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $koffi = User::where('email', 'koffi@ly-agricole.test')->firstOrFail();
        $ligne = $this->derniere();

        $this->assertSame($depart + 1, JournalActivite::count());
        $this->assertSame(ActionJournal::Creation, $ligne->action);
        $this->assertSame($admin->id, $ligne->user_id);
        $this->assertSame('user', $ligne->objet_type);
        $this->assertSame((string) $koffi->id, $ligne->objet_id);
        $this->assertSame('koffi@ly-agricole.test', $ligne->apres['email'] ?? null);
        $this->assertSame('agent', $ligne->apres['role'] ?? null);
        $this->assertSame('(masqué)', $ligne->apres['password'] ?? null);
        $this->assertStringNotContainsString('motdepasse-secret', (string) json_encode($ligne->apres));
        $this->assertStringNotContainsString($koffi->password, (string) json_encode($ligne->apres));
    }

    #[Test]
    public function une_modification_ne_garde_que_les_champs_changes_avant_et_apres(): void
    {
        $admin = User::factory()->role(Role::Admin)->create();
        $agent = User::factory()->create(['nom' => 'Ancien Nom', 'role' => Role::Agent]);

        Livewire::actingAs($admin)
            ->test(GestionUtilisateurs::class)
            ->call('modifier', $agent->id)
            ->set('nom', 'Nouveau Nom')
            ->set('role', 'comptable')
            ->call('enregistrer')
            ->assertHasNoErrors();

        $ligne = $this->derniere();

        $this->assertSame(ActionJournal::Modification, $ligne->action);
        $this->assertSame($admin->id, $ligne->user_id);
        $this->assertSame(['nom' => 'Ancien Nom', 'role' => 'agent'], $ligne->avant);
        $this->assertSame(['nom' => 'Nouveau Nom', 'role' => 'comptable'], $ligne->apres);
    }

    #[Test]
    public function un_nouveau_mot_de_passe_est_journalise_masque(): void
    {
        $agent = User::factory()->create();

        $agent->update(['password' => 'nouveau-secret']);

        $ligne = $this->derniere();
        $this->assertSame(['password' => '(masqué)'], $ligne->avant);
        $this->assertSame(['password' => '(masqué)'], $ligne->apres);
    }

    #[Test]
    public function un_changement_du_seul_jeton_de_session_ne_fait_pas_de_ligne(): void
    {
        $agent = User::factory()->create();
        $depart = JournalActivite::count();

        $agent->update(['remember_token' => 'autre-jeton']);

        $this->assertSame($depart, JournalActivite::count());
    }

    #[Test]
    public function une_operation_annulee_n_a_pas_de_trace(): void
    {
        $depart = JournalActivite::count();

        try {
            DB::transaction(function () {
                User::factory()->create(['email' => 'annule@ly-agricole.test']);

                throw new RuntimeException('échec au milieu');
            });
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('users', ['email' => 'annule@ly-agricole.test']);
        $this->assertSame($depart, JournalActivite::count());
    }

    #[Test]
    public function connexion_echec_et_deconnexion_sont_journalises(): void
    {
        $agent = User::factory()->create(['email' => 'agent@ly-agricole.test']);

        Livewire::test(Connexion::class)
            ->set('email', 'agent@ly-agricole.test')
            ->set('password', 'faux')
            ->call('connecter');

        $echec = $this->derniere();
        $this->assertSame(ActionJournal::EchecConnexion, $echec->action);
        $this->assertSame(['email' => 'agent@ly-agricole.test'], $echec->apres);

        Livewire::test(Connexion::class)
            ->set('email', 'agent@ly-agricole.test')
            ->set('password', 'password')
            ->call('connecter');

        $connexion = $this->derniere();
        $this->assertSame(ActionJournal::Connexion, $connexion->action);
        $this->assertSame($agent->id, $connexion->user_id);
        $this->assertSame('127.0.0.1', $connexion->ip);

        $this->post('/deconnexion');

        $deconnexion = $this->derniere();
        $this->assertSame(ActionJournal::Deconnexion, $deconnexion->action);
        $this->assertSame($agent->id, $deconnexion->user_id);
    }

    #[Test]
    public function un_compte_inconnu_qui_essaie_laisse_l_adresse_tapee(): void
    {
        Livewire::test(Connexion::class)
            ->set('email', 'inconnu@exemple.test')
            ->set('password', 'devine')
            ->call('connecter');

        $ligne = $this->derniere();
        $this->assertSame(ActionJournal::EchecConnexion, $ligne->action);
        $this->assertNull($ligne->user_id);
        $this->assertSame(['email' => 'inconnu@exemple.test'], $ligne->apres);
    }

    #[Test]
    public function le_blocage_apres_trop_d_essais_est_journalise(): void
    {
        User::factory()->create(['email' => 'agent@ly-agricole.test']);
        $composant = Livewire::test(Connexion::class)->set('email', 'agent@ly-agricole.test');

        for ($i = 0; $i <= Connexion::TENTATIVES_MAX; $i++) {
            $composant->set('password', 'faux')->call('connecter');
        }

        $ligne = $this->derniere();
        $this->assertSame(ActionJournal::BlocageConnexion, $ligne->action);
        $this->assertSame(['email' => 'agent@ly-agricole.test'], $ligne->apres);
    }
}
