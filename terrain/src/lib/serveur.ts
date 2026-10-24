/**
 * Adresse du serveur (question 27, 2026-09-28) : les agents envoient depuis les villages
 * par les données mobiles, donc le serveur est EN LIGNE et en HTTPS. Le http n'est
 * accepté que pour le développement : localhost, réseau privé, noms en .test / .local.
 */

const PRIVES = [/^localhost$/, /^127\./, /^10\./, /^192\.168\./, /^172\.(1[6-9]|2\d|3[01])\./, /\.test$/, /\.local$/, /^\[::1\]$/];

export function verifierAdresse(saisie: string): { adresse: string } | { erreur: string } {
    let url: URL;
    try {
        url = new URL(saisie.trim());
    } catch {
        return { erreur: 'Adresse du serveur invalide (ex. https://gestion.exemple.ci).' };
    }
    if (url.protocol === 'https:') {
        return { adresse: url.origin };
    }
    if (url.protocol === 'http:' && PRIVES.some((r) => r.test(url.hostname))) {
        return { adresse: url.origin };
    }

    return { erreur: 'Le serveur doit être en https:// (le http n\'est permis que pour les essais sur le réseau local).' };
}
