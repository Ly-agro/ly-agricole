<?php

namespace Tests\Feature\Api;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutAchat;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\ConfirmationSms;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\MouvementStock;
use App\Models\MouvementTresorerie;
use App\Models\OperationRecue;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\Synchronisation;
use App\Models\User;
use App\Models\Village;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Livrable de la semaine 7 : le même lot d'opérations envoyé deux fois ne crée rien en
 * double. Et les deux autres tests obligatoires du skill terrain-hors-ligne : envoi
 * coupé en deux = envoi complet ; une opération invalide ne bloque pas les autres.
 */
class SynchronisationTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $direction->id,
        ]);
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 1_000_000, Carbon::today(), 'Avance achats', $direction);
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '5000000']);
        $this->village = Village::factory()->create();
    }

    /** @return array<string, mixed> */
    private function opProducteur(string $uuid, string $telephone = '0701020304'): array
    {
        return ['uuid' => $uuid, 'type' => 'producteur', 'cree_at' => now()->subHour()->toIso8601String(), 'donnees' => [
            'nom' => 'Koné', 'prenoms' => 'Awa', 'telephone' => $telephone, 'village_id' => $this->village->id, 'consentement' => true,
        ]];
    }

    /**
     * @param  array<string, mixed>  $surcharge
     * @return array<string, mixed>
     */
    private function opAchat(string $uuid, string $producteurId, array $surcharge = []): array
    {
        return ['uuid' => $uuid, 'type' => 'achat', 'cree_at' => now()->subMinutes(30)->toIso8601String(), 'donnees' => array_merge([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'compte_id' => $this->caisseAgent->id,
            'fournisseur_type' => 'producteur', 'producteur_id' => $producteurId,
            'date_achat' => now()->subMinutes(30)->toIso8601String(),
            'poids_brut_g' => 505_000, 'tare_g' => 5_000, 'humidite_pour_mille' => 80, 'prix_kg_fcfa' => 425,
        ], $surcharge)];
    }

    /** @param  list<array<string, mixed>>  $operations */
    private function envoyer(array $operations, ?User $user = null): TestResponse
    {
        Sanctum::actingAs($user ?? $this->agent);

        return $this->postJson('/api/sync', ['appareil_id' => 'telephone-test', 'operations' => $operations]);
    }

    /** @return array{producteurs: int, achats: int, stock: int, tresorerie: int, sms: int, caisse: int} */
    private function etat(): array
    {
        return [
            'producteurs' => Producteur::query()->count(),
            'achats' => Achat::query()->count(),
            'stock' => (int) MouvementStock::query()->sum('grammes'),
            'tresorerie' => MouvementTresorerie::query()->count(),
            'sms' => ConfirmationSms::query()->count(),
            'caisse' => $this->caisseAgent->solde(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function lotDOperations(): array
    {
        $p1 = (string) Str::uuid7();
        $p2 = (string) Str::uuid7();

        return [
            $this->opProducteur($p1, '0701020304'),
            $this->opAchat((string) Str::uuid7(), $p1),
            $this->opProducteur($p2, '0501020304'),
            $this->opAchat((string) Str::uuid7(), $p2, ['poids_brut_g' => 202_000, 'tare_g' => 2_000]),
        ];
    }

    #[Test]
    public function le_meme_lot_envoye_deux_fois_ne_cree_rien_en_double(): void
    {
        $operations = $this->lotDOperations();

        $premier = $this->envoyer($operations)->assertOk();
        $this->assertSame(['accepte', 'accepte', 'accepte', 'accepte'], array_column($premier->json('resultats'), 'statut'));
        $apresPremier = $this->etat();
        $this->assertSame(2, $apresPremier['producteurs']);
        $this->assertSame(2, $apresPremier['achats']);
        $this->assertSame(700_000, $apresPremier['stock']);
        // 500 kg × 425 = 212 500 ; 200 kg × 425 = 85 000.
        $this->assertSame(1_000_000 - 212_500 - 85_000, $apresPremier['caisse']);
        $this->assertSame(2, $apresPremier['sms'], 'Un SMS par achat.');

        // La réponse s'est perdue : le téléphone renvoie tout.
        $second = $this->envoyer($operations)->assertOk();
        $this->assertSame(['deja_recu', 'deja_recu', 'deja_recu', 'deja_recu'], array_column($second->json('resultats'), 'statut'));
        $this->assertSame($apresPremier, $this->etat());

        $this->assertSame(2, Synchronisation::query()->count());
        $trace = Synchronisation::query()->latest('id')->first();
        $this->assertSame([4, 0, 4, 0], [$trace->nb_operations, $trace->nb_acceptees, $trace->nb_deja_recues, $trace->nb_rejetees]);
    }

    #[Test]
    public function un_envoi_coupe_en_deux_donne_le_meme_resultat_qu_un_envoi_complet(): void
    {
        $operations = $this->lotDOperations();

        // Première moitié, puis coupure ; au retour du réseau, tout est renvoyé.
        $this->envoyer(array_slice($operations, 0, 2))->assertOk();
        $retour = $this->envoyer($operations)->assertOk();

        $this->assertSame(['deja_recu', 'deja_recu', 'accepte', 'accepte'], array_column($retour->json('resultats'), 'statut'));
        $etat = $this->etat();
        $this->assertSame([2, 2, 700_000, 1_000_000 - 297_500], [$etat['producteurs'], $etat['achats'], $etat['stock'], $etat['caisse']]);
    }

    #[Test]
    public function une_operation_invalide_ne_bloque_pas_les_autres_et_revient_avec_son_motif(): void
    {
        $operations = $this->lotDOperations();
        // Prix sous le prix officiel (400) : refusé par le serveur.
        $operations[1] = $this->opAchat($operations[1]['uuid'], $operations[0]['uuid'], ['prix_kg_fcfa' => 350]);

        $resultats = $this->envoyer($operations)->assertOk()->json('resultats');

        $this->assertSame(['accepte', 'rejete', 'accepte', 'accepte'], array_column($resultats, 'statut'));
        $this->assertStringContainsString('prix officiel', $resultats[1]['motif']);
        $this->assertSame(1, Achat::query()->count());
        $this->assertSame(OperationRecue::REJETE, OperationRecue::query()->find($operations[1]['uuid'])?->statut);

        // Corrigée sur le téléphone, renvoyée avec le même UUID : acceptée cette fois.
        $operations[1] = $this->opAchat($operations[1]['uuid'], $operations[0]['uuid']);
        $this->assertSame('accepte', $this->envoyer([$operations[1]])->json('resultats.0.statut'));
        $this->assertSame(2, Achat::query()->count());
        $this->assertTrue(Achat::query()->whereKey($operations[1]['uuid'])->exists(), 'L\'UUID du téléphone est l\'identifiant de l\'achat.');
    }

    #[Test]
    public function un_poids_ou_un_prix_non_entier_est_refuse_pas_arrondi(): void
    {
        $p = (string) Str::uuid7();
        $resultats = $this->envoyer([
            $this->opProducteur($p),
            $this->opAchat((string) Str::uuid7(), $p, ['poids_brut_g' => 505_000.5]),
            $this->opAchat((string) Str::uuid7(), $p, ['prix_kg_fcfa' => '425']),
        ])->json('resultats');

        $this->assertSame(['accepte', 'rejete', 'rejete'], array_column($resultats, 'statut'));
        $this->assertSame(0, Achat::query()->count());
    }

    #[Test]
    public function uuid_invalide_type_inconnu_et_uuid_reutilise_sont_rejetes(): void
    {
        $p = (string) Str::uuid7();
        $this->envoyer([$this->opProducteur($p)])->assertOk();

        $resultats = $this->envoyer([
            ['uuid' => 'pas-un-uuid', 'type' => 'producteur', 'donnees' => []],
            ['uuid' => (string) Str::uuid7(), 'type' => 'virement', 'donnees' => []],
            // Même UUID que le producteur, mais pour un achat.
            $this->opAchat($p, $p),
        ])->json('resultats');

        $this->assertSame(['rejete', 'rejete', 'rejete'], array_column($resultats, 'statut'));
        $this->assertStringContainsString('UUID', $resultats[0]['motif']);
        $this->assertStringContainsString('inconnu', $resultats[1]['motif']);
        $this->assertStringContainsString('déjà servi', $resultats[2]['motif']);
        $this->assertSame(1, Producteur::query()->count());
        $this->assertSame(0, Achat::query()->count());
    }

    #[Test]
    public function un_producteur_sans_consentement_ou_en_doublon_d_alerte_est_rejete_jusqu_a_confirmation(): void
    {
        $sansAccord = $this->opProducteur((string) Str::uuid7());
        $sansAccord['donnees']['consentement'] = false;
        Producteur::factory()->create(['telephone' => '0799999999']);
        $doublon = $this->opProducteur((string) Str::uuid7(), '0799999999');

        $resultats = $this->envoyer([$sansAccord, $doublon])->json('resultats');
        $this->assertSame(['rejete', 'rejete'], array_column($resultats, 'statut'));
        $this->assertStringContainsString('accord', $resultats[0]['motif']);
        $this->assertStringContainsString('À confirmer', $resultats[1]['motif']);

        $doublon['donnees']['doublons_confirmes'] = true;
        $this->assertSame('accepte', $this->envoyer([$doublon])->json('resultats.0.statut'));
        $cree = Producteur::query()->findOrFail($doublon['uuid']);
        $this->assertSame($this->agent->id, $cree->cree_par);
        $this->assertSame($this->agent->id, $cree->consentement_par);
    }

    #[Test]
    public function au_dessus_du_seuil_l_achat_du_terrain_attend_une_validation_rien_ne_bouge(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationAchat], ['valeur' => '100000']);
        $p = (string) Str::uuid7();

        $resultats = $this->envoyer([$this->opProducteur($p), $this->opAchat((string) Str::uuid7(), $p)])->json('resultats');

        $this->assertSame('accepte', $resultats[1]['statut']);
        $this->assertSame(StatutAchat::AValider, Achat::query()->sole()->statut);
        $this->assertSame(0, (int) MouvementStock::query()->sum('grammes'));
        $this->assertSame(1_000_000, $this->caisseAgent->solde());
        $this->assertSame(0, ConfirmationSms::query()->count(), 'Pas de SMS pour un achat pas encore validé.');
    }

    #[Test]
    public function un_role_sans_droit_voit_ses_operations_rejetees(): void
    {
        $agronome = User::factory()->role(Role::Agronome)->create();
        $resultats = $this->envoyer([$this->opProducteur((string) Str::uuid7())], $agronome)->json('resultats');

        $this->assertSame('rejete', $resultats[0]['statut']);
        $this->assertSame(0, Producteur::query()->count());
    }

    #[Test]
    public function sans_jeton_l_api_refuse(): void
    {
        $this->postJson('/api/sync', ['appareil_id' => 'x', 'operations' => []])->assertUnauthorized();
        $this->getJson('/api/referentiels')->assertUnauthorized();
    }
}
