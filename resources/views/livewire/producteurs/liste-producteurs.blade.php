<div>
    @if (session('statut'))
        <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ session('statut') }}</p>
    @endif
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Producteurs</h1>

        <div class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="recherche" class="mb-1 block text-stone-600">Rechercher</label>
                <input wire:model.live.debounce.400ms="recherche" id="recherche" type="search"
                    placeholder="Nom, code carte ou téléphone"
                    class="w-64 rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
            </div>
            <div>
                <label for="filtreVillage" class="mb-1 block text-stone-600">Village</label>
                <select wire:model.live="filtreVillage" id="filtreVillage"
                    class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Tous</option>
                    @foreach ($villages as $v)
                        <option value="{{ $v->id }}">{{ $v->nom }}</option>
                    @endforeach
                </select>
            </div>
            @can('gerer-producteurs')
                <a href="{{ route('producteurs.groupes') }}"
                    class="rounded-md border border-stone-300 bg-white px-4 py-2 text-stone-800 hover:bg-stone-50">
                    Groupes
                </a>
                <a href="{{ route('producteurs.nouveau') }}"
                    class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">
                    Nouveau producteur
                </a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Code</th>
                    <th class="px-4 py-3 font-medium">Nom</th>
                    <th class="px-4 py-3 font-medium">Village</th>
                    <th class="px-4 py-3 font-medium">Groupe</th>
                    <th class="px-4 py-3 font-medium">Téléphone</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($producteurs as $p)
                    <tr wire:key="producteur-{{ $p->id }}" @class(['text-stone-400' => ! $p->actif])>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $p->code }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('producteurs.fiche', $p) }}" class="font-medium text-emerald-800 hover:underline">{{ $p->nomComplet() }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $p->village->nom }} <span class="text-stone-400">({{ $p->village->zone->nom }})</span></td>
                        <td class="px-4 py-3">{{ $p->groupe?->nom ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ \App\Support\Telephone::afficher($p->telephone) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-stone-500">Aucun producteur.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $producteurs->links() }}</div>
</div>
