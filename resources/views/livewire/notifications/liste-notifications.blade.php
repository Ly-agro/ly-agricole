@php
    // Une couleur et une icône par sorte d'avis : on voit d'un coup d'œil ce qui attend.
    $sortes = [
        'a_valider' => ['libelle' => 'À valider', 'pastille' => 'bg-amber-100 text-amber-900', 'rond' => 'bg-amber-100 text-amber-800', 'fond' => 'bg-amber-50/70',
            'trace' => 'M12 7v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'valide' => ['libelle' => 'Validé', 'pastille' => 'bg-emerald-100 text-emerald-900', 'rond' => 'bg-emerald-100 text-emerald-800', 'fond' => 'bg-emerald-50/70',
            'trace' => 'M5 12.5l4.5 4.5L19 7.5'],
        'refuse' => ['libelle' => 'Refusé', 'pastille' => 'bg-red-100 text-red-900', 'rond' => 'bg-red-100 text-red-800', 'fond' => 'bg-red-50/70',
            'trace' => 'M7 7l10 10M17 7 7 17'],
        'alerte' => ['libelle' => 'Alerte', 'pastille' => 'bg-sky-100 text-sky-900', 'rond' => 'bg-sky-100 text-sky-800', 'fond' => 'bg-sky-50/70',
            'trace' => 'M12 8v5M12 16.5v.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z'],
        'activite' => ['libelle' => 'Activité agent', 'pastille' => 'bg-stone-200 text-stone-800', 'rond' => 'bg-stone-200 text-stone-700', 'fond' => 'bg-stone-50',
            'trace' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 20a8 8 0 0 1 16 0'],
    ];
    // Avis d'avant la distinction validé / refusé : présentés comme « validé ».
    $sortes['traite'] = $sortes['valide'];
@endphp

<div class="mx-auto max-w-3xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Notifications</h1>
            <p class="mt-1 text-sm text-stone-600">
                @if ($nonLues === 0)
                    Tout est lu.
                @else
                    <span class="font-semibold text-stone-900 tabular-nums">{{ $nonLues }}</span> non lue{{ $nonLues > 1 ? 's' : '' }}
                    @if ($aValider > 0)
                        · <span class="font-semibold text-amber-800 tabular-nums">{{ $aValider }}</span> à valider
                    @endif
                @endif
            </p>
        </div>
        @if ($nonLues > 0)
            <button type="button" wire:click="toutLu" class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm text-stone-700 shadow-sm hover:bg-stone-50">Tout marquer comme lu</button>
        @endif
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif

    {{-- Activation du push sur CE navigateur : l'état dépend du navigateur, lu en JavaScript. --}}
    <section wire:ignore data-push data-cle="{{ $clePublique }}"
        class="mb-6 flex flex-wrap items-center gap-4 rounded-xl border border-emerald-900/10 bg-gradient-to-r from-emerald-900 to-emerald-800 p-4 text-emerald-50 shadow-sm sm:p-5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/10" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16ZM10 20a2 2 0 0 0 4 0" /></svg>
        </span>
        <div class="min-w-0 flex-1">
            <p class="font-medium text-white">Prévenu même page fermée</p>
            <p class="mt-0.5 text-sm text-emerald-100/90" data-push-etat>
                @if ($clePublique === null)
                    Pas encore configuré sur le serveur (clés VAPID). Les avis restent visibles ici.
                @else
                    Vérification…
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            <button type="button" data-push-activer hidden class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-emerald-900 shadow hover:bg-emerald-50">Activer sur cet appareil</button>
            <button type="button" data-push-retirer hidden class="rounded-lg border border-white/30 px-4 py-2 text-sm text-white hover:bg-white/10">Désactiver</button>
        </div>
    </section>
    @error('push') <p class="-mt-4 mb-4 text-sm text-red-700">{{ $message }}</p> @enderror

    <nav class="mb-4 flex gap-1 rounded-lg bg-stone-200/60 p-1 text-sm" aria-label="Filtrer les notifications">
        @foreach ($filtres as $cle => $libelle)
            <button type="button" wire:click="$set('filtre', '{{ $cle }}')" @if ($filtre === $cle) aria-current="true" @endif
                @class([
                    'flex-1 rounded-md px-3 py-1.5 transition-colors',
                    'bg-white font-medium text-stone-900 shadow-sm' => $filtre === $cle,
                    'text-stone-600 hover:text-stone-900' => $filtre !== $cle,
                ])>
                {{ $libelle }}
                @if ($cle === 'non_lues' && $nonLues > 0)<span class="ml-1 tabular-nums text-stone-500">{{ $nonLues }}</span>@endif
                @if ($cle === 'a_valider' && $aValider > 0)<span class="ml-1 tabular-nums text-amber-700">{{ $aValider }}</span>@endif
            </button>
        @endforeach
    </nav>

    @forelse ($parJour as $jour => $avisDuJour)
        <h2 class="mb-2 mt-5 px-1 text-xs font-medium uppercase tracking-wider text-stone-500 first-of-type:mt-0">{{ $jour }}</h2>
        <ul class="overflow-hidden rounded-xl border border-stone-200 bg-white shadow-sm">
            @foreach ($avisDuJour as $a)
                @php $s = $sortes[$a->data['categorie'] ?? 'alerte'] ?? $sortes['alerte']; $nonLu = $a->read_at === null; @endphp
                <li wire:key="avis-{{ $a->id }}" class="border-t border-stone-100 first:border-t-0">
                    <button type="button" wire:click="ouvrir('{{ $a->id }}')"
                        @class(['group flex w-full items-start gap-3 px-4 py-3.5 text-left transition-colors hover:bg-stone-50', $s['fond'] => $nonLu])>
                        <span @class(['mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full', $s['rond']]) aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4.5 w-4.5"><path d="{{ $s['trace'] }}" /></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span @class(['text-sm', 'font-semibold text-stone-900' => $nonLu, 'text-stone-700' => ! $nonLu])>{{ $a->data['titre'] ?? '' }}</span>
                                <span @class(['rounded-full px-2 py-0.5 text-[11px] font-medium', $s['pastille']])>{{ $s['libelle'] }}</span>
                            </span>
                            <span class="mt-0.5 block text-sm text-stone-600">{{ $a->data['texte'] ?? '' }}</span>
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1.5">
                            <span class="text-xs text-stone-500 tabular-nums">{{ $a->created_at->format('H:i') }}</span>
                            @if ($nonLu)
                                <span class="h-2 w-2 rounded-full bg-emerald-600" title="Non lue"></span>
                            @endif
                        </span>
                    </button>
                </li>
            @endforeach
        </ul>
    @empty
        <div class="rounded-xl border border-dashed border-stone-300 bg-white/60 px-6 py-10 text-center">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="mx-auto h-10 w-10 text-stone-400" aria-hidden="true"><path d="M6 16V11a6 6 0 1 1 12 0v5l1.5 2h-15L6 16ZM10 20a2 2 0 0 0 4 0" /></svg>
            <p class="mt-3 font-medium text-stone-800">
                {{ $filtre === 'toutes' ? 'Aucune notification' : ($filtre === 'non_lues' ? 'Rien de non lu' : 'Rien à valider') }}
            </p>
            <p class="mt-1 text-sm text-stone-500">Vous serez prévenu ici quand une saisie attend votre validation, ou quand la vôtre est validée ou refusée.</p>
        </div>
    @endforelse
</div>

@script
<script>
    const bloc = $wire.$el.querySelector('[data-push]');
    const etat = bloc.querySelector('[data-push-etat]');
    const activer = bloc.querySelector('[data-push-activer]');
    const retirer = bloc.querySelector('[data-push-retirer]');
    const cle = bloc.dataset.cle;

    // Clé VAPID publique (base64url) → octets attendus par PushManager.subscribe().
    const octets = (b64) => {
        const brut = atob((b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(brut, (c) => c.charCodeAt(0));
    };
    const appareil = () => (navigator.userAgentData?.platform || navigator.platform || '') + ' — ' + (navigator.userAgent.match(/(Firefox|Edg|Chrome|Safari)\/[\d.]+/)?.[0] ?? 'navigateur');

    async function afficher() {
        if (!cle) return;
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            etat.textContent = 'Ce navigateur ne sait pas recevoir de notifications.';
            return;
        }
        if (Notification.permission === 'denied') {
            etat.textContent = 'Bloquées pour ce site dans le navigateur : les autoriser dans ses réglages.';
            activer.hidden = retirer.hidden = true;
            return;
        }
        const enregistrement = await navigator.serviceWorker.register('/sw-ly.js');
        const abonnement = await enregistrement.pushManager.getSubscription();
        etat.textContent = abonnement
            ? 'Activé sur cet appareil : une alerte s\'affiche dès qu\'une saisie vous attend.'
            : 'Désactivé sur cet appareil : les avis n\'apparaissent qu\'ici.';
        activer.hidden = !!abonnement;
        retirer.hidden = !abonnement;
    }

    activer.addEventListener('click', async () => {
        etat.textContent = 'Activation…';
        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                etat.textContent = 'Autorisation refusée : rien ne sera envoyé sur cet appareil.';
                return;
            }
            const enregistrement = await navigator.serviceWorker.register('/sw-ly.js');
            const abonnement = await enregistrement.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: octets(cle) });
            await $wire.enregistrerAbonnement(abonnement.toJSON(), appareil());
        } catch (e) {
            etat.textContent = 'Activation impossible : ' + (e?.message ?? e);
            return;
        }
        afficher();
    });

    retirer.addEventListener('click', async () => {
        const enregistrement = await navigator.serviceWorker.getRegistration('/sw-ly.js');
        const abonnement = await enregistrement?.pushManager.getSubscription();
        if (abonnement) {
            await $wire.retirerAbonnement(abonnement.endpoint);
            await abonnement.unsubscribe();
        }
        afficher();
    });

    afficher();
</script>
@endscript
