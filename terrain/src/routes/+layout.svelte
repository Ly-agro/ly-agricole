<script lang="ts">
    import '../app.css';
    import { goto } from '$app/navigation';
    import { page } from '$app/state';
    import { liveQuery } from 'dexie';
    import { db, reglage } from '$lib/db';

    let { children } = $props();

    const enAttente = liveQuery(() => db.operations.where('statut').equals('en_attente').count());
    const rejetees = liveQuery(() => db.operations.where('statut').equals('rejete').count());

    let pret = $state(false);

    // Sans jeton, seule la connexion est accessible (les saisies en file restent).
    $effect(() => {
        const chemin = page.url.pathname;
        reglage<string>(db, 'jeton').then((jeton) => {
            if (!jeton && chemin !== '/connexion') {
                goto('/connexion', { replaceState: true });
            }
            pret = true;
        });
    });

    const onglets = [
        { href: '/', libelle: 'Accueil' },
        { href: '/achat', libelle: 'Achat' },
        { href: '/envoyer', libelle: 'À envoyer' },
    ];
</script>

<div class="mx-auto flex min-h-dvh max-w-lg flex-col">
    <header class="flex items-center justify-between bg-emerald-800 px-4 py-3 text-white">
        <span class="font-bold tracking-wide">LY Terrain</span>
        {#if ($enAttente ?? 0) > 0}
            <a href="/envoyer" class="rounded-full bg-amber-400 px-3 py-0.5 text-sm font-semibold text-amber-950">{$enAttente} à envoyer</a>
        {/if}
    </header>

    <main class="flex-1 px-4 py-4">
        {#if pret}
            {@render children()}
        {/if}
    </main>

    {#if page.url.pathname !== '/connexion'}
        <nav class="sticky bottom-0 grid grid-cols-3 border-t border-stone-300 bg-white">
            {#each onglets as o (o.href)}
                <a href={o.href}
                    class="py-3 text-center text-sm font-medium {page.url.pathname === o.href ? 'text-emerald-800' : 'text-stone-500'}">
                    {o.libelle}
                    {#if o.href === '/envoyer' && ($rejetees ?? 0) > 0}
                        <span class="ml-1 rounded-full bg-red-600 px-1.5 text-xs text-white">{$rejetees}</span>
                    {/if}
                </a>
            {/each}
        </nav>
    {/if}
</div>
