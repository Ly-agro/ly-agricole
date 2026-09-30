<?php

namespace App\Services\Ia;

use App\Models\PhotoTerrain;

/**
 * Le service ia/ (FastAPI + Ollama) sur le serveur IA de LY. Appelé seulement depuis la
 * file d'attente. Les tests remplacent l'implémentation (aucun appel réseau).
 */
interface ClientIa
{
    /** @return array{statut: string, classe: string|null, confiance_pour_mille: int, motif: string, modele: string} */
    public function diagnostiquer(PhotoTerrain $photo, string $culture): array;

    /**
     * @param  array{culture: string, observation: string, diagnostic: string|null, fiches: list<array<string, mixed>>, noms_connus: list<string>}  $demande
     * @return array{statut: string, texte: string, fiches_citees: list<int>, motifs_rejet: list<string>, modele: string}
     */
    public function conseil(array $demande): array;
}
