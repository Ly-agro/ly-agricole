<?php

// Une clé .env déclarée VIDE rend '' et non null (piège connu) : on filtre les deux.
$env = fn (string $cle): ?string => ($v = env($cle)) === null || $v === '' ? null : (string) $v;

return [
    /*
     * Service ia/ (FastAPI + Ollama) sur le serveur IA de LY, joint par le VPN
     * (docs/INSTALLATION_IA.md). Sans adresse, aucune demande ne part : les diagnostics
     * restent « en attente » et le signalent.
     */
    'url' => $env('IA_URL'),
    'jeton' => $env('IA_JETON'),
    // Un modèle local peut répondre en dizaines de secondes : la file d'attente patiente.
    'delai_secondes' => (int) ($env('IA_DELAI_SECONDES') ?? 240),
];
