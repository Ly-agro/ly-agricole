@php use App\Support\Format; @endphp

<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Visites de parcelle</h1>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="recherche" class="mb-1 block text-stone-600">Producteur ou parcelle</label>
                <input wire:model.live.debounce.400ms="recherche" id="recherche" type="search" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
            </div>
            <div>
                <label for="pratique" class="mb-1 block text-stone-600">Pratique constatée</label>
                <select wire:model.live="pratique" id="pratique" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Toutes</option>
                    @foreach ($pratiques as $p)
                        <option value="{{ $p->value }}">{{ $p->libelle() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="space-y-4">
        @forelse ($visites as $v)
            <article wire:key="visite-{{ $v->id }}" class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <div>
                        <h2 class="font-semibold">
                            {{ $v->parcelle->producteur->nomComplet() }}
                            <span class="font-normal text-stone-500">· {{ $v->parcelle->nom }}</span>
                        </h2>
                        <p class="text-xs text-stone-500">
                            {{ $v->parcelle->producteur->village->nom ?? '' }}
                            @if ($v->parcelle->produit) · {{ $v->parcelle->produit->nom }} @endif
                            · {{ Format::hectares($v->parcelle->surface_m2) }}
                        </p>
                    </div>
                    <p class="text-sm text-stone-600">
                        {{ $v->date_visite->format('d/m/Y') }} · {{ $v->auteur->nom }}
                        @if ($v->lat !== null)
                            · <a href="https://www.openstreetmap.org/?mlat={{ $v->lat }}&mlon={{ $v->lng }}#map=17/{{ $v->lat }}/{{ $v->lng }}" target="_blank" rel="noopener" class="text-emerald-800 hover:underline">position</a>
                        @endif
                    </p>
                </div>

                @if ($v->pratiquesConstatees() !== [])
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($v->pratiquesConstatees() as $p)
                            <li class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs text-emerald-900">{{ $p->libelle() }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($v->observations)
                    <p class="mt-3 whitespace-pre-line text-sm text-stone-800">{{ $v->observations }}</p>
                @endif

                @if ($v->photos->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($v->photos as $photo)
                            <a href="{{ route('photos-terrain', $photo) }}" target="_blank" rel="noopener">
                                <img src="{{ route('photos-terrain', $photo) }}" alt="Photo de la visite" loading="lazy" class="h-24 w-24 rounded-md object-cover">
                            </a>
                        @endforeach
                    </div>
                    @can('demander-avis-ia')
                        <button type="button" wire:click="demanderAvisIa('{{ $v->id }}')" class="mt-3 rounded-md border border-stone-300 px-3 py-1.5 text-sm text-stone-700 hover:bg-stone-50">Demander un avis IA sur les photos</button>
                    @endcan
                @endif
            </article>
        @empty
            <p class="rounded-xl border border-stone-200 bg-white px-4 py-8 text-center text-sm text-stone-500">
                Aucune visite. Elles se saisissent sur le téléphone (Saisir → Visite de parcelle).
            </p>
        @endforelse
    </div>

    <div class="mt-4">{{ $visites->links() }}</div>
</div>
