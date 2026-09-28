<script lang="ts">
    import { goto } from '$app/navigation';
    import { liveQuery } from 'dexie';
    import { db, type Operation, type StatutOperation } from '$lib/db';
    import { abandonner, envoyer, renvoyer, SessionExpiree, telechargerReferentiels } from '$lib/synchro';

    /**
     * Écran « À envoyer » : l'agent sait toujours ce qui n'est pas encore au bureau, et
     * décide quoi faire d'un rejet : renvoyer (après correction au bureau, ou doublon
     * confirmé), ou abandonner (gardé pour la trace, plus jamais envoyé).
     */
    const operations = liveQuery(() => db.operations.reverse().limit(200).toArray());
    const photosRejetees = liveQuery(() => db.photos.where('statut').equals('rejete').toArray());

    let occupe = $state(false);
    let message = $state('');
    let erreur = $state('');

    async function envoyerMaintenant() {
        occupe = true;
        message = erreur = '';
        try {
            const b = await envoyer(db);
            message = `Envoyé : ${b.envoyees} nouveau(x), ${b.dejaRecues} déjà reçu(s) par le bureau, ${b.rejetees} rejeté(s), ${b.photos} photo(s).`;
            // Restants dus, codes des nouveaux producteurs : ils ont changé au bureau.
            await telechargerReferentiels(db).catch(() => undefined);
        } catch (e) {
            erreur = (e as Error).message;
            if (e instanceof SessionExpiree) {
                goto('/connexion');
            }
        } finally {
            occupe = false;
        }
    }

    const doublonAConfirmer = (o: Operation) => o.type === 'producteur' && (o.motif ?? '').startsWith('À confirmer');

    function confirmerAbandon(o: Operation) {
        if (window.confirm(`Abandonner « ${o.resume} » ? Elle ne sera jamais envoyée.`)) {
            abandonner(db, o.uuid);
        }
    }

    const libelles: Record<StatutOperation, [string, string]> = {
        en_attente: ['En attente', 'bg-amber-100 text-amber-900'],
        envoye: ['Au bureau', 'bg-emerald-100 text-emerald-900'],
        rejete: ['Rejeté', 'bg-red-100 text-red-900'],
        abandonne: ['Abandonné', 'bg-stone-200 text-stone-600'],
    };
</script>

<section class="space-y-4">
    <button type="button" onclick={envoyerMaintenant} disabled={occupe}
        class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white disabled:opacity-50">
        {occupe ? 'Envoi…' : 'Envoyer maintenant'}
    </button>
    {#if message}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message}</p>{/if}
    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}
    {#each $photosRejetees ?? [] as p (p.uuid)}
        <p class="rounded-md bg-red-50 p-3 text-sm text-red-800">Photo refusée par le bureau : {p.motif}</p>
    {/each}

    <ul class="space-y-2">
        {#each $operations ?? [] as o (o.uuid)}
            <li class="rounded-lg bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm">{o.resume}</p>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {libelles[o.statut][1]}">{libelles[o.statut][0]}</span>
                </div>
                <p class="mt-1 text-xs text-stone-500">Saisi le {new Date(o.cree_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</p>
                {#if o.motif}<p class="mt-1 text-sm text-red-800">{o.motif}</p>{/if}
                {#if o.statut === 'rejete'}
                    <div class="mt-2 flex flex-wrap gap-2">
                        {#if doublonAConfirmer(o)}
                            <button type="button" onclick={() => renvoyer(db, o.uuid, { doublons_confirmes: true })}
                                class="rounded-md bg-amber-500 px-3 py-1.5 text-sm font-medium text-amber-950">Confirmer (même famille) et renvoyer</button>
                        {:else}
                            <button type="button" onclick={() => renvoyer(db, o.uuid)}
                                class="rounded-md bg-stone-800 px-3 py-1.5 text-sm font-medium text-white">Renvoyer</button>
                        {/if}
                        <button type="button" onclick={() => confirmerAbandon(o)} class="rounded-md px-3 py-1.5 text-sm text-stone-600 underline">Abandonner</button>
                    </div>
                {/if}
            </li>
        {:else}
            <li class="text-center text-sm text-stone-500">Aucune saisie.</li>
        {/each}
    </ul>
</section>
