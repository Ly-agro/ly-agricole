<?php

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
