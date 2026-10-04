<?php

namespace App\Notifications;

use App\Notifications\Canaux\FcmCanal;
use App\Notifications\Canaux\WebPushCanal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Un avis de la plateforme : gardé dans la liste de l'application (canal `database`) et
 * poussé aux appareils abonnés (navigateur, téléphone). En file d'attente, et seulement
 * APRÈS le commit : un avis ne part jamais pour une opération annulée.
 */
class AvisLy extends Notification implements ShouldQueue
{
    use Queueable;

    /** Ce qu'affiche l'écran verrouillé quand l'avis n'a pas de texte « push » à lui. */
    public const TEXTE_PUSH = 'Ouvrir LY AGRICOLE pour voir le détail.';

    /**
     * @param  string  $categorie  a_valider | valide | refuse | alerte
     * @param  string|null  $textePush  texte GÉNÉRIQUE montré par le push (écran verrouillé) :
     *                                  ni nom de producteur, ni montant, ni téléphone. Le texte
     *                                  complet reste dans l'application.
     */
    public function __construct(
        public string $titre,
        public string $texte,
        public ?string $url = null,
        public string $categorie = 'alerte',
        public ?string $textePush = null,
    ) {
        $this->afterCommit();
    }

    /** @return array{titre: string, texte: string, url: string|null, categorie: string} */
    public function pourPush(): array
    {
        return ['titre' => $this->titre, 'texte' => $this->textePush ?? self::TEXTE_PUSH, 'url' => $this->url, 'categorie' => $this->categorie];
    }

    /**
     * Diffusion en direct (Reverb) seulement si un diffuseur réel est configuré : en file, un
     * canal par tâche, donc un serveur Reverb arrêté ne bloque ni la liste ni le push.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $canaux = ['database', WebPushCanal::class, FcmCanal::class];
        if (! in_array(config('broadcasting.default'), ['null', 'log', null], true)) {
            $canaux[] = 'broadcast';
        }

        return $canaux;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /** @return array{titre: string, texte: string, url: string|null, categorie: string} */
    public function toArray(object $notifiable): array
    {
        return ['titre' => $this->titre, 'texte' => $this->texte, 'url' => $this->url, 'categorie' => $this->categorie];
    }
}
