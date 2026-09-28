@props(['lignes', 'format' => 'nombre', 'vide' => 'Rien à afficher pour l\'instant.'])

@php
    $max = max(1, (int) collect($lignes)->max('valeur'));
@endphp

@if (collect($lignes)->sum('valeur') === 0)
    <p class="text-sm text-stone-500">{{ $vide }}</p>
@else
    <ul class="space-y-3">
        @foreach ($lignes as $ligne)
            <li>
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <span class="text-stone-700">{{ $ligne['label'] }}</span>
                    <span class="font-medium tabular-nums text-stone-900">{{ $format === 'fcfa' ? \App\Support\Format::fcfa($ligne['valeur']) : $ligne['valeur'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-stone-100">
                    <div class="h-2 rounded-full bg-emerald-700" style="width: {{ max(intdiv($ligne['valeur'] * 100, $max), $ligne['valeur'] > 0 ? 2 : 0) }}%"></div>
                </div>
            </li>
        @endforeach
    </ul>
@endif
