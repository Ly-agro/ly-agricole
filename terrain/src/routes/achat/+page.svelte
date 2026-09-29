<script lang="ts">
    import { liveQuery } from 'dexie';
    import BoutonImprimer from '$lib/BoutonImprimer.svelte';
    import { db, type Operation, type Producteur } from '$lib/db';
    import { depuisSaisie, fcfa, fcfaDepuisSaisie, grammesPourSolder, kg, montantAchat } from '$lib/mesure';
    import ChoixProducteur from '$lib/ChoixProducteur.svelte';
    import PrisePhoto from '$lib/PrisePhoto.svelte';
    import { mettreEnFile } from '$lib/synchro';

    /**
     * Achat bord-champ SANS réseau : tout est lu dans la base du téléphone et l'achat
     * part dans la file d'envoi. Le serveur revalide tout (prix officiel, lot, caisse,
     * seuil) ; l'aperçu ici n'est qu'une aide.
     */

    const lots = liveQuery(async () => {
        const campagnes = await db.campagnes.filter((c) => c.actif).toArray();
        const ids = new Set(campagnes.map((c) => c.id));

        return (await db.lots.filter((l) => l.actif && ids.has(l.campagne_id)).toArray()).map((l) => ({
            ...l,
            campagne: campagnes.find((c) => c.id === l.campagne_id)!,
        }));
    });
    const comptes = liveQuery(() => db.comptes.toArray());
    const points = liveQuery(() => db.points_collecte.filter((p) => p.actif).toArray());

    let producteur = $state<Producteur | null>(null);
    let photoPesee = $state<string | null>(null);
    let lotId = $state<number | null>(null);
    let compteId = $state<number | null>(null);
    let pointId = $state<number | null>(null);
    let brut = $state('');
    let tare = $state('0');
    let humidite = $state('');
    let prix = $state('');
    let pretId = $state('');
    let kilosRetenus = $state('');
    let erreur = $state('');
    let succes = $state('');
    /** Dernier achat enregistré : son bon de pesée peut être imprimé tout de suite. */
    let dernier = $state<Operation | null>(null);

    // $derived ne suit pas un liveQuery recréé : on relit les prêts à chaque changement.
    let prets = $state<{ id: string; reference: string; restant_du_fcfa: number }[]>([]);
    $effect(() => {
        const id = producteur?.id;
        if (!id) {
            prets = [];
            pretId = '';
            return;
        }
        db.prets_en_cours.where('producteur_id').equals(id).toArray().then((p) => {
            prets = p;
            pretId = p[0]?.id ?? '';
            kilosRetenus = '';
        });
    });

    // Valeurs par défaut quand les référentiels arrivent : un seul choix ⇒ choisi.
    $effect(() => {
        if (lotId === null && $lots?.length === 1) lotId = $lots[0].id;
        if (compteId === null && $comptes?.length === 1) compteId = $comptes[0].id;
    });
    const lot = $derived($lots?.find((l) => l.id === lotId) ?? null);
    $effect(() => {
        if (prix === '' && lot?.campagne.prix_officiel_kg_fcfa) prix = String(lot.campagne.prix_officiel_kg_fcfa);
    });

    const apercu = $derived.by(() => {
        const b = depuisSaisie(brut, 3);
        const t = depuisSaisie(tare === '' ? '0' : tare, 3);
        const p = fcfaDepuisSaisie(prix);
        if (b === null || t === null || p === null || p <= 0) {
            return null;
        }
        const net = b - t;
        if (net <= 0) {
            return null;
        }
        const pret = prets.find((x) => x.id === pretId) ?? null;
        let retenus = 0;
        if (pret) {
            const saisi = kilosRetenus === '' ? null : depuisSaisie(kilosRetenus, 3);
            retenus = Math.max(0, Math.min(saisi ?? grammesPourSolder(pret.restant_du_fcfa, p), net));
        }

        return { brut: b, tare: t, net, prix: p, montant: montantAchat(net, p), pret, retenus, especes: montantAchat(net - retenus, p) };
    });

    async function enregistrer(e: SubmitEvent) {
        e.preventDefault();
        erreur = succes = '';
        const humiditePourMille = humidite === '' ? null : depuisSaisie(humidite, 1);

        if (!producteur) return (erreur = 'Choisir le producteur.');
        if (!lot) return (erreur = 'Choisir le lot.');
        if (compteId === null) return (erreur = 'Aucune caisse pour payer : télécharger les référentiels.');
        if (!apercu) return (erreur = 'Pesée ou prix incorrect : poids brut > tare, prix en FCFA entiers.');
        if (humidite !== '' && humiditePourMille === null) return (erreur = 'Humidité : un nombre, au plus 1 chiffre après la virgule (ex. 8,5).');
        const officiel = lot.campagne.prix_officiel_kg_fcfa;
        if (officiel && apercu.prix < officiel) return (erreur = `Prix inférieur au prix officiel (${fcfa(officiel)}/kg) : le bureau le refusera.`);
        if (apercu.pret && apercu.retenus <= 0) return (erreur = 'Kilos retenus pour le prêt : au moins 1 g, ou choisir « aucun prêt ».');

        dernier = await mettreEnFile(db, 'achat', {
            campagne_id: lot.campagne_id,
            lot_id: lot.id,
            compte_id: compteId,
            fournisseur_type: 'producteur',
            producteur_id: producteur.id,
            point_collecte_id: pointId,
            date_achat: new Date().toISOString(),
            poids_brut_g: apercu.brut,
            tare_g: apercu.tare,
            humidite_pour_mille: humiditePourMille,
            prix_kg_fcfa: apercu.prix,
            pret_id: apercu.pret?.id ?? null,
            grammes_rembourses: apercu.pret ? apercu.retenus : 0,
            photo_pesee: photoPesee,
        }, `${producteur.nom} ${producteur.prenoms} — ${kg(apercu.net)} × ${fcfa(apercu.prix)} = ${fcfa(apercu.montant)}, espèces ${fcfa(apercu.especes)}`);

        succes = `Achat enregistré sur le téléphone : ${producteur.nom} ${producteur.prenoms}, ${kg(apercu.net)}, payer ${fcfa(apercu.especes)}. Il partira au prochain envoi.`;
        producteur = null;
        brut = humidite = kilosRetenus = '';
        photoPesee = null;
        tare = '0';
        window.scrollTo(0, 0);
    }

    const champ = 'mt-1 w-full rounded-md border border-stone-300 bg-white px-3 py-2';
