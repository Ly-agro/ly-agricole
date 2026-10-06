<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

/*
 * PostgreSQL : DB_* d'abord, sinon les POSTGRES_* posés par l'intégration Neon de
 * Vercel. Une valeur vide compte comme absente (piège « clé .env vide »).
 */
$pg = static function (string $cle, string $repli, ?string $defaut = null): ?string {
    foreach ([$cle, $repli] as $nom) {
        $valeur = env($nom);
        if ($valeur !== null && $valeur !== '') {
            return (string) $valeur;
        }
    }

    return $defaut;
};
$pgHote = $pg('DB_HOST', 'POSTGRES_HOST', '127.0.0.1');
$pgMotDePasse = $pg('DB_PASSWORD', 'POSTGRES_PASSWORD', '');
$pgUtilisateur = $pg('DB_USERNAME', 'POSTGRES_USER', 'root');
$pgBase = $pg('DB_DATABASE', 'POSTGRES_DATABASE', 'laravel');
// Une DB_URL serait appliquée par Laravel À LA CONNEXION, par-dessus le mot de passe
// préfixé ci-dessous : on la décompose ici et on ne la transmet pas.
$pgUrl = env('DB_URL') ?: null;
$u = is_string($pgUrl) ? parse_url($pgUrl) : false;
if (is_array($u) && in_array($u['scheme'] ?? '', ['postgres', 'postgresql', 'pgsql'], true)) {
    $pgHote = $u['host'] ?? $pgHote;
    $pgUtilisateur = isset($u['user']) ? urldecode($u['user']) : $pgUtilisateur;
    $pgMotDePasse = isset($u['pass']) ? urldecode($u['pass']) : $pgMotDePasse;
    $pgBase = isset($u['path']) && $u['path'] !== '/' ? urldecode(ltrim($u['path'], '/')) : $pgBase;
    $pgUrl = null;
}
// La libpq du runtime PHP de Vercel n'envoie pas le SNI : Neon exige alors l'identifiant
// du point d'accès dans le mot de passe (neon.tech/sni). Seulement là (DB_NEON_ENDPOINT,
// posé par api/index.php) : avec une libpq récente (XAMPP), ce préfixe fait REFUSER le
// mot de passe (vu le 2026-10-05).
if (filter_var(env('DB_NEON_ENDPOINT', false), FILTER_VALIDATE_BOOL)
    && preg_match('/^(ep-[a-z0-9-]+?)(-pooler)?\.[^.]+.*\.neon\.tech$/', $pgHote, $m)
    && ! str_starts_with($pgMotDePasse, 'endpoint=')) {
    $pgMotDePasse = 'endpoint='.$m[1].';'.$pgMotDePasse;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => $pgUrl,
            'host' => $pgHote,
            'port' => env('DB_PORT', '5432'),
            'database' => $pgBase,
            'username' => $pgUtilisateur,
            'password' => $pgMotDePasse,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', str_ends_with($pgHote, '.neon.tech') ? 'require' : 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
