<?php

namespace App\Services\Push;

use App\Models\AbonnementPush;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging (appli terrain), API HTTP v1. Pilote `journal` (par défaut) :
 * le message est écrit dans storage/logs et rien ne part, comme les SMS. Pilote `fcm` :
 * jeton d'accès OAuth obtenu avec le compte de service Firebase (JSON privé, hors dépôt).
 *
 * NON VÉRIFIÉ contre Firebase : ni projet Firebase ni APK sur le poste de dev (question 26).
 */
class FirebaseFcm implements ExpediteurPush
{
    private const PORTEE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function envoyer(array $abonnements, array $message): array
    {
        if ($abonnements === []) {
            return [];
        }

        if (config('notifications_push.fcm.pilote') !== 'fcm') {
            foreach ($abonnements as $a) {
                Log::info('Push FCM (pilote journal)', ['abonnement' => $a->id, 'user_id' => $a->user_id] + $message);
            }

            return array_fill_keys(array_map(fn (AbonnementPush $a) => $a->id, $abonnements), 'ok');
        }

        $projet = (string) config('notifications_push.fcm.projet');
        $jeton = $this->jetonAcces();
        $resultats = [];

        foreach ($abonnements as $a) {
            $reponse = Http::withToken($jeton)->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$projet}/messages:send", ['message' => [
                    'token' => $a->destination,
                    'notification' => ['title' => $message['titre'], 'body' => $message['texte']],
                    'data' => ['categorie' => $message['categorie'], 'url' => (string) $message['url']],
                    'android' => ['priority' => 'high'],
                ]]);

            if ($reponse->successful()) {
                $resultats[$a->id] = 'ok';
            } else {
                // 404 / UNREGISTERED : appli désinstallée ou jeton remplacé.
                $code = (string) $reponse->json('error.details.0.errorCode', '');
                $resultats[$a->id] = $reponse->status() === 404 || $code === 'UNREGISTERED' ? 'disparu' : 'echec';
                Log::warning('Push FCM refusé', ['abonnement' => $a->id, 'statut' => $reponse->status(), 'code' => $code]);
            }
        }

        return $resultats;
    }

    /** Jeton OAuth 2 (JWT signé RS256 avec la clé du compte de service), gardé 50 min. */
    private function jetonAcces(): string
    {
        return Cache::remember('push.fcm.jeton', 3000, function (): string {
            $chemin = (string) config('notifications_push.fcm.compte_service');
            $compte = is_file($chemin) ? json_decode((string) file_get_contents($chemin), true) : null;
            if (! is_array($compte) || ! isset($compte['client_email'], $compte['private_key'])) {
                throw new RuntimeException('Compte de service Firebase introuvable ou illisible (PUSH_FCM_COMPTE_SERVICE).');
            }

            $b64 = fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
            $maintenant = time();
            $entete = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $corps = $b64((string) json_encode([
                'iss' => $compte['client_email'], 'scope' => self::PORTEE,
                'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $maintenant, 'exp' => $maintenant + 3600,
            ]));
            if (! openssl_sign("{$entete}.{$corps}", $signature, $compte['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Signature du jeton Firebase impossible.');
            }

            $reponse = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$entete}.{$corps}.".$b64($signature),
            ])->throw();

            return (string) $reponse->json('access_token');
        });
    }
}
