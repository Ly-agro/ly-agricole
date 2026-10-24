<?php

// Une clé .env déclarée VIDE rend '' et non null (piège connu) : on filtre les deux.
$env = fn (string $cle): ?string => ($v = env($cle)) === null || $v === '' ? null : (string) $v;

return [
    /*
     * Coordonnées publiques de LY AGRICOLE, affichées sur la vitrine (section « Nous trouver »
     * et pied de page). Téléphone : 10 chiffres ivoiriens, sans indicatif.
     */
    'contact' => [
        'email' => $env('VITRINE_EMAIL') ?? 'direction@ylagro.com',
        'telephone' => $env('VITRINE_TELEPHONE') ?? '0778155878',
    ],
];
