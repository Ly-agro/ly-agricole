<?php

namespace Tests\Feature\Ia;

use App\Enums\Role;
use App\Enums\StatutDiagnostic;
use App\Exceptions\OperationRefusee;
use App\Livewire\Ia\ListeDiagnostics;
use App\Livewire\Visites\ListeVisites;
use App\Models\Diagnostic;
use App\Models\FicheTraitement;
use App\Models\Parcelle;
use App\Models\PhotoTerrain;
use App\Models\Produit;
use App\Models\User;
use App\Models\Visite;
use App\Services\Ia\ClientIa;
use App\Services\Ia\Diagnostics;
use App\Services\Ia\Referentiel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/** IA (phase 3) : référentiel de l'agronome, diagnostics validés par un humain, brouillons contrôlés. */
class IaTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $direction;

    private User $agronome;

    private Produit $anacarde;

    private Visite $visite;

    /** Faux service IA : réponses imposées, dernière demande de conseil gardée. */
    public array $reponseDiagnostic = ['statut' => 'incertain', 'classe' => null, 'confiance_pour_mille' => 0, 'motif' => 'Aucun modèle de vision entraîné.', 'modele' => 'aucun'];

    public ?array $reponseConseil = null;

    public ?array $derniereDemande = null;

    public bool $panne = false;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->agent = User::factory()->role(Role::Agent)->create();
        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->agronome = User::factory()->role(Role::Agronome)->create();
        $this->anacarde = Produit::factory()->create(['nom' => 'Anacarde']);
        $parcelle = Parcelle::factory()->create(['produit_id' => $this->anacarde->id]);
        $this->visite = $this->visiteAvecPhotos($parcelle, 2);

        $test = $this;
        $this->app->instance(ClientIa::class, new class($test) implements ClientIa
        {
            public function __construct(private IaTest $t) {}

            public function diagnostiquer(PhotoTerrain $photo, string $culture): array
            {
                if ($this->t->panne) {
                    throw new RuntimeException('Connexion refusée au serveur IA.');
                }

                return $this->t->reponseDiagnostic;
            }

            public function conseil(array $demande): array
            {
                $this->t->derniereDemande = $demande;

                return $this->t->reponseConseil ?? [
                    'statut' => 'brouillon', 'modele' => 'faux', 'motifs_rejet' => [],
                    'fiches_citees' => array_column($demande['fiches'], 'id'),
                    'texte' => implode(' puis ', array_map(fn ($f) => "[FICHE-{$f['id']}]", $demande['fiches'])),
                ];
            }
        });
    }

    private function visiteAvecPhotos(Parcelle $parcelle, int $n): Visite
    {
        $visite = Visite::query()->create([
            'id' => (string) Str::uuid7(), 'parcelle_id' => $parcelle->id, 'date_visite' => now()->toDateString(),
            'observations' => 'Taches noires', 'cree_par' => $this->agent->id,
        ]);
        for ($i = 0; $i < $n; $i++) {
            $id = (string) Str::uuid7();
            Storage::disk('local')->put("terrain/photos/{$id}.jpg", "jpeg-{$i}");
            PhotoTerrain::query()->create(['id' => $id, 'user_id' => $this->agent->id, 'appareil_id' => 'tel', 'chemin' => "terrain/photos/{$id}.jpg",
                'mime' => 'image/jpeg', 'taille_octets' => 10, 'recu_at' => now()]);
            $visite->photos()->attach($id);
        }

        return $visite;
    }

    /** @param  array<string, mixed>  $surcharge */
    private function fiche(array $surcharge = []): FicheTraitement
    {
        return Referentiel::enregistrer(array_merge([
            'produit_id' => $this->anacarde->id, 'type' => 'pratique', 'cible' => 'anthracnose', 'titre' => 'Brûler les feuilles tombées',
        ], $surcharge), $this->agronome);
    }

    private function chimiqueComplete(array $surcharge = []): FicheTraitement
    {
        return $this->fiche(array_merge([
            'type' => 'chimique', 'titre' => 'Traitement homologué', 'nom_commercial' => 'Produit-Test', 'matiere_active' => 'Matière-Test',
            'dose' => '2 l/ha', 'passages' => 2, 'delai_avant_recolte_jours' => 21, 'toxicite_humaine' => 'III',
            'protection' => 'gants, masque', 'effet_abeilles' => 'toxique',
        ], $surcharge));
    }

    #[Test]
    public function seul_l_agronome_tient_le_referentiel_et_une_fiche_chimique_incomplete_n_est_pas_proposable(): void
    {
        foreach ([$this->direction, $this->agent] as $u) {
            $this->assertThrows(fn () => Referentiel::enregistrer(['produit_id' => $this->anacarde->id, 'type' => 'pratique', 'cible' => 'x', 'titre' => 'y'], $u), OperationRefusee::class);
        }

        $incomplete = $this->chimiqueComplete(['delai_avant_recolte_jours' => null, 'effet_abeilles' => '']);
        $this->assertFalse($incomplete->proposable());
        $this->assertSame(['délai avant récolte', 'effet sur les abeilles'], $incomplete->manquants());
        $this->assertTrue($this->chimiqueComplete()->proposable());

        // Une fiche non chimique ne garde ni nom commercial ni dose.
        $pratique = $this->fiche(['nom_commercial' => 'Glissé-là', 'dose' => '3 kg']);
        $this->assertNull($pratique->nom_commercial);
        $this->assertNull($pratique->dose);
        $this->assertSame($this->agronome->id, $pratique->validee_par);
    }

    #[Test]
    public function un_agent_demande_un_avis_les_photos_sont_traitees_en_file_et_restent_incertaines(): void
    {
        $this->assertSame(2, Diagnostics::demander($this->visite, $this->agent));
        $this->assertSame(0, Diagnostics::demander($this->visite, $this->agent)); // déjà traitées

        $this->assertSame(2, Diagnostic::query()->where('statut', StatutDiagnostic::Incertain)->count());
        $this->assertSame('Aucun modèle de vision entraîné.', Diagnostic::query()->first()->motif);
    }

    #[Test]
    public function une_panne_du_service_se_voit_et_se_redemande(): void
    {
        $this->panne = true;
        Diagnostics::demander($this->visite, $this->agent);
        $this->assertSame(2, Diagnostic::query()->where('statut', StatutDiagnostic::Erreur)->count());
        $this->assertStringContainsString('Connexion refusée', (string) Diagnostic::query()->first()->motif);

        $this->panne = false;
        $this->assertSame(2, Diagnostics::demander($this->visite, $this->agent));
        $this->assertSame(0, Diagnostic::query()->where('statut', StatutDiagnostic::Erreur)->count());
    }

    #[Test]
    public function seul_l_agronome_valide_et_une_proposition_se_confirme_ou_se_corrige(): void
    {
        $this->reponseDiagnostic = ['statut' => 'propose', 'classe' => 'anthracnose', 'confiance_pour_mille' => 910, 'motif' => '', 'modele' => 'essai.onnx'];
        Diagnostics::demander($this->visite, $this->agent);
        [$d1, $d2] = Diagnostic::query()->orderBy('id')->get()->all();
        $this->assertSame(StatutDiagnostic::Propose, $d1->statut);

        $this->assertThrows(fn () => Diagnostics::valider($d1, $this->direction, null), OperationRefusee::class);

        $this->assertSame(StatutDiagnostic::Confirme, Diagnostics::valider($d1, $this->agronome, null)->statut);
        $corrige = Diagnostics::valider($d2, $this->agronome, 'oïdium', 'Feutrage blanc sous les feuilles');
        $this->assertSame(StatutDiagnostic::Corrige, $corrige->statut);
        $this->assertSame('oïdium', $corrige->classe_retenue);
    }

    #[Test]
    public function un_diagnostic_incertain_se_valide_seulement_en_nommant_la_classe(): void
    {
        Diagnostics::demander($this->visite, $this->agent);
        $d = Diagnostic::query()->first();

        $this->assertThrows(fn () => Diagnostics::valider($d, $this->agronome, ''), OperationRefusee::class, 'Indiquer la maladie');
        $this->assertSame(StatutDiagnostic::Corrige, Diagnostics::valider($d, $this->agronome, 'sain')->statut);
    }

    #[Test]
    public function le_brouillon_ne_part_qu_apres_validation_avec_les_seules_fiches_proposables(): void
    {
        $pratique = $this->fiche();
        $chimique = $this->chimiqueComplete();
        $incomplete = $this->chimiqueComplete(['nom_commercial' => 'Autre-Produit', 'delai_avant_recolte_jours' => null]);
        $retiree = $this->chimiqueComplete(['nom_commercial' => 'Produit-Retiré', 'statut' => 'retiree']);
        $autreCulture = $this->fiche(['produit_id' => Produit::factory()->create()->id]);

        Diagnostics::demander($this->visite, $this->agent);
        $d = Diagnostic::query()->first();
        $this->assertThrows(fn () => Diagnostics::demanderConseil($d, $this->agronome, 'Taches noires'), OperationRefusee::class, 'Confirmer ou corriger');

        Diagnostics::valider($d, $this->agronome, 'anthracnose');
        Diagnostics::demanderConseil($d->refresh(), $this->agronome, 'Taches noires sur les jeunes feuilles');

        $this->assertSame([$pratique->id, $chimique->id], array_column($this->derniereDemande['fiches'], 'id'));
        $this->assertSame('anthracnose', $this->derniereDemande['diagnostic']);
        // Tous les noms connus, retirés et incomplets compris : le contrôle les rejettera en clair.
        foreach (['Produit-Test', 'Autre-Produit', 'Produit-Retiré', 'Matière-Test'] as $nom) {
            $this->assertContains($nom, $this->derniereDemande['noms_connus']);
        }
        $this->assertNotContains($autreCulture->id, array_column($this->derniereDemande['fiches'], 'id'));
        $this->assertNotContains($incomplete->id, array_column($this->derniereDemande['fiches'], 'id'));
        $this->assertNotContains($retiree->id, array_column($this->derniereDemande['fiches'], 'id'));

        $d->refresh();
        $this->assertSame('brouillon', $d->conseil_statut);
        // Rendu : les repères deviennent le texte VALIDÉ des fiches.
        $rendu = Diagnostics::rendreConseil($d);
        $this->assertStringContainsString('« Brûler les feuilles tombées »', $rendu);
        $this->assertStringContainsString('Produit-Test, Matière-Test : 2 l/ha', $rendu);
        $this->assertStringContainsString('délai avant récolte 21 jours', $rendu);
    }

    #[Test]
    public function une_annotation_provisoire_ne_valide_rien_et_ne_sort_que_sur_demande(): void
    {
        Diagnostics::demander($this->visite, $this->agent);
        [$d1, $d2] = Diagnostic::query()->orderBy('id')->get()->all();

        $this->artisan('ia:annoter')->expectsOutputToContain((string) $d1->id)->assertSuccessful();
        $this->artisan('ia:annoter', ['diagnostic' => $d1->id, 'classe' => 'anthracnose', '--note' => 'taches brunes en bordure'])->assertSuccessful();

        $d1->refresh();
        $this->assertSame(StatutDiagnostic::Incertain, $d1->statut, 'une annotation n\'est pas une validation');
        $this->assertSame('anthracnose', $d1->annotation_classe);
        $this->assertSame('claude', $d1->annotation_source);
        $this->assertNull($d1->valide_par);

        // Un agronome tranche : sa réponse prime, plus d'annotation provisoire possible.
        Diagnostics::valider($d2, $this->agronome, 'sain');
        $this->artisan('ia:annoter', ['diagnostic' => $d2->id, 'classe' => 'anthracnose'])->assertFailed();

        $dossier = storage_path('framework/testing/jeu-provisoire');
        // Par défaut : seulement la validation de l'agronome.
        $this->artisan('ia:exporter-jeu', ['dossier' => $dossier])->assertSuccessful();
        $this->assertCount(2, array_filter(explode("\n", trim(File::get($dossier.'/manifeste.csv')))));
        // Sur demande : l'annotation s'ajoute, marquée provisoire, jamais au jeu de test.
        $this->artisan('ia:exporter-jeu', ['dossier' => $dossier, '--avec-provisoires' => true])->assertSuccessful();
        $lignes = array_values(array_filter(explode("\n", trim(File::get($dossier.'/manifeste.csv')))));
        $this->assertCount(3, $lignes);
        $provisoire = array_values(array_filter($lignes, fn ($l) => str_contains($l, 'provisoire:claude')));
        $this->assertCount(1, $provisoire);
        $this->assertSame('entrainement', explode(';', $provisoire[0])[3]);
        File::deleteDirectory($dossier);
    }

    #[Test]
    public function une_fiche_citee_mais_non_fournie_fait_rejeter_le_brouillon(): void
    {
        $this->fiche();
        Diagnostics::demander($this->visite, $this->agent);
        $d = Diagnostic::query()->first();
        Diagnostics::valider($d, $this->agronome, 'anthracnose');
        $this->reponseConseil = ['statut' => 'brouillon', 'texte' => 'Voir [FICHE-999].', 'fiches_citees' => [999], 'motifs_rejet' => [], 'modele' => 'faux'];

        Diagnostics::demanderConseil($d->refresh(), $this->agronome, 'Taches noires');

        $this->assertSame('rejete', $d->refresh()->conseil_statut);
        $this->assertSame([], $d->conseil_fiches);
    }

    #[Test]
    public function l_export_ne_prend_que_les_photos_validees_sans_donnee_personnelle(): void
    {
        Diagnostics::demander($this->visite, $this->agent);
        Diagnostics::valider(Diagnostic::query()->first(), $this->agronome, 'Anthracnose');
        $dossier = storage_path('framework/testing/jeu-ia');

        $this->artisan('ia:exporter-jeu', ['dossier' => $dossier])->assertSuccessful();

        $manifeste = File::get($dossier.'/manifeste.csv');
        $lignes = array_values(array_filter(explode("\n", trim($manifeste))));
        $this->assertCount(2, $lignes); // en-tête + une seule photo validée
        $this->assertStringContainsString('anacarde/anthracnose/', $lignes[1]);
        $this->assertStringNotContainsString($this->visite->parcelle->producteur->nom, $manifeste);
        $this->assertFileExists($dossier.'/'.explode(';', $lignes[1])[0]);

        // Même photo, même jeu (entraînement ou test) à chaque export.
        $jeu = explode(';', $lignes[1])[3];
        $this->artisan('ia:exporter-jeu', ['dossier' => $dossier])->assertSuccessful();
        $this->assertSame($jeu, explode(';', explode("\n", trim(File::get($dossier.'/manifeste.csv')))[1])[3]);
        File::deleteDirectory($dossier);
    }

    #[Test]
    public function acces_aux_ecrans_ia(): void
    {
        foreach ([[$this->direction, 200], [$this->agronome, 200], [$this->agent, 403], [User::factory()->role(Role::Comptable)->create(), 403]] as [$u, $code]) {
            $this->actingAs($u)->get('/ia/diagnostics')->assertStatus($code);
            $this->actingAs($u)->get('/ia/referentiel')->assertStatus($code);
        }
    }

    #[Test]
    public function depuis_les_visites_l_agent_demande_un_avis_et_l_agronome_le_voit(): void
    {
        $this->actingAs($this->agent);
        Livewire::test(ListeVisites::class)
            ->assertSee('Demander un avis IA sur les photos')
            ->call('demanderAvisIa', $this->visite->id)
            ->assertSet('statut', fn ($s) => str_contains((string) $s, '2 photo(s) confiée(s) au service IA.'));

        $this->actingAs($this->agronome);
        Livewire::test(ListeDiagnostics::class)
            ->assertSee('Incertain (avis humain)')
            ->assertSee('Confirmer ou corriger');

        $this->actingAs($this->direction);
        Livewire::test(ListeDiagnostics::class)
            ->assertSee('Seul un agronome confirme')
            ->assertDontSee('Confirmer ou corriger');
    }
}
