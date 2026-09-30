<?php

namespace Tests\Feature\Notifications;

use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Livewire\Notifications\ListeNotifications;
use App\Models\AbonnementPush;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Producteur;
use App\Models\User;
use App\Notifications\AvisLy;
use App\Services\Achats;
use App\Services\Notifications;
use App\Services\Push\ExpediteurPush;
use App\Services\Push\FirebaseFcm;
use App\Services\Push\WebPushVapid;
use App\Services\Tresorerie;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Notifications (phase 2) : qui est prévenu, quand, et comment les appareils abonnés
 * sont tenus à jour. Les envois réels (Web Push, Firebase) sont remplacés par un faux.
 */
class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $caisseAgent;

    /** @var list<array{canal: string, abonnements: list<int>, message: array<string, mixed>}> */
    private array $envois = [];

    /** @var array<int, string> résultat forcé par abonnement */
    private array $resultatsForces = [];

    /** Simule une panne côté serveur (OpenSSL mal configuré, Firebase injoignable). */
    private bool $panneServeur = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::factory()->role(Role::Agent)->create(['nom' => 'Agent Koné']);
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['prix_officiel_kg_fcfa' => 400]);
        $this->lot = Lot::query()->create([
            'produit_id' => $this->campagne->produit_id, 'campagne_id' => $this->campagne->id,
            'magasin_id' => Magasin::factory()->create()->id, 'statut' => StatutLot::Ouvert, 'cree_par' => $this->comptable->id,
        ]);
        $centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($centrale, 5_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance', $this->direction);

        // Faux expéditeurs : on note ce qui partirait, et on rend le résultat voulu.
        foreach ([WebPushVapid::class => AbonnementPush::WEB, FirebaseFcm::class => AbonnementPush::FCM] as $classe => $canal) {
            $this->app->instance($classe, new class($this, $canal) implements ExpediteurPush
            {
                public function __construct(private NotificationsTest $test, private string $canal) {}

                public function envoyer(array $abonnements, array $message): array
                {
                    return $this->test->noterEnvoi($this->canal, $abonnements, $message);
                }
            });
        }
    }

    /**
     * @param  list<AbonnementPush>  $abonnements
     * @param  array<string, mixed>  $message
     * @return array<int, 'ok'|'disparu'|'echec'>
     */
    public function noterEnvoi(string $canal, array $abonnements, array $message): array
    {
        if ($this->panneServeur) {
            throw new \RuntimeException('Unable to create the key');
        }
        $ids = array_map(fn (AbonnementPush $a) => $a->id, $abonnements);
        $this->envois[] = ['canal' => $canal, 'abonnements' => $ids, 'message' => $message];

        /** @var array<int, 'ok'|'disparu'|'echec'> $r */
        $r = array_map(fn (int $id) => $this->resultatsForces[$id] ?? 'ok', array_combine($ids, $ids));

        return $r;
    }

    private function acheter(): Achat
    {
        // Aucun seuil défini : l'achat attend une validation (question 5).
        return Achats::enregistrer([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id,
            'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => Producteur::factory()->create(['nom' => 'Coulibaly', 'prenoms' => 'Awa'])->id,
            'date_achat' => now()->subHour(), 'poids_brut_g' => 505_000, 'tare_g' => 5_000,
            'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $this->agent);
    }

    #[Test]
    public function un_achat_a_valider_previent_ceux_qui_valident_mais_pas_son_auteur(): void
    {
        $achat = $this->acheter();

        foreach ([$this->comptable, $this->direction] as $validateur) {
            $avis = $validateur->notifications()->sole();
            $this->assertSame(AvisLy::class, $avis->type);
            $this->assertSame("Achat {$achat->reference} à valider", $avis->data['titre']);
            $this->assertStringContainsString('500 kg', $avis->data['texte']);
            $this->assertStringContainsString('Coulibaly Awa', $avis->data['texte']);
            $this->assertSame('a_valider', $avis->data['categorie']);
        }
        $this->assertSame(0, $this->agent->notifications()->count());
    }

    #[Test]
    public function l_agent_est_prevenu_quand_son_achat_est_valide_ou_refuse(): void
    {
        $valide = $this->acheter();
        Achats::valider($valide, $this->comptable);
        // Deux avis dans la même seconde n'ont pas d'ordre (identifiants UUID) : on sépare les dates.
        $this->travel(1)->seconds();
        $refuse = $this->acheter();
        Achats::refuser($refuse, $this->direction, 'Pesée illisible sur la photo');

        // notifications() trie déjà du plus récent au plus ancien : reorder() avant oldest().
        $titres = $this->agent->notifications()->reorder()->oldest()->get()->map(fn ($n) => $n->data['titre'].' | '.$n->data['texte'])->all();
        $this->assertCount(2, $titres);
        $this->assertStringStartsWith("Achat {$valide->reference} validé", $titres[0]);
        $this->assertStringStartsWith("Achat {$refuse->reference} refusé", $titres[1]);
        $this->assertStringContainsString('Motif : Pesée illisible sur la photo', $titres[1]);
    }

    #[Test]
    public function rien_ne_part_pour_une_operation_annulee(): void
    {
        try {
            DB::transaction(function () {
                $this->acheter();
                throw new \RuntimeException('annulée');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, $this->comptable->notifications()->count());
        $this->assertSame([], $this->envois);
    }

    #[Test]
    public function l_avis_est_pousse_aux_appareils_abonnes_et_les_appareils_disparus_sont_oublies(): void
    {
        $navigateur = Notifications::abonner($this->comptable, AbonnementPush::WEB, 'https://push.exemple/abc', 'cle', 'auth', 'Windows — Chrome');
        $perime = Notifications::abonner($this->comptable, AbonnementPush::WEB, 'https://push.exemple/perime', 'cle', 'auth');
        $telephone = Notifications::abonner($this->agent, AbonnementPush::FCM, 'jeton-fcm-agent');
        $this->resultatsForces[$perime->id] = 'disparu';

        $achat = $this->acheter();
        Achats::valider($achat, $this->comptable);

        $web = collect($this->envois)->where('canal', AbonnementPush::WEB)->first();
        $this->assertNotNull($web);
        $this->assertEqualsCanonicalizing([$navigateur->id, $perime->id], $web['abonnements']);
        $this->assertSame("Achat {$achat->reference} à valider", $web['message']['titre']);
        $fcm = collect($this->envois)->where('canal', AbonnementPush::FCM)->first();
        $this->assertNotNull($fcm);
        $this->assertSame([$telephone->id], $fcm['abonnements']);

        $this->assertNull(AbonnementPush::query()->find($perime->id));
        $this->assertNotNull($navigateur->refresh()->dernier_envoi_at);
        $this->assertNotNull($telephone->refresh()->dernier_envoi_at);
    }

    #[Test]
    public function une_panne_du_serveur_n_oublie_aucun_appareil_et_l_avis_reste_dans_la_liste(): void
    {
        $a = Notifications::abonner($this->direction, AbonnementPush::WEB, 'https://push.exemple/y', 'cle', 'auth');
        $this->panneServeur = true;

        for ($i = 0; $i < AbonnementPush::ECHECS_MAX + 1; $i++) {
            Notifications::envoyer([$this->direction], 'Essai', 'Essai');
        }

        $this->assertSame(0, $a->refresh()->echecs);
        $this->assertSame(AbonnementPush::ECHECS_MAX + 1, $this->direction->notifications()->count());
    }

    #[Test]
    public function un_appareil_en_echec_cinq_fois_de_suite_est_oublie(): void
    {
        $a = Notifications::abonner($this->direction, AbonnementPush::WEB, 'https://push.exemple/x', 'cle', 'auth');
        $this->resultatsForces[$a->id] = 'echec';

        for ($i = 1; $i < AbonnementPush::ECHECS_MAX; $i++) {
            Notifications::envoyer([$this->direction], 'Essai', 'Essai');
            $this->assertSame($i, $a->refresh()->echecs);
        }
        Notifications::envoyer([$this->direction], 'Essai', 'Essai');
        $this->assertNull(AbonnementPush::query()->find($a->id));
    }

    #[Test]
    public function un_appareil_repris_par_un_autre_utilisateur_ne_previent_plus_l_ancien(): void
    {
        Notifications::abonner($this->comptable, AbonnementPush::WEB, 'https://push.exemple/poste-partage', 'cle', 'auth');
        Notifications::abonner($this->direction, AbonnementPush::WEB, 'https://push.exemple/poste-partage', 'cle', 'auth');

        $this->assertSame($this->direction->id, AbonnementPush::query()->sole()->user_id);
    }

    #[Test]
    public function un_utilisateur_desactive_ne_recoit_rien(): void
    {
        $this->comptable->update(['actif' => false]);
        $this->acheter();

        $this->assertSame(0, $this->comptable->notifications()->count());
        $this->assertSame(1, $this->direction->notifications()->count());
    }

    #[Test]
    public function le_telephone_declare_et_retire_son_jeton_firebase(): void
    {
        Sanctum::actingAs($this->agent);
        $this->postJson('/api/push', ['jeton' => 'jeton-fcm-1', 'appareil' => 'Tecno Spark 10'])->assertOk();
        $this->postJson('/api/push', ['jeton' => 'jeton-fcm-1'])->assertOk(); // renvoi : pas de doublon

        $a = AbonnementPush::query()->sole();
        $this->assertSame(AbonnementPush::FCM, $a->canal);
        $this->assertSame($this->agent->id, $a->user_id);

        $this->deleteJson('/api/push', ['jeton' => 'jeton-fcm-1'])->assertOk();
        $this->assertSame(0, AbonnementPush::query()->count());
    }

    #[Test]
    public function firebase_en_pilote_journal_n_envoie_rien_et_l_ecrit(): void
    {
        $this->app->forgetInstance(FirebaseFcm::class);
        Log::spy();
        $a = Notifications::abonner($this->agent, AbonnementPush::FCM, 'jeton-fcm-agent');

        $r = (new FirebaseFcm)->envoyer([$a], ['titre' => 'T', 'texte' => 'X', 'url' => null, 'categorie' => 'valide']);

        $this->assertSame([$a->id => 'ok'], $r);
        Log::shouldHaveReceived('info')->withArgs(fn ($m) => $m === 'Push FCM (pilote journal)')->once();
    }

    #[Test]
    public function la_page_montre_ses_avis_et_ouvrir_un_avis_le_marque_lu(): void
    {
        $achat = $this->acheter();
        $this->actingAs($this->comptable);

        $this->get('/tableau-de-bord')->assertSeeHtml('Notifications : 1 non lue(s)');

        $avis = $this->comptable->notifications()->sole();
        Livewire::test(ListeNotifications::class)
            ->assertSee("Achat {$achat->reference} à valider")
            ->call('ouvrir', $avis->id)
            ->assertRedirect(route('achats', ['statut' => 'a_valider']));

        $this->assertNotNull($avis->refresh()->read_at);
        $this->get('/tableau-de-bord')->assertDontSeeHtml('non lue(s)');
    }

    #[Test]
    public function on_ne_lit_pas_les_avis_d_un_autre(): void
    {
        $this->acheter();
        $avisDuComptable = $this->comptable->notifications()->sole();

        $this->actingAs($this->direction);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ListeNotifications::class)->call('ouvrir', $avisDuComptable->id);
    }

    #[Test]
    public function un_abonnement_de_navigateur_incomplet_est_refuse(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(ListeNotifications::class)
            ->call('enregistrerAbonnement', ['endpoint' => 'http://pas-https.exemple'], 'PC')
            ->assertHasErrors('push');
        Livewire::test(ListeNotifications::class)
            ->call('enregistrerAbonnement', ['endpoint' => 'https://push.exemple/ok', 'keys' => ['p256dh' => 'c', 'auth' => 'a']], 'PC')
            ->assertHasNoErrors()
            ->assertSee('Notifications activées sur ce navigateur.');

        $this->assertSame(1, AbonnementPush::query()->where('canal', AbonnementPush::WEB)->count());
    }
}