</script>

<form onsubmit={enregistrer} class="space-y-4">
    <h1 class="text-lg font-semibold">Achat bord-champ</h1>

    {#if succes}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{succes}</p>{/if}
    {#if dernier}<BoutonImprimer operation={dernier} />{/if}

    <ChoixProducteur bind:producteur />

    <fieldset class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
        <label class="block"><span class="text-sm text-stone-600">Lot</span>
            <select bind:value={lotId} class={champ}>
                <option value={null}>—</option>
                {#each $lots ?? [] as l (l.id)}<option value={l.id}>{l.code} ({l.campagne.code})</option>{/each}
            </select>
        </label>
        {#if ($comptes?.length ?? 0) > 1}
            <label class="block"><span class="text-sm text-stone-600">Payé depuis</span>
                <select bind:value={compteId} class={champ}>
                    {#each $comptes ?? [] as c (c.id)}<option value={c.id}>{c.nom}</option>{/each}
                </select>
            </label>
        {/if}
        {#if ($points?.length ?? 0) > 0}
            <label class="block"><span class="text-sm text-stone-600">Point de collecte (facultatif)</span>
                <select bind:value={pointId} class={champ}>
                    <option value={null}>—</option>
                    {#each $points ?? [] as p (p.id)}<option value={p.id}>{p.nom}</option>{/each}
                </select>
            </label>
        {/if}
    </fieldset>

    <fieldset class="grid grid-cols-2 gap-3 rounded-lg bg-white p-4 shadow-sm">
        <label class="block"><span class="text-sm text-stone-600">Poids brut (kg)</span>
            <input bind:value={brut} inputmode="decimal" required class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Tare (kg)</span>
            <input bind:value={tare} inputmode="decimal" class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Humidité (%)</span>
            <input bind:value={humidite} inputmode="decimal" class={champ} /></label>
        <label class="block"><span class="text-sm text-stone-600">Prix (FCFA / kg)</span>
            <input bind:value={prix} inputmode="numeric" required class={champ} /></label>
    </fieldset>

    {#if prets.length > 0}
        <fieldset class="space-y-3 rounded-lg bg-amber-50 p-4 shadow-sm">
            <label class="block"><span class="text-sm text-stone-700">Prêt à rembourser en kilos</span>
                <select bind:value={pretId} class={champ}>
                    <option value="">Aucun (tout en espèces)</option>
                    {#each prets as p (p.id)}<option value={p.id}>{p.reference} — reste {fcfa(p.restant_du_fcfa)}</option>{/each}
                </select>
            </label>
            {#if pretId}
                <label class="block"><span class="text-sm text-stone-700">Kilos retenus (vide = ce qu'il faut pour solder)</span>
                    <input bind:value={kilosRetenus} inputmode="decimal" class={champ} /></label>
                <p class="text-xs text-stone-600">Restant dû du dernier téléchargement ; le bureau valorise les kilos selon la règle du prêt.</p>
            {/if}
        </fieldset>
    {/if}

    <PrisePhoto libelle="Photo de la pesée (facultative)" bind:uuid={photoPesee} />

    {#if apercu}
        <dl class="grid grid-cols-2 gap-y-1 rounded-lg bg-stone-900 p-4 text-sm text-white">
            <dt>Poids net</dt><dd class="text-right">{kg(apercu.net)}</dd>
            <dt>Valeur</dt><dd class="text-right">{fcfa(apercu.montant)}</dd>
            {#if apercu.pret}<dt>Retenu (prêt)</dt><dd class="text-right">{kg(apercu.retenus)}</dd>{/if}
            <dt class="font-semibold">À payer en espèces</dt><dd class="text-right text-lg font-semibold">{fcfa(apercu.especes)}</dd>
        </dl>
    {/if}

    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}

    <button type="submit" class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white">Enregistrer l'achat</button>
</form>
