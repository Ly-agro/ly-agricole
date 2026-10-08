@props(['lignes', 'vide' => 'Rien à afficher pour l\'instant.'])

@php

    $traits = ['stroke-emerald-800', 'stroke-amber-500', 'stroke-emerald-500', 'stroke-stone-500', 'stroke-amber-700', 'stroke-stone-300'];
    $pastilles = ['bg-emerald-800', 'bg-amber-500', 'bg-emerald-500', 'bg-stone-500', 'bg-amber-700', 'bg-stone-300'];

    $lignes = collect($lignes)->values();
    if ($lignes->count() > 6) {
        $lignes = $lignes->take(5)->push(['label' => 'Autres', 'valeur' => (int) $lignes->slice(5)->sum('valeur')]);
    }
    $total = (int) $lignes->sum('valeur');
    $rayon = 15.9155;
    $decalage = 0;
@endphp

@if ($total === 0)
    <p class="text-sm text-stone-500">{{ $vide }}</p>
@else
    <div class="flex flex-wrap items-center gap-6">
        <svg viewBox="0 0 42 42" class="h-36 w-36 shrink-0 -rotate-90" role="img" aria-label="Répartition par catégorie">
            <circle cx="21" cy="21" r="{{ $rayon }}" fill="none" class="stroke-stone-100" stroke-width="6" />
            @foreach ($lignes as $i => $ligne)
                @php
                    $part = $ligne['valeur'] * 100 / $total;
                @endphp
                <circle cx="21" cy="21" r="{{ $rayon }}" fill="none" stroke-width="6"
                    class="{{ $traits[$i % count($traits)] }}"
                    stroke-dasharray="{{ round($part, 3) }} {{ round(100 - $part, 3) }}"
                    stroke-dashoffset="{{ round(-$decalage, 3) }}">
                    <title>{{ $ligne['label'] }} · {{ \App\Support\Format::fcfa($ligne['valeur']) }}</title>
                </circle>
                @php $decalage += $part; @endphp
            @endforeach
        </svg>
        <ul class="min-w-0 flex-1 space-y-2 text-sm">
            @foreach ($lignes as $i => $ligne)
                <li class="flex items-center justify-between gap-3">
                    <span class="flex min-w-0 items-center gap-2 text-stone-700">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-sm {{ $pastilles[$i % count($pastilles)] }}"></span>
                        <span class="truncate">{{ $ligne['label'] }}</span>
                    </span>
                    <span class="tabular-nums text-stone-900">{{ intdiv($ligne['valeur'] * 100 + intdiv($total, 2), $total) }} %</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
