<?php

namespace App\Support;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * La libpq du runtime PHP de Vercel n'envoie pas le SNI : Neon ne sait alors pas quel
 * point d'accès est visé (« Endpoint ID is not specified »). On le lui donne par le
 * paramètre `options` de la connexion (neon.tech/sni), que Laravel ne sait pas poser.
 * L'astuce « endpoint=…; » dans le mot de passe n'a pas suffi sur Vercel (2026-10-06).
 */
class ConnecteurPostgresNeon extends PostgresConnector
{
    /**
     * @param  array<string, mixed>  $config
     */
    protected function getDsn(array $config): string
    {
        $dsn = parent::getDsn($config);

        $pointAcces = $config['neon_endpoint'] ?? null;
        if (is_string($pointAcces) && preg_match('/^ep-[a-z0-9-]+$/', $pointAcces)) {
            $dsn .= ";options='endpoint={$pointAcces}'";
        }

        return $dsn;
    }
}
