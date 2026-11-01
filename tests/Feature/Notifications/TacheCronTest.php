<?php

namespace Tests\Feature\Notifications;

use App\Enums\Role;
use App\Enums\StatutCampagne;
use App\Models\Campagne;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Adresses /cron/* appelées par Vercel Cron à la place de schedule:run. */
class TacheCronTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sans_secret_configure_tout_est_refuse(): void
    {
        config(['services.cron.secret' => null]);

        $this->get('/cron/alertes')->assertForbidden();
        $this->withToken('')->get('/cron/alertes')->assertForbidden();
    }

    #[Test]
    public function un_mauvais_jeton_est_refuse(): void
    {
        config(['services.cron.secret' => 'le-bon-secret']);

        $this->get('/cron/alertes')->assertForbidden();
        $this->withToken('un-autre')->get('/cron/alertes')->assertForbidden();
    }

    #[Test]
    public function une_tache_hors_liste_est_introuvable(): void
    {
        config(['services.cron.secret' => 'le-bon-secret']);

        $this->withToken('le-bon-secret')->get('/cron/sauvegarder')->assertNotFound();
    }

    #[Test]
    public function le_bon_jeton_lance_les_alertes(): void
    {
        config(['services.cron.secret' => 'le-bon-secret']);

        $this->withToken('le-bon-secret')->get('/cron/alertes')
            ->assertOk()
            ->assertJson(['tache' => 'alertes', 'code' => 0]);
    }

    #[Test]
    public function le_cron_suspend_automatiquement_les_campagnes_expirees(): void
    {
        config(['services.cron.secret' => 'le-bon-secret']);
        $this->actingAs(User::factory()->role(Role::Direction)->create());
        $campagne = Campagne::factory()->statut(StatutCampagne::Ouverte)->create(['fin' => '2027-09-30']);
        Carbon::setTestNow('2027-10-01 00:06:00');

        try {
            $this->withToken('le-bon-secret')->get('/cron/suspendre-campagnes')
                ->assertOk()
                ->assertJson(['tache' => 'suspendre-campagnes', 'code' => 0]);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame(StatutCampagne::Suspendue, $campagne->refresh()->statut);
    }
}
