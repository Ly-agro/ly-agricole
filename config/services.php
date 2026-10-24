<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Confirmations SMS aux producteurs (D10). « ?: » et non la valeur par défaut d'env() :
    // une clé déclarée vide (SMS_PILOTE=) vaut '' et non null (piège de CLAUDE.md).
    'sms' => [
        'pilote' => env('SMS_PILOTE') ?: 'journal',
    ],

    // Tâches planifiées appelées par Vercel Cron (vercel.json), qui envoie ce secret en
    // « Authorization: Bearer ». Absent ou vide : les adresses /cron/* refusent tout.
    'cron' => [
        'secret' => env('CRON_SECRET') ?: null,
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
