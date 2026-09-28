<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Livewire\TableauDeBord;
use App\Models\Campagne;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TableauDeBordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_prix_bord_champ_de_la_campagne_ouverte_s_affiche(): void
    {
        Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['code' => '2031-2032', 'prix_officiel_kg_fcfa' => 425]);
        $direction = User::factory()->role(Role::Direction)->create();

        Livewire::actingAs($direction)->test(TableauDeBord::class)
            ->assertSee('2031-2032')
            ->assertSee('425')
            ->assertSee('FCFA / kg');
    }

    #[Test]
    public function une_campagne_sans_prix_le_dit_au_lieu_d_afficher_zero(): void
    {
        Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => null]);
        $direction = User::factory()->role(Role::Direction)->create();

        Livewire::actingAs($direction)->test(TableauDeBord::class)->assertSee('Pas encore annoncé');
    }

    #[Test]
    public function sans_campagne_la_page_l_explique(): void
    {
        $direction = User::factory()->role(Role::Direction)->create();

        Livewire::actingAs($direction)->test(TableauDeBord::class)
            ->assertSee('Aucune campagne n\'est encore créée', escape: false)
            ->assertSee('Créer une campagne');
    }

    #[Test]
    public function un_agent_ne_voit_ni_la_tresorerie_ni_les_depenses(): void
    {
        Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 425]);
        $agent = User::factory()->role(Role::Agent)->create();

        Livewire::actingAs($agent)->test(TableauDeBord::class)
            ->assertSee('425')
            ->assertDontSee('Trésorerie disponible')
            ->assertDontSee('Entrées et sorties d\'argent', escape: false)
            ->assertDontSee('Où part l\'argent', escape: false);
    }

    #[Test]
    public function un_compte_sans_role_voit_le_message_et_aucun_chiffre_d_argent(): void
    {
        $user = User::factory()->role(null)->create();

        Livewire::actingAs($user)->test(TableauDeBord::class)
            ->assertSee('Aucun rôle ne vous est attribué')
            ->assertDontSee('Trésorerie disponible');
    }
}
