<?php

/*
 * Vercel serverless entrypoint. The function filesystem is read-only except
 * /tmp, so Laravel's caches, compiled views and logs are redirected there.
 * Values already set in the Vercel project settings take precedence, except
 * the forced ones below.
 */
$vercelDefaults = [
    'APP_ENV' => 'production',
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
];

// Imposés quoi que disent les réglages Vercel.
$vercelForces = [
    // La page d'erreur détaillée publiait cookies, en-têtes et jetons Vercel (2026-10-06).
    'APP_DEBUG' => 'false',
    // libpq sans SNI : point d'accès Neon en option de connexion (config/database.php).
    'DB_NEON_ENDPOINT' => 'true',
];

foreach ($vercelDefaults + $vercelForces as $key => $value) {
    if (getenv($key) === false || array_key_exists($key, $vercelForces)) {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (! is_dir('/tmp/views')) {
    mkdir('/tmp/views', 0755, true);
}

require __DIR__.'/../public/index.php';
