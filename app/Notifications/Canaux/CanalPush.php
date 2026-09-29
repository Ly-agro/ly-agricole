<?php

namespace App\Notifications\Canaux;

use App\Models\AbonnementPush;
use App\Models\User;
use App\Notifications\AvisLy;
use App\Services\Notifications;
use App\Services\Push\ExpediteurPush;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pousse un avis aux appareils de l'utilisateur pour un canal (web ou fcm), puis tient
 * les abonnements à jour : appareil disparu oublié, échec passager compté.
 */
abstract class CanalPush
{
    abstract protected function canal(): string;

    abstract protected function expediteur(): ExpediteurPush;

    public function send(object $notifiable, AvisLy $avis): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $abonnements = AbonnementPush::query()->where('user_id', $notifiable->id)->where('canal', $this->canal())->get();
        if ($abonnements->isEmpty()) {
            return;
        }

        try {
            $resultats = $this->expediteur()->envoyer($abonnements->all(), $avis->toArray($notifiable));
        } catch (Throwable $e) {
            // Panne du serveur (OpenSSL mal configuré, Firebase injoignable…) : ce n'est pas
            // la faute des appareils, on ne les compte pas en échec. L'avis reste dans la
            // liste de l'application.
            Log::error('Push : envoi impossible', ['canal' => $this->canal(), 'user_id' => $notifiable->id, 'erreur' => $e->getMessage()]);

            return;
        }

        foreach ($abonnements as $a) {
            match ($resultats[$a->id] ?? null) {
                'ok' => Notifications::reussi($a),
                'disparu' => Notifications::echec($a, definitif: true),
                'echec' => Notifications::echec($a, definitif: false),
                default => null, // non envoyé (pas de clés VAPID) : rien à compter
            };
        }
    }
}
