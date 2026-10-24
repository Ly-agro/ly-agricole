<script lang="ts">
    import { goto } from '$app/navigation';
    import { onMount } from 'svelte';
    import { db, reglage } from '$lib/db';
    import { connecter, telechargerReferentiels } from '$lib/synchro';

    let serveur = $state('');
    let email = $state('');
    let motDePasse = $state('');
    let occupe = $state(false);
    let erreur = $state('');

    onMount(async () => {
        serveur = (await reglage<string>(db, 'serveur')) ?? 'http://localhost:8000';
    });

    async function valider(e: SubmitEvent) {
        e.preventDefault();
        occupe = true;
        erreur = '';
        try {
            await connecter(db, serveur, email, motDePasse);
            motDePasse = '';
            // Premier téléchargement tout de suite, tant qu'il y a du réseau.
            await telechargerReferentiels(db).catch(() => undefined);
            goto('/', { replaceState: true });
        } catch (err) {
            erreur = (err as Error).message;
        } finally {
            occupe = false;
        }
    }
</script>

<form onsubmit={valider} class="space-y-4 rounded-lg bg-white p-4 shadow-sm">
    <h1 class="text-lg font-semibold">Connexion</h1>
    <label class="block">
        <span class="text-sm text-stone-600">Adresse du serveur</span>
        <input bind:value={serveur} type="url" required autocomplete="url" class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2" />
    </label>
    <label class="block">
        <span class="text-sm text-stone-600">Adresse e-mail</span>
        <input bind:value={email} type="email" required autocomplete="username" class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2" />
    </label>
    <label class="block">
        <span class="text-sm text-stone-600">Mot de passe</span>
        <input bind:value={motDePasse} type="password" required autocomplete="current-password" class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2" />
    </label>
    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}
    <button type="submit" disabled={occupe} class="w-full rounded-md bg-emerald-700 py-3 font-medium text-white disabled:opacity-50">
        {occupe ? 'Connexion…' : 'Se connecter'}
    </button>
    <p class="text-xs text-stone-500">La connexion demande du réseau. Ensuite, les achats se saisissent sans réseau.</p>
</form>
