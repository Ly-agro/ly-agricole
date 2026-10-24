<?php

namespace Tests\Feature\Publications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Les coordonnées publiques de LY AGRICOLE figurent sur la vitrine, en liens cliquables. */
class ContactVitrineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_vitrine_donne_l_email_et_le_telephone_de_la_direction(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('href="mailto:direction@ylagro.com"', false)
            ->assertSee('href="tel:+2250778155878"', false)
            ->assertSee('07 78 15 58 78');
    }

    #[Test]
    public function une_cle_env_vide_garde_les_coordonnees_par_defaut(): void
    {
        putenv('VITRINE_EMAIL=');
        try {
            $config = require base_path('config/vitrine.php');
        } finally {
            putenv('VITRINE_EMAIL');
        }

        $this->assertSame('direction@ylagro.com', $config['contact']['email']);
    }
}
