<?php

namespace Tests\Feature\Producteurs;

use App\Enums\Role;
use App\Livewire\Producteurs\FormulaireProducteur;
use App\Livewire\Referentiels\Langues;
use App\Models\Langue;
use App\Models\Producteur;
use App\Models\User;
use App\Models\Village;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Langue de préférence d'un producteur pour les messages (question 12) : le français par défaut,
 * les langues locales ajoutées par la direction quand elles sont confirmées, choisies au bureau
 * comme sur le téléphone. Aucune n'est pré-remplie.
 */
class LanguesTest extends TestCase
{
    use RefreshDatabase;

    private User $direction;

    private User $agent;

    private Village $village;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->village = Village::factory()->create();
    }

    #[Test]
    public function aucune_langue_n_est_pre_remplie(): void
    {
        $this->assertSame(0, Langue::query()->count());
    }

    #[Test]
    public function la_direction_ajoute_une_langue_avec_un_code_en_minuscules_et_unique(): void
    {
        $this->actingAs($this->direction);

        Livewire::test(Langues::class)->call('nouveau')
            ->set('donnees.code', '  DYU ')->set('donnees.nom', 'Dioula')->call('enregistrer')->assertHasNoErrors();

        $this->assertSame('dyu', Langue::query()->sole()->code);

        Livewire::test(Langues::class)->call('nouveau')
            ->set('donnees.code', 'dyu')->set('donnees.nom', 'Autre')->call('enregistrer')->assertHasErrors();
        Livewire::test(Langues::class)->call('nouveau')
            ->set('donnees.code', '9x')->set('donnees.nom', 'Mauvais code')->call('enregistrer')->assertHasErrors();
        $this->assertSame(1, Langue::query()->count());
    }

    #[Test]
    public function l_ecran_montre_combien_de_producteurs_parlent_chaque_langue(): void
    {
        $dioula = Langue::query()->create(['code' => 'dyu', 'nom' => 'Dioula']);
        Producteur::factory()->count(2)->create(['village_id' => $this->village->id, 'langue_id' => $dioula->id]);
        Producteur::factory()->create(['village_id' => $this->village->id]);

        $this->actingAs($this->direction);
        Livewire::test(Langues::class)->assertSee('Dioula')->assertSee('Producteurs');

        $this->assertSame(2, Langue::query()->withCount('producteurs')->sole()->producteurs_count);
    }

    /** @return array<string, array{Role, int}> */
    public static function roles(): array
    {
        return ['direction' => [Role::Direction, 200], 'admin' => [Role::Admin, 200], 'agent' => [Role::Agent, 403], 'comptable' => [Role::Comptable, 403], 'investisseur' => [Role::Investisseur, 403]];
    }

    #[Test]
    #[DataProvider('roles')]
    public function l_ecran_des_langues_suit_le_droit_sur_les_referentiels(Role $role, int $attendu): void
    {
        $this->actingAs(User::factory()->role($role)->create());

        $this->get('/referentiels/langues')->assertStatus($attendu);
    }

    #[Test]
    public function le_formulaire_du_bureau_enregistre_la_langue_et_par_defaut_c_est_le_francais(): void
    {
        $dioula = Langue::query()->create(['code' => 'dyu', 'nom' => 'Dioula']);
        $this->actingAs($this->agent);

        $saisie = fn (array $plus) => Livewire::test(FormulaireProducteur::class)
            ->set('nom', 'Coulibaly')->set('prenoms', 'Awa')->set('villageId', (string) $this->village->id)->set('consentement', true)
            ->set('telephone', $plus['telephone'])->set('langueId', $plus['langue'])->call('enregistrer');

        $saisie(['telephone' => '0701020304', 'langue' => (string) $dioula->id])->assertHasNoErrors();
        $saisie(['telephone' => '0501020304', 'langue' => ''])->assertHasNoErrors();

        $this->assertSame($dioula->id, Producteur::query()->where('telephone', '0701020304')->firstOrFail()->langue_id);
        $this->assertNull(Producteur::query()->where('telephone', '0501020304')->firstOrFail()->langue_id);
    }

    #[Test]
    public function le_formulaire_refuse_une_langue_desactivee_ou_inconnue(): void
    {
        $inactive = Langue::query()->create(['code' => 'sef', 'nom' => 'Sénoufo', 'actif' => false]);
        $this->actingAs($this->agent);

        foreach ([(string) $inactive->id, '9999'] as $langue) {
            Livewire::test(FormulaireProducteur::class)
                ->set('nom', 'Coulibaly')->set('prenoms', 'Awa')->set('villageId', (string) $this->village->id)->set('consentement', true)
                ->set('langueId', $langue)->call('enregistrer')->assertHasErrors(['langueId']);
        }
        $this->assertSame(0, Producteur::query()->count());
    }

    #[Test]
    public function la_fiche_dit_la_langue_ou_le_francais_par_defaut(): void
    {
        $dioula = Langue::query()->create(['code' => 'dyu', 'nom' => 'Dioula']);
        $avec = Producteur::factory()->create(['village_id' => $this->village->id, 'langue_id' => $dioula->id]);
        $sans = Producteur::factory()->create(['village_id' => $this->village->id]);

        $this->actingAs($this->direction);
        $this->get('/producteurs/'.$avec->id)->assertOk()->assertSee('Langue des messages')->assertSee('Dioula');
        $this->get('/producteurs/'.$sans->id)->assertOk()->assertSee('Français (par défaut)');
    }

    /** @param  array<string, mixed>  $surcharge */
    private function operationProducteur(array $surcharge = []): array
    {
        return ['uuid' => (string) Str::uuid7(), 'type' => 'producteur', 'cree_at' => now()->toIso8601String(), 'donnees' => array_merge([
            'nom' => 'Koné', 'prenoms' => 'Awa', 'telephone' => '0701020304', 'village_id' => $this->village->id, 'consentement' => true,
        ], $surcharge)];
    }

    #[Test]
    public function le_telephone_envoie_la_langue_par_la_synchronisation(): void
    {
        $dioula = Langue::query()->create(['code' => 'dyu', 'nom' => 'Dioula']);
        Sanctum::actingAs($this->agent);
        $avec = $this->operationProducteur(['langue_id' => $dioula->id]);
        $sans = $this->operationProducteur(['telephone' => '0501020304']);

        $r = $this->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => [$avec, $sans]])->assertOk()->json('resultats');

        $this->assertSame(['accepte', 'accepte'], array_column($r, 'statut'));
        $this->assertSame($dioula->id, Producteur::query()->findOrFail($avec['uuid'])->langue_id);
        $this->assertNull(Producteur::query()->findOrFail($sans['uuid'])->langue_id, 'Une vieille appli qui n\'envoie pas la langue : français.');
    }

    #[Test]
    public function la_synchronisation_rejette_une_langue_inconnue_ou_desactivee_avec_un_motif(): void
    {
        $inactive = Langue::query()->create(['code' => 'sef', 'nom' => 'Sénoufo', 'actif' => false]);
        Sanctum::actingAs($this->agent);

        foreach ([$inactive->id, 9999] as $langue) {
            $op = $this->operationProducteur(['langue_id' => $langue, 'telephone' => '07'.random_int(10_000_000, 99_999_999)]);
            $r = $this->postJson('/api/sync', ['appareil_id' => 'tel', 'operations' => [$op]])->assertOk()->json('resultats.0');

            $this->assertSame('rejete', $r['statut']);
            $this->assertStringContainsString('langue', $r['motif']);
        }
        $this->assertSame(0, Producteur::query()->count());
    }

    #[Test]
    public function les_referentiels_envoient_les_langues_au_telephone_avec_leur_etat(): void
    {
        Langue::query()->create(['code' => 'dyu', 'nom' => 'Dioula']);
        Langue::query()->create(['code' => 'sef', 'nom' => 'Sénoufo', 'actif' => false]);
        Sanctum::actingAs($this->agent);

        $langues = collect($this->getJson('/api/referentiels')->assertOk()->json('langues'));

        $this->assertSame(['dyu', 'sef'], $langues->pluck('code')->sort()->values()->all());
        $this->assertFalse($langues->firstWhere('code', 'sef')['actif']);
    }
}
