<script lang="ts">
    import { goto } from '$app/navigation';
    import { liveQuery } from 'dexie';
    import { onMount } from 'svelte';
    import { db, reglage } from '$lib/db';
    import { choisirImprimante, ImpressionImpossible, imprimanteChoisie, imprimer, type ImprimanteChoisie } from '$lib/imprimante';
    import { fcfa } from '$lib/mesure';
    import { activerNotifications } from '$lib/push';
    import { deconnecter, SessionExpiree, telechargerReferentiels, type Utilisateur } from '$lib/synchro';
    import { Ticket } from '$lib/ticket';

    const campagnes = liveQuery(() => db.campagnes.filter((c) => c.actif).toArray());
    const imprimante = liveQuery(() => imprimanteChoisie(db));
    let messageImprimante = $state('');

    // Notifications du bureau sur le téléphone (appli Android) : demandées une fois.
    onMount(() => {
        activerNotifications(db).catch(() => undefined);
    });

    async function choisir() {
        messageImprimante = '';
        try {
            const i: ImprimanteChoisie = await choisirImprimante(db);
            messageImprimante = `Imprimante « ${i.nom} » choisie.`;
        } catch (e) {
            messageImprimante = (e as Error).message;
        }
    }

    async function essai() {
        messageImprimante = 'Impression…';
        try {
            await imprimer(db, new Ticket().titre('LY AGRICOLE').centre('Essai d\'impression 58 mm').trait()
                .paire('Poids net', '1 234,5 kg').paire('Montant', '524 663 FCFA', true).texte('Accents : é è à ç ê ô').vide());
            messageImprimante = 'Essai envoyé : vérifier le ticket (32 caractères par ligne, accents lisibles).';
        } catch (e) {
            messageImprimante = e instanceof ImpressionImpossible ? e.message : 'Impression impossible : ' + (e as Error).message;
        }
    }
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

    <div class="rounded-lg bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Imprimante 58 mm</h2>
        <p class="mt-1 text-sm text-stone-600">{$imprimante ? `« ${$imprimante.nom} »` : 'Aucune : choisir l\'imprimante Bluetooth (Xprinter, mini POS 58 mm) allumée à côté.'}</p>
        <div class="mt-3 grid grid-cols-2 gap-2">
            <button type="button" onclick={choisir} class="rounded-md border border-stone-400 py-2 text-sm font-medium">{$imprimante ? 'Changer' : 'Choisir'}</button>
            <button type="button" onclick={essai} disabled={!$imprimante} class="rounded-md border border-stone-400 py-2 text-sm font-medium disabled:opacity-40">Imprimer un essai</button>
        </div>
        {#if messageImprimante}<p class="mt-2 text-sm text-stone-700">{messageImprimante}</p>{/if}
    </div>

    <button type="button" onclick={seDeconnecter} class="w-full py-2 text-sm text-stone-500 underline">Se déconnecter</button>
</section>
