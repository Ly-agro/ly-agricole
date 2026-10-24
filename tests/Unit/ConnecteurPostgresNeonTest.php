<?php

namespace Tests\Unit;

use App\Support\ConnecteurPostgresNeon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/** Neon sans SNI (Vercel) : le point d'accès part dans l'option `options` du DSN. */
class ConnecteurPostgresNeonTest extends TestCase
{
    /** @param array<string, mixed> $config */
    private function dsn(array $config): string
    {
        $methode = new ReflectionMethod(ConnecteurPostgresNeon::class, 'getDsn');

        return $methode->invoke(new ConnecteurPostgresNeon, $config + [
            'host' => 'ep-jolly-queen-b2bnoe03.c-6.eu-central-1.aws.neon.tech',
            'port' => 5432,
            'database' => 'neondb',
            'sslmode' => 'require',
        ]);
    }

    #[Test]
    public function le_point_d_acces_est_ajoute_en_option(): void
    {
        $dsn = $this->dsn(['neon_endpoint' => 'ep-jolly-queen-b2bnoe03']);

        $this->assertStringEndsWith(";options='endpoint=ep-jolly-queen-b2bnoe03'", $dsn);
        $this->assertStringContainsString('sslmode=require', $dsn);
    }

    #[Test]
    public function sans_point_d_acces_le_dsn_est_celui_de_laravel(): void
    {
        $this->assertStringNotContainsString('options=', $this->dsn([]));
        $this->assertStringNotContainsString('options=', $this->dsn(['neon_endpoint' => null]));
    }

    #[Test]
    public function un_point_d_acces_mal_forme_est_ignore(): void
    {
        $this->assertStringNotContainsString('options=', $this->dsn(['neon_endpoint' => "ep-x' host=ailleurs"]));
    }
}
