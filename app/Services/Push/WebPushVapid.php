<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Web Push (navigateur du bureau), chiffré et signé VAPID par minishlink/web-push. Sans
 * clés VAPID configurées, rien ne part (la liste dans l'application reste).
 */
class WebPushVapid implements ExpediteurPush
{
    public function envoyer(array $abonnements, array $message): array
    {
        $publique = config('notifications_push.vapid.publique');
        $privee = config('notifications_push.vapid.privee');
        if (! is_string($publique) || ! is_string($privee) || $abonnements === []) {
            return [];
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('notifications_push.vapid.sujet'),
            'publicKey' => $publique,
            'privateKey' => $privee,
        ]], ['TTL' => 86_400]);

        $parAdresse = [];
        foreach ($abonnements as $a) {
            $parAdresse[$a->destination] = $a->id;
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $a->destination,
                'keys' => ['p256dh' => (string) $a->cle_p256dh, 'auth' => (string) $a->cle_auth],
            ]), (string) json_encode($message, JSON_UNESCAPED_UNICODE));
        }

        $resultats = [];
        foreach ($webPush->flush() as $rapport) {
            $id = $parAdresse[$rapport->getEndpoint()] ?? null;
            if ($id === null) {
                continue;
            }
            if ($rapport->isSuccess()) {
                $resultats[$id] = 'ok';
            } else {
                $resultats[$id] = $rapport->isSubscriptionExpired() ? 'disparu' : 'echec';
                Log::warning('Web Push refusé', ['abonnement' => $id, 'raison' => $rapport->getReason()]);
            }
        }

        return $resultats;
    }
}
