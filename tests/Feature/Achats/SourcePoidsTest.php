<?php

namespace Tests\Feature\Achats;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\SourcePoids;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Exceptions\OperationRefusee;
use App\Livewire\Achats\ListeAchats;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Achats;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Source du poids d'un achat (balance Bluetooth ou saisie à la main) : une TRACE, jamais un
 * blocage. Cahier §10 : « poids sans saisie manuelle ».
 */
class SourcePoidsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    private Producteur $producteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->direction->id,
        ]);
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance achats', $this->direction);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '5000000']);
        $this->producteur = Producteur::factory()->create();
    }

    /** @param  array<string, mixed>  $surcharge */
    private function acheter(array $surcharge = []): Achat
    {
        return Achats::enregistrer(array_merge([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => $this->producteur->id, 'date_achat' => now()->subMinute(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $surcharge), $this->agent);
    }

    #[Test]
    public function la_source_du_poids_est_gardee_avec_l_achat(): void
    {
        $this->assertSame(SourcePoids::Balance, $this->acheter(['poids_source' => 'balance'])->refresh()->poids_source);
        $this->assertSame(SourcePoids::Manuel, $this->acheter(['poids_source' => SourcePoids::Manuel])->refresh()->poids_source);
    }

    #[Test]
    public function sans_source_transmise_elle_reste_inconnue_et_n_est_pas_inventee(): void
    {
        $this->assertNull($this->acheter()->refresh()->poids_source);
    }

    #[Test]
    public function une_source_inconnue_est_refusee_avec_un_motif(): void
    {
        $this->expectException(OperationRefusee::class);
        $this->expectExceptionMessage('Source du poids inconnue');

        $this->acheter(['poids_source' => 'devine']);
    }

    #[Test]
    public function la_source_ne_change_ni_le_poids_ni_le_montant(): void
    {
        $balance = $this->acheter(['poids_source' => 'balance']);
        $manuel = $this->acheter(['poids_source' => 'manuel']);

        $this->assertSame($balance->poids_net_g, $manuel->poids_net_g);
        $this->assertSame($balance->montant_fcfa, $manuel->montant_fcfa);
    }

    /** @return array<string, mixed> */
    private function operationAchat(?string $source, string $uuid): array
    {
        $donnees = [
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'compte_id' => $this->caisseAgent->id,
            'fournisseur_type' => 'producteur', 'producteur_id' => $this->producteur->id, 'date_achat' => now()->subMinute()->toIso8601String(),
            'poids_brut_g' => 505_000, 'tare_g' => 5_000, 'prix_kg_fcfa' => 425,
        ];
        if ($source !== null) {
            $donnees['poids_source'] = $source;
        }

        return ['uuid' => $uuid, 'type' => 'achat', 'cree_at' => now()->toIso8601String(), 'donnees' => $donnees];
    }

    #[Test]
    public function l_appli_terrain_envoie_la_source_par_la_synchronisation(): void
    {
        Sanctum::actingAs($this->agent);
        $a = (string) Str::uuid7();
        $b = (string) Str::uuid7();
        $c = (string) Str::uuid7();

        $r = $this->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => [
            $this->operationAchat('balance', $a), $this->operationAchat('manuel', $b), $this->operationAchat(null, $c),
        ]])->assertOk()->json('resultats');

        $this->assertSame(['accepte', 'accepte', 'accepte'], array_column($r, 'statut'));
        $this->assertSame(SourcePoids::Balance, Achat::query()->findOrFail($a)->poids_source);
        $this->assertSame(SourcePoids::Manuel, Achat::query()->findOrFail($b)->poids_source);
        $this->assertNull(Achat::query()->findOrFail($c)->poids_source, 'Une vieille appli qui ne l\'envoie pas : source inconnue.');
    }

    #[Test]
    public function une_source_invalide_dans_la_synchronisation_rejette_l_operation_avec_un_motif(): void
    {
        Sanctum::actingAs($this->agent);
        $uuid = (string) Str::uuid7();

        $r = $this->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => [$this->operationAchat('devine', $uuid)]])
            ->assertOk()->json('resultats.0');

        $this->assertSame('rejete', $r['statut']);
        $this->assertStringContainsString('poids source', $r['motif']);
        $this->assertSame(0, Achat::query()->count());
    }

    #[Test]
    public function la_liste_des_achats_distingue_balance_et_saisie_a_la_main(): void
    {
        $this->acheter(['poids_source' => 'balance']);
        $this->acheter(['poids_source' => 'manuel']);

        $this->actingAs($this->direction);
        Livewire::test(ListeAchats::class)->assertSee('balance')->assertSee('à la main');
    }
}
