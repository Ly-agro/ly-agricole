<?php

namespace Tests\Feature\Notifications;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Models\Producteur;
use App\Models\User;
use App\Notifications\AvisLy;
use App\Services\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Chaque action d'un agent prévient la direction ; ni ses connexions, ni les actions des autres rôles. */
class SuiviAgentsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $direction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->role(Role::Agent)->create(['nom' => 'Koffi']);
        $this->direction = User::factory()->role(Role::Direction)->create();
    }

    #[Test]
    public function la_direction_est_prevenue_quand_un_agent_enregistre_un_producteur(): void
    {
        Notification::fake();
        $this->actingAs($this->agent);

        $producteur = Producteur::factory()->create();

        Notification::assertSentTo($this->direction, AvisLy::class, function (AvisLy $avis) {
            return $avis->categorie === 'activite'
                && $avis->titre === 'Koffi (agent) a enregistré un producteur'
                && $avis->url !== null;
        });
        // L'écran verrouillé ne montre pas le nom du producteur.
        Notification::assertSentTo($this->direction, AvisLy::class, fn (AvisLy $a) => ! str_contains($a->pourPush()['titre'].$a->pourPush()['texte'], $producteur->nom));
        Notification::assertNotSentTo($this->agent, AvisLy::class);
    }

    #[Test]
    public function ni_les_connexions_ni_les_actions_des_autres_roles(): void
    {
        Notification::fake();

        Journal::enregistrer(ActionJournal::Connexion, $this->agent, userId: $this->agent->id);
        $this->actingAs(User::factory()->role(Role::Comptable)->create());
        Producteur::factory()->create();

        Notification::assertNothingSentTo($this->direction);
    }

    #[Test]
    public function l_avis_arrive_dans_la_liste_de_la_direction(): void
    {
        $this->actingAs($this->agent);
        Producteur::factory()->create();

        $avis = $this->direction->notifications()->first();
        $this->assertNotNull($avis);
        $this->assertSame('activite', $avis->data['categorie']);
        $this->actingAs($this->direction)->get('/notifications')->assertOk()->assertSee('Activité agent');
    }

    #[Test]
    public function avec_un_diffuseur_reel_l_avis_part_aussi_en_direct(): void
    {
        config(['broadcasting.default' => 'reverb']);
        $this->assertContains('broadcast', (new AvisLy('t', 'x'))->via($this->direction));

        config(['broadcasting.default' => 'null']);
        $this->assertNotContains('broadcast', (new AvisLy('t', 'x'))->via($this->direction));
    }
}
