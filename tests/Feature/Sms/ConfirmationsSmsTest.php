<?php

namespace Tests\Feature\Sms;

use App\Enums\CleParametre;
use App\Enums\FormePret;
use App\Enums\ModeDecaissement;
use App\Enums\NatureMouvement;
use App\Enums\RegleValorisationNature;
use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Enums\StatutLot;
use App\Enums\TypeFournisseur;
use App\Models\Achat;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\ConfirmationSms;
use App\Models\Lot;
use App\Models\Magasin;
use App\Models\Parametre;
use App\Models\Pret;
use App\Models\Producteur;
use App\Models\User;
use App\Services\Achats;
use App\Services\Prets;
use App\Services\Remboursements;
use App\Services\Sms\EnvoyeurSms;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ConfirmationsSmsTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $comptable;

    private User $direction;

    private Campagne $campagne;

    private Lot $lot;

    private CompteTresorerie $centrale;

    private CompteTresorerie $caisseAgent;

    private Producteur $producteur;

    /** @var list<array{string, string}> */
    private array $envoyes = [];

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
        $this->centrale = CompteTresorerie::factory()->create();
        $this->caisseAgent = CompteTresorerie::factory()->caisseDe($this->agent)->create();
        Tresorerie::entree($this->centrale, 10_000_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Tresorerie::avanceAgent($this->centrale, $this->caisseAgent, 2_000_000, Carbon::today(), 'Avance', $this->direction);
        $this->producteur = Producteur::factory()->create(['telephone' => '0701020304']);
        foreach ([CleParametre::SeuilValidationAchat, CleParametre::SeuilValidationPret] as $cle) {
            Parametre::query()->updateOrCreate(['cle' => $cle], ['valeur' => '5000000']);
        }
        Parametre::query()->updateOrCreate(['cle' => CleParametre::RegleRemboursementNature], ['valeur' => RegleValorisationNature::PrixAchat->value]);

        // Pilote espion : on voit ce qui « part ».
        $this->app->instance(EnvoyeurSms::class, new class($this->envoyes) implements EnvoyeurSms
        {
            /** @param  list<array{string, string}>  $envoyes */
            public function __construct(private array &$envoyes) {}

            public function nom(): string
            {
                return 'espion';
            }

            public function envoyer(string $telephone, string $message): void
            {
                $this->envoyes[] = [$telephone, $message];
            }
        });
    }

    private function pretDecaisse(int $montant): Pret
    {
        $pret = Prets::demander([
            'producteur_id' => $this->producteur->id, 'campagne_id' => $this->campagne->id, 'montant_fcfa' => $montant,
            'forme' => FormePret::Especes, 'echeance' => Carbon::today()->addMonths(5),
        ], $this->agent);
        Prets::valider($pret, $this->direction);
        Prets::decaisser($pret, [
            'compte_id' => $this->centrale->id, 'mode' => ModeDecaissement::Especes, 'montant_fcfa' => $montant, 'date' => Carbon::today(),
        ], $this->comptable, 'prets/recus/r.jpg');

        return $pret->refresh();
    }

    /** @param  array<string, mixed>  $surcharge */
    private function acheter(array $surcharge = []): Achat
    {
        return Achats::enregistrer(array_merge([
            'campagne_id' => $this->campagne->id, 'lot_id' => $this->lot->id, 'fournisseur_type' => TypeFournisseur::Producteur,
            'producteur_id' => $this->producteur->id, 'date_achat' => now()->subMinute(),
            'poids_brut_g' => 505_000, 'tare_g' => 5_000, 'prix_kg_fcfa' => 425, 'compte_id' => $this->caisseAgent->id,
        ], $surcharge), $this->agent);
    }

    #[Test]
    public function achat_sous_pret_le_producteur_recoit_poids_prix_retenue_paiement_et_restant_du(): void
    {
        $pret = $this->pretDecaisse(300_000);
        $this->envoyes = [];

        $achat = $this->acheter(['pret_id' => $pret->id, 'grammes_rembourses' => 200_000]);

        $sms = ConfirmationSms::query()->where('objet_type', 'achat')->sole();
        $this->assertSame(ConfirmationSms::ENVOYE, $sms->statut);
        $this->assertSame('espion', $sms->pilote);
        $this->assertCount(1, $this->envoyes);
        [$telephone, $message] = $this->envoyes[0];
        $this->assertSame('0701020304', $telephone);
        // 500 kg × 425 = 212 500 ; 200 kg retenus = 85 000 ; payé 127 500 ; reste 215 000.
        foreach ([$achat->reference, '500 kg', '425 F/kg', '212 500 F', '200 kg', '127 500 F', 'Reste du: 215 000 F'] as $attendu) {
            $this->assertStringContainsString($attendu, $message);
        }
    }

    #[Test]
    public function decaissement_et_remboursement_especes_sont_confirmes(): void
    {
        $pret = $this->pretDecaisse(300_000);
        $this->assertStringContainsString('vous avez recu 300 000 F', $this->envoyes[0][1]);

        Remboursements::especes($pret, $this->centrale, 100_000, Carbon::today(), $this->comptable);

        $this->assertSame(2, ConfirmationSms::query()->count());
        $this->assertStringContainsString('remboursement de 100 000 F', $this->envoyes[1][1]);
        $this->assertStringContainsString('Reste du: 200 000 F', $this->envoyes[1][1]);
    }

    #[Test]
    public function aucun_sms_ne_part_avant_le_commit_ni_pour_une_operation_annulee(): void
    {
        try {
            DB::transaction(function () {
                $this->acheter();
                // Au milieu de la transaction : la ligne existe, rien n'est parti.
                $this->assertSame(1, ConfirmationSms::query()->count());
                $this->assertSame([], $this->envoyes);
                throw new RuntimeException('coupure');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->envoyes, 'Opération annulée : aucun SMS.');
        $this->assertSame(0, ConfirmationSms::query()->count());

        $this->acheter();
        $this->assertCount(1, $this->envoyes);
    }

    #[Test]
    public function producteur_sans_telephone_pas_de_sms_et_l_achat_passe(): void
    {
        $this->producteur->update(['telephone' => null]);

        $this->acheter();

        $this->assertSame(1, Achat::query()->count());
        $this->assertSame(0, ConfirmationSms::query()->count());
    }

    #[Test]
    public function un_echec_d_envoi_est_note_et_n_annule_pas_l_achat(): void
    {
        $this->app->instance(EnvoyeurSms::class, new class implements EnvoyeurSms
        {
            public function nom(): string
            {
                return 'panne';
            }

            public function envoyer(string $telephone, string $message): void
            {
                throw new RuntimeException('Opérateur injoignable');
            }
        });

        try {
            $this->acheter();
        } catch (RuntimeException) {
            // En file « sync » (tests), l'exception du job remonte ; en production elle
            // reste dans le worker et le job est retenté.
        }

        $this->assertSame(1, Achat::query()->count());
        $sms = ConfirmationSms::query()->sole();
        $this->assertSame(ConfirmationSms::ECHEC, $sms->statut);
        $this->assertSame('Opérateur injoignable', $sms->erreur);
    }

    #[Test]
    public function le_pilote_par_defaut_est_journal(): void
    {
        $this->assertSame('journal', config('services.sms.pilote'));
        $this->app->forgetInstance(EnvoyeurSms::class);
        $this->assertSame('journal', $this->app->make(EnvoyeurSms::class)->nom());
    }
}
