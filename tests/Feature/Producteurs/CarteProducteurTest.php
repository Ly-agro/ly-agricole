<?php

namespace Tests\Feature\Producteurs;

use App\Enums\ActionJournal;
use App\Enums\Role;
use App\Enums\TypePiece;
use App\Models\JournalActivite;
use App\Models\Producteur;
use App\Models\User;
use App\Support\CodeQr;
use chillerlan\QRCode\QRCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CarteProducteurTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_qr_code_une_fois_lu_redonne_exactement_le_code_de_la_carte(): void
    {
        $png = base64_decode(explode(',', CodeQr::pngDataUri('LYP-000042'), 2)[1]);

        $this->assertSame('LYP-000042', (string) (new QRCode)->readFromBlob($png));
    }

    #[Test]
    public function la_carte_est_un_pdf_journalise_au_nom_de_qui_l_imprime(): void
    {
        Storage::fake('local');
        $agent = User::factory()->role(Role::Agent)->create();
        $producteur = Producteur::factory()->create([
            'photo' => UploadedFile::fake()->image('p.jpg', 300, 400)->store('producteurs/photos', 'local'),
        ]);

        $reponse = $this->actingAs($agent)->get(route('producteurs.carte', $producteur));

        $reponse->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', (string) $reponse->getContent());

        $ligne = JournalActivite::query()->where('action', ActionJournal::ImpressionCarte)->firstOrFail();
        $this->assertSame($agent->id, $ligne->user_id);
        $this->assertSame($producteur->id, $ligne->objet_id);
    }

    #[Test]
    public function la_carte_ne_porte_ni_telephone_ni_numero_de_piece(): void
    {
        $producteur = Producteur::factory()->create([
            'telephone' => '0701020304',
            'piece_type' => TypePiece::Cni,
            'piece_numero' => 'CI0099887',
        ]);

        $html = View::make('producteurs.carte', [
            'producteur' => $producteur->load('village.zone'),
            'photo' => null,
            'qr' => CodeQr::pngDataUri($producteur->code),
        ])->render();

        $this->assertStringContainsString($producteur->code, $html);
        $this->assertStringContainsString(e($producteur->nom), $html);
        $this->assertStringNotContainsString('0701020304', $html);
        $this->assertStringNotContainsString('CI0099887', $html);
    }

    #[Test]
    public function seuls_ceux_qui_gerent_les_producteurs_impriment_une_carte(): void
    {
        $producteur = Producteur::factory()->create();

        $this->actingAs(User::factory()->role(Role::Comptable)->create())
            ->get(route('producteurs.carte', $producteur))
            ->assertForbidden();

        $this->assertSame(0, JournalActivite::query()->where('action', ActionJournal::ImpressionCarte)->count());
    }
}
