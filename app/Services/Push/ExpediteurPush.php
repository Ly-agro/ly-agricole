<?php

namespace App\Services\Push;

use App\Models\AbonnementPush;

/**
 * Envoie un message « push » à des appareils d'un même canal. Rend, pour chaque
 * abonnement, le résultat : `ok`, `disparu` (appareil à oublier) ou `echec` (passager).
 * Les tests remplacent l'implémentation par une fausse (voir NotificationsTest).
 */
interface ExpediteurPush
{
    /**
     * @param  list<AbonnementPush>  $abonnements
     * @param  array{titre: string, texte: string, url: string|null, categorie: string}  $message
     * @return array<int, 'ok'|'disparu'|'echec'> clé = id de l'abonnement
     */
    public function envoyer(array $abonnements, array $message): array;
}
