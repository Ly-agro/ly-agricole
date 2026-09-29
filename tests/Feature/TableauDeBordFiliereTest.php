<?php

namespace Tests\Feature;

use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Livewire\TableauDeBord;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Achats;
use App\Services\Prets;
use App\Services\Remboursements;
use App\Services\Tresorerie;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Les chiffres de la filière (kilos achetés, stock, remboursements) sur le tableau de
 * bord viennent des registres : achats validés, mouvements de stock, remboursements.
 */
class TableauDeBordFiliereTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private CompteTresorerie $caisseCentrale;

    private CompteTresorerie $caisseAgent;

    private Lot $lot;

    private Producteur $producteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $magasin = Magasin::factory()->create();
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => $magasin->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        $this->caisseCentrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($this->caisseCentrale, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->caisseCentrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance achats', $this->direction);
        $this->producteur = Producteur::factory()->create();
        Parametre::query()->updateOrCreate(['cle' => 'seuil_validation_achat_fcfa'], ['valeur' => '5000000']);
        Parametre::query()->updateOrCreate(['cle' => 'seuil_validation_pret_fcfa'], ['valeur' => '5000000']);
    }

    private function pretDecaisse(int $montant): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => $this->producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => $montant,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, [
            'compte_id' => $this->caisseCentrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => $montant, 'date' => Carbon::today(),
        ], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    private function acheter(int $netG = 500_000): void
    {
        Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id,
            'fournisseur_type' => TypeFournisseur::Producteur, 'producteur_id' => $this->producteur->id,
            'date_achat' => now()->subMinute(), 'poids_brut_g' => $netG + 5_000, 'tare_g' => 5_000,
            'humidite_pour_mille' => 80, 'kor_centieme_lbs' => 4_850, 'grainage_noix_kg' => 190,
            'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);
    }

    #[Test]
    public function les_kilos_achetes_et_le_stock_viennent_des_registres(): void
    {
        $this->acheter(500_000);

        Livewire::actingAs($this->direction)->test(TableauDeBord::class)
            ->assertSee('Kilos achetés')
            ->assertSee(Format::kg(500_000))
            ->assertSee('Stock en magasin')
            ->assertSee(Format::fcfa(212_500));
    }

    #[Test]
    public function un_remboursement_reduit_le_restant_du_et_sa_contre_passation_le_retablit(): void
    {
        $pret = $this->pretDecaisse(1_000_000);

        $rembourse = Remboursements::especes($pret, $this->caisseCentrale, 250_000, Carbon::today(), $this->comptable);

        $chiffre = fn (int $montant) => '<dd class="mt-1 text-xl font-semibold tabular-nums">'.Format::fcfa($montant).'</dd>';

        $vue = Livewire::actingAs($this->direction)->test(TableauDeBord::class)
            ->assertSeeHtml($chiffre(250_000))   // remboursé
            ->assertSeeHtml($chiffre(750_000));  // restant dû

        Remboursements::contrePasser($rembourse, 'Erreur de saisie', $this->direction);

        $vue->call('$refresh')
            ->assertDontSeeHtml($chiffre(250_000))
            ->assertDontSeeHtml($chiffre(750_000))
            ->assertSeeHtml($chiffre(0))          // remboursé
            ->assertSeeHtml($chiffre(1_000_000)); // restant dû = tout ce qui a été remis
    }

    #[Test]
    public function un_achat_en_attente_de_validation_n_est_pas_compte_dans_les_kilos(): void
    {
        // Au-dessus du seuil : l'achat attend une autre personne que son auteur.
        Parametre::query()->updateOrCreate(['cle' => 'seuil_validation_achat_fcfa'], ['valeur' => '100000']);
        $this->acheter(500_000);

        Livewire::actingAs($this->direction)->test(TableauDeBord::class)
            ->assertSee('achat à valider')
            ->assertSee('Aucun achat validé sur cette campagne.')
            ->assertDontSeeHtml('<dd class="mt-1 text-xl font-semibold tabular-nums">'.Format::kg(500_000).'</dd>');
    }

    #[Test]
    public function un_agent_voit_les_achats_mais_ni_le_stock_ni_le_restant_du(): void
    {
        $this->acheter(500_000);

        Livewire::actingAs($this->agent)->test(TableauDeBord::class)
            ->assertSee('Kilos achetés')
            ->assertDontSee('Stock en magasin');
    }
}
