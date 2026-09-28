<x-layouts.app title="Tableau de bord">
    <h1 class="text-xl font-semibold">Tableau de bord</h1>
    <p class="mt-2 text-stone-600">Bienvenue, {{ auth()->user()->nom }}.</p>

    @if (auth()->user()->role === null)
        <p class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">
            Aucun rôle ne vous est attribué : vous n'avez accès à aucun écran. Contactez l'administrateur.
        </p>
    @endif
</x-layouts.app>
