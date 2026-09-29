<?php

namespace Tests\Feature\Notifications;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\PosteBudget;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Models\AbonnementPush;
use App\Models\Campagne;
use App\Models\CategorieDepense;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Producteur;
use App\Models\User;
use App\Notifications\AvisLy;
use App\Services\Achats;
use App\Services\Alertes;
use App\Services\Budgets;
use App\Services\Depenses;
use App\Services\Notifications;
use App\Services\Prets;
use App\Services\Push\ExpediteurPush;
use App\Services\Push\WebPushVapid;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Alertes quotidiennes (réponse 52) et avis de campagne. */
class AlertesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private User $agronome;

    private User $investisseur;

    private Campagne $campagne;

    private CompteTresorerie $centrale;

    private CompteTresorerie $caisseAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->agronome = User::factory()->role(Role::Agronome)->create();
        $this->investisseur = User::factory()->role(Role::Investisseur)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($this->centrale, 20_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance', $this->direction);
    }

    /** @return list<string> titres des avis reçus */
    private function titres(User $u): array
    {
        return $u->notifications()->oldest()->get()->map(fn ($n) => (string) $n->data['titre'])->all();
    }

    private function avisDe(User $u, string $titre): int
    {
        return count(array_filter($this->titres($u), fn (string $t) => str_starts_with($t, $titre)));
    }

    private function acheter(): void
    {
        $lot = Lot::query()->firstOrCreate(['campagne_id' => $this->campagne->id], [
            'produit_id' => $this->campagne->produit_id, 'magasin_id' => Magasin::factory()->create()->id,
            'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => Producteur::factory()->create()->id, 'date_achat' => now()->subHour(),
            'poids_brut_g' => 100_000, 'tare_g' => 0, 'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);
    }

    #[Test]
    public function une_saisie_en_attente_depuis_plus_de_48_h_est_rappelee_une_fois_par_jour(): void
    {
        $this->travel(-3)->days();
        $this->acheter(); // pas de seuil : à valider
        $this->travelBack();
        $this->acheter(); // récent : pas encore concerné

        $this->assertSame(2, Alertes::executer()['attente']); // comptable et direction
        $this->assertSame(0, Alertes::executer()['attente']); // relancé le même jour : rien

        $rappel = $this->comptable->notifications()->get()->first(fn ($n) => $n->data['titre'] === 'Saisies en attente depuis plus de 2 jours');
        $this->assertNotNull($rappel);
        $this->assertSame('1 achat à valider.', $rappel->data['texte']);
        $this->assertSame(0, $this->avisDe($this->agent, 'Saisies en attente'), 'l\'auteur ne valide pas sa saisie');

        $this->travel(1)->days();
        $this->assertSame(2, Alertes::executer()['attente']); // le lendemain, de nouveau
    }

    #[Test]
    public function les_prets_en_retard_vont_a_qui_voit_les_prets_et_a_personne_d_autre(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationPret], ['valeur' => '10000000']);
        $pret = Prets::valider(Prets::demander([
            'producteur_id' => Producteur::factory()->create(['nom' => 'Coulibaly'])->id, 'campagne_id' => $this->campagne->id,
            'montant_fcfa' => 300_000, 'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addDays(10),
        ], $this->agent), $this->direction);
        Prets::decaisser($pret, ['compte_id' => $this->centrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => 300_000, 'date' => Carbon::today()],
            $this->comptable, 'prets/recus/r.jpg');

        $this->assertSame(0, Alertes::executer()['retard']); // pas encore échu

        $this->travel(15)->days();
        $this->assertSame(1, Alertes::executer()['retard']);
        $this->assertSame(0, Alertes::executer()['retard']);

        foreach ([$this->direction, $this->comptable, $this->agent] as $u) {
            $this->assertSame(1, $this->avisDe($u, 'Prêts en retard'));
        }
        foreach ([$this->agronome, $this->investisseur] as $u) {
            $this->assertSame(0, $this->avisDe($u, 'Prêts en retard'));
        }
        $avis = $this->direction->notifications()->get()->first(fn ($n) => $n->data['titre'] === 'Prêts en retard');
        $this->assertSame('1 prêt en retard (échéance dépassée, pas soldé).', $avis->data['texte']);
        $this->assertStringNotContainsString('Coulibaly', $avis->data['texte']);
    }

    #[Test]
    public function un_budget_depasse_previent_une_fois_puis_de_nouveau_si_le_prevu_change(): void
    {
        Parametre::query()->updateOrCreate(['cle' => CleParametre::SeuilValidationDepense], ['valeur' => '10000000']);
        $transport = CategorieDepense::query()->create(['nom' => 'Transport']);
        Budgets::definir($this->campagne, PosteBudget::Categorie, $transport->id, 100_000, $this->direction);
        Depenses::saisir([
            'categorie_id' => $transport->id, 'compte_id' => $this->centrale->id, 'montant_fcfa' => 130_000,
            'date_depense' => Carbon::today(), 'beneficiaire' => 'Transporteur', 'campagne_id' => $this->campagne->id,
        ], 'depenses/justificatifs/r.jpg', $this->comptable);

        $this->assertSame(1, Alertes::executer()['budget']);
        $this->assertSame(0, Alertes::executer()['budget']);
        $this->assertSame(1, $this->avisDe($this->comptable, 'Budget dépassé : Transport'));
        $this->assertSame(0, $this->avisDe($this->agent, 'Budget dépassé'));

        Budgets::definir($this->campagne, PosteBudget::Categorie, $transport->id, 120_000, $this->direction, motif: 'Révisé');
        $this->assertSame(1, Alertes::executer()['budget']);
    }

    #[Test]
    public function l_etat_des_sauvegardes_du_rapport_d_alertes_va_a_qui_voit_les_rapports(): void
    {
        // Aucune restauration vérifiée sur une base neuve : le rapport « Alertes » le signale.
        $this->assertGreaterThanOrEqual(1, Alertes::executer()['rapports']);
        $this->assertSame(0, Alertes::executer()['rapports']);

        $this->assertNotSame([], array_filter($this->titres($this->direction), fn ($t) => str_starts_with($t, 'Sauvegarde')));
        $this->assertSame([], array_filter($this->titres($this->agent), fn ($t) => str_starts_with($t, 'Sauvegarde')));
    }

    #[Test]
    public function ouvrir_une_campagne_ou_changer_son_prix_previent_ceux_qui_achetent(): void
    {
        $nouvelle = Campagne::factory()->statut(StatutCampagne::Preparation)->create(['prix_officiel_kg_fcfa' => null]);
        $nouvelle->update(['statut' => StatutCampagne::Ouverte]);
        $nouvelle->update(['prix_officiel_kg_fcfa' => 450]);
        $nouvelle->update(['prix_officiel_kg_fcfa' => 475]);

        $avis = $this->agent->notifications()->oldest()->get()->map(fn ($n) => $n->data['titre'].' | '.$n->data['texte'])->all();
        $this->assertCount(3, $avis);
        $this->assertStringContainsString('ouverte', $avis[0]);
        $this->assertStringContainsString('prix officiel pas encore annoncé', $avis[0]);
        $this->assertMatchesRegularExpression('/450.FCFA\/kg\.$/u', $avis[1]);
        $this->assertMatchesRegularExpression('/475.FCFA\/kg \(avant : 450.FCFA\/kg\)\.$/u', $avis[2]);
        $this->assertSame([], $this->titres($this->agronome));
    }

    #[Test]
    public function le_push_d_un_avis_a_valider_ne_montre_ni_nom_ni_montant(): void
    {
        $envoye = null;
        $this->app->instance(WebPushVapid::class, new class($envoye) implements ExpediteurPush
        {
            public function __construct(private mixed &$envoye) {}

            public function envoyer(array $abonnements, array $message): array
            {
                $this->envoye = $message;

                return array_fill_keys(array_map(fn (AbonnementPush $a) => $a->id, $abonnements), 'ok');
            }
        });
        Notifications::abonner($this->comptable, AbonnementPush::WEB, 'https://push.exemple/c', 'cle', 'auth');

        $this->acheter();

        $this->assertIsArray($envoye);
        $this->assertStringContainsString('à valider', $envoye['titre']);
        $this->assertSame(AvisLy::TEXTE_PUSH, $envoye['texte']);
        // Le détail (fournisseur, montant) reste dans l'application.
        $this->assertStringContainsString('FCFA', (string) $this->comptable->notifications()->sole()->data['texte']);
    }
}
