@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <a href="{{ route('rendements') }}" class="text-sm text-emerald-800 hover:underline">← Rendements</a>
        <h1 class="mt-1 text-xl font-semibold">{{ $producteur->prenoms }} {{ $producteur->nom }}</h1>
        <p class="mt-1 text-sm text-stone-600">Rendement campagne après campagne. Chaque écart se mesure à la campagne précédente du même produit.</p>
    </div>

    @if ($lignes === [])
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun rendement calculable pour ce producteur.</p>
            <p class="mt-1 text-sm text-stone-600">Il faut un prêt accordé avec parcelle relevée sur au moins une campagne.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Campagne</th>
                        <th class="px-4 py-2 text-right">Surface financée</th>
                        <th class="px-4 py-2 text-right">Livré</th>
                        <th class="px-4 py-2 text-right">Rendement</th>
                        <th class="px-4 py-2 text-right">Écart</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($lignes as $l)
                        <tr>
                            <td class="px-4 py-2">{{ $l['campagne']->produit->nom }} · {{ $l['campagne']->code }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::hectares($l['surface_m2']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::kg($l['grammes']) }}</td>
                            <td class="px-4 py-2 text-right font-medium tabular-nums">{{ Format::entier($l['kg_par_ha']) }} kg/ha</td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                @if ($l['ecart_kg_par_ha'] === null)
                                    <span class="text-stone-400">première campagne</span>
                                @elseif ($l['ecart_kg_par_ha'] > 0)
                                    <span class="text-emerald-700">+{{ Format::entier($l['ecart_kg_par_ha']) }} kg/ha</span>
                                @elseif ($l['ecart_kg_par_ha'] < 0)
                                    <span class="text-red-700">−{{ Format::entier(abs($l['ecart_kg_par_ha'])) }} kg/ha</span>
                                @else
                                    <span class="text-stone-500">inchangé</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
