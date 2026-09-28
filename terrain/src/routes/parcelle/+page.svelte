<script lang="ts">
    import { onDestroy } from 'svelte';
    import ChoixProducteur from '$lib/ChoixProducteur.svelte';
    import { db, type Producteur } from '$lib/db';
    import { distanceM, garderPoint, hectares, polygone, type Position, surfaceM2 } from '$lib/geo';
    import { mettreEnFile } from '$lib/synchro';

    /**
     * Relevé de parcelle EN MARCHANT : l'agent fait le tour, le téléphone garde un point
     * tous les 3 m quand la précision est ≤ 15 m. Il peut aussi poser un point à chaque
     * coin. La surface affichée est un aperçu : le bureau la recalcule.
     */

    let producteur = $state<Producteur | null>(null);
    let nom = $state('');
    let points = $state<Position[]>([]);
    let precision = $state<number | null>(null);
    let derniere = $state<Position | null>(null);
    let suivi = $state<number | null>(null);
    let erreur = $state('');
    let succes = $state('');

    const surface = $derived(surfaceM2(points));
    const perimetre = $derived(points.length < 2 ? 0 : points.reduce((t, p, i) => t + distanceM(p, points[(i + 1) % points.length]), 0));

    function demarrer() {
        erreur = succes = '';
        if (!('geolocation' in navigator)) {
            erreur = 'Pas de GPS sur cet appareil.';
            return;
        }
        suivi = navigator.geolocation.watchPosition(
            (p) => {
                const position: Position = [p.coords.longitude, p.coords.latitude];
                precision = Math.round(p.coords.accuracy);
                derniere = position;
                if (garderPoint(points, position, p.coords.accuracy)) {
                    points = [...points, position];
                }
            },
            (e) => {
                erreur = e.code === e.PERMISSION_DENIED ? 'Autoriser la position (GPS) pour relever la parcelle.' : 'Signal GPS perdu : continuer à marcher à découvert.';
            },
            { enableHighAccuracy: true, maximumAge: 0, timeout: 30_000 },
        );
    }

    function arreter() {
        if (suivi !== null) navigator.geolocation.clearWatch(suivi);
        suivi = null;
    }

    /** Point posé à la main à un coin (même filtre de précision, sans écart minimal). */
    function pointIci() {
        if (derniere && precision !== null && precision <= 15) {
            points = [...points, derniere];
        } else {
            erreur = 'Précision insuffisante pour poser un point : attendre un meilleur signal.';
        }
    }

    onDestroy(arreter);

    async function enregistrer() {
        erreur = succes = '';
        if (!producteur) return (erreur = 'Choisir le producteur.');
        if (nom.trim() === '') return (erreur = 'Donner un nom à la parcelle (ex. « Champ du bas »).');
        if (points.length < 3 || surface <= 0) return (erreur = 'Il faut au moins 3 points qui entourent une surface.');
        arreter();

        await mettreEnFile(db, 'parcelle', {
            producteur_id: producteur.id,
            nom: nom.trim(),
            contour: polygone(points),
        }, `Parcelle « ${nom.trim()} » de ${producteur.nom} ${producteur.prenoms} — ${points.length} points, ≈ ${hectares(surface)}`);

        succes = `Parcelle enregistrée sur le téléphone (≈ ${hectares(surface)}). Elle partira au prochain envoi.`;
        points = [];
        nom = '';
    }
</script>

<section class="space-y-4">
    <h1 class="text-lg font-semibold">Relevé de parcelle</h1>
    {#if succes}<p class="rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">{succes}</p>{/if}

    <ChoixProducteur bind:producteur />

    <label class="block rounded-lg bg-white p-4 shadow-sm"><span class="text-sm text-stone-600">Nom de la parcelle</span>
        <input bind:value={nom} class="mt-1 w-full rounded-md border border-stone-300 px-3 py-2" /></label>

    <div class="space-y-3 rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-stone-600">Démarrer au bord de la parcelle, faire le tour à pied, puis terminer.</p>
        <dl class="grid grid-cols-2 gap-y-1 text-sm">
            <dt>Points gardés</dt><dd class="text-right font-medium">{points.length}</dd>
            <dt>Précision GPS</dt>
            <dd class="text-right {precision !== null && precision > 15 ? 'text-red-700' : ''}">{precision === null ? '—' : `± ${precision} m`}</dd>
            <dt>Périmètre</dt><dd class="text-right">{Math.round(perimetre)} m</dd>
            <dt class="font-semibold">Surface (aperçu)</dt><dd class="text-right text-lg font-semibold">{hectares(surface)}</dd>
        </dl>
        {#if suivi === null}
            <button type="button" onclick={demarrer} class="w-full rounded-md bg-emerald-700 py-3 font-medium text-white">
                {points.length === 0 ? 'Démarrer le relevé' : 'Reprendre le relevé'}
            </button>
        {:else}
            <div class="grid grid-cols-2 gap-2">
                <button type="button" onclick={pointIci} class="rounded-md bg-stone-800 py-3 text-sm font-medium text-white">Point ici (coin)</button>
                <button type="button" onclick={arreter} class="rounded-md bg-amber-500 py-3 text-sm font-medium text-amber-950">Pause</button>
            </div>
        {/if}
        {#if points.length > 0}
            <button type="button" onclick={() => (points = [])} class="w-full py-1 text-sm text-stone-500 underline">Tout effacer</button>
        {/if}
    </div>

    {#if erreur}<p class="rounded-md bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}

    <button type="button" onclick={enregistrer} class="w-full rounded-md bg-emerald-700 py-4 text-lg font-semibold text-white">Terminer et enregistrer</button>
</section>
