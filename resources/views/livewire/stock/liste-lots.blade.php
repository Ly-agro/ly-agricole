@php($champ = 'block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none')
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Lots</h1>
        <button type="button" wire:click="ouvrir" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Nouveau lot</button>
    </div>

    @if ($formulaireOuvert)
        <form wire:submit="creer" class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="campagneId" class="mb-1 block text-sm font-medium text-stone-700">Campagne</label>
                    <select wire:model="campagneId" id="campagneId" class="{{ $champ }}">
                        <option value="">— Choisir —</option>
                        @foreach ($campagnes as $c)
                            <option value="{{ $c->id }}">{{ $c->produit->nom }} {{ $c->code }}</option>
                        @endforeach
                    </select>
                    @error('campagneId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="magasinId" class="mb-1 block text-sm font-medium text-stone-700">Magasin</label>
                    <select wire:model="magasinId" id="magasinId" class="{{ $champ }}">
                        <option value="">— Choisir —</option>
                        @foreach ($magasins->where('actif', true) as $m)
                            <option value="{{ $m->id }}">{{ $m->nom }}</option>
                        @endforeach
                    </select>
                    @error('magasinId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="description" class="mb-1 block text-sm font-medium text-stone-700">Description <span class="font-normal text-stone-500">(facultatif)</span></label>
                    <input wire:model="description" id="description" type="text" placeholder="ex. Noix triées, zone nord" class="{{ $champ }}">
                </div>
            </div>
            <button type="submit" class="mt-4 rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800">Créer le lot</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Lot</th>
                    <th class="px-4 py-3 font-medium">Produit · campagne</th>
                    <th class="px-4 py-3 font-medium">Statut</th>
                    <th class="px-4 py-3 font-medium">Stock par magasin</th>
                    <th class="px-4 py-3 text-right font-medium">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($lots as $l)
                    @php($parMagasin = ($stocks[$l->id] ?? collect())->filter(fn ($g) => $g !== 0))
                    <tr wire:key="lot-{{ $l->id }}">
                        <td class="px-4 py-3"><a href="{{ route('lots.fiche', $l) }}" class="font-mono text-xs font-medium text-emerald-800 hover:underline">{{ $l->code }}</a>
                            @if ($l->description) <span class="block text-xs text-stone-500">{{ $l->description }}</span> @endif</td>
                        <td class="px-4 py-3">{{ $l->produit->nom }} · {{ $l->campagne->code }}</td>
                        <td class="px-4 py-3">{{ $l->statut->libelle() }}</td>
                        <td class="px-4 py-3">
                            @forelse ($parMagasin as $magasinId => $g)
                                <span class="block">{{ $magasins->firstWhere('id', $magasinId)?->nom }} : {{ \App\Support\Format::kg($g) }}</span>
                            @empty — @endforelse
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums" id="stock-{{ $l->id }}">{{ \App\Support\Format::kg((int) $parMagasin->sum()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-stone-500">Aucun lot.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
