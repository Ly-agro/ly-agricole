<script lang="ts">
    const saisies = [
        { href: '/achat', titre: 'Achat bord-champ', detail: 'Pesée, prix, prêt, photo de pesée' },
        { href: '/parcelle', titre: 'Relevé de parcelle', detail: 'Faire le tour à pied avec le GPS' },
        { href: '/visite', titre: 'Visite de parcelle', detail: 'Pratiques constatées, observations, photos' },
        { href: '/depense', titre: 'Dépense terrain', detail: 'Payée depuis votre caisse, photo du reçu' },
        { href: '/producteur', titre: 'Nouveau producteur', detail: 'Fiche et accord du producteur' },
    ];
</script>

<section class="space-y-3">
    <h1 class="text-lg font-semibold">Saisir</h1>
    {#each saisies as s (s.href)}
        <a href={s.href} class="block rounded-lg bg-white p-4 shadow-sm">
            <span class="font-semibold">{s.titre}</span>
            <span class="block text-sm text-stone-500">{s.detail}</span>
        </a>
    {/each}
    <p class="text-xs text-stone-500">Tout se saisit sans réseau et part au prochain envoi.</p>
</section>
