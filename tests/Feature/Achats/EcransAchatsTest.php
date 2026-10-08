<?php

namespace Tests\Feature\Achats;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\RegleValorisationNature;
use App\Enums\Role;
use App\Enums\StatutAchat;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\StatutPret;
use App\Enums\TypeFournisseur;
use App\Livewire\Achats\FormulaireAchat;
use App\Livewire\Achats\ListeAchats;
use App\Livewire\Prets\FichePret;
use App\Livewire\Referentiels\Parametres;
use App\Livewire\Stock\FicheLot;
use App\Livewire\Stock\ListeLots;
use App\Models\Achat;
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
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EcransAchatsTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Magasin $magasin;

    private Lot $lot;

    private CompteTresorerie $caisseCentrale;

    private CompteTresorerie $caisseAgent;

    private Producteur $producteur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create(['nom' => 'Koffi Agent']);
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->magasin = Magasin::factory()->create(['nom' => 'Magasin central']);
        $this->lot = Lot::query()->create(['produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => $this->magasin->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id]);
        $this->caisseCentrale = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($this->caisseCentrale, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->caisseCentrale, $this->caisseAgent, 1_000_000, Carbon::today(), 'Avance', $this->direction);
        $this->producteur = Producteur::factory()->create(['nom' => 'Coulibaly', 'prenoms' => 'Awa']);
        Parametre::query()->create(['cle' => CleParametre::SeuilValidationAchat, 'valeur' => '5000000']);
        Parametre::query()->create(['cle' => CleParametre::SeuilValidationPret, 'valeur' => '5000000']);
    }

    private function pretDecaisse(int $montant): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => $this->producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => $montant,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, ['compte_id' => $this->caisseCentrale->id, 'mode' => ModeDecaissement::Especes,
            'montant_fcfa' => $montant, 'date' => Carbon::today()], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    /** @return array<string, array{Role, int, int, int}> [rôle, /achats, /achats/nouveau, /lots] */
    public static function acces(): array
    {
        return [
            'direction' => [Role::Direction, 200, 200, 200],
            'comptable' => [Role::Comptable, 200, 200, 200],
            'agent' => [Role::Agent, 200, 200, 403],
            'agronome' => [Role::Agronome, 403, 403, 403],
            'admin' => [Role::Admin, 403, 403, 403],
            'investisseur' => [Role::Investisseur, 403, 403, 403],
        ];
    }

    #[Test]
    #[DataProvider('acces')]
    public function acces_aux_achats_et_aux_lots(Role $role, int $liste, int $nouveau, int $lots): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/achats')->assertStatus($liste);
        $this->get('/achats/nouveau')->assertStatus($nouveau);
        $this->get('/lots')->assertStatus($lots);
        $this->get("/lots/{$this->lot->id}")->assertStatus($lots);
    }

    #[Test]
    public function l_agent_achete_500_kg_a_un_producteur_sous_pret_depuis_l_ecran(): void
    {
        Parametre::query()->create(['cle' => CleParametre::RegleRemboursementNature, 'valeur' => RegleValorisationNature::PrixAchat->value]);
        $pret = $this->pretDecaisse(100_000);
        $this->actingAs($this->agent);

        Livewire::test(FormulaireAchat::class)
            ->assertSet('prixKg', '400')
            ->assertSet('compteId', (string) $this->caisseAgent->id)
            ->set('lotId', (string) $this->lot->id)
            ->set('producteurId', $this->producteur->id)
            ->assertSet('pretId', $pret->id)
            ->set('poidsBrutKg', '505')
            ->set('tareKg', '5')
            ->set('prixKg', '425')
            ->set('humidite', '8,5')
            ->set('kor', '48,50')
            ->set('grainage', '190')
            // Vide = ce qu'il faut pour solder : 100 000 ÷ 425 → 235,295 kg.
            ->assertSeeHtml('id="apercu-net">500 kg</dd>')
            ->assertSeeHtml('id="apercu-retenu">235,295 kg · 100'.self::FINE.'000 FCFA</dd>')
            ->assertSeeHtml('id="apercu-especes">112'.self::FINE.'500 FCFA</dd>')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('achats'));

        $achat = Achat::firstOrFail();
        $this->assertSame(StatutAchat::Valide, $achat->statut);
        $this->assertSame(85, $achat->humidite_pour_mille);
        $this->assertSame(4_850, $achat->kor_centieme_lbs);
        $this->assertSame(235_295, $achat->grammes_rembourses);
        $pret->refresh();
        $this->assertSame(0, $pret->restantDu());
        $this->assertSame(StatutPret::Solde, $pret->statut);
        $this->assertSame(500_000, $this->lot->stock());
    }

    #[Test]
    public function sans_regle_choisie_l_ecran_l_explique_et_l_achat_avec_pret_est_refuse(): void
    {
        $this->pretDecaisse(100_000);
        $this->actingAs($this->agent);

        Livewire::test(FormulaireAchat::class)
            ->set('lotId', (string) $this->lot->id)
            ->set('producteurId', $this->producteur->id)
            ->set('poidsBrutKg', '100')
            ->assertSeeHtml('id="regle-manquante"')
            ->assertSee('question 3')
            ->call('enregistrer')
            ->assertHasErrors('achat');

        $this->assertSame(0, Achat::count());
    }

    #[Test]
    public function la_direction_choisit_la_regle_dans_les_parametres(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(Parametres::class)
            ->assertSee('Valorisation des remboursements en kilos')
            ->call('modifier', CleParametre::RegleRemboursementNature->value)
            ->set('valeur', 'n_importe_quoi')
            ->call('enregistrer')
            ->assertHasErrors('valeur')
            ->set('valeur', RegleValorisationNature::PrixReferencePret->value)
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Prix de référence fixé dans le prêt');

        $this->assertSame(RegleValorisationNature::PrixReferencePret, Parametre::regleRemboursementNature());
    }

    #[Test]
    public function un_achat_au_dessus_du_seuil_se_valide_depuis_la_liste_par_un_autre(): void
    {
        Parametre::query()->where('cle', CleParametre::SeuilValidationAchat)->update(['valeur' => '10000']);
        $this->actingAs($this->agent);
        Livewire::test(FormulaireAchat::class)
            ->set('lotId', (string) $this->lot->id)
            ->set('producteurId', $this->producteur->id)
            ->set('pretId', '')
            ->set('poidsBrutKg', '100')
            ->call('enregistrer')
            ->assertHasNoErrors();
        $achat = Achat::firstOrFail();
        $this->assertSame(StatutAchat::AValider, $achat->statut);
        $this->assertSame(0, $this->lot->stock());

        $this->actingAs($this->comptable);
        Livewire::test(ListeAchats::class)
            ->assertSeeHtml("wire:click=\"valider('{$achat->id}')\"")
            ->call('valider', $achat->id)
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Achat validé'));

        $this->assertSame(100_000, $this->lot->stock());
        $this->assertSame(960_000, $this->caisseAgent->solde());
    }

    #[Test]
    public function l_agent_ne_voit_que_ses_achats_et_ne_valide_pas(): void
    {
        Parametre::query()->where('cle', CleParametre::SeuilValidationAchat)->update(['valeur' => '1']);
        $this->actingAs($this->agent);
        Livewire::test(FormulaireAchat::class)->set('lotId', (string) $this->lot->id)->set('producteurId', $this->producteur->id)
            ->set('pretId', '')->set('poidsBrutKg', '10')->call('enregistrer')->assertHasNoErrors();

        Livewire::test(ListeAchats::class)
            ->assertSee('Coulibaly Awa')
            ->assertDontSeeHtml('wire:click="valider(')
            ->call('valider', Achat::firstOrFail()->id)
            ->assertForbidden();
    }

    #[Test]
    public function creer_un_lot_puis_transferer_et_inventorier_depuis_sa_fiche(): void
    {
        $this->actingAs($this->comptable);
        $autre = Magasin::factory()->create(['nom' => 'Magasin port']);

        Livewire::test(ListeLots::class)
            ->call('ouvrir')
            ->assertSet('campagneId', (string) $this->campagne->id)
            ->set('magasinId', (string) $this->magasin->id)
            ->call('creer')
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Lot LOT-00002 créé.'));

        $this->actingAs($this->agent);
        Livewire::test(FormulaireAchat::class)->set('lotId', (string) $this->lot->id)->set('producteurId', $this->producteur->id)
            ->set('pretId', '')->set('poidsBrutKg', '300')->call('enregistrer')->assertHasNoErrors();
        $this->actingAs($this->comptable);

        Livewire::test(FicheLot::class, ['lot' => $this->lot])
            ->assertSeeHtml('id="stock-total">300 kg</span>')
            ->call('ouvrir', 'transfert')
            ->set('magasinId', (string) $this->magasin->id)
            ->set('magasinDestinationId', (string) $autre->id)
            ->set('poidsKg', '120,5')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->call('ouvrir', 'inventaire')
            ->set('magasinId', (string) $this->magasin->id)
            ->set('poidsKg', '175')
            ->set('motif', 'Inventaire de fin de mois')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSeeHtml('id="stock-total">295,500 kg</span>');

        $this->assertSame(175_000, $this->lot->stock($this->magasin->id));
        $this->assertSame(120_500, $this->lot->stock($autre->id));
    }

    #[Test]
    public function encaisser_un_remboursement_en_especes_depuis_la_fiche_du_pret(): void
    {
        $pret = $this->pretDecaisse(300_000);
        $this->actingAs($this->comptable);

        Livewire::test(FichePret::class, ['pret' => $pret])
            ->call('ouvrirRemboursement')
            ->assertSet('montantRemboursement', '300000')
            ->set('compteRemboursementId', (string) $this->caisseCentrale->id)
            ->set('montantRemboursement', '120 000')
            ->set('referenceRemboursement', 'RECU-7')
            ->call('encaisserRemboursement')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Remboursement encaissé'))
            ->assertSeeHtml('id="restant-du">180'.self::FINE.'000 FCFA</dd>');

        $this->assertSame(180_000, $pret->refresh()->restantDu());
    }

    #[Test]
    public function le_bon_d_achat_pdf_est_servi_a_l_acheteur_et_au_valideur_seulement(): void
    {
        $achat = Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => $this->producteur->id, 'date_achat' => now()->subMinute(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'humidite_pour_mille' => 85, 'kor_centieme_lbs' => 4_805, 'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);

        foreach ([$this->agent, $this->comptable] as $qui) {
            $reponse = $this->actingAs($qui)->get(route('achats.bon', $achat))->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', (string) $reponse->getContent());
        }
        $this->actingAs(User::factory()->role(Role::Agent)->create())->get(route('achats.bon', $achat))->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Investisseur)->create())->get(route('achats.bon', $achat))->assertForbidden();

        // Contenu : la vue du PDF, rendue en HTML.
        $html = view('achats.bon-achat', ['achat' => $achat, 'mention' => null, 'valeurRetenue' => 0, 'restantDu' => null])->render();
        foreach (['505 kg', '500 kg', '8,5 %', '48,05', '212'.self::FINE.'500', 'Payé en espèces', $achat->reference] as $attendu) {
            $this->assertStringContainsString($attendu, $html);
        }
    }
}
