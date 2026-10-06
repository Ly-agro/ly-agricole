<?php

/*
 * Vercel serverless entrypoint. The function filesystem is read-only except
 * /tmp, so Laravel's caches, compiled views and logs are redirected there.
 * Values already set in the Vercel project settings always take precedence.
 */
$vercelDefaults = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => '/tmp/config.php',
    'APP_EVENTS_CACHE' => '/tmp/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/views',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'cookie',
    // Pas de serveur WebSocket (Reverb) en serverless : avis en direct coupés, la liste
    // des avis et le push restent.
    'BROADCAST_CONNECTION' => 'log',
    // libpq sans SNI : identifiant Neon dans le mot de passe (config/database.php).
    'DB_NEON_ENDPOINT' => 'true',
];

foreach ($vercelDefaults as $key => $value) {
    if (getenv($key) === false) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (! is_dir('/tmp/views')) {
    mkdir('/tmp/views', 0755, true);
}

require __DIR__.'/../public/index.php';
