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
use App\Enums\TypeMouvementStock;
use App\Enums\TypeRemboursement;
use App\Exceptions\OperationRefusee;
use App\Exceptions\RegistreImmuableException;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\MouvementStock;
use App\Models\MouvementTresorerie;
use App\Models\Parametre;
use App\Models\Pisteur;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\Remboursement;
use App\Models\User;
use App\Services\Achats;
use App\Services\Prets;
use App\Services\Remboursements;
use App\Services\Stock;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AchatsTest extends TestCase
{
    use RefreshDatabase;

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

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->magasin = Magasin::factory()->create();
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => $this->magasin->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        $this->caisseCentrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($this->caisseCentrale, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->caisseCentrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance achats', $this->direction);
        $this->producteur = Producteur::factory()->create();
        $this->parametre(CleParametre::SeuilValidationAchat, '5000000');
        $this->parametre(CleParametre::SeuilValidationPret, '5000000');
    }

    private function parametre(CleParametre $cle, ?string $valeur): void
    {
        Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }

    private function pretDecaisse(int $montant = 300_000, ?int $prixReference = null): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => $this->producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => $montant,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5), 'prix_reference_kg_fcfa' => $prixReference,
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, [
            'compte_id' => $this->caisseCentrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => $montant, 'date' => Carbon::today(),
        ], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    /** @param  array<string, mixed>  $surcharge */
    private function acheter(array $surcharge = [], ?User $auteur = null): Achat
    {
        return Achats::enregistrer(array_merge([
            'campagne_id' => $this->campagne->id,
            'lot_id' => $this->lot->id,
            'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => $this->producteur->id,
            'date_achat' => now()->subMinute(),
            'poids_brut_g' => 505_000,
            'tare_g' => 5_000,
            'humidite_pour_mille' => 80,
            'kor_centieme_lbs' => 4_850,
            'grainage_noix_kg' => 190,
            'prix_kg_fcfa' => 425,
            'compte_id' => $this->caisseAgent->id,
        ], $surcharge), $auteur ?? $this->agent);
    }

    private function refusAttendu(callable $tentative, string $message): void
    {
        try {
            $tentative();
            $this->fail("Aucun refus, « $message » attendu.");
        } catch (OperationRefusee $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    #[Test]
    public function un_producteur_sous_pret_livre_500_kg_restant_du_lot_et_caisse_de_l_agent_bougent(): void
    {
        // Livrable de la semaine 6 (docs/PLAN_IMPLEMENTATION.md).
        $this->parametre(CleParametre::RegleRemboursementNature, RegleValorisationNature::PrixAchat->value);
        $pret = $this->pretDecaisse(300_000);
        $caisseAvant = $this->caisseAgent->solde();

        // 500 kg nets à 425 FCFA/kg = 212 500 ; 400 kg retenus (170 000) sur le prêt, 100 kg payés (42 500).
        $achat = $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 400_000]);

        $this->assertSame(StatutAchat::Valide, $achat->statut);
        $this->assertSame(500_000, $achat->poids_net_g);
        $this->assertSame(212_500, $achat->montant_fcfa);
        $this->assertSame(42_500, $achat->montant_especes_fcfa);

        $this->assertSame(130_000, $pret->refresh()->restantDu());
        $this->assertSame(500_000, $this->lot->stock());
        $this->assertSame(42_500, $caisseAvant - $this->caisseAgent->solde());

        $remboursement = Remboursement::query()->where('achat_id', $achat->id)->firstOrFail();
        $this->assertSame(TypeRemboursement::Nature, $remboursement->type);
        $this->assertSame(400_000, $remboursement->grammes);
        $this->assertSame(425, $remboursement->prix_kg_fcfa);
        $this->assertSame(RegleValorisationNature::PrixAchat, $remboursement->regle_valorisation);
    }

    #[Test]
    public function sans_regle_de_valorisation_choisie_un_achat_ne_rembourse_pas_de_pret(): void
    {
        // Question 3 non tranchée : le code ne choisit pas à la place de la direction.
        $pret = $this->pretDecaisse();

        $this->refusAttendu(fn () => $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 100_000]), 'question 3');
        $this->assertSame(0, Achat::count());
        $this->assertSame(0, $this->lot->stock());

        // Sans prêt, l'achat passe.
        $this->acheter();
        $this->assertSame(500_000, $this->lot->stock());
    }

    #[Test]
    public function regle_du_prix_de_reference_du_pret(): void
    {
        $this->parametre(CleParametre::RegleRemboursementNature, RegleValorisationNature::PrixReferencePret->value);
        $pret = $this->pretDecaisse(300_000, 350);

        $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 200_000]);

        // 200 kg à 350 (prix du prêt) = 70 000, même si l'achat est payé 425.
        $this->assertSame(230_000, $pret->refresh()->restantDu());
        $sansReference = $this->pretDecaisse(100_000);
        $this->refusAttendu(fn () => $this->acheter(['pret_id' => $sansReference->id, 'grammes_rembourses' => 1_000]), 'pas de prix de référence');
    }

    #[Test]
    public function le_restant_du_ne_devient_jamais_negatif_et_le_pret_est_solde(): void
    {
        // Invariant 1.
        $this->parametre(CleParametre::RegleRemboursementNature, RegleValorisationNature::PrixAchat->value);
        $pret = $this->pretDecaisse(100_000);

        // 500 kg retenus valent 212 500 > 100 000 restant dû : plafonné au restant dû.
        $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 500_000]);

        $pret->refresh();
        $this->assertSame(0, $pret->restantDu());
        $this->assertSame(StatutPret::Solde, $pret->statut);
        $this->refusAttendu(fn () => $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 1_000]), 'rien à rembourser');
    }

    #[Test]
    public function le_montant_est_arrondi_au_franc_une_seule_fois(): void
    {
        // 1 234,567 kg à 425 = 524 690,975 → 524 691.
        $achat = $this->acheter(['poids_brut_g' => 1_234_567, 'tare_g' => 0]);

        $this->assertSame(524_691, $achat->montant_fcfa);
        $this->assertSame(1_234_567, $this->lot->stock());
    }

    #[Test]
    public function au_dessus_du_seuil_rien_ne_bouge_avant_la_validation_par_un_autre(): void
    {
        $this->parametre(CleParametre::SeuilValidationAchat, '100000');
        $caisseAvant = $this->caisseAgent->solde();

        $achat = $this->acheter();
        $this->assertSame(StatutAchat::AValider, $achat->statut);
        $this->assertSame(0, $this->lot->stock());
        $this->assertSame($caisseAvant, $this->caisseAgent->solde());

        $this->refusAttendu(fn () => Achats::valider($achat, $this->agent), 'ne permet pas');
        $comptableAuteur = $this->acheter(['compte_id' => $this->caisseCentrale->id], $this->comptable);
        $this->refusAttendu(fn () => Achats::valider($comptableAuteur, $this->comptable), 'votre propre achat');

        Achats::valider($achat, $this->direction);
        $achat->refresh();
        $this->assertSame(StatutAchat::Valide, $achat->statut);
        $this->assertSame($this->direction->id, $achat->valide_par);
        $this->assertSame(500_000, $this->lot->stock());
        $this->assertSame(212_500, $caisseAvant - $this->caisseAgent->solde());
        $this->refusAttendu(fn () => Achats::valider($achat, $this->comptable), 'plus à valider');
    }

    #[Test]
    public function seuil_non_defini_tout_achat_attend_une_validation(): void
    {
        $this->parametre(CleParametre::SeuilValidationAchat, null);

        $this->assertSame(StatutAchat::AValider, $this->acheter(['poids_brut_g' => 6_000, 'tare_g' => 1_000])->statut);
    }

    #[Test]
    public function un_refus_ne_touche_ni_stock_ni_caisse(): void
    {
        $this->parametre(CleParametre::SeuilValidationAchat, '1');
        $achat = $this->acheter();

        Achats::refuser($achat, $this->comptable, 'Pesée non conforme');

        $this->assertSame(StatutAchat::Refuse, $achat->refresh()->statut);
        $this->assertSame(0, $this->lot->stock());
        $this->assertSame(0, MouvementTresorerie::query()->where('nature', NatureMouvement::AchatBordChamp)->count());
    }

    #[Test]
    public function saisies_refusees(): void
    {
        $fermee = Campagne::factory()->create();
        $lotFerme = Lot::query()->create(['produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => $this->magasin->id, 'statut' => StatutLot::Ferme, 'cree_par' => $this->comptable->id]);

        $this->refusAttendu(fn () => $this->acheter(['prix_kg_fcfa' => 399]), 'prix officiel bord-champ');
        $this->refusAttendu(fn () => $this->acheter(['tare_g' => 505_000]), 'Pesée incohérente');
        $this->refusAttendu(fn () => $this->acheter(['humidite_pour_mille' => 1_200]), 'humidité');
        $this->refusAttendu(fn () => $this->acheter(['kor_centieme_lbs' => 20_000]), 'KOR');
        $this->refusAttendu(fn () => $this->acheter(['campagne_id' => $fermee->id]), 'pas ouverte');
        $this->refusAttendu(fn () => $this->acheter(['lot_id' => $lotFerme->id]), 'pas un lot ouvert');
        $this->refusAttendu(fn () => $this->acheter(['compte_id' => $this->caisseCentrale->id]), 'sa propre caisse');
        $this->refusAttendu(fn () => $this->acheter(['date_achat' => now()->addHour()]), 'futur');
        $this->refusAttendu(fn () => $this->acheter(['fournisseur_type' => TypeFournisseur::Cooperative]), 'coopérative');

        $this->assertSame(0, Achat::count());
    }

    #[Test]
    public function un_pisteur_ou_une_cooperative_peuvent_vendre(): void
    {
        $pisteur = Pisteur::query()->create(['nom' => 'Drissa pisteur']);

        $a = $this->acheter(['fournisseur_type' => TypeFournisseur::Pisteur, 'producteur_id' => null, 'pisteur_id' => $pisteur->id]);
        $b = $this->acheter(['fournisseur_type' => TypeFournisseur::Cooperative, 'producteur_id' => null, 'fournisseur_nom' => 'COOP Espoir']);

        $this->assertSame('Drissa pisteur', $a->nomFournisseur());
        $this->assertSame('COOP Espoir', $b->nomFournisseur());
        $this->assertSame(1_000_000, $this->lot->stock());
    }

    #[Test]
    public function transfert_perte_inventaire_et_stock_jamais_negatif(): void
    {
        // Invariant 2.
        $this->acheter();
        $autre = Magasin::factory()->create();

        Stock::transferer($this->lot, $this->magasin, $autre, 200_000, Carbon::today(), $this->comptable);
        $this->assertSame(300_000, $this->lot->stock($this->magasin->id));
        $this->assertSame(200_000, $this->lot->stock($autre->id));

        $this->refusAttendu(fn () => Stock::transferer($this->lot, $this->magasin, $autre, 300_001, Carbon::today(), $this->comptable), 'Stock insuffisant');

        Stock::perte($this->lot, $autre, 5_000, Carbon::today(), 'Sacs percés', $this->comptable);
        // Séchage : on compte 290 kg au lieu de 300 : l'écart de 10 kg devient un mouvement.
        $ajustement = Stock::inventaire($this->lot, $this->magasin, 290_000, Carbon::today(), 'Inventaire de fin de mois', $this->comptable);
        $this->assertSame(-10_000, $ajustement?->grammes);
        $this->assertNull(Stock::inventaire($this->lot, $this->magasin, 290_000, Carbon::today(), 'Recomptage', $this->comptable));

        $this->assertSame(485_000, $this->lot->stock());
        $this->assertSame(485_000, (int) MouvementStock::query()->where('lot_id', $this->lot->id)->sum('grammes'));
        $this->refusAttendu(fn () => Stock::perte($this->lot, $this->magasin, 1, Carbon::today(), 'x', $this->comptable), 'motif');
        $this->refusAttendu(fn () => Stock::perte($this->lot, $this->magasin, 1, Carbon::today(), 'Test agent', $this->agent), 'Votre rôle');
    }

    #[Test]
    public function contre_passer_un_transfert_rend_le_stock_et_l_entree_d_achat_ne_se_contre_passe_pas(): void
    {
        $achat = $this->acheter();
        $autre = Magasin::factory()->create();
        $transfert = Stock::transferer($this->lot, $this->magasin, $autre, 100_000, Carbon::today(), $this->comptable);

        Stock::contrePasser($transfert['entree'], 'Erreur de magasin', $this->comptable);
        $this->assertSame(500_000, $this->lot->stock($this->magasin->id));
        $this->assertSame(0, $this->lot->stock($autre->id));

        $entree = MouvementStock::query()->where('achat_id', $achat->id)->firstOrFail();
        $this->refusAttendu(fn () => Stock::contrePasser($entree, 'Annuler l\'achat', $this->comptable), 'suit l\'achat');
    }

    #[Test]
    public function remboursement_en_especes_et_sa_contre_passation(): void
    {
        $pret = $this->pretDecaisse(300_000);
        $caisse = $this->caisseCentrale->solde();

        $this->refusAttendu(fn () => Remboursements::especes($pret, $this->caisseCentrale, 300_001, Carbon::today(), $this->comptable), 'restant dû');
        $this->refusAttendu(fn () => Remboursements::especes($pret, $this->caisseCentrale, 1_000, Carbon::today(), $this->agent), 'Votre rôle');

        $r = Remboursements::especes($pret, $this->caisseCentrale, 300_000, Carbon::today(), $this->comptable, 'REÇU-12');
        $pret->refresh();
        $this->assertSame(0, $pret->restantDu());
        $this->assertSame(StatutPret::Solde, $pret->statut);
        $this->assertSame($caisse + 300_000, $this->caisseCentrale->solde());

        // La trésorerie ne corrige pas seule un remboursement : il faut passer par le prêt.
        $this->refusAttendu(fn () => Tresorerie::contrePasser(MouvementTresorerie::query()->findOrFail($r->mouvement_id), 'Erreur', $this->comptable), 'depuis le prêt');

        Remboursements::contrePasser($r, 'Billets faux rendus au producteur', $this->comptable);
        $pret->refresh();
        $this->assertSame(300_000, $pret->restantDu());
        $this->assertSame(StatutPret::Decaisse, $pret->statut);
        $this->assertSame($caisse, $this->caisseCentrale->solde());
    }

    #[Test]
    public function registres_immuables_du_stock_et_des_remboursements(): void
    {
        // Invariant 6 sur mouvements_stock et remboursements.
        $this->parametre(CleParametre::RegleRemboursementNature, RegleValorisationNature::PrixAchat->value);
        $pret = $this->pretDecaisse();
        $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 100_000]);
        $mouvement = MouvementStock::query()->where('type', TypeMouvementStock::EntreeAchat)->firstOrFail();
        $remboursement = Remboursement::query()->firstOrFail();

        foreach ([
            fn () => $mouvement->update(['grammes' => 1]), fn () => $mouvement->delete(), fn () => MouvementStock::query()->update(['grammes' => 1]),
            fn () => $remboursement->update(['montant_fcfa' => 1]), fn () => $remboursement->delete(), fn () => Remboursement::query()->delete(),
        ] as $tentative) {
            try {
                $tentative();
                $this->fail('Registre modifié.');
            } catch (RegistreImmuableException) {
            }
        }

        $this->assertSame(500_000, $this->lot->stock());
    }

    #[Test]
    public function des_grammes_de_campagne_au_dela_de_2_puissance_31_restent_exacts(): void
    {
        // 3 000 t = 3 × 10⁹ g (skill argent-et-kilos, § pièges) : sommes identiques sqlite / MySQL.
        // Seuil haut et caisse garnie : on ne teste ici que les sommes de grammes.
        $this->parametre(CleParametre::SeuilValidationAchat, '99999999999');
        Tresorerie::entree($this->caisseCentrale, 1_300_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds de test', $this->direction);
        foreach (range(1, 31) as $i) {
            $this->acheter(['poids_brut_g' => 100_000_000, 'tare_g' => 0, 'prix_kg_fcfa' => 400, 'compte_id' => $this->caisseCentrale->id], $this->comptable);
        }

        $this->assertSame(3_100_000_000, $this->lot->stock());
    }
}
