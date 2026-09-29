<?php

namespace Tests\Feature\Sauvegardes;

use App\Enums\NatureMouvement;
use App\Enums\Role;
use App\Models\CompteTresorerie;
use App\Models\PhotoTerrain;
use App\Models\User;
use App\Services\Rapports;
use App\Services\Sauvegardes;
use App\Services\Tresorerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Les tests tournent sur sqlite : `mysqldump` et l'import MySQL sont remplacés par un
 * faux (la restauration « rend » la base de test). Tout le reste est réel : archive,
 * chiffrement, empreintes SHA-256, comptes et sommes, fichiers cités, conservation.
 * Le vrai dump / la vraie restauration MySQL sont vérifiés en vrai (voir le rapport).
 */
class SauvegardesTest extends TestCase
{
    use RefreshDatabase;

    private string $dossier;

    private User $direction;

    private CompteTresorerie $caisse;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dossier = storage_path('framework/testing/sauvegardes-'.uniqid());
        config(['sauvegardes.dossier' => $this->dossier, 'sauvegardes.mot_de_passe' => null]);
        $this->app->instance(Sauvegardes::class, new SauvegardesDeTest);

        $this->direction = User::factory()->role(Role::Direction)->create();
        $this->caisse = CompteTresorerie::factory()->create();
        Tresorerie::entree($this->caisse, 1_500_000, NatureMouvement::Apport, Carbon::today(), 'Fonds', $this->direction);
        Storage::disk('local')->put('depenses/justificatifs/recu.jpg', 'image');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dossier);
        parent::tearDown();
    }

    private function service(): SauvegardesDeTest
    {
        /** @var SauvegardesDeTest */
        return app(Sauvegardes::class);
    }

    #[Test]
    public function l_archive_contient_la_base_les_fichiers_et_une_empreinte_des_registres(): void
    {
        $archive = $this->service()->creer();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive));
        $this->assertNotFalse($zip->locateName('base.sql'));
        $this->assertSame('image', $zip->getFromName('fichiers/depenses/justificatifs/recu.jpg'));
        $manifeste = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertSame(1, $manifeste['empreinte']['tables']['mouvements_tresorerie']);
        $this->assertSame(1_500_000, $manifeste['empreinte']['sommes']['tresorerie_entrees_fcfa']);
        $this->assertArrayNotHasKey('sessions', $manifeste['empreinte']['tables']);
        $this->assertSame(hash('sha256', 'image'), $manifeste['sha256']['fichiers/depenses/justificatifs/recu.jpg']);
    }

    #[Test]
    public function une_restauration_identique_est_verifiee_et_notee(): void
    {
        $archive = $this->service()->creer();

        $rapport = $this->service()->verifier($archive);

        $this->assertTrue($rapport['ok'], implode(' ', $rapport['erreurs']));
        $this->assertTrue($this->service()->derniereVerification()['ok']);
        $this->artisan('ly:verifier-sauvegarde')->expectsOutputToContain('Restauration vérifiée')->assertSuccessful();
    }

    #[Test]
    public function une_restauration_qui_ne_rend_pas_les_memes_sommes_echoue(): void
    {
        $archive = $this->service()->creer();
        // La base « restaurée » diffère de l'instantané : un mouvement en plus.
        Tresorerie::entree($this->caisse, 250_000, NatureMouvement::Apport, Carbon::today(), 'Apport', $this->direction);

        $rapport = $this->service()->verifier($archive);

        $this->assertFalse($rapport['ok']);
        $erreurs = implode("\n", $rapport['erreurs']);
        $this->assertStringContainsString('mouvements_tresorerie = 2, attendu 1', $erreurs);
        $this->assertStringContainsString('tresorerie_entrees_fcfa = 1750000, attendu 1500000', $erreurs);
        $this->artisan('ly:verifier-sauvegarde', ['archive' => $archive])->assertFailed();
    }

    #[Test]
    public function une_archive_alteree_est_refusee_avant_toute_restauration(): void
    {
        $archive = $this->service()->creer();
        $zip = new ZipArchive;
        $zip->open($archive);
        $zip->addFromString('base.sql', "-- modifié après coup\n");
        $zip->close();

        $rapport = $this->service()->verifier($archive);

        $this->assertFalse($rapport['ok']);
        $this->assertStringContainsString('altéré ou manquant', $rapport['erreurs'][0]);
        $this->assertSame(0, $this->service()->restaurations, 'Pas de restauration d\'une archive altérée.');
    }

    #[Test]
    public function un_fichier_cite_par_la_base_mais_absent_est_signale(): void
    {
        PhotoTerrain::query()->create(['id' => '01900000-0000-7000-8000-000000000009', 'user_id' => $this->direction->id,
            'appareil_id' => 'tel', 'chemin' => 'terrain/photos/absente.jpg', 'mime' => 'image/jpeg', 'taille_octets' => 10, 'recu_at' => now()]);

        $rapport = $this->service()->verifier($this->service()->creer());

        $this->assertFalse($rapport['ok']);
        $this->assertStringContainsString('terrain/photos/absente.jpg', implode(' ', $rapport['erreurs']));
    }

    #[Test]
    public function avec_un_mot_de_passe_l_archive_est_chiffree_aes_256(): void
    {
        config(['sauvegardes.mot_de_passe' => 'coffre-LY-2026']);
        $archive = $this->service()->creer();

        $zip = new ZipArchive;
        $zip->open($archive);
        $this->assertSame(ZipArchive::EM_AES_256, $zip->statName('base.sql')['encryption_method']);
        $this->assertSame(ZipArchive::EM_AES_256, $zip->statName('fichiers/depenses/justificatifs/recu.jpg')['encryption_method']);
        $zip->close();

        $this->assertTrue($this->service()->verifier($archive)['ok']);

        config(['sauvegardes.mot_de_passe' => 'mauvais']);
        $this->assertFalse($this->service()->verifier($archive)['ok']);
    }

    #[Test]
    public function les_anciennes_archives_partent_mais_les_7_dernieres_restent(): void
    {
        File::ensureDirectoryExists($this->dossier);
        foreach (range(1, 10) as $i) {
            $f = $this->dossier.'/ly-agricole-202601'.sprintf('%02d', $i).'-020000.zip';
            File::put($f, 'x');
            touch($f, now()->subDays(60)->getTimestamp());
        }

        $this->service()->creer();

        // 10 vieilles + 1 nouvelle : les 7 plus récentes restent, dont la nouvelle.
        $this->assertCount(7, glob($this->dossier.'/ly-agricole-*.zip'));
    }

    #[Test]
    public function la_base_de_verification_ne_peut_pas_etre_la_base_de_production(): void
    {
        config(['sauvegardes.base_verification' => config('database.connections.sqlite.database')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ne peut pas être la base de production');
        (new SauvegardesDeTest)->restaurerVraiment('x.sql');
    }

    #[Test]
    public function hors_mysql_la_sauvegarde_echoue_clairement(): void
    {
        $this->app->instance(Sauvegardes::class, new Sauvegardes);

        $this->artisan('ly:sauvegarder')->expectsOutputToContain('MySQL / MariaDB seulement')->assertFailed();
    }

    #[Test]
    public function sans_restauration_verifiee_les_rapports_le_signalent(): void
    {
        $alertes = fn () => array_column(app(Rapports::class)->alertes()->lignes, 0);
        $this->assertContains('Sauvegarde non vérifiée', $alertes());

        $this->service()->verifier($this->service()->creer());
        $this->assertNotContains('Sauvegarde non vérifiée', $alertes());

        $this->travel(9)->days();
        $this->assertContains('Sauvegarde non vérifiée', $alertes());
    }
}

/** Remplace seulement mysqldump et l'import MySQL (indisponibles sur sqlite). */
class SauvegardesDeTest extends Sauvegardes
{
    public int $restaurations = 0;

    protected function dump(string $fichier): void
    {
        file_put_contents($fichier, "-- faux dump pour les tests\n");
    }

    protected function restaurer(string $sql): string
    {
        $this->restaurations++;

        return DB::getDefaultConnection();
    }

    public function restaurerVraiment(string $sql): string
    {
        return parent::restaurer($sql);
    }
}
