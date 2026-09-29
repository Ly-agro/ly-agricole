@props(['route', 'motif', 'icone'])

@php
    $actif = request()->routeIs($motif);
    // Tracés d'icônes en trait (24×24). Quelques formes simples, pas de bibliothèque.
    $traces = [
        'accueil' => 'M3 10.5 12 3l9 7.5M5 9.5V20h5v-5h4v5h5V9.5',
        'producteurs' => 'M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19M10 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM20 19v-1a3 3 0 0 0-2.2-2.9M15.5 5.2a3 3 0 0 1 0 5.6',
        'prets' => 'M12 3v18M16.5 7.5c-.6-1-2-1.5-4.5-1.5-2.6 0-4 1-4 2.6 0 3.6 9 1.6 9 5.6 0 1.7-1.6 2.8-4.5 2.8-2.3 0-4-.6-4.7-2',
        'tresorerie' => 'M3 7.5A1.5 1.5 0 0 1 4.5 6h15A1.5 1.5 0 0 1 21 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 16.5v-9ZM3 10h18M16 14.5h2',
        'intrants' => 'M21 8 12 3 3 8m18 0-9 5m9-5v8l-9 5m0-8L3 8m9 5v8M3 8v8l9 5',
        // Balance (achats pesés) et sacs empilés (lots).
        'achats' => 'M12 4v16M8 20h8M5 7h14M5 7l-2.5 6a2.5 2.5 0 0 0 5 0L5 7Zm14 0-2.5 6a2.5 2.5 0 0 0 5 0L19 7Z',
        'lots' => 'M7 10c-2 0-3 1.5-3 4.5S5 20 8 20h8c3 0 4-2.5 4-5.5S19 10 17 10M9 10l-1-3h8l-1 3M9 10h6',
        // Sortie du lot vers l'acheteur : flèche qui sort d'un bac.
        'ventes' => 'M4 15.5V19a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3.5M7 9.5 12 4.5 17 9.5M12 5.5v11',
        'depenses' => 'M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 8h6M9 12h6',
        // Argent qui entre dans la campagne : flèche vers un bac, sens inverse de « ventes ».
        'apports' => 'M4 8.5V5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v3.5M7 13.5 12 18.5l5-5M12 17.5v-11',
        // Prévu contre réel : deux barres, une pleine, une en cours.
        'budget' => 'M4 20h16M7 20V9m5 11V5m5 15v-7',
        'referentiels' => 'M4 6h16M4 12h16M4 18h10',
        'journal' => 'M8 4h9a2 2 0 0 1 2 2v14H8a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2ZM6 8H4m2 4H4m2 4H4M10 9h6M10 13h6',
        'utilisateurs' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 20a8 8 0 0 1 16 0',
    ];
@endphp

<a href="{{ route($route) }}" @if ($actif) aria-current="page" @endif
    @class([
        'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-colors',
        'bg-emerald-800 font-medium text-white' => $actif,
        'text-stone-700 hover:bg-stone-200/70 hover:text-stone-900' => ! $actif,
    ])>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
        class="h-5 w-5 shrink-0" aria-hidden="true"><path d="{{ $traces[$icone] }}" /></svg>
    <span>{{ $slot }}</span>
</a>
