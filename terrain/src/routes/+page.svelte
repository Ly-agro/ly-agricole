<script lang="ts">
    import { goto } from '$app/navigation';
    import { liveQuery } from 'dexie';
    import { db, reglage } from '$lib/db';
    import { fcfa } from '$lib/mesure';
    import { deconnecter, SessionExpiree, telechargerReferentiels, type Utilisateur } from '$lib/synchro';

    const campagnes = liveQuery(() => db.campagnes.filter((c) => c.actif).toArray());
    const nbProducteurs = liveQuery(() => db.producteurs.filter((p) => p.actif).count());
    const horodatage = liveQuery(() => reglage<string>(db, 'horodatage'));
    const utilisateur = liveQuery(() => reglage<Utilisateur>(db, 'utilisateur'));
    const nbEnAttente = liveQuery(() => db.operations.where('statut').equals('en_attente').count());

    let occupe = $state(false);
    let message = $state('');
    let erreur = $state('');

    async function telecharger() {
        occupe = true;
        message = erreur = '';
        try {
            const r = await telechargerReferentiels(db);
            message = r.complet ? 'Référentiels téléchargés.' : `À jour (${r.producteurs} producteur(s) modifié(s) ou nouveau(x)).`;
        } catch (e) {
            erreur = (e as Error).message;
            if (e instanceof SessionExpiree) {
                goto('/connexion');
            }
        } finally {
            occupe = false;
        }
    }

    async function seDeconnecter() {
        if (($nbEnAttente ?? 0) > 0) {
            erreur = 'Des saisies ne sont pas encore envoyées : envoyez-les avant de vous déconnecter.';
            return;
        }
        await deconnecter(db);
        goto('/connexion');
    }

    function date(iso: string | undefined): string {
        return iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : 'jamais';
    }
</script>

<section class="space-y-4">
    <div class="rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-stone-500">Connecté</p>
        <p class="font-semibold">{$utilisateur?.nom ?? '…'}</p>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Campagne</h2>
        {#each $campagnes ?? [] as c (c.id)}
            <p class="mt-1">{c.code} — prix officiel : {c.prix_officiel_kg_fcfa ? fcfa(c.prix_officiel_kg_fcfa) + ' / kg' : 'non fixé'}</p>
        {:else}
            <p class="mt-1 text-sm text-stone-500">Aucune campagne ouverte sur le téléphone : télécharger les référentiels.</p>
        {/each}
        <p class="mt-3 text-sm text-stone-500">{$nbProducteurs ?? 0} producteurs sur le téléphone · mis à jour : {date($horodatage)}</p>
        <button type="button" onclick={telecharger} disabled={occupe}
            class="mt-3 w-full rounded-md bg-emerald-700 py-3 font-medium text-white disabled:opacity-50">
            {occupe ? 'Téléchargement…' : 'Télécharger les référentiels'}
        </button>
    </div>

    {#if message}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{message}</p>{/if}
    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}

    <a href="/achat" class="block rounded-md bg-stone-900 py-4 text-center text-lg font-semibold text-white">Nouvel achat</a>

    <button type="button" onclick={seDeconnecter} class="w-full py-2 text-sm text-stone-500 underline">Se déconnecter</button>
</section>
