/*
 * Service worker de LY AGRICOLE : affiche les notifications « push » du bureau.
 * Rien d'autre (pas de cache hors ligne : le bureau est en ligne, D2).
 */
self.addEventListener('push', (evenement) => {
    let avis = { titre: 'LY AGRICOLE', texte: '', url: '/notifications', categorie: 'alerte' };
    try {
        avis = { ...avis, ...evenement.data.json() };
    } catch {
        // Message illisible : on affiche quand même un avis générique.
    }
    evenement.waitUntil(self.registration.showNotification(avis.titre, {
        body: avis.texte,
        tag: avis.categorie + ':' + avis.titre,
        data: { url: avis.url || '/notifications' },
        lang: 'fr',
    }));
});

self.addEventListener('notificationclick', (evenement) => {
    evenement.notification.close();
    const url = new URL(evenement.notification.data?.url || '/notifications', self.location.origin).href;
    evenement.waitUntil((async () => {
        const fenetres = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const f of fenetres) {
            if (f.url === url && 'focus' in f) return f.focus();
        }
        return self.clients.openWindow(url);
    })());
});
