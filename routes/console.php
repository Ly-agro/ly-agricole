<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Minishlink\WebPush\VAPID;

// Clés VAPID du Web Push (notifications du bureau) : à copier dans .env, une seule fois.
// Les changer oblige chaque navigateur à se réabonner.
Artisan::command('notifications:cles-vapid', function () {
    $cles = VAPID::createVapidKeys();
    $this->line('PUSH_VAPID_PUBLIQUE='.$cles['publicKey']);
    $this->line('PUSH_VAPID_PRIVEE='.$cles['privateKey']);
    $this->comment('À mettre dans .env (la clé privée ne se partage pas), puis php artisan config:clear.');
})->purpose('Générer les clés VAPID des notifications du bureau');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
