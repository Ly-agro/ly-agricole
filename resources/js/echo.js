import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Avis en direct (Reverb) : seulement pour un utilisateur connecté (balise
 * <meta name="ly-utilisateur">), jamais sur la vitrine publique. Un avis arrive dans
 * la seconde : petit bandeau en bas à droite, compteur de la cloche mis à jour.
 * Sans serveur Reverb, rien ne casse : la liste des avis et le push restent.
 */
const meta = document.querySelector('meta[name="ly-utilisateur"]');
const cle = import.meta.env.VITE_REVERB_APP_KEY;

if (meta && cle) {
    window.Pusher = Pusher;
    const schema = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: cle,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: schema === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo.private(`App.Models.User.${meta.content}`).notification((avis) => {
        mettreAJourCloche();
        montrerBandeau(avis);
    });
}

function mettreAJourCloche() {
    const cloche = document.querySelector('[data-cloche]');
    if (!cloche) return;
    let pastille = cloche.querySelector('[data-cloche-compte]');
    if (!pastille) {
        pastille = document.createElement('span');
        pastille.dataset.clocheCompte = '';
        pastille.className = 'absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-[11px] font-semibold leading-5 text-white tabular-nums';
        pastille.textContent = '0';
        cloche.appendChild(pastille);
    }
    const n = (parseInt(pastille.textContent, 10) || 0) + 1;
    pastille.textContent = n > 99 ? '99+' : String(n);
    cloche.setAttribute('aria-label', `Notifications : ${n} non lue(s)`);
}

function montrerBandeau(avis) {
    let zone = document.querySelector('[data-bandeaux]');
    if (!zone) {
        zone = document.createElement('div');
        zone.dataset.bandeaux = '';
        zone.setAttribute('role', 'status');
        zone.setAttribute('aria-live', 'polite');
        zone.className = 'fixed bottom-4 right-4 z-50 flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2';
        document.body.appendChild(zone);
    }
    const carte = document.createElement(avis.url ? 'a' : 'div');
    if (avis.url) carte.href = avis.url;
    carte.className = 'block rounded-xl border border-stone-200 bg-white p-3 text-sm shadow-lg transition hover:border-emerald-600';
    const titre = document.createElement('strong');
    titre.className = 'block text-stone-900';
    titre.textContent = avis.titre ?? 'Nouvel avis';
    const texte = document.createElement('span');
    texte.className = 'mt-0.5 block text-stone-600';
    texte.textContent = avis.texte ?? '';
    carte.append(titre, texte);
    zone.appendChild(carte);
    setTimeout(() => carte.remove(), 8000);
}
