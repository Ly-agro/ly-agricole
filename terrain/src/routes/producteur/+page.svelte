<script lang="ts">
    import { liveQuery } from 'dexie';
    import { db } from '$lib/db';
    import { mettreEnFile } from '$lib/synchro';

    /**
     * Nouvelle fiche producteur sur le terrain. Elle est aussitôt utilisable hors ligne
     * (achat, parcelle) : l'envoi garde l'ordre, la fiche part avant ce qui la cite.
     * Doublons et consentement : revérifiés par le bureau.
     */

    const villages = liveQuery(() => db.villages.filter((v) => v.actif).sortBy('nom'));

    let nom = $state('');
    let prenoms = $state('');
    let telephone = $state('');
    let villageId = $state<number | null>(null);
    let consentement = $state(false);
    let erreur = $state('');
    let succes = $state('');

    async function enregistrer(e: SubmitEvent) {
        e.preventDefault();
        erreur = succes = '';
        const tel = telephone.replace(/\D/g, '');
        if (nom.trim() === '' || prenoms.trim() === '') return (erreur = 'Nom et prénoms obligatoires.');
        if (tel !== '' && tel.length !== 10) return (erreur = 'Le téléphone doit avoir 10 chiffres (ex. 07 01 02 03 04).');
        if (villageId === null) return (erreur = 'Choisir le village.');
        if (!consentement) return (erreur = 'Sans l\'accord du producteur, sa fiche ne peut pas être créée.');

        const op = await mettreEnFile(db, 'producteur', {
            nom: nom.trim(),
            prenoms: prenoms.trim(),
            telephone: tel || null,
            village_id: villageId,
            consentement: true,
        }, `Nouveau producteur ${nom.trim()} ${prenoms.trim()}`);
        // Utilisable tout de suite sur le téléphone ; le code (LYP-…) viendra du bureau.
        await db.producteurs.put({ id: op.uuid, code: null, nom: nom.trim(), prenoms: prenoms.trim(), telephone: tel || null, village_id: villageId, groupe_id: null, actif: true });

        succes = `Fiche de ${nom.trim()} ${prenoms.trim()} enregistrée. Il peut déjà vendre ; sa carte sera imprimée au bureau.`;
        nom = prenoms = telephone = '';
        consentement = false;
    }

    const champ = 'mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2';
</script>

<form onsubmit={enregistrer} class="space-y-4">
    <h1 class="text-lg font-semibold">Nouveau producteur</h1>
    {#if succes}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{succes}</p>{/if}

    <fieldset class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
        <label class="block"><span class="text-sm text-stone-600">Nom</span><input bind:value={nom} required class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Prénoms</span><input bind:value={prenoms} required class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Téléphone (facultatif)</span>
            <input bind:value={telephone} inputmode="tel" class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Village</span>
            <select bind:value={villageId} class={champ}>
                <option value={null}>—</option>
                {#each $villages ?? [] as v (v.id)}<option value={v.id}>{v.nom}</option>{/each}
            </select>
        </label>
    </fieldset>

    <label class="flex gap-3 rounded-lg bg-amber-50 p-4 text-sm">
        <input type="checkbox" bind:checked={consentement} class="mt-1" />
        <span>Le producteur a été informé que LY AGRICOLE enregistre ses données pour ses achats et ses prêts, et il est d'accord.</span>
    </label>

    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}
    <button type="submit" class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white">Enregistrer la fiche</button>
</form>
