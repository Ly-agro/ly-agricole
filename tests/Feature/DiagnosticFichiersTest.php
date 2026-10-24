<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiagnosticFichiersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_direction_voit_l_essai_du_disque_sans_les_secrets_les_autres_non(): void
    {
        Storage::fake('local');
        config(['filesystems.disks.r2.secret' => 'secret-a-ne-jamais-montrer']);

        $this->actingAs(User::factory()->role(Role::Agent)->create())->get('/diagnostic/fichiers')->assertForbidden();

        $this->actingAs(User::factory()->role(Role::Direction)->create())->get('/diagnostic/fichiers')
            ->assertOk()
            ->assertJsonPath('disque_fichiers', 'local')
            ->assertJsonPath('essais.local', 'OK : écriture, lecture et suppression réussies')
            ->assertJsonPath('variables_r2.R2_SECRET_ACCESS_KEY', true)
            ->assertDontSee('secret-a-ne-jamais-montrer');
    }
}
