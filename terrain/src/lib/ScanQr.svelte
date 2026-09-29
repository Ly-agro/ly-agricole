<script lang="ts">
    import QrScanner from 'qr-scanner';
    import { onDestroy, onMount } from 'svelte';

    /** Lit le QR de la carte producteur (il ne contient que le code, ex. LYP-000001). */
    let { onCode, onFermer }: { onCode: (code: string) => void; onFermer: () => void } = $props();

    let video: HTMLVideoElement;
    let scanner: QrScanner | null = null;
    let erreur = $state('');

    onMount(async () => {
        scanner = new QrScanner(video, (r) => {
            scanner?.stop();
            onCode(r.data.trim());
        }, { returnDetailedScanResult: true, preferredCamera: 'environment' });
        try {
            await scanner.start();
        } catch {
            erreur = 'Caméra indisponible (autorisation refusée ?). Taper le code de la carte à la place.';
        }
    });

    onDestroy(() => scanner?.destroy());
</script>

<div class="fixed inset-0 z-50 flex flex-col bg-black">
    <!-- svelte-ignore a11y_media_has_caption -->
    <video bind:this={video} class="flex-1 object-cover"></video>
    {#if erreur}<p class="bg-red-50 p-3 text-sm text-red-800">{erreur}</p>{/if}
    <button type="button" onclick={onFermer} class="bg-white py-4 font-medium">Fermer</button>
</div>
