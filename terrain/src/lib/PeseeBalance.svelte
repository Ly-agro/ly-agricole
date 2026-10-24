<script lang="ts">
    import { onDestroy } from 'svelte';
    import { balanceChoisie, BalanceIndisponible, choisirBalance, ecouterBalance, grammesEnKgTexte, type BalanceChoisie } from './balance';
    import { db } from './db';

    /**
     * Lecture du poids sur une balance Bluetooth. L'agent voit le poids en direct, et ne peut le
     * reprendre que lorsqu'il est STABLE. Rien n'est enregistré ici : le poids va dans le champ
     * demandé, et le formulaire retient qu'il vient de la balance (« source du poids »).
     * NON VÉRIFIÉ sur une vraie balance (question ouverte n° 38).
     */
    let { onbrut, ontare }: { onbrut: (grammes: number) => void; ontare: (grammes: number) => void } = $props();

    let choisie = $state<BalanceChoisie | undefined>(undefined);
    let ecoute = $state(false);
    let occupe = $state(false);
    let poids = $state<{ grammes: number; stable: boolean } | null>(null);
    let erreur = $state('');
    let arreter: (() => Promise<void>) | null = null;

    balanceChoisie(db).then((b) => (choisie = b));

    async function choisir() {
        erreur = '';
        occupe = true;
        try {
            choisie = await choisirBalance(db);
        } catch (e) {
            erreur = e instanceof BalanceIndisponible ? e.message : 'Choix de la balance impossible.';
        } finally {
            occupe = false;
        }
    }

    async function demarrer() {
        erreur = '';
        occupe = true;
        try {
            arreter = await ecouterBalance(db, (p) => (poids = p));
            ecoute = true;
        } catch (e) {
            erreur = e instanceof BalanceIndisponible ? e.message : 'Lecture de la balance impossible.';
        } finally {
            occupe = false;
        }
    }

    async function couper() {
        await arreter?.();
        arreter = null;
        ecoute = false;
        poids = null;
    }

    onDestroy(() => {
        void arreter?.();
    });
</script>

<fieldset class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
    <legend class="px-1 text-sm text-stone-600">Balance Bluetooth</legend>

    {#if !choisie}
        <button type="button" onclick={choisir} disabled={occupe} class="w-full rounded-md border border-stone-400 py-2 text-sm disabled:opacity-50">
            {occupe ? 'Recherche…' : 'Choisir la balance'}
        </button>
    {:else}
        <p class="text-sm text-stone-600">Balance : <strong class="font-medium">{choisie.nom}</strong>
            <button type="button" onclick={choisir} class="ml-2 text-xs underline">changer</button></p>

        {#if !ecoute}
            <button type="button" onclick={demarrer} disabled={occupe} class="w-full rounded-md bg-stone-800 py-2 text-sm font-medium text-white disabled:opacity-50">
                {occupe ? 'Connexion…' : 'Lire la balance'}
            </button>
        {:else}
            <div class="rounded-md bg-stone-50 p-3 text-center">
                <p class="text-3xl font-semibold tabular-nums">{poids ? grammesEnKgTexte(poids.grammes) : '—'} <span class="text-base font-normal">kg</span></p>
                <p class="mt-1 text-sm {poids?.stable ? 'text-emerald-700' : 'text-amber-700'}">{poids ? (poids.stable ? 'Poids stable ✓' : 'En attente de stabilité…') : 'Poser la charge sur la balance'}</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" disabled={!poids?.stable} onclick={() => poids && onbrut(poids.grammes)}
                    class="rounded-md bg-emerald-700 py-3 text-sm font-medium text-white disabled:opacity-40">→ Poids brut</button>
                <button type="button" disabled={!poids?.stable} onclick={() => poids && ontare(poids.grammes)}
                    class="rounded-md border border-emerald-700 py-3 text-sm font-medium text-emerald-800 disabled:opacity-40">→ Tare</button>
            </div>
            <button type="button" onclick={couper} class="w-full text-xs text-stone-500 underline">Arrêter la lecture</button>
        {/if}
    {/if}

    {#if erreur}<p class="rounded-md bg-red-50 p-2 text-sm text-red-800">{erreur}</p>{/if}
    <p class="text-xs text-stone-500">Un poids tapé à la main reste possible ; il est enregistré « à la main ».</p>
</fieldset>
