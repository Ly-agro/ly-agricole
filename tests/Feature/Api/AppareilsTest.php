<?php

namespace Tests\Feature\Api;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Exceptions\OperationRefusee;
use App\Livewire\Appareils\ListeAppareils;
use App\Models\JournalActivite;
use App\Models\Synchronisation;
use App\Models\User;
use App\Services\Appareils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Appareils de l'appli terrain (question 25) : liste, dernière synchronisation, et surtout la
 * possibilité de COUPER un téléphone perdu : son jeton ne marche plus à la requête suivante.
 */
class AppareilsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $autreAgent;

    private User $direction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->role(Role::Agent)->create(['nom' => 'Agent Un']);
        $this->autreAgent = User::factory()->role(Role::Agent)->create(['nom' => 'Agent Deux']);
        $this->direction = User::factory()->role(Role::Direction)->create();
    }

    /** @return array{0: string, 1: PersonalAccessToken} texte du jeton et son enregistrement */
    private function jeton(User $user, string $nom = 'LY Terrain a1b2c3d4'): array
    {
        $nouveau = $user->createToken($nom);

        return [$nouveau->plainTextToken, $nouveau->accessToken];
    }

    private function synchro(User $user, string $appareilId, int $rejetees = 0): Synchronisation
    {
        return Synchronisation::query()->create([
            'appareil_id' => $appareilId, 'user_id' => $user->id, 'recu_at' => now()->subHour(),
            'nb_operations' => 4, 'nb_acceptees' => 4 - $rejetees, 'nb_deja_recues' => 0, 'nb_rejetees' => $rejetees,
        ]);
    }

    /** Oublie l'utilisateur déjà authentifié : deux requêtes d'un même test sont indépendantes. */
    private function nouvelleRequete(): void
    {
        $this->app['auth']->forgetGuards();
    }

    #[Test]
    public function un_appareil_coupe_ne_peut_plus_rien_envoyer(): void
    {
        [$texte, $jeton] = $this->jeton($this->agent);
        $this->withToken($texte)->getJson('/api/referentiels')->assertOk();

        Appareils::revoquer($this->direction, $jeton, 'Téléphone perdu');

        $this->nouvelleRequete();
        $this->withToken($texte)->getJson('/api/referentiels')->assertUnauthorized();
        $this->nouvelleRequete();
        $this->withToken($texte)->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => []])->assertUnauthorized();
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    #[Test]
    public function couper_un_appareil_laisse_les_autres_de_l_utilisateur(): void
    {
        [, $perdu] = $this->jeton($this->agent, 'LY Terrain 11111111');
        [$garde] = $this->jeton($this->agent, 'LY Terrain 22222222');

        Appareils::revoquer($this->direction, $perdu, 'Téléphone volé');

        $this->nouvelleRequete();
        $this->withToken($garde)->getJson('/api/referentiels')->assertOk();
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    #[Test]
    public function couper_tous_les_appareils_d_un_utilisateur_ne_touche_pas_les_autres_utilisateurs(): void
    {
        $this->jeton($this->agent, 'LY Terrain 11111111');
        $this->jeton($this->agent, 'LY Terrain 22222222');
        [$autre] = $this->jeton($this->autreAgent, 'LY Terrain 33333333');

        $nb = Appareils::revoquerTous($this->direction, $this->agent, 'Départ de l\'agent');

        $this->assertSame(2, $nb);
        $this->assertSame(0, $this->agent->tokens()->count());
        $this->nouvelleRequete();
        $this->withToken($autre)->getJson('/api/referentiels')->assertOk();
    }

    #[Test]
    public function couper_tous_les_appareils_de_quelqu_un_qui_n_en_a_pas_est_refuse(): void
    {
        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessage('aucun appareil connecté');

        Appareils::revoquerTous($this->direction, $this->agent, 'Vérification');
    }

    #[Test]
    public function la_coupure_laisse_une_ligne_au_journal_avec_le_motif_et_l_auteur(): void
    {
        [, $jeton] = $this->jeton($this->agent);

        Appareils::revoquer($this->direction, $jeton, '  Téléphone perdu au marché  ');

        $ligne = JournalActivite::query()->where('action', ActionJournal::RevocationAppareil)->sole();
        $this->assertSame($this->direction->id, $ligne->user_id);
        $this->assertSame('Téléphone perdu au marché', $ligne->apres['motif']);
        $this->assertSame('LY Terrain a1b2c3d4', $ligne->apres['appareil']);
        $this->assertSame((string) $this->agent->id, $ligne->objet_id);
    }

    #[Test]
    public function un_motif_trop_court_ou_un_role_sans_droit_est_refuse_et_rien_n_est_coupe(): void
    {
        [, $jeton] = $this->jeton($this->agent);

        foreach ([['motif' => ' x ', 'auteur' => $this->direction, 'attendu' => 'motif'], ['motif' => 'Téléphone perdu', 'auteur' => $this->agent, 'attendu' => 'rôle'],
            ['motif' => 'Téléphone perdu', 'auteur' => User::factory()->role(Role::Comptable)->create(), 'attendu' => 'rôle']] as $cas) {
            try {
                Appareils::revoquer($cas['auteur'], $jeton, $cas['motif']);
                $this->fail('Aurait dû être refusé.');
            } catch (OperationRefusee $e) {
                $this->assertStringContainsString($cas['attendu'], $e->getMessage());
            }
        }
        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertSame(0, JournalActivite::query()->where('action', ActionJournal::RevocationAppareil)->count());
    }

    #[Test]
    public function l_administrateur_peut_couper_un_appareil(): void
    {
        [, $jeton] = $this->jeton($this->agent);

        Appareils::revoquer(User::factory()->role(Role::Admin)->create(), $jeton, 'Téléphone perdu');

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    #[Test]
    public function la_liste_rattache_la_derniere_synchronisation_par_la_fin_de_l_identifiant_de_l_appareil(): void
    {
        $this->jeton($this->agent, 'LY Terrain a1b2c3d4');
        $this->synchro($this->agent, '01a0ec9b-dcda-714a-af15-2463a1b2c3d4', rejetees: 1);
        $this->synchro($this->agent, '01a0ec9b-dcda-714a-af15-2463ffffffff'); // un autre téléphone du même agent
        $this->synchro($this->autreAgent, '01a0ec9b-dcda-714a-af15-2463a1b2c3d4'); // même fin, autre utilisateur

        $liste = Appareils::lister();

        $this->assertCount(1, $liste);
        $ligne = $liste->first();
        $this->assertSame($this->agent->id, $ligne['utilisateur']->id);
        $this->assertNotNull($ligne['derniere_synchro']);
        $this->assertSame($this->agent->id, $ligne['derniere_synchro']->user_id);
        $this->assertSame(1, $ligne['derniere_synchro']->nb_rejetees);
    }

    #[Test]
    public function un_appareil_jamais_synchronise_n_a_pas_de_derniere_synchronisation(): void
    {
        $this->jeton($this->agent, 'Téléphone sans identifiant');

        $this->assertNull(Appareils::lister()->first()['derniere_synchro']);
    }

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'admin' => [Role::Admin, 200], 'comptable' => [Role::Comptable, 403],
            'agent' => [Role::Agent, 403], 'agronome' => [Role::Agronome, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function l_ecran_suit_le_role(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/appareils')->assertStatus($attendu);
    }

    #[Test]
    public function l_ecran_liste_puis_coupe_avec_un_motif_obligatoire(): void
    {
        [$texte, $jeton] = $this->jeton($this->agent);
        $this->synchro($this->agent, '01a0ec9b-dcda-714a-af15-2463a1b2c3d4');

        $this->actingAs($this->direction);
        Livewire::test(ListeAppareils::class)
            ->assertSee('Agent Un')->assertSee('LY Terrain a1b2c3d4')->assertSee('4 opération(s)')
            ->call('demanderCoupure', $jeton->id)->assertSee('Couper cet appareil')
            ->set('motif', '')->call('couper')->assertHasErrors(['motif'])
            ->set('motif', 'Téléphone perdu')->call('couper')->assertHasNoErrors()
            ->assertSee('Appareil coupé')->assertDontSee('LY Terrain a1b2c3d4');

        $this->nouvelleRequete();
        $this->withToken($texte)->getJson('/api/referentiels')->assertUnauthorized();
    }

    #[Test]
    public function l_ecran_coupe_tous_les_appareils_d_un_utilisateur(): void
    {
        $this->jeton($this->agent, 'LY Terrain 11111111');
        $this->jeton($this->agent, 'LY Terrain 22222222');

        $this->actingAs($this->direction);
        Livewire::test(ListeAppareils::class)
            ->call('demanderCoupureTous', $this->agent->id)->assertSee('TOUS les appareils')
            ->set('motif', 'Départ de l\'agent')->call('couper')
            ->assertSee('2 appareil(s) coupé(s)');

        $this->assertSame(0, $this->agent->tokens()->count());
    }

    #[Test]
    public function un_compte_desactive_est_signale_dans_la_liste(): void
    {
        $this->jeton($this->agent);
        $this->agent->update(['actif' => false]);

        $this->actingAs($this->direction);
        Livewire::test(ListeAppareils::class)->assertSee('compte désactivé');
    }
}
