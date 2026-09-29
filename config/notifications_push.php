<?php

// Une clé .env déclarée VIDE rend '' et non null (piège connu) : on filtre les deux.
$env = fn (string $cle): ?string => ($v = env($cle)) === null || $v === '' ? null : (string) $v;

return [
    /*
     * Web Push (navigateur du bureau). Clés VAPID : `php artisan notifications:cles-vapid`.
     * Sans clés, rien ne part vers les navigateurs ; la liste dans l'application reste.
     */
    'vapid' => [
        'sujet' => $env('PUSH_VAPID_SUJET') ?? 'mailto:contact@ylagro.com',
        'publique' => $env('PUSH_VAPID_PUBLIQUE'),
        'privee' => $env('PUSH_VAPID_PRIVEE'),
    ],

    /*
     * Firebase Cloud Messaging (appli terrain). Pilote `journal` : les messages sont écrits
     * dans storage/logs, rien ne part — comme les SMS tant que le fournisseur n'est pas
     * choisi. Pilote `fcm` : projet Firebase et compte de service (fichier JSON privé).
     */
    'fcm' => [
        'pilote' => $env('PUSH_FCM_PILOTE') ?? 'journal',
        'projet' => $env('PUSH_FCM_PROJET'),
        'compte_service' => $env('PUSH_FCM_COMPTE_SERVICE'),
    ],
];
