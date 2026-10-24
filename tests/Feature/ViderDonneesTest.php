<?php

namespace Tests\Feature;

use App\Enums\FormePret;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\Produit;
use App\Models\User;
use App\Models\Zone;
use App\Services\Prets;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViderDonneesTest extends TestCase
{
    use RefreshDatabase;

    private function donneesDEssai(): User
    {
        $direction = User::factory()->role(Role::Direction)->create();
        $campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create();
        $caisse = CompteTresorerie::factory()->create();
        Tresorerie::entree($caisse, 1_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $direction);
        Prets::demander([
            'producteur_id' => Producteur::factory()->create()->id, 'campagne_id' => $campagne->id, 'montant_fcfa' => 100_000,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonth(),
        ], $direction);

        return $direction;
    }

    #[Test]
    public function sans_le_mot_vider_rien_n_est_vide(): void
    {
        $this->donneesDEssai();

        $this->artisan('ly:vider-donnees', ['--confirmation' => 'oui'])->assertFailed();

        $this->assertSame(1, Pret::query()->count());
    }

    #[Test]
    public function vide_les_operations_et_garde_comptes_produits_parametres_et_referentiels(): void
    {
        $direction = $this->donneesDEssai();
        $zones = Zone::query()->count();
        $produits = Produit::query()->count();
        Parametre::query()->create(['cle' => 'seuil_validation_achat_fcfa', 'valeur' => '500000']);
        Storage::fake('local');
        Storage::disk('local')->put('depenses/justificatifs/a.jpg', 'x');

        $this->artisan('ly:vider-donnees', ['--confirmation' => 'VIDER', '--fichiers' => true])->assertSuccessful();

        foreach (['prets', 'producteurs', 'campagnes', 'mouvements_tresorerie', 'comptes_tresorerie', 'journal_activite', 'validations_pret'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertTrue(User::query()->whereKey($direction->id)->exists());
        $this->assertSame($produits, Produit::query()->count());
        $this->assertSame($zones, Zone::query()->count());
        $this->assertSame(1, Parametre::query()->count());
        Storage::disk('local')->assertMissing('depenses/justificatifs/a.jpg');

        // Les références repartent de zéro.
        $nouveau = $this->donneesDEssai();
        $this->assertSame('LYPR-000001', Pret::query()->sole()->reference);
        $this->assertNotNull($nouveau);
    }
}
