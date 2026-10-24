import { Capacitor } from '@capacitor/core';
import type { BaseTerrain } from './db';
import { declarerJetonPush } from './synchro';

/**
 * Notifications sur le téléphone (appli Android seulement : Firebase Cloud Messaging).
 * L'agent est prévenu quand le bureau valide ou refuse sa saisie. Dans le navigateur,
 * rien : l'appli terrain n'y reçoit pas de notifications.
 *
 * Demande l'autorisation une fois, puis déclare le jeton au bureau (il change parfois :
 * Firebase le renvoie alors, et on le redéclare). Il faut `google-services.json` du projet
 * Firebase dans android/app pour que l'appli construite reçoive quelque chose.
 * NON VÉRIFIÉ : pas d'APK ni de projet Firebase sur le poste de dev (question 26).
 */
export async function activerNotifications(base: BaseTerrain): Promise<'active' | 'refuse' | 'indisponible'> {
    if (!Capacitor.isNativePlatform()) {
        return 'indisponible';
    }
    const { PushNotifications } = await import('@capacitor/push-notifications');

    let permission = await PushNotifications.checkPermissions();
    if (permission.receive === 'prompt' || permission.receive === 'prompt-with-rationale') {
        permission = await PushNotifications.requestPermissions();
    }
    if (permission.receive !== 'granted') {
        return 'refuse';
    }

    await PushNotifications.removeAllListeners();
    await PushNotifications.addListener('registration', ({ value }) => {
        // Hors réseau : le jeton sera redéclaré au prochain démarrage.
        declarerJetonPush(base, value, `Android ${navigator.userAgent.match(/Android [\d.]+; ([^)]+)\)/)?.[1] ?? ''}`.trim()).catch(() => undefined);
    });
    await PushNotifications.register();

    return 'active';
}
