<?php

namespace Tests\Feature\Rapports;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\RegleValorisationNature;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
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
use App\Services\Rapports;
use App\Services\Remboursements;
use App\Services\Stock;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Livrable de la semaine 10 : la direction répond sans aide à « combien reste dû,
 * combien en stock, combien en caisse » — et chaque chiffre est une somme de registres.
 */
class RapportsTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $centrale;

    private CompteTresorerie $caisseAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create(['produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id]);
        $this->centrale = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create(['nom' => 'Caisse agent']);
        Tresorerie::entree($this->centrale, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->centrale, $this->caisseAgent, 1_000_000, Carbon::today(), 'Avance', $this->direction);
        foreach ([CleParametre::SeuilValidationAchat, CleParametre::SeuilValidationPret] as $cle) {
            Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => '5000000']);
        }
        Parametre::query()->updateOrCreate(['cle' => CleParametre::RegleRemboursementNature], ['valeur' => RegleValorisationNature::PrixAchat->value]);
    }

    private function pret(int $montant, Carbon $echeance): Pret
    {
        $pret = Prets::demander(['producteur_id' => Producteur::factory()->create()->id, 'campagne_id' => $this->campagne->id,
            'montant_fcfa' => $montant, 'forme' => FormePret::Especes, 'echeance' => $echeance], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, ['compte_id' => $this->centrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => $montant,
            'date' => Carbon::today()], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    /** 2 prêts (300 000 dont 100 000 remboursés, 200 000 échu), 500 kg achetés, 5 kg perdus. */
    private function scenario(): void
    {
        $pret = $this->pret(300_000, Carbon::today()->addMonths(3));
        Remboursements::especes($pret, $this->centrale, 100_000, Carbon::today(), $this->comptable);
        $this->travelTo(Carbon::today()->subDays(10));
        $echu = $this->pret(200_000, Carbon::today()->addDays(2));
        $this->travelBack();

        Achats::enregistrer(['campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => $echu->producteur_id, 'date_achat' => now()->subMinute(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'prix_kg_fcfa' => 400, 'compte_id' => $this->caisseAgent->id], $this->agent);
        Stock::perte($this->lot, $this->lot->magasin, 5_000, Carbon::today(), 'Séchage au magasin', $this->comptable);
    }

    #[Test]
    public function combien_reste_du_combien_en_stock_combien_en_caisse(): void
    {
        $this->scenario();

        $s = app(Rapports::class)->synthese();

        // Restant dû = remis − remboursé = (300 000 + 200 000) − 100 000.
        $this->assertSame(400_000, $s['restant_du']);
        $this->assertSame(1, $s['prets_echus']);
        $this->assertSame([['produit' => $this->campagne->produit->nom, 'grammes' => 495_000]], $s['stock']);
        // 5 000 000 − 500 000 versés + 100 000 remboursés − 200 000 payés pour 500 kg.
        $this->assertSame(4_400_000, $s['tresorerie']);
        $this->assertSame($this->centrale->solde() + $this->caisseAgent->solde(), $s['tresorerie']);

        $this->actingAs($this->direction)->get(route('rapports'))->assertOk()
            ->assertSeeHtml('tabular-nums">400'.self::FINE.'000 FCFA</p>')
            ->assertSee('495 kg')
            ->assertSeeHtml('tabular-nums">4'.self::FINE.'400'.self::FINE.'000 FCFA</p>');
    }

    #[Test]
    public function le_portefeuille_detaille_chaque_pret_et_ses_totaux(): void
    {
        $this->scenario();

        $t = app(Rapports::class)->portefeuille();

        $this->assertCount(2, $t->lignes);
        $this->assertSame([500_000, 500_000, 100_000, 400_000], array_slice($t->totaux ?? [], 5, 4));
        $echus = array_values(array_filter($t->lignes, fn ($l) => $l[9] !== ''));
        $this->assertCount(1, $echus);
        $this->assertStringContainsString('échu depuis 8 j', (string) $echus[0][9]);
    }

    #[Test]
    public function les_ecarts_de_poids_comparent_achats_et_stock_en_entiers(): void
    {
        $this->scenario();

        $ligne = app(Rapports::class)->ecarts()->lignes[0];

        // [lot, produit, achats, pertes, inventaires, corrections, autres sorties, stock, écart, ‰]
        $this->assertSame([500_000, -5_000, 0, 0, 0, 495_000, -5_000, -10], array_slice($ligne, 2));
    }

    #[Test]
    public function une_vente_n_est_pas_un_ecart_de_poids(): void
    {
        $this->scenario();
        // Sortie de vente (module de phase 2), écrite directement : le type n'existe pas encore ici.
        DB::table('mouvements_stock')->insert(['lot_id' => $this->lot->id, 'magasin_id' => $this->lot->magasin_id, 'type' => 'sortie_vente',
            'grammes' => -400_000, 'date_mouvement' => today(), 'cree_par' => $this->comptable->id, 'created_at' => now()]);

        $ligne = app(Rapports::class)->ecarts()->lignes[0];

        $this->assertSame([500_000, -5_000, 0, 0, -400_000, 95_000, -5_000, -10], array_slice($ligne, 2));
    }

    #[Test]
    public function alertes_seuil_non_defini_tout_ecart_est_signale_puis_seulement_au_dela_du_seuil(): void
    {
        $this->scenario();

        $alertes = fn () => array_column(app(Rapports::class)->alertes()->lignes, 0);
        $this->assertContains('Prêt échu non soldé', $alertes());
        $this->assertContains('Écart de poids', $alertes());

        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilAlerteEcartPoids], ['valeur' => '20']);
        $this->assertNotContains('Écart de poids', $alertes(), '1 % d\'écart, seuil 2 % : pas d\'alerte.');

        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilAlerteEcartPoids], ['valeur' => '10']);
        $this->assertContains('Écart de poids', $alertes());
    }

    #[Test]
    public function exports_pdf_et_excel_avec_des_nombres_additionnables(): void
    {
        $this->scenario();
        $this->actingAs($this->comptable);

        $pdf = $this->get(route('rapports.voir', ['prets', 'format' => 'pdf']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());

        $csv = $this->get(route('rapports.voir', ['prets', 'format' => 'excel']))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\u{FEFF}\"Portefeuille de prêts", $csv);
        $this->assertStringContainsString('Restant dû (FCFA)', $csv);
        // Totaux bruts, sans espace de milliers : Excel peut les additionner.
        $this->assertStringContainsString(';500000;500000;100000;400000;', $csv);

        $stock = $this->get(route('rapports.voir', ['stock', 'format' => 'excel']))->streamedContent();
        $this->assertStringContainsString(';495,000', $stock);
    }

    #[Test]
    public function chaque_rapport_s_affiche_et_seules_direction_et_comptable_y_ont_acces(): void
    {
        $this->scenario();

        foreach (['prets', 'stock', 'caisses', 'ecarts', 'alertes'] as $r) {
            $this->actingAs($this->comptable)->get(route('rapports.voir', $r))->assertOk();
        }
        $this->actingAs($this->comptable)->get('/rapports/inconnu')->assertNotFound();

        foreach ([Role::Agent, Role::Admin, Role::Investisseur, Role::Agronome] as $role) {
            $this->actingAs(User::factory()->role($role)->create())->get(route('rapports'))->assertForbidden();
        }
    }
}
