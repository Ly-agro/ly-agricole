<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiTerrainTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function connexion_rend_un_jeton_qui_ouvre_l_api_et_la_deconnexion_le_revoque(): void
    {
        $agent = User::factory()->role(Role::Agent)->create(['email' => 'agent@ly.test']);

        $jeton = $this->postJson('/api/connexion', ['email' => 'agent@ly.test', 'password' => 'password', 'appareil' => 'Tecno A1'])
            ->assertOk()->assertJsonPath('utilisateur.role', 'agent')->json('jeton');

        $this->withToken($jeton)->getJson('/api/referentiels')->assertOk();
        $this->assertSame('Tecno A1', $agent->tokens()->sole()->name);

        $this->withToken($jeton)->postJson('/api/deconnexion')->assertOk();
        $this->assertSame(0, $agent->tokens()->count());
    }

    #[Test]
    public function mauvais_mot_de_passe_ou_compte_desactive_meme_refus(): void
    {
        User::factory()->role(Role::Agent)->create(['email' => 'agent@ly.test']);
        User::factory()->role(Role::Agent)->inactif()->create(['email' => 'ancien@ly.test']);

        $mauvais = $this->postJson('/api/connexion', ['email' => 'agent@ly.test', 'password' => 'faux', 'appareil' => 'x'])->assertUnprocessable();
        $inactif = $this->postJson('/api/connexion', ['email' => 'ancien@ly.test', 'password' => 'password', 'appareil' => 'x'])->assertUnprocessable();
        $this->assertSame($mauvais->json('errors.email'), $inactif->json('errors.email'));
    }

    #[Test]
    public function un_compte_desactive_apres_connexion_ne_passe_plus_avec_son_jeton(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $jeton = $agent->createToken('tel')->plainTextToken;
        $agent->update(['actif' => false]);

        $this->withToken($jeton)->getJson('/api/referentiels')->assertUnauthorized();
    }

    #[Test]
    public function referentiels_complets_puis_seulement_ce_qui_a_change(): void
    {
        $agent = User::factory()->role(Role::Agent)->create();
        $autreAgent = User::factory()->role(Role::Agent)->create();
        Campagne::factory()->statut(StatutCampagne::Ouverte)->create();
        $village = Village::factory()->create();
        Producteur::factory()->count(2)->create(['village_id' => $village->id]);
        $caisse = CompteTresorerie::factory()->caisseDe($agent)->create();
        CompteTresorerie::factory()->caisseDe($autreAgent)->create();
        CompteTresorerie::factory()->create();
        Sanctum::actingAs($agent);

        $complet = $this->getJson('/api/referentiels')->assertOk();
        $this->assertTrue($complet->json('complet'));
        $this->assertCount(2, $complet->json('producteurs'));
        // Un agent ne paie que depuis sa propre caisse : il ne reçoit que celle-là.
        $this->assertSame([$caisse->id], array_column($complet->json('comptes'), 'id'));

        $this->travel(1)->minutes();
        $nouveau = Producteur::factory()->create(['village_id' => $village->id]);
        $delta = $this->getJson('/api/referentiels?depuis='.urlencode($complet->json('horodatage')))->assertOk();

        $this->assertFalse($delta->json('complet'));
        $this->assertSame([$nouveau->id], array_column($delta->json('producteurs'), 'id'));
        $this->assertSame([], $delta->json('villages'));
    }

    #[Test]
    public function un_role_sans_saisie_terrain_n_a_pas_les_referentiels(): void
    {
        Sanctum::actingAs(User::factory()->role(Role::Comptable)->create());
        // Le comptable saisit des achats (bureau) : il y a droit. L'agronome, non.
        $this->getJson('/api/referentiels')->assertOk();

        Sanctum::actingAs(User::factory()->role(Role::Agronome)->create());
        $this->getJson('/api/referentiels')->assertForbidden();
    }
}
