<?php

namespace Tests\Feature\Resultat;

use App\Enums\CleParametre;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeAcheteur;
use App\Enums\TypeFournisseur;
use App\Livewire\RapportCampagne\PointEtape;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Vente;
use App\Services\Achats;
use App\Services\Apports;
use App\Services\Depenses;
use App\Services\Encaissements;
use App\Services\RapportCampagne;
use App\Services\Tresorerie;
use App\Services\Ventes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Point d'étape du contrat de campagne (art. 18.1) : chiffres relus dans les registres, PDF, droits. */
class RapportCampagneTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    private CompteTresorerie $compteDedie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        $this->compteDedie = CompteTresorerie::factory()->create(['campagne_id' => $this->campagne->id, 'nom' => 'Fonds campagne']);
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance achats', $this->direction);
        foreach ([CleParametre::SeuilValidationAchat, CleParametre::SeuilValidationVente, CleParametre::SeuilValidationDepense] as $cle) {
            Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => '1000000000']);
        }
    }

    /** 2 M + 8 M d'investisseurs, 2,5 M de LY ; 500 kg achetés (212 500), 400 kg vendus (360 000) encaissés, 30 000 de transport. */
    private function scenario(): Vente
    {
        $a = User::factory()->role(Role::Investisseur)->create();
        $b = User::factory()->role(Role::Investisseur)->create();
        Apports::enregistrer($a->id, $this->campagne->id, $this->compteDedie->id, 2_000_000, Carbon::today(), $this->direction);
        Apports::enregistrer($b->id, $this->campagne->id, $this->compteDedie->id, 8_000_000, Carbon::today(), $this->direction);
        Apports::enregistrer(null, $this->campagne->id, $this->compteDedie->id, 2_500_000, Carbon::today(), $this->direction);

        Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => Producteur::factory()->create()->id, 'date_achat' => now()->subDay(), 'poids_brut_g' => 505_000,
            'tare_g' => 5_000, 'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);
        $vente = Ventes::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'type_acheteur' => TypeAcheteur::Exportateur,
            'acheteur_nom' => 'Ivoire Export SA', 'date_vente' => now()->subMinute(), 'poids_net_g' => 400_000, 'prix_kg_fcfa' => 900,
        ], $this->direction);
        Encaissements::encaisser($vente, $this->compteDedie, 200_000, Carbon::today(), $this->comptable);
        Depenses::saisir([
            'categorie_id' => CategorieDepense::query()->create(['nom' => 'Transport'])->id, 'compte_id' => $this->caisseAgent->id,
            'montant_fcfa' => 30_000, 'date_depense' => Carbon::today(), 'beneficiaire' => 'Transporteur', 'campagne_id' => $this->campagne->id,
        ], 'depenses/j.jpg', $this->agent);

        return $vente;
    }

    #[Test]
    public function le_point_d_etape_donne_les_chiffres_des_registres(): void
    {
        $this->scenario();

        $r = RapportCampagne::pointEtape($this->campagne, '  Démarrage des achats.  ');

        $this->assertSame(['investisseurs' => 10_000_000, 'nb_investisseurs' => 2, 'ly' => 2_500_000, 'total' => 12_500_000], $r['fonds']);
        $this->assertSame(['achete_g' => 500_000, 'nb_achats' => 1, 'vendu_g' => 400_000, 'nb_ventes' => 1, 'stock_g' => 100_000], $r['volumes']);
        $this->assertSame(212_500, $r['engage']['achats']);
        $this->assertSame([['categorie' => 'Transport', 'montant' => 30_000]], $r['engage']['depenses']);
        $this->assertSame(30_000, $r['engage']['total_depenses']);
        // Vente de 400 kg à 900 F = 360 000 ; 200 000 encaissés ⇒ 160 000 restent à encaisser.
        $this->assertSame(['facture' => 360_000, 'encaisse' => 200_000, 'reste' => 160_000], $r['ventes']);
        // Le compte dédié : 12 500 000 d'apports + 200 000 encaissés.
        $this->assertSame([['nom' => 'Fonds campagne', 'solde' => 12_700_000]], $r['tresorerie']['comptes']);
        $this->assertSame(12_700_000, $r['tresorerie']['total']);
        $this->assertSame('Démarrage des achats.', $r['evenements']);
    }

    #[Test]
    public function une_campagne_vide_donne_des_zeros_et_pas_d_erreur(): void
    {
        $vide = Campagne::factory()->create();

        $r = RapportCampagne::pointEtape($vide);

        $this->assertSame(0, $r['fonds']['total']);
        $this->assertSame(0, $r['volumes']['achete_g']);
        $this->assertSame([], $r['tresorerie']['comptes']);
        $this->assertSame('', $r['evenements']);
    }

    #[Test]
    public function les_evenements_sont_coupes_a_la_limite(): void
    {
        $r = RapportCampagne::pointEtape($this->campagne, str_repeat('é', RapportCampagne::MAX_EVENEMENTS + 500));

        $this->assertSame(RapportCampagne::MAX_EVENEMENTS, mb_strlen($r['evenements']));
    }

    #[Test]
    public function la_note_ne_contient_ni_resultat_ni_quote_part_ni_nom_de_producteur(): void
    {
        $this->scenario();

        $html = view('rapport-campagne.point-etape', RapportCampagne::pointEtape($this->campagne))->render();

        $this->assertStringContainsString('Point d\'étape de la campagne', $html);
        $this->assertStringContainsString('article 18.1', $html);
        $this->assertStringContainsString('12'."\u{202F}".'500'."\u{202F}".'000 FCFA', $html);
        $this->assertStringContainsString('Aucun événement particulier signalé', $html);
        foreach (['Résultat net', 'quote-part de', 'Ivoire Export'] as $interdit) {
            $this->assertStringNotContainsString($interdit, $html, "La note ne doit pas contenir « {$interdit} ».");
        }
    }

    #[Test]
    public function le_pdf_est_un_vrai_pdf_pour_la_direction(): void
    {
        $this->scenario();
        $this->actingAs($this->direction);

        $reponse = $this->post('/rapport-campagne/point-etape', ['campagne_id' => $this->campagne->id, 'evenements' => 'Retard de livraison à Korhogo.']);

        $reponse->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $reponse->getContent());
        $this->assertGreaterThan(1_500, strlen($reponse->getContent()));
    }

    #[Test]
    public function le_pdf_refuse_une_campagne_inconnue_ou_un_texte_trop_long(): void
    {
        $this->actingAs($this->direction);

        $this->post('/rapport-campagne/point-etape', ['campagne_id' => 999_999])->assertSessionHasErrors('campagne_id');
        $this->post('/rapport-campagne/point-etape', ['campagne_id' => $this->campagne->id, 'evenements' => str_repeat('x', RapportCampagne::MAX_EVENEMENTS + 1)])
            ->assertSessionHasErrors('evenements');
    }

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'comptable' => [Role::Comptable, 200], 'agent' => [Role::Agent, 403],
            'agronome' => [Role::Agronome, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function l_ecran_et_le_pdf_suivent_le_role(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/rapport-campagne')->assertStatus($attendu);
        $this->post('/rapport-campagne/point-etape', ['campagne_id' => $this->campagne->id])->assertStatus($attendu);
    }

    #[Test]
    public function l_ecran_montre_l_apercu_et_le_formulaire_du_pdf(): void
    {
        $this->scenario();
        $this->actingAs($this->direction);

        Livewire::test(PointEtape::class)
            ->assertSee('Fonds de la campagne')
            ->assertSee('12'."\u{202F}".'500'."\u{202F}".'000 FCFA')
            ->assertSee('Trésorerie disponible')
            ->assertSee('Principaux événements')
            ->assertSee('Télécharger le PDF');
    }
}
