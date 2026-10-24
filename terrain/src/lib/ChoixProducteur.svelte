<script lang="ts">
    import { db, type Producteur } from './db';
    import ScanQr from './ScanQr.svelte';

    /** Producteur cherché par nom, code ou téléphone, ou scanné sur sa carte (QR = code). */
    let { producteur = $bindable<Producteur | null>(null) }: { producteur?: Producteur | null } = $props();

    let recherche = $state('');
    let scan = $state(false);
    let erreur = $state('');

    // Relu à chaque frappe ($effect suit `recherche` ; un liveQuery ne la suivrait pas).
    let resultats = $state<Producteur[]>([]);
    $effect(() => {
        const q = recherche.trim().toLowerCase();
        if (q.length < 2) {
            resultats = [];
            return;
        }
        db.producteurs
            .filter((p) => p.actif && (`${p.nom} ${p.prenoms}`.toLowerCase().includes(q) || (p.code ?? '').toLowerCase().includes(q) || (p.telephone ?? '').includes(q)))
            .limit(20)
            .toArray()
            .then((r) => {
                if (recherche.trim().toLowerCase() === q) resultats = r;
            });
    });

    function choisirParCode(code: string) {
        scan = false;
        erreur = '';
        db.producteurs.where('code').equals(code.toUpperCase()).first().then((p) => {
            if (p && p.actif) {
                producteur = p;
                recherche = '';
            } else {
                erreur = `Aucun producteur actif « ${code} » sur le téléphone. Télécharger les référentiels ?`;
            }
        });
    }
</script>

{#if scan}
    <ScanQr onCode={choisirParCode} onFermer={() => (scan = false)} />
{/if}

<fieldset class="space-y-2 rounded-lg bg-white p-4 shadow-sm">
    <legend class="sr-only">Producteur</legend>
    {#if producteur}
        <div class="flex items-start justify-between">
            <div>
                <p class="font-semibold">{producteur.nom} {producteur.prenoms}</p>
                <p class="text-sm text-stone-500">{producteur.code ?? 'nouveau, code à venir'}</p>
            </div>
            <button type="button" onclick={() => (producteur = null)} class="text-sm text-emerald-800 underline">Changer</button>
        </div>
    {:else}
        <span class="text-sm text-stone-600">Producteur</span>
        <div class="flex gap-2">
            <input bind:value={recherche} placeholder="Nom, code ou téléphone" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2" />
            <button type="button" onclick={() => (scan = true)} class="rounded-md bg-stone-800 px-3 text-sm text-white">Scanner</button>
        </div>
        {#each resultats as p (p.id)}
            <button type="button" onclick={() => { producteur = p; recherche = ''; }}
                class="block w-full rounded-md border border-stone-200 px-3 py-2 text-left">
                {p.nom} {p.prenoms} <span class="text-sm text-stone-500">{p.code ?? ''}</span>
            </button>
        {/each}
        {#if erreur}<p class="text-sm text-red-800">{erreur}</p>{/if}
    {/if}
</fieldset>
