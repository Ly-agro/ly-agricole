<?php

namespace App\Notifications;

use App\Notifications\Canaux\FcmCanal;
use App\Notifications\Canaux\WebPushCanal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Un avis de la plateforme : gardé dans la liste de l'application (canal `database`) et
 * poussé aux appareils abonnés (navigateur, téléphone). En file d'attente, et seulement
 * APRÈS le commit : un avis ne part jamais pour une opération annulée.
 */
class AvisLy extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $categorie  a_valider | valide | refuse | alerte
     */
    public function __construct(
        public string $titre,
        public string $texte,
        public ?string $url = null,
        public string $categorie = 'alerte',
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', WebPushCanal::class, FcmCanal::class];
    }

    /** @return array{titre: string, texte: string, url: string|null, categorie: string} */
    public function toArray(object $notifiable): array
    {
        return ['titre' => $this->titre, 'texte' => $this->texte, 'url' => $this->url, 'categorie' => $this->categorie];
    }
}
