<?php

/*
 * Sauvegardes (semaine 12) : base MySQL + fichiers privés (justificatifs, photos,
 * pièces), en une archive datée, vérifiée par une VRAIE restauration dans une base à
 * part. « ?: » et non la valeur par défaut d'env() : une clé déclarée vide vaut ''
 * (piège de CLAUDE.md).
 */
return [
    // Hors du dossier web. En production : un disque différent, puis copie hors site.
    'dossier' => env('SAUVEGARDE_DOSSIER') ?: storage_path('sauvegardes'),

    'mysqldump' => env('SAUVEGARDE_MYSQLDUMP') ?: 'mysqldump',
    'mysql' => env('SAUVEGARDE_MYSQL') ?: 'mysql',

    // Données personnelles des producteurs (loi 2013-450) : archive chiffrée (AES-256)
    // dès qu'un mot de passe est défini. À garder HORS du serveur (coffre, papier).
    'mot_de_passe' => env('SAUVEGARDE_MOT_DE_PASSE') ?: null,

    // Durée de conservation ; les 7 plus récentes sont toujours gardées.
    'conserver_jours' => (int) (env('SAUVEGARDE_CONSERVER_JOURS') ?: 30),

    // Base jetable où la vérification restaure l'archive, puis la supprime.
    'base_verification' => env('SAUVEGARDE_BASE_VERIFICATION') ?: 'ly_agricole_verif',
];
