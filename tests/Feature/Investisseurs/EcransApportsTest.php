<?php

namespace Tests\Feature\Investisseurs;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Livewire\Investisseurs\GestionApports;
use App\Livewire\Investisseurs\PortailInvestisseur;
use App\Models\Apport;
use App\Models\Campagne;
use App\Models\CompteTresorerie;
use App\Models\User;
use App\Services\Apports;
use App\Services\DeclarationsApport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EcransApportsTest extends TestCase
{
    use RefreshDatabase;

    private const FINE = "\u{202F}";

    private User $direction;

    private User $comptable;

    private User $investisseurA;

    private User $investisseurB;

    private Campagne $campagne;

    private CompteTresorerie $compteDedie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->comptable = User::factory()->role(Role::Comptable)->create();
        $this->investisseurA = User::factory()->role(Role::Investisseur)->create(['nom' => 'Fonds A']);
        $this->investisseurB = User::factory()->role(Role::Investisseur)->create(['nom' => 'Fonds B']);
        $this->campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create();
        $this->compteDedie = CompteTresorerie::factory()->create(['campagne_id' => $this->campagne->id, 'nom' => 'Campagne '.$this->campagne->code]);
    }

    /** @return array<string, array{Role, int, int}> [rôle, /apports, /mon-investissement] */
    public static function acces(): array
    {
        return [
            'direction' => [Role::Direction, 200, 403],
            'comptable' => [Role::Comptable, 200, 403],
            'investisseur' => [Role::Investisseur, 403, 200],
            'agent' => [Role::Agent, 403, 403],
            'agronome' => [Role::Agronome, 403, 403],
            'admin' => [Role::Admin, 403, 403],
        ];
    }

    #[Test]
    #[DataProvider('acces')]
    public function acces_aux_apports_et_au_portail_investisseur(Role $role, int $apports, int $portail): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/apports')->assertStatus($apports);
        $this->get('/mon-investissement')->assertStatus($portail);
    }

    #[Test]
    public function la_direction_enregistre_un_apport_depuis_l_ecran(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(GestionApports::class, ['campagneId' => (string) $this->campagne->id])
            ->set('campagneId', (string) $this->campagne->id)
            ->call('ouvrir')
            ->set('investisseurId', (string) $this->investisseurA->id)
            ->set('compteId', (string) $this->compteDedie->id)
            ->set('montant', '3 000 000')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Apport enregistré'));

        $apport = Apport::firstOrFail();
        $this->assertSame(3_000_000, $apport->montant_fcfa);
        $this->assertSame($this->investisseurA->id, $apport->investisseur_id);
        $this->assertSame(3_000_000, $this->compteDedie->solde());
    }

    #[Test]
    public function un_compte_de_campagne_cloturee_ne_peut_pas_servir_a_un_nouvel_apport(): void
    {
        $campagneCloturee = Campagne::factory()->statut(StatutCampagne::Cloturee)->create();
        $compteCloture = CompteTresorerie::factory()->create([
            'nom' => 'Compte terminé',
            'campagne_id' => $campagneCloturee->id,
        ]);
        $this->actingAs($this->direction);

        Livewire::test(GestionApports::class)
            ->set('campagneId', (string) $this->campagne->id)
            ->call('ouvrir')
            ->assertViewHas('comptesDedies', fn ($comptes) => ! $comptes->contains('id', $compteCloture->id))
            ->assertSee($this->compteDedie->nom);

        Livewire::test(GestionApports::class)
            ->set('campagneId', (string) $campagneCloturee->id)
            ->assertSee($campagneCloturee->code)
            ->assertDontSee('Nouvel apport');
    }

    #[Test]
    public function un_nom_tape_dans_le_champ_investisseur_enregistre_un_investisseur_sans_compte(): void
    {
        $compteLibre = CompteTresorerie::factory()->create();
        $this->actingAs($this->direction);

        Livewire::test(GestionApports::class, ['campagneId' => (string) $this->campagne->id])
            ->call('ouvrir')
            ->assertSee($compteLibre->nom)
            ->set('investisseurId', 'Coopérative Wassa')
            ->set('compteId', (string) $compteLibre->id)
            ->set('montant', '1 000 000')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertSee('Coopérative Wassa');

        $apport = Apport::query()->sole();
        $this->assertNull($apport->investisseur_id);
        $this->assertSame('Coopérative Wassa', $apport->apporteur_nom);
        $this->assertFalse($apport->estDeLy());
    }

    #[Test]
    public function contre_passer_un_apport_depuis_l_ecran(): void
    {
        $this->actingAs($this->direction);
        $apport = Apports::enregistrer($this->investisseurA->id, $this->campagne->id, $this->compteDedie->id, 2_000_000, Carbon::today(), $this->direction);

        Livewire::test(GestionApports::class, ['campagneId' => (string) $this->campagne->id])
            ->call('preparerContrePassation', $apport->id)
            ->set('motifContrePassation', 'Virement jamais reçu')
            ->call('contrePasser')
            ->assertHasNoErrors()
            ->assertSet('statut', fn ($s) => str_contains((string) $s, 'Apport contre-passé'));

        $this->assertSame(0, $this->compteDedie->solde());
    }

    #[Test]
    public function l_ecran_de_gestion_affiche_la_repartition_sans_parler_de_resultat(): void
    {
        $this->actingAs($this->direction);
        Apports::enregistrer($this->investisseurA->id, $this->campagne->id, $this->compteDedie->id, 6_000_000, Carbon::today(), $this->direction);
        Apports::enregistrer($this->investisseurB->id, $this->campagne->id, $this->compteDedie->id, 4_000_000, Carbon::today(), $this->direction);

        Livewire::test(GestionApports::class, ['campagneId' => (string) $this->campagne->id])
            ->assertSee('Fonds A')
            ->assertSee('60,0 %')
            ->assertSee('Fonds B')
            ->assertSee('40,0 %')
            ->assertDontSee('Apport propre de LY')
            ->assertSee('pas une quote-part du résultat');
    }

    #[Test]
    public function un_investisseur_ne_voit_que_ses_propres_apports_sur_le_portail(): void
    {
        Apports::enregistrer($this->investisseurA->id, $this->campagne->id, $this->compteDedie->id, 6_000_000, Carbon::today(), $this->direction);
        Apports::enregistrer($this->investisseurB->id, $this->campagne->id, $this->compteDedie->id, 4_000_000, Carbon::today(), $this->direction);

        Livewire::actingAs($this->investisseurA)->test(PortailInvestisseur::class)
            ->assertSee('6'.self::FINE.'000'.self::FINE.'000 FCFA')
            ->assertSee('60,0 %')
            ->assertDontSee('4'.self::FINE.'000'.self::FINE.'000 FCFA');
    }

    #[Test]
    public function un_investisseur_sans_apport_voit_un_message_clair(): void
    {
        Livewire::actingAs($this->investisseurA)->test(PortailInvestisseur::class)
            ->assertSee('Aucun apport confirmé à votre nom');
    }

    #[Test]
    public function un_investisseur_declare_son_apport_sans_creer_le_registre_comptable(): void
    {
        Livewire::actingAs($this->investisseurA)->test(PortailInvestisseur::class)
            ->call('ouvrirDeclaration')
            ->set('campagneDeclarationId', (string) $this->campagne->id)
            ->set('montantDeclaration', '3 000 000')
            ->set('dateVirement', Carbon::today()->toDateString())
            ->set('referenceVirement', 'VIR-INV-001')
            ->call('declarerApport')
            ->assertHasNoErrors()
            ->assertSee('en attente de confirmation du bureau');

        $this->assertSame(0, Apport::count());
        $this->assertSame(0, $this->compteDedie->solde());
        $this->assertDatabaseHas('declarations_apports', [
            'investisseur_id' => $this->investisseurA->id,
            'campagne_id' => $this->campagne->id,
            'montant_fcfa' => 3_000_000,
            'reference_virement' => 'VIR-INV-001',
            'statut' => 'en_attente',
        ]);
    }

    #[Test]
    public function le_bureau_confirme_une_declaration_sur_le_compte_actif_choisi(): void
    {
        $declaration = DeclarationsApport::declarer(
            $this->investisseurA, $this->campagne->id, 1_500_000, Carbon::today(), 'VIR-CONF-1',
        );
        $compteLibre = CompteTresorerie::factory()->create(['nom' => 'Banque de dépôt']);
        $this->actingAs($this->direction);

        Livewire::test(GestionApports::class, ['campagneId' => (string) $this->campagne->id])
            ->assertSee('Déclarations à traiter')
            ->set("comptesConfirmation.{$declaration->id}", (string) $compteLibre->id)
            ->call('confirmerDeclaration', $declaration->id)
            ->assertHasNoErrors()
            ->assertSet('statut', 'Apport confirmé et enregistré.');

        $this->assertSame('confirmee', $declaration->refresh()->statut->value);
        $this->assertSame(1_500_000, $compteLibre->refresh()->solde());
        $this->assertSame($this->investisseurA->id, Apport::query()->sole()->investisseur_id);
    }

    #[Test]
    public function le_refus_d_une_declaration_mentionne_le_motif_sans_creer_d_apport(): void
    {
        $declaration = DeclarationsApport::declarer(
            $this->investisseurA, $this->campagne->id, 1_500_000, Carbon::today(), 'VIR-REF-1',
        );
        $this->actingAs($this->comptable);

        Livewire::test(GestionApports::class, ['campagneId' => (string) $this->campagne->id])
            ->set("motifsRefusDeclaration.{$declaration->id}", 'Virement non reçu')
            ->call('refuserDeclaration', $declaration->id)
            ->assertHasNoErrors();

        $this->assertSame('refusee', $declaration->refresh()->statut->value);
        $this->assertSame(0, Apport::count());
        Livewire::actingAs($this->investisseurA)->test(PortailInvestisseur::class)
            ->assertSee('Virement non reçu')
            ->assertSee('Refusée');
    }
}
