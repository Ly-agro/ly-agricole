<?php

namespace Tests\Feature\Producteurs;

use App\Enums\ActionJournal;
use App\Enums\OperateurMobileMoney;
use App\Enums\Role;
use App\Enums\TypePiece;
use App\Livewire\Producteurs\FormulaireProducteur;
use App\Models\GroupeProducteur;
use App\Models\JournalActivite;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormulaireProducteurTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->actingAs($this->agent);
        $this->village = Village::factory()->create();
    }

    /** @param  array<string, mixed>  $champs */
    private function saisir(array $champs = []): Testable
    {
        $ecran = Livewire::test(FormulaireProducteur::class);

        foreach (array_merge([
            'nom' => 'Coulibaly',
            'prenoms' => 'Awa',
            'villageId' => (string) $this->village->id,
            'consentement' => true,
        ], $champs) as $champ => $valeur) {
            $ecran->set($champ, $valeur);
        }

        return $ecran->call('enregistrer');
    }

    #[Test]
    public function une_fiche_creee_recoit_un_uuid_v7_un_code_de_carte_et_la_trace_du_consentement(): void
    {
        $this->saisir()->assertHasNoErrors()->assertRedirect();

        $awa = Producteur::firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $awa->id);
        $this->assertSame('LYP-000001', $awa->code);
        $this->assertSame($this->agent->id, $awa->consentement_par);
        $this->assertSame($this->agent->id, $awa->cree_par);
        $this->assertTrue($awa->consentement_at->isToday());

        $ligne = JournalActivite::query()->where('objet_type', 'producteur')->firstOrFail();
        $this->assertSame(ActionJournal::Creation, $ligne->action);
        $this->assertSame($awa->id, $ligne->objet_id);
    }

    #[Test]
    public function sans_l_accord_du_producteur_pas_de_fiche(): void
    {
        $this->saisir(['consentement' => false])
            ->assertHasErrors(['consentement' => 'accepted'])
            ->assertSee('Sans l&#039;accord du producteur, sa fiche ne peut pas être créée.', false);

        $this->assertSame(0, Producteur::count());
    }

    #[Test]
    public function les_codes_de_carte_se_suivent_sans_trou_meme_apres_un_echec(): void
    {
        $this->saisir(['telephone' => '0701010101']);
        $this->saisir(['nom' => 'Échec', 'consentement' => false]);
        $this->saisir(['nom' => 'Traoré', 'telephone' => '0702020202']);

        $this->assertSame(['LYP-000001', 'LYP-000002'], Producteur::query()->orderBy('code')->pluck('code')->all());
    }

    #[Test]
    public function le_telephone_est_normalise_a_10_chiffres(): void
    {
        $this->saisir(['telephone' => '+225 07 01-02.03 04'])->assertHasNoErrors();
        $this->assertSame('0701020304', Producteur::firstOrFail()->telephone);

        $this->saisir(['nom' => 'Autre', 'telephone' => '07 01 02'])
            ->assertHasErrors(['telephone' => 'regex'])
            ->assertSee('Le téléphone doit avoir 10 chiffres');
    }

    #[Test]
    public function un_numero_mobile_money_exige_son_operateur_et_inversement(): void
    {
        $this->saisir(['numeroMobileMoney' => '0501020304'])->assertHasErrors(['operateurMm' => 'required_with']);
        $this->saisir(['operateurMm' => 'wave'])->assertHasErrors(['numeroMobileMoney' => 'required_with']);

        $this->saisir(['numeroMobileMoney' => '0501020304', 'operateurMm' => 'wave'])->assertHasNoErrors();
        $this->assertSame(OperateurMobileMoney::Wave, Producteur::firstOrFail()->operateur_mm);
    }

    #[Test]
    public function une_piece_d_identite_deja_enregistree_bloque_la_creation(): void
    {
        $existant = Producteur::factory()->create([
            'nom' => 'Koné', 'prenoms' => 'Issa', 'piece_type' => TypePiece::Cni, 'piece_numero' => 'CI0012345',
        ]);

        $this->saisir(['pieceType' => 'cni', 'pieceNumero' => ' ci0012345 '])
            ->assertHasErrors('pieceNumero')
            ->assertSee("Cette pièce d'identité est déjà celle de Koné Issa ({$existant->code}).");

        $this->assertSame(1, Producteur::count());
    }

    #[Test]
    public function un_telephone_deja_connu_demande_une_confirmation_journalisee(): void
    {
        $existant = Producteur::factory()->create(['nom' => 'Ouattara', 'prenoms' => 'Siaka', 'telephone' => '0701020304']);

        $ecran = $this->saisir(['telephone' => '07 01 02 03 04'])
            ->assertHasNoErrors()
            ->assertNoRedirect()
            ->assertSee('Doublon possible')
            ->assertSee("Ouattara Siaka ({$existant->code})");

        $this->assertSame(1, Producteur::count());

        $ecran->set('doublonsConfirmes', true)->call('enregistrer')->assertRedirect();

        $this->assertSame(2, Producteur::count());
        $ligne = JournalActivite::query()->where('action', ActionJournal::DoublonConfirme)->firstOrFail();
        $this->assertSame($this->agent->id, $ligne->user_id);
        $this->assertStringContainsString('0701020304', $ligne->apres['alertes'][0] ?? '');
    }

    #[Test]
    public function un_numero_mobile_money_deja_utilise_comme_telephone_est_aussi_signale(): void
    {
        Producteur::factory()->create(['telephone' => '0501020304']);

        $this->saisir(['numeroMobileMoney' => '0501020304', 'operateurMm' => 'orange_money'])
            ->assertSee('Le numéro Mobile Money 0501020304 figure déjà');

        $this->assertSame(1, Producteur::count());
    }

    #[Test]
    public function changer_le_numero_annule_la_confirmation_deja_donnee(): void
    {
        Producteur::factory()->create(['telephone' => '0701020304']);
        Producteur::factory()->create(['telephone' => '0709090909']);

        $ecran = $this->saisir(['telephone' => '0701020304'])->set('doublonsConfirmes', true);
        $ecran->set('telephone', '0709090909')
            ->assertSet('doublonsConfirmes', false)
            ->call('enregistrer')
            ->assertNoRedirect()
            ->assertSee('0709090909 figure déjà');

        $this->assertSame(2, Producteur::count());
    }

    #[Test]
    public function le_groupe_doit_etre_du_village_choisi(): void
    {
        $groupeAilleurs = GroupeProducteur::factory()->create();
        $groupeIci = GroupeProducteur::factory()->create(['village_id' => $this->village->id]);

        $this->saisir(['groupeId' => (string) $groupeAilleurs->id])->assertHasErrors(['groupeId' => 'exists']);
        $this->saisir(['groupeId' => (string) $groupeIci->id])->assertHasNoErrors();

        $this->assertSame($groupeIci->id, Producteur::firstOrFail()->groupe_id);
    }

    #[Test]
    public function la_photo_va_sur_le_disque_prive_et_l_ancienne_est_effacee(): void
    {
        $this->saisir(['photo' => UploadedFile::fake()->image('awa.jpg', 400, 400)])->assertHasNoErrors();

        $awa = Producteur::firstOrFail();
        $this->assertNotNull($awa->photo);
        Storage::disk('local')->assertExists($awa->photo);
        $this->assertStringStartsWith('producteurs/photos/', $awa->photo);
        $premiere = $awa->photo;

        Livewire::test(FormulaireProducteur::class, ['producteur' => $awa])
            ->set('photo', UploadedFile::fake()->image('awa2.jpg'))
            ->call('enregistrer')
            ->assertHasNoErrors();

        Storage::disk('local')->assertMissing($premiere);
        Storage::disk('local')->assertExists((string) $awa->refresh()->photo);
    }

    #[Test]
    public function un_fichier_qui_n_est_pas_une_image_est_refuse_des_qu_il_est_choisi(): void
    {
        // Avant correction : la page plantait en essayant d'afficher l'aperçu d'un PDF.
        Livewire::test(FormulaireProducteur::class)
            ->set('photo', UploadedFile::fake()->create('releve.pdf', 10, 'application/pdf'))
            ->assertHasErrors('photo')
            ->assertOk();

        $this->saisir(['photo' => UploadedFile::fake()->create('releve.pdf', 10, 'application/pdf')])
            ->assertHasErrors('photo');

        $this->assertSame(0, Producteur::count());
    }

    #[Test]
    public function modifier_ne_redemande_pas_et_ne_change_pas_le_consentement(): void
    {
        $awa = Producteur::factory()->create(['nom' => 'Coulibaly', 'village_id' => $this->village->id]);
        $consentement = $awa->consentement_at->toIso8601String();

        Livewire::test(FormulaireProducteur::class, ['producteur' => $awa])
            ->assertDontSee('Le producteur a donné son accord')
            ->set('nom', 'Coulibaly-Traoré')
            ->call('enregistrer')
            ->assertHasNoErrors()
            ->assertRedirect(route('producteurs.fiche', $awa));

        $awa->refresh();
        $this->assertSame('Coulibaly-Traoré', $awa->nom);
        $this->assertSame($consentement, $awa->consentement_at->toIso8601String());
        $this->assertSame(['nom' => 'Coulibaly'], JournalActivite::query()->latest('id')->firstOrFail()->avant);
    }

    #[Test]
    public function le_numero_de_sa_propre_fiche_n_est_pas_un_doublon(): void
    {
        $awa = Producteur::factory()->create(['telephone' => '0701020304', 'village_id' => $this->village->id]);

        Livewire::test(FormulaireProducteur::class, ['producteur' => $awa])
            ->set('prenoms', 'Awa Mariam')
            ->call('enregistrer')
            ->assertRedirect();
    }
}
