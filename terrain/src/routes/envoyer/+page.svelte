<script lang="ts">
    import { goto } from '$app/navigation';
    import { liveQuery } from 'dexie';
    import { db, type StatutOperation } from '$lib/db';
    import { envoyer, SessionExpiree, telechargerReferentiels } from '$lib/synchro';

    /** Écran « À envoyer » : l'agent sait toujours ce qui n'est pas encore au bureau. */
    const operations = liveQuery(() => db.operations.reverse().limit(200).toArray());

    let occupe = $state(false);
    let message = $state('');
    let erreur = $state('');

    async function envoyerMaintenant() {
        occupe = true;
        message = erreur = '';
        try {
            const b = await envoyer(db);
            message = `Envoyé : ${b.envoyees} nouveau(x), ${b.dejaRecues} déjà reçu(s) par le bureau, ${b.rejetees} rejeté(s).`;
            // Restants dus et producteurs ont changé au bureau.
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

    const libelles: Record<StatutOperation, [string, string]> = {
        en_attente: ['En attente', 'bg-amber-100 text-amber-900'],
        envoye: ['Au bureau', 'bg-emerald-100 text-emerald-900'],
        rejete: ['Rejeté', 'bg-red-100 text-red-900'],
    };
</script>

<section class="space-y-4">
    <button type="button" onclick={envoyerMaintenant} disabled={occupe}
        class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white disabled:opacity-50">
        {occupe ? 'Envoi…' : 'Envoyer maintenant'}
    </button>
    {#if message}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message}</p>{/if}
    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}

    <ul class="space-y-2">
        {#each $operations ?? [] as o (o.uuid)}
            <li class="rounded-lg bg-white p-3 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm">{o.resume}</p>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {libelles[o.statut][1]}">{libelles[o.statut][0]}</span>
                </div>
                <p class="mt-1 text-xs text-stone-500">Saisi le {new Date(o.cree_at).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })}</p>
                {#if o.motif}<p class="mt-1 text-sm text-red-800">{o.motif}</p>{/if}
            </li>
        {:else}
            <li class="text-center text-sm text-stone-500">Aucune saisie.</li>
        {/each}
    </ul>
</section>
