<script lang="ts">
    import { liveQuery } from 'dexie';
    import { db } from '$lib/db';
    import { fcfa, fcfaDepuisSaisie } from '$lib/mesure';
    import PrisePhoto from '$lib/PrisePhoto.svelte';
    import { mettreEnFile } from '$lib/synchro';

    /**
     * Dépense terrain (carburant, manutention…), payée depuis la caisse de l'agent.
     * Justificatif photo OBLIGATOIRE ; seuil et validation : au bureau.
     */

    const categories = liveQuery(() => db.categories_depense.filter((c) => c.actif).sortBy('nom'));
    const comptes = liveQuery(() => db.comptes.toArray());

    let categorieId = $state<number | null>(null);
    let compteId = $state<number | null>(null);
    let montant = $state('');
    let beneficiaire = $state('');
    let description = $state('');
    let photo = $state<string | null>(null);
    let erreur = $state('');
    let succes = $state('');

    $effect(() => {
        if (compteId === null && $comptes?.length === 1) compteId = $comptes[0].id;
    });

    async function enregistrer(e: SubmitEvent) {
        e.preventDefault();
        erreur = succes = '';
        const m = fcfaDepuisSaisie(montant);
        const categorie = $categories?.find((c) => c.id === categorieId);

        if (!categorie) return (erreur = 'Choisir la catégorie.');
        if (compteId === null) return (erreur = 'Aucune caisse pour payer : télécharger les référentiels.');
        if (m === null || m <= 0) return (erreur = 'Montant en FCFA entiers (ex. 15 000).');
        if (beneficiaire.trim() === '') return (erreur = 'Indiquer à qui l\'argent a été payé.');
        if (!photo) return (erreur = 'Photo du reçu obligatoire.');

        await mettreEnFile(db, 'depense', {
            categorie_id: categorie.id,
            compte_id: compteId,
            montant_fcfa: m,
            date_depense: new Date().toISOString().slice(0, 10),
            beneficiaire: beneficiaire.trim(),
            description: description.trim() || null,
            justificatif_photo: photo,
        }, `Dépense ${categorie.nom} — ${fcfa(m)} à ${beneficiaire.trim()}`);

        succes = `Dépense de ${fcfa(m)} enregistrée sur le téléphone. Elle partira au prochain envoi, avec sa photo.`;
        montant = beneficiaire = description = '';
        photo = null;
    }

    const champ = 'mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2';
</script>

<form onsubmit={enregistrer} class="space-y-4">
    <h1 class="text-lg font-semibold">Dépense terrain</h1>
    {#if succes}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{succes}</p>{/if}

    <fieldset class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
        <label class="block"><span class="text-sm text-stone-600">Catégorie</span>
            <select bind:value={categorieId} class={champ}>
                <option value={null}>—</option>
                {#each $categories ?? [] as c (c.id)}<option value={c.id}>{c.nom}</option>{/each}
            </select>
        </label>
        {#if ($comptes?.length ?? 0) > 1}
            <label class="block"><span class="text-sm text-stone-600">Payé depuis</span>
                <select bind:value={compteId} class={champ}>
                    {#each $comptes ?? [] as c (c.id)}<option value={c.id}>{c.nom}</option>{/each}
                </select>
            </label>
        {/if}
        <label class="block"><span class="text-sm text-stone-600">Montant (FCFA)</span>
            <input bind:value={montant} inputmode="numeric" required class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Payé à</span>
            <input bind:value={beneficiaire} required class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Détail (facultatif)</span>
            <input bind:value={description} class={champ} /></label>
    </fieldset>

    <PrisePhoto libelle="Photo du reçu (obligatoire)" bind:uuid={photo} />

    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}
    <button type="submit" class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white">Enregistrer la dépense</button>
</form>
