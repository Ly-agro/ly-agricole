<script lang="ts">
    import { db, type Operation } from './db';
    import { bonDepuisOperation } from './bons';
    import { ImpressionImpossible, imprimer } from './imprimante';

    /** « Imprimer le bon » d'un achat gardé sur le téléphone (imprimante 58 mm). */
    let { operation, libelle = 'Imprimer le bon de pesée' }: { operation: Operation; libelle?: string } = $props();

    let occupe = $state(false);
    let message = $state('');
    let erreur = $state(false);

    async function lancer() {
        occupe = true;
        message = '';
        erreur = false;
        try {
            const ticket = await bonDepuisOperation(db, operation);
            if (!ticket) return;
            await imprimer(db, ticket);
            message = 'Bon imprimé.';
        } catch (e) {
            erreur = true;
            message = e instanceof ImpressionImpossible ? e.message : 'Impression impossible : ' + (e instanceof Error ? e.message : String(e));
        } finally {
            occupe = false;
        }
    }
</script>

<button type="button" onclick={lancer} disabled={occupe} class="w-full rounded-md border border-stone-400 bg-white py-3 font-medium text-stone-800 disabled:opacity-50">
    {occupe ? 'Impression…' : libelle}
</button>
{#if message}<p class="mt-1 text-sm {erreur ? 'text-red-800' : 'text-emerald-800'}">{message}</p>{/if}
