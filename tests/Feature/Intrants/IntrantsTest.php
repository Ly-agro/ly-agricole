<?php

namespace Tests\Feature\Intrants;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutPret;
use App\Enums\TypeMouvementIntrant;
use App\Enums\UniteIntrant;
use App\Exceptions\OperationRefusee;
use App\Exceptions\RegistreImmuableException;
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
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntrantsTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;

    private User $direction;

    private User $agent;

    private Magasin $magasin;

    private Intrant $npk;

    private CompteTresorerie $caisse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->magasin = Magasin::factory()->create(['nom' => 'Magasin central']);
        $this->npk = Intrant::query()->create(['nom' => 'NPK 15-15-15 — sac de 50 kg', 'unite' => UniteIntrant::Sac, 'prix_unitaire_fcfa' => 18_500]);
        $this->caisse = CompteTresorerie::factory()->create(['nom' => 'Caisse centrale']);
        Tresorerie::entree($this->caisse, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Parametre::query()->create(['cle' => CleParametre::SeuilValidationPret, 'valeur' => '5000000']);
    }

    private function pret(FormePret $forme, int $montant): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => Producteur::factory()->create()->id,
            'campagne_id' => Campagne::factory()->statut(StatutCampagne::Ouverte)->create()->id,
            'montant_fcfa' => $montant,
            'forme' => $forme,
            'echeance' => Carbon::today()->addMonths(6),
        ], $this->agent);

        return Prets::valider($pret, $this->direction);
    }

    private function entree(int $quantite): MouvementIntrant
    {
        return StockIntrants::entree($this->npk, $this->magasin, $quantite, Carbon::today(), $this->comptable);
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
    public function un_pret_mixte_especes_et_engrais_a_le_bon_restant_du(): void
    {
        // Livrable de la semaine 5 (docs/PLAN_IMPLEMENTATION.md).
        $this->entree(100);
        $pret = $this->pret(FormePret::Mixte, 1_000_000);

        // 20 sacs à 18 500 = 370 000 FCFA d'engrais, puis 630 000 en espèces.
        StockIntrants::distribuer($pret, $this->npk, $this->magasin, 20, Carbon::today(), $this->comptable);
        $pret->refresh();
        $this->assertSame(370_000, $pret->valeurIntrantsRemis());
        $this->assertSame(370_000, $pret->restantDu());
        $this->assertSame(630_000, $pret->resteARemettre());
        $this->assertSame(StatutPret::Valide, $pret->statut);

        Prets::decaisser($pret, [
            'compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes,
            'montant_fcfa' => 630_000, 'date' => Carbon::today(),
        ], $this->comptable, 'prets/recus/r.jpg');

        $pret->refresh();
        $this->assertSame(630_000, $pret->montantDecaisse());
        $this->assertSame(1_000_000, $pret->montantRemis());
        $this->assertSame(1_000_000, $pret->restantDu());
        $this->assertSame(0, $pret->resteARemettre());
        $this->assertSame(StatutPret::Decaisse, $pret->statut);
        $this->assertSame(80, $this->npk->stock($this->magasin->id));
        $this->assertSame(9_370_000, $this->caisse->solde());
    }

    #[Test]
    public function la_valeur_d_une_remise_est_figee_au_prix_du_jour(): void
    {
        $this->entree(50);
        $pret = $this->pret(FormePret::Intrants, 500_000);
        StockIntrants::distribuer($pret, $this->npk, $this->magasin, 10, Carbon::today(), $this->comptable);

        $this->npk->update(['prix_unitaire_fcfa' => 25_000]);

        $this->assertSame(185_000, $pret->refresh()->restantDu());
        $mouvement = MouvementIntrant::query()->where('type', TypeMouvementIntrant::Distribution)->firstOrFail();
        $this->assertSame(18_500, $mouvement->prix_unitaire_fcfa);
        $this->assertSame(-185_000, $mouvement->valeur_fcfa);
    }

    #[Test]
    public function le_stock_est_la_somme_des_mouvements_et_ne_passe_jamais_en_negatif(): void
    {
        $autreMagasin = Magasin::factory()->create();
        $this->entree(30);
        StockIntrants::entree($this->npk, $autreMagasin, 5, Carbon::today(), $this->comptable);
        StockIntrants::corriger($this->npk, $this->magasin, TypeMouvementIntrant::Perte, 3, Carbon::today(), 'Sacs percés par les rongeurs', $this->comptable);

        $this->assertSame(27, $this->npk->stock($this->magasin->id));
        $this->assertSame(32, $this->npk->stock());
        $this->assertSame(27, (int) MouvementIntrant::query()->where('magasin_id', $this->magasin->id)->sum('quantite'));

        $pret = $this->pret(FormePret::Intrants, 5_000_000);
        $this->refusAttendu(fn () => StockIntrants::distribuer($pret, $this->npk, $this->magasin, 28, Carbon::today(), $this->comptable), 'Stock insuffisant');
        $this->refusAttendu(fn () => StockIntrants::corriger($this->npk, $autreMagasin, TypeMouvementIntrant::Ajustement, -6, Carbon::today(), 'Inventaire du mois', $this->comptable), 'Stock insuffisant');
        $this->assertSame(32, $this->npk->stock());
    }

    #[Test]
    public function on_ne_remet_jamais_plus_que_le_montant_du_pret(): void
    {
        $this->entree(100);
        $pret = $this->pret(FormePret::Intrants, 100_000);

        // 6 sacs = 111 000 > 100 000.
        $this->refusAttendu(fn () => StockIntrants::distribuer($pret, $this->npk, $this->magasin, 6, Carbon::today(), $this->comptable), 'plus que le reste à remettre');
        StockIntrants::distribuer($pret, $this->npk, $this->magasin, 5, Carbon::today(), $this->comptable);

        $this->assertSame(92_500, $pret->refresh()->montantRemis());
        $this->assertSame(7_500, $pret->resteARemettre());
    }

    #[Test]
    public function la_forme_du_pret_decide_de_ce_qui_se_remet(): void
    {
        $this->entree(10);
        $especes = $this->pret(FormePret::Especes, 100_000);
        $intrants = $this->pret(FormePret::Intrants, 100_000);

        $this->refusAttendu(fn () => StockIntrants::distribuer($especes, $this->npk, $this->magasin, 1, Carbon::today(), $this->comptable), 'ne se remet pas en intrants');
        $this->refusAttendu(fn () => Prets::decaisser($intrants, [
            'compte_id' => $this->caisse->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => 1, 'date' => Carbon::today(),
        ], $this->comptable, 'r.jpg'), 'remettre des intrants');
    }

    #[Test]
    public function un_pret_non_valide_ne_recoit_rien(): void
    {
        $this->entree(10);
        $demande = Prets::demander([
            'producteur_id' => Producteur::factory()->create()->id,
            'campagne_id' => Campagne::factory()->statut(StatutCampagne::Ouverte)->create()->id,
            'montant_fcfa' => 100_000,
            'forme' => FormePret::Intrants,
            'echeance' => Carbon::today()->addMonth(),
        ], $this->agent);

        $this->refusAttendu(fn () => StockIntrants::distribuer($demande, $this->npk, $this->magasin, 1, Carbon::today(), $this->comptable), 'Seul un prêt validé');
    }

    #[Test]
    public function contre_passer_une_remise_la_rend_au_stock_et_rouvre_le_pret(): void
    {
        $this->entree(10);
        $pret = $this->pret(FormePret::Intrants, 185_000);
        $remise = StockIntrants::distribuer($pret, $this->npk, $this->magasin, 10, Carbon::today(), $this->comptable);
        $this->assertSame(StatutPret::Decaisse, $pret->refresh()->statut);

        $this->refusAttendu(fn () => StockIntrants::contrePasser($remise, 'non', $this->comptable), 'motif');
        $inverse = StockIntrants::contrePasser($remise, 'Sacs remis au mauvais producteur', $this->comptable);

        $pret->refresh();
        $this->assertSame(10, $this->npk->stock($this->magasin->id));
        $this->assertSame(0, $pret->restantDu());
        $this->assertSame(StatutPret::Valide, $pret->statut);
        $this->assertSame($remise->id, $inverse->annule_id);
        $this->assertSame(2, MouvementIntrant::query()->where('pret_id', $pret->id)->count());

        $this->refusAttendu(fn () => StockIntrants::contrePasser($remise, 'Encore une fois', $this->comptable), 'déjà été contre-passé');
        $this->refusAttendu(fn () => StockIntrants::contrePasser($inverse, 'Annuler l\'annulation', $this->comptable), 'ne se contre-passe pas');
    }

    #[Test]
    public function contre_passer_une_entree_deja_distribuee_est_refuse(): void
    {
        $entree = $this->entree(10);
        StockIntrants::distribuer($this->pret(FormePret::Intrants, 1_000_000), $this->npk, $this->magasin, 8, Carbon::today(), $this->comptable);

        $this->refusAttendu(fn () => StockIntrants::contrePasser($entree, 'Entrée en double', $this->comptable), 'Stock insuffisant');
    }

    #[Test]
    public function un_mouvement_d_intrant_ne_se_modifie_ni_ne_se_supprime(): void
    {
        // Invariant 6 sur mouvements_intrants.
        $mouvement = $this->entree(10);

        foreach ([fn () => $mouvement->update(['quantite' => 1000]), fn () => $mouvement->delete(), fn () => MouvementIntrant::query()->update(['quantite' => 0])] as $tentative) {
            try {
                $tentative();
                $this->fail('Registre modifié.');
            } catch (RegistreImmuableException) {
            }
        }

        $this->assertSame(10, $this->npk->stock());
    }

    #[Test]
    public function droits_et_saisies_invalides(): void
    {
        $this->refusAttendu(fn () => StockIntrants::entree($this->npk, $this->magasin, 5, Carbon::today(), $this->agent), 'Votre rôle');
        $this->refusAttendu(fn () => $this->entree(0), 'différent de zéro');
        $this->refusAttendu(fn () => StockIntrants::entree($this->npk, $this->magasin, 5, Carbon::tomorrow(), $this->comptable), 'futur');
        $this->refusAttendu(fn () => StockIntrants::corriger($this->npk, $this->magasin, TypeMouvementIntrant::Perte, 1, Carbon::today(), '', $this->comptable), 'motif');

        $this->npk->update(['actif' => false]);
        $this->refusAttendu(fn () => $this->entree(5), 'désactivé');

        $this->assertSame(0, MouvementIntrant::count());
    }
}
