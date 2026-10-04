@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Rendements</h1>
            <p class="mt-1 text-sm text-stone-600">Kilos livrés ÷ hectares financés, producteur par producteur.</p>
        </div>
        <label class="text-sm">
            <span class="mb-1 block text-xs text-stone-500">Campagne</span>
            <select wire:model.live="campagneId" class="rounded-lg border-stone-300 text-sm">
                @foreach ($campagnes as $c)
                    <option value="{{ $c->id }}" @selected($campagne?->id === $c->id)>{{ $c->produit->nom }} · {{ $c->code }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($resultat === null || ($resultat['classes']->isEmpty() && $resultat['sansSurface']->isEmpty()))
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun rendement calculable pour cette campagne.</p>
            <p class="mt-1 text-sm text-stone-600">Il faut des prêts accordés avec parcelle relevée, et des achats validés.</p>
        </div>
    @else
        <div class="rounded-xl border border-stone-200 bg-white p-5">
            <p class="text-xs text-stone-500">Rendement moyen (pondéré par la surface)</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">{{ $resultat['moyenneKgParHa'] === null ? '—' : Format::entier($resultat['moyenneKgParHa']).' kg/ha' }}</p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">Producteur</th>
                        <th class="px-4 py-2 text-right">Surface financée</th>
                        <th class="px-4 py-2 text-right">Livré</th>
                        <th class="px-4 py-2 text-right">Rendement</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($resultat['classes'] as $l)
                        <tr>
                            <td class="px-4 py-2 tabular-nums">{{ $l['rang'] }}</td>
                            <td class="px-4 py-2"><a href="{{ route('rendements.producteur', $l['producteur']) }}" class="text-emerald-800 hover:underline">{{ $l['producteur']->nom }}</a></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::hectares($l['surface_m2']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::kg($l['grammes']) }}</td>
                            <td class="px-4 py-2 text-right font-medium tabular-nums">{{ Format::entier($l['kg_par_ha']) }} kg/ha</td>
                            <td class="px-4 py-2 text-xs">
                                @if ($l['groupe'] === 'meilleurs')
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">20 % meilleurs</span>
                                @elseif ($l['groupe'] === 'moins_bons')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">20 % moins bons</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($carte !== null && $carte['parcelles'] !== [])
            @php
                // Du plus clair (rendement faible) au plus foncé (fort) ; gris = pas de rendement.
                $couleurs = ['#d9f0d3', '#a6dba0', '#5aae61', '#1b7837', '#00441b'];
            @endphp
            <div class="rounded-xl border border-stone-200 bg-white p-5">
                <h2 class="mb-3 font-semibold">Carte des parcelles financées</h2>
                <svg viewBox="0 0 {{ $dessin['largeur'] }} {{ $dessin['hauteur'] }}" class="mx-auto max-h-[32rem] w-full rounded-lg bg-stone-50" role="img" aria-label="Parcelles colorées par rendement">
                    @foreach ($carte['parcelles'] as $p)
                        @foreach ($dessin['polygones'][$p['id']] as $points)
                            <polygon points="{{ $points }}" fill="{{ $p['classe'] === null ? '#d6d3d1' : $couleurs[$p['classe']] }}" stroke="#57534e" stroke-width="0.8" stroke-linejoin="round">
                                <title>{{ $p['nom'] }} — {{ $p['producteur'] }} : {{ $p['kg_par_ha'] === null ? 'pas de rendement' : Format::entier($p['kg_par_ha']).' kg/ha' }}</title>
                            </polygon>
                        @endforeach
                    @endforeach
                </svg>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-stone-600">
                    @if ($carte['bornes'] !== null)
                        @foreach ($couleurs as $i => $couleur)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-3 w-3 rounded-sm border border-stone-400" style="background: {{ $couleur }}"></span>
                                {{ Format::entier($carte['bornes'][$i]) }} – {{ Format::entier($carte['bornes'][$i + 1]) }} kg/ha
                            </span>
                        @endforeach
                    @endif
                    @if (collect($carte['parcelles'])->contains(fn ($p) => $p['classe'] === null))
                        <span class="inline-flex items-center gap-1.5">
                            <span class="inline-block h-3 w-3 rounded-sm border border-stone-400" style="background: #d6d3d1"></span>
                            pas de rendement
                        </span>
                    @endif
                </div>
                <p class="mt-2 text-xs text-stone-500">
                    Les kilos sont pesés par producteur, pas par parcelle : chaque parcelle porte le rendement de son producteur.
                </p>
            </div>
        @endif

        @if ($resultat['sansSurface']->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm">
                <p class="font-medium text-amber-900">Sans rendement : aucune parcelle financée avec contour relevé</p>
                <ul class="mt-2 space-y-1 text-amber-900">
                    @foreach ($resultat['sansSurface'] as $l)
                        <li>{{ $l['producteur']->nom }} — {{ Format::kg($l['grammes']) }} livrés</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-xs text-stone-500">
            Kilos livrés = poids net des achats validés du producteur sur la campagne. Hectares = parcelles des prêts
            accordés, contour relevé seulement, chaque parcelle comptée une fois.
        </p>
    @endif
</div>
