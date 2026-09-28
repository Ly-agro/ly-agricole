<?php

namespace Tests\Feature\Intrants;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutPret;
use App\Enums\UniteIntrant;
use App\Livewire\Intrants\StockIntrant;
use App\Livewire\Prets\FichePret;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Intrant;
use App\Models\Magasin;
use App\Models\MouvementIntrant;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Prets;
use App\Services\StockIntrants;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EcransIntrantsTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $comptable;

    private User $direction;

    private Magasin $magasin;

    private Intrant $npk;

    private CompteTresorerie $caisse;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->comptable = User::factory()->role(Role::Comptable)->create(['nom' => 'Adjoua Comptable']);
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->magasin = Magasin::factory()->create(['nom' => 'Magasin central']);
        $this->npk = Intrant::query()->create(['nom' => 'NPK 15-15-15 — sac de 50 kg', 'unite' => UniteIntrant::Sac, 'prix_unitaire_fcfa' => 18_500]);
        $this->caisse = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
        Tresorerie::entree($this->caisse, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Parametre::query()->create(['cle' => CleParametre::SeuilValidationPret, 'valeur' => '5000000']);
    }

    private function pret(FormePret $forme, int $montant): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => Producteur::factory()->create(['nom' => 'Coulibaly', 'prenoms' => 'Awa'])->id,
            'campagne_id' => Campagne::factory()->statut(StatutCampagne::Ouverte)->create()->id,
            'montant_fcfa' => $montant,
            'forme' => $forme,
            'echeance' => Carbon::today()->addMonths(6),
        ], User::factory()->role(Role::Agent)->create());

        return Prets::valider($pret, $this->direction);
    }

    /** @return array<string, array{Role, int}> */
    public static function acces(): array
    {
        return [
            'direction' => [Role::Direction, 200],
            'comptable' => [Role::Comptable, 200],
            'agent' => [Role::Agent, 403],
            'agronome' => [Role::Agronome, 403],
            'admin' => [Role::Admin, 403],
            'investisseur' => [Role::Investisseur, 403],
        ];
    }

    #[Test]
    #[DataProvider('acces')]
    public function acces_au_stock_d_intrants(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create())->get('/intrants')->assertStatus($attendu);
    }

    #[Test]
    public function l_ecran_de_stock_enregistre_une_entree_et_affiche_le_total(): void
    {
        $this->actingAs($this->comptable);

        Livewire::test(StockIntrant::class)
            ->call('ouvrir', 'entree')
            ->set('intrantId', (string) $this->npk->id)
            ->set('magasinId', (string) $this->magasin->id)
            ->set('quantite', '120')
            ->set('motif', 'Livraison fournisseur BL 2231')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Mouvement enregistré.')
            ->assertSeeHtml('id="total-'.$this->npk->id.'">120 sacs</td>');

        $this->assertSame(120, $this->npk->stock());
    }

    #[Test]
    public function une_perte_sans_motif_ou_au_dela_du_stock_est_refusee_a_l_ecran(): void
    {
        $this->actingAs($this->comptable);
        StockIntrants::entree($this->npk, $this->magasin, 5, Carbon::today(), $this->comptable);

        $ecran = Livewire::test(StockIntrant::class)
            ->call('ouvrir', 'perte')
            ->set('intrantId', (string) $this->npk->id)
            ->set('magasinId', (string) $this->magasin->id)
            ->set('quantite', '6')
            ->call('enregistrer')
            ->assertHasErrors(['motif' => 'required']);

        $ecran->set('motif', 'Sacs mouillés par la pluie')
            ->call('enregistrer')
            ->assertHasErrors('quantite')
            ->assertSee('Stock insuffisant');

        $this->assertSame(5, $this->npk->stock());
    }

    #[Test]
    public function un_pret_mixte_se_remet_en_engrais_puis_en_especes_depuis_sa_fiche(): void
    {
        StockIntrants::entree($this->npk, $this->magasin, 100, Carbon::today(), $this->comptable);
        $pret = $this->pret(FormePret::Mixte, 1_000_000);
        $this->actingAs($this->comptable);

        $fiche = Livewire::test(FichePret::class, ['pret' => $pret])
            ->assertSee('Remettre des intrants')
            ->assertSeeHtml('Verser de l\'argent')
            ->call('ouvrirRemise')
            ->set('intrantId', (string) $this->npk->id)
            ->set('magasinId', (string) $this->magasin->id)
            ->set('quantiteIntrant', '20')
            ->assertSee('Valeur au prix du jour')
            ->assertSee('370'.self::FINE.'000 FCFA')
            ->call('remettreIntrants')
            ->assertHasNoErrors()
            ->assertSee('Intrants remis')
            ->assertSeeHtml('id="restant-du">370'.self::FINE.'000 FCFA</dd>')
            ->assertSeeHtml('id="reste">630'.self::FINE.'000 FCFA</dd>');

        $fiche->call('ouvrirDecaissement')
            ->assertSet('montant', '630000')
            ->assertSet('modeVersement', 'especes')
            ->set('compteId', (string) $this->caisse->id)
            ->set('recu', UploadedFile::fake()->image('recu.jpg'))
            ->call('decaisser')
            ->assertHasNoErrors()
            ->assertSeeHtml('id="restant-du">1'.self::FINE.'000'.self::FINE.'000 FCFA</dd>')
            ->assertSeeHtml('id="detail-remis">argent 630'.self::FINE.'000 FCFA · intrants 370'.self::FINE.'000 FCFA</dd>');

        $this->assertSame(StatutPret::Decaisse, $pret->refresh()->statut);
    }

    #[Test]
    public function un_pret_en_intrants_n_offre_pas_de_versement_d_argent(): void
    {
        $pret = $this->pret(FormePret::Intrants, 500_000);
        $this->actingAs($this->comptable);

        Livewire::test(FichePret::class, ['pret' => $pret])
            ->assertSee('Remettre des intrants')
            ->assertDontSeeHtml('Verser de l\'argent');
    }

    #[Test]
    public function une_remise_trop_chere_ou_sans_stock_est_expliquee(): void
    {
        StockIntrants::entree($this->npk, $this->magasin, 3, Carbon::today(), $this->comptable);
        $pret = $this->pret(FormePret::Intrants, 50_000);
        $this->actingAs($this->comptable);

        $fiche = Livewire::test(FichePret::class, ['pret' => $pret])
            ->call('ouvrirRemise')
            ->set('intrantId', (string) $this->npk->id)
            ->set('magasinId', (string) $this->magasin->id)
            ->set('quantiteIntrant', '3')
            ->call('remettreIntrants')
            ->assertHasErrors('quantiteIntrant')
            ->assertSee('plus que le reste à remettre');

        $grosPret = $this->pret(FormePret::Intrants, 5_000_000);
        Livewire::test(FichePret::class, ['pret' => $grosPret])
            ->call('ouvrirRemise')
            ->set('intrantId', (string) $this->npk->id)
            ->set('magasinId', (string) $this->magasin->id)
            ->set('quantiteIntrant', '4')
            ->call('remettreIntrants')
            ->assertSee('Stock insuffisant');

        $this->assertSame(3, $this->npk->stock());
    }

    #[Test]
    public function le_recu_pdf_d_une_remise_est_un_pdf_et_refuse_une_remise_d_un_autre_pret(): void
    {
        StockIntrants::entree($this->npk, $this->magasin, 10, Carbon::today(), $this->comptable);
        $pret = $this->pret(FormePret::Mixte, 1_000_000);
        $remise = StockIntrants::distribuer($pret, $this->npk, $this->magasin, 4, Carbon::today(), $this->comptable);
        $versement = Prets::decaisser($pret, [
            'compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => 100_000, 'date' => Carbon::today(),
        ], $this->comptable, 'prets/recus/r.jpg');
        $autrePret = $this->pret(FormePret::Intrants, 100_000);
        $this->actingAs($this->comptable);

        foreach ([route('prets.recu-pdf', [$pret, 'intrants', $remise->id]), route('prets.recu-pdf', [$pret, 'argent', $versement->id])] as $url) {
            $reponse = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF-', (string) $reponse->getContent());
        }

        $this->get(route('prets.recu-pdf', [$autrePret, 'intrants', $remise->id]))->assertNotFound();
        $this->actingAs(User::factory()->role(Role::Investisseur)->create())
            ->get(route('prets.recu-pdf', [$pret, 'intrants', $remise->id]))->assertForbidden();
    }

    #[Test]
    public function le_recu_montre_la_remise_le_total_remis_et_le_restant_du(): void
    {
        StockIntrants::entree($this->npk, $this->magasin, 10, Carbon::today(), $this->comptable);
        $pret = $this->pret(FormePret::Intrants, 1_000_000);
        StockIntrants::distribuer($pret, $this->npk, $this->magasin, 4, Carbon::today(), $this->comptable);
        $pret->load('producteur.village', 'campagne.produit');

        $html = view('prets.recu-remise', [
            'pret' => $pret,
            'numero' => 'R-TEST',
            'date' => '01/01/2027',
            'lignes' => [['libelle' => 'NPK 15-15-15 — sac de 50 kg', 'quantite' => '4 sacs', 'prix' => 18_500, 'valeur' => 74_000]],
            'remis' => $pret->montantRemis(),
            'restantDu' => $pret->restantDu(),
        ])->render();

        $this->assertStringContainsString('Coulibaly Awa', $html);
        $this->assertStringContainsString('4 sacs', $html);
        $this->assertStringContainsString('74'.self::FINE.'000 FCFA', $html);
        $this->assertStringContainsString('Restant dû', $html);
        $this->assertStringContainsString('Aucun intérêt', $html);
        $this->assertSame(1, MouvementIntrant::query()->where('pret_id', $pret->id)->count());
    }
}
