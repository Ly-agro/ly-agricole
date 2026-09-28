<script lang="ts">
    import { db } from './db';
    import { garderPhoto } from './photos';

    /** Bouton « prendre une photo » : ouvre l'appareil photo, garde la photo compressée. */
    let { libelle, uuid = $bindable<string | null>(null) }: { libelle: string; uuid?: string | null } = $props();

    let apercu = $state<string | null>(null);
    let occupe = $state(false);
    let erreur = $state('');

    async function choisir(e: Event) {
        const fichier = (e.currentTarget as HTMLInputElement).files?.[0];
        if (!fichier) return;
        erreur = '';
        occupe = true;
        try {
            if (uuid) await db.photos.delete(uuid); // photo remplacée avant envoi
            uuid = await garderPhoto(db, fichier);
            if (apercu) URL.revokeObjectURL(apercu);
            apercu = URL.createObjectURL(fichier);
        } catch {
            erreur = 'Ce fichier n\'est pas une photo lisible.';
        } finally {
            occupe = false;
        }
    }
</script>

<label class="block rounded-md border border-dashed border-stone-400 p-3 text-center text-sm text-stone-700">
    {occupe ? 'Compression…' : uuid ? 'Photo prise — toucher pour la refaire' : libelle}
    <input type="file" accept="image/*" capture="environment" class="sr-only" onchange={choisir} />
    {#if apercu}<img src={apercu} alt="" class="mx-auto mt-2 max-h-40 rounded" />{/if}
</label>
{#if erreur}<p class="mt-1 text-sm text-red-800">{erreur}</p>{/if}
