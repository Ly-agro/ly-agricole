<?php

namespace Tests\Feature\Producteurs;

use App\Enums\Role;
use App\Livewire\Producteurs\ListeProducteurs;
use App\Models\Producteur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccesProducteursTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{Role, bool, bool}> [rôle, voit, saisit] */
    public static function droits(): array
    {
        return [
            'direction' => [Role::Direction, true, true],
            'agent' => [Role::Agent, true, true],
            'comptable' => [Role::Comptable, true, false],
            'agronome' => [Role::Agronome, true, false],
            'admin' => [Role::Admin, false, false],
            'investisseur' => [Role::Investisseur, false, false],
        ];
    }

    #[Test]
    #[DataProvider('droits')]
    public function les_donnees_personnelles_ne_sont_vues_que_par_qui_en_a_besoin(Role $role, bool $voit, bool $saisit): void
    {
        Storage::fake('local');
        $producteur = Producteur::factory()->create([
            'photo' => UploadedFile::fake()->image('p.jpg')->store('producteurs/photos', 'local'),
        ]);
        $user = User::factory()->role($role)->create();
        $this->actingAs($user);

        $this->get('/producteurs')->assertStatus($voit ? 200 : 403);
        $this->get("/producteurs/{$producteur->id}")->assertStatus($voit ? 200 : 403);
        $this->get("/producteurs/{$producteur->id}/photo")->assertStatus($voit ? 200 : 403);
        $this->get('/producteurs/nouveau')->assertStatus($saisit ? 200 : 403);
        $this->get("/producteurs/{$producteur->id}/modifier")->assertStatus($saisit ? 200 : 403);

        $lien = 'href="'.route('producteurs').'"';
        $tableau = $this->get('/tableau-de-bord');
        $voit ? $tableau->assertSee($lien, false) : $tableau->assertDontSee($lien, false);
    }

    #[Test]
    public function la_photo_n_est_pas_dans_le_dossier_public(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->actingAs(User::factory()->role(Role::Agent)->create());

        $chemin = UploadedFile::fake()->image('p.jpg')->store('producteurs/photos', 'local');
        $producteur = Producteur::factory()->create(['photo' => $chemin]);

        Storage::disk('public')->assertMissing($chemin);
        $this->get(route('producteurs.photo', $producteur))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, private');
    }

    #[Test]
    public function la_recherche_trouve_par_nom_code_ou_telephone_meme_mal_formate(): void
    {
        $this->actingAs(User::factory()->role(Role::Comptable)->create());
        $awa = Producteur::factory()->create(['nom' => 'Coulibaly', 'prenoms' => 'Awa', 'telephone' => '0701020304']);
        Producteur::factory()->create(['nom' => 'Traoré', 'prenoms' => 'Issa', 'telephone' => '0709090909']);

        foreach (['couli', strtolower($awa->code), '+225 07 01 02 03 04'] as $terme) {
            Livewire::test(ListeProducteurs::class)
                ->set('recherche', $terme)
                ->assertSee('Coulibaly Awa')
                ->assertDontSee('Traoré Issa');
        }

        Livewire::test(ListeProducteurs::class)->assertDontSee('Nouveau producteur');
    }
}
