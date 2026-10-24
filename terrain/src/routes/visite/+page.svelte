<script lang="ts">
    import ChoixProducteur from '$lib/ChoixProducteur.svelte';
    import { db, type Parcelle, type Producteur } from '$lib/db';
    import { hectares } from '$lib/geo';
    import { positionActuelle } from '$lib/photos';
    import PrisePhoto from '$lib/PrisePhoto.svelte';
    import { MAX_PHOTOS, PRATIQUES } from '$lib/pratiques';
    import { mettreEnFile } from '$lib/synchro';

    /**
     * Visite de parcelle : fiche courte (pratiques constatées, observations) et photos
     * géolocalisées. Un constat, sans argent : le bureau et l'agronome la lisent.
     */

    let producteur = $state<Producteur | null>(null);
    let parcelles = $state<Parcelle[]>([]);
    let parcelleId = $state<string | null>(null);
    let pratiques = $state<string[]>([]);
    let observations = $state('');
    let photos = $state<(string | null)[]>([null]);
    let erreur = $state('');
    let succes = $state('');
    let occupe = $state(false);

    // Parcelles du producteur choisi (relues quand il change).
    $effect(() => {
        const id = producteur?.id;
        parcelleId = null;
        if (!id) {
            parcelles = [];
            return;
        }
        db.parcelles.where('producteur_id').equals(id).filter((p) => p.actif).sortBy('nom').then((r) => {
            if (producteur?.id === id) {
                parcelles = r;
                if (r.length === 1) parcelleId = r[0].id;
            }
        });
    });

    function basculer(code: string) {
        pratiques = pratiques.includes(code) ? pratiques.filter((c) => c !== code) : [...pratiques, code];
    }

    async function enregistrer(e: SubmitEvent) {
        e.preventDefault();
        erreur = succes = '';
        const parcelle = parcelles.find((p) => p.id === parcelleId);
        const prises = photos.filter((p): p is string => p !== null);

        if (!producteur) return (erreur = 'Choisir le producteur.');
        if (!parcelle) return (erreur = parcelles.length === 0 ? 'Ce producteur n\'a aucune parcelle sur le téléphone : la relever d\'abord, ou télécharger les référentiels.' : 'Choisir la parcelle.');
        if (pratiques.length === 0 && observations.trim() === '' && prises.length === 0) {
            return (erreur = 'Fiche vide : cocher une pratique, écrire une observation ou prendre une photo.');
        }

        occupe = true;
        try {
            const position = await positionActuelle();
            // $state.snapshot : IndexedDB ne sait pas copier les tableaux réactifs de
            // Svelte (Proxy) — l'enregistrement échouerait (DataCloneError).
            await mettreEnFile(db, 'visite', {
                parcelle_id: parcelle.id,
                date_visite: new Date().toISOString().slice(0, 10),
                lat: position?.lat ?? null,
                lng: position?.lng ?? null,
                pratiques: $state.snapshot(pratiques),
                observations: observations.trim() || null,
                photos: $state.snapshot(prises),
            }, `Visite de « ${parcelle.nom} » (${producteur.nom} ${producteur.prenoms}) — ${pratiques.length} pratique(s), ${prises.length} photo(s)`);
        } catch (e) {
            return (erreur = 'La visite n\'a pas pu être gardée sur le téléphone : ' + (e instanceof Error ? e.message : String(e)));
        } finally {
            occupe = false;
        }

        succes = `Visite de « ${parcelle.nom} » enregistrée sur le téléphone. Elle partira au prochain envoi, avec ses photos.`;
        pratiques = [];
        observations = '';
        photos = [null];
    }

    const champ = 'mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2';
</script>

<form onsubmit={enregistrer} class="space-y-4">
    <h1 class="text-lg font-semibold">Visite de parcelle</h1>
    {#if succes}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{succes}</p>{/if}

    <ChoixProducteur bind:producteur />

    {#if producteur}
        <fieldset class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
            {#if parcelles.length === 0}
                <p class="text-sm text-amber-800">Aucune parcelle de ce producteur sur le téléphone. <a href="/parcelle" class="underline">Relever une parcelle</a>.</p>
            {:else}
                <label class="block"><span class="text-sm text-stone-600">Parcelle</span>
                    <select bind:value={parcelleId} class={champ}>
                        <option value={null}>—</option>
                        {#each parcelles as p (p.id)}
                            <option value={p.id}>{p.nom}{p.surface_m2 !== null ? ` — ${hectares(p.surface_m2)}` : ''}</option>
                        {/each}
                    </select>
                </label>
            {/if}
        </fieldset>
    {/if}

    <fieldset class="rounded-lg bg-white p-4 shadow-sm">
        <legend class="px-1 text-sm text-stone-600">Pratiques constatées</legend>
        <div class="mt-1 grid grid-cols-2 gap-2">
            {#each PRATIQUES as p (p.code)}
                <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm {pratiques.includes(p.code) ? 'border-emerald-600 bg-emerald-50' : 'border-stone-300'}">
                    <input type="checkbox" checked={pratiques.includes(p.code)} onchange={() => basculer(p.code)} class="h-4 w-4" />
                    {p.libelle}
                </label>
            {/each}
        </div>
    </fieldset>

    <label class="block rounded-lg bg-white p-4 shadow-sm"><span class="text-sm text-stone-600">Observations (état des arbres, maladies, conseils donnés…)</span>
        <textarea bind:value={observations} rows="4" maxlength="2000" class={champ}></textarea></label>

    <div class="space-y-2">
        {#each photos as _, i (i)}
            <PrisePhoto libelle={i === 0 ? 'Prendre une photo (feuilles, fruits, parcelle)' : 'Autre photo'} bind:uuid={photos[i]} />
        {/each}
        {#if photos.length < MAX_PHOTOS && photos[photos.length - 1] !== null}
            <button type="button" onclick={() => (photos = [...photos, null])} class="w-full py-1 text-sm text-emerald-800 underline">Ajouter une photo</button>
        {/if}
    </div>

    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}
    <button type="submit" disabled={occupe} class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white disabled:opacity-60">
        {occupe ? 'Position GPS…' : 'Enregistrer la visite'}
    </button>
</form>
