@php($champ = 'block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none')
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Stock d'intrants</h1>
        <div class="flex flex-wrap gap-2 text-sm">
            <button type="button" wire:click="ouvrir('entree')" class="rounded-md bg-emerald-700 px-3 py-2 font-medium text-white hover:bg-emerald-800">Entrée en stock</button>
            <button type="button" wire:click="ouvrir('perte')" class="rounded-md border border-stone-300 bg-white px-3 py-2 hover:bg-stone-50">Perte</button>
            <button type="button" wire:click="ouvrir('ajustement')" class="rounded-md border border-stone-300 bg-white px-3 py-2 hover:bg-stone-50">Ajustement d'inventaire</button>
            <a href="{{ route('referentiels.intrants') }}" class="rounded-md px-3 py-2 text-emerald-800 hover:underline">Fiches d'intrants</a>
        </div>
    </div>

    @if ($formulaire !== null)
        <form wire:submit="enregistrer" class="mb-8 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">{{ ['entree' => 'Entrée en stock', 'perte' => 'Perte', 'ajustement' => 'Ajustement d\'inventaire'][$formulaire] }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="intrantId" class="mb-1 block text-sm font-medium text-stone-700">Intrant</label>
                    <select wire:model="intrantId" id="intrantId" class="{{ $champ }}">
                        <option value="">— Choisir —</option>
                        @foreach ($intrants->where('actif', true) as $i)
                            <option value="{{ $i->id }}">{{ $i->nom }}</option>
                        @endforeach
                    </select>
                    @error('intrantId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
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
                    <label for="quantite" class="mb-1 block text-sm font-medium text-stone-700">
                        Quantité {{ $formulaire === 'ajustement' ? '(+ trouvé en plus, − manquant)' : '' }}
                    </label>
                    <input wire:model="quantite" id="quantite" type="number" step="1" class="{{ $champ }} text-right">
                    @error('quantite') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="dateMouvement" class="mb-1 block text-sm font-medium text-stone-700">Date</label>
                    <input wire:model="dateMouvement" id="dateMouvement" type="date" class="{{ $champ }}">
                    @error('dateMouvement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="motif" class="mb-1 block text-sm font-medium text-stone-700">Motif {{ $formulaire === 'entree' ? '(facultatif : fournisseur, bon de livraison…)' : '' }}</label>
                    <input wire:model="motif" id="motif" type="text" class="{{ $champ }}">
                    @error('motif') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Enregistrer</button>
                <button type="button" wire:click="annuler" class="rounded-md px-4 py-2 text-sm text-stone-700 hover:bg-stone-100">Annuler</button>
            </div>
        </form>
    @endif

    <div class="mb-8 overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Intrant</th>
                    @foreach ($magasins as $m)
                        <th class="px-4 py-3 text-right font-medium">{{ $m->nom }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-medium">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($intrants as $i)
                    @php($parMagasin = $stocks[$i->id] ?? collect())
                    <tr wire:key="stock-{{ $i->id }}" @class(['text-stone-400' => ! $i->actif])>
                        <td class="px-4 py-3 font-medium">{{ $i->nom }}</td>
                        @foreach ($magasins as $m)
                            <td class="px-4 py-3 text-right tabular-nums">{{ $parMagasin[$m->id] ?? 0 }}</td>
                        @endforeach
                        <td class="px-4 py-3 text-right font-semibold tabular-nums" id="total-{{ $i->id }}">{{ $parMagasin->sum() }} {{ $i->unite->libelle($parMagasin->sum()) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $magasins->count() + 2 }}" class="px-4 py-8 text-center text-stone-500">Aucun intrant : créer d'abord les fiches.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($aContrePasser !== null)
        <form wire:submit="contrePasser" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-950">Contre-passer le mouvement n° {{ $aContrePasser }}</h2>
            <label for="motifContrePassation" class="mt-2 block text-sm font-medium text-amber-950">Motif</label>
            <input wire:model="motifContrePassation" id="motifContrePassation" type="text" class="mt-1 block w-full rounded-md border border-amber-300 px-3 py-2 focus:outline-none">
            @error('motifContrePassation') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="mt-3 flex gap-3">
                <button type="submit" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800">Contre-passer</button>
                <button type="button" wire:click="annuler" class="rounded-md px-4 py-2 text-sm text-stone-700 hover:bg-amber-100">Annuler</button>
            </div>
        </form>
    @endif

    <h2 class="mb-3 font-semibold">Mouvements</h2>
    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">N°</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 font-medium">Intrant</th>
                    <th class="px-4 py-3 font-medium">Magasin</th>
                    <th class="px-4 py-3 text-right font-medium">Quantité</th>
                    <th class="px-4 py-3 font-medium">Prêt / motif</th>
                    <th class="px-4 py-3 font-medium">Par</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($mouvements as $m)
                    <tr wire:key="mvt-{{ $m->id }}" @class(['text-stone-400 line-through' => $m->contrePassation !== null])>
                        <td class="px-4 py-3 tabular-nums">{{ $m->id }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $m->date_mouvement->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $m->type->libelle() }}</td>
                        <td class="px-4 py-3">{{ $m->intrant->nom }}</td>
                        <td class="px-4 py-3">{{ $m->magasin->nom }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $m->quantite > 0 ? '+' : '' }}{{ $m->quantite }}</td>
                        <td class="px-4 py-3">
                            @if ($m->pret)
                                <a href="{{ route('prets.fiche', $m->pret) }}" class="text-emerald-800 hover:underline">{{ $m->pret->reference }}</a> — {{ $m->pret->producteur->nomComplet() }}
                                @if ($m->valeur_fcfa !== null) <span class="text-stone-500">({{ \App\Support\Format::fcfa(abs($m->valeur_fcfa)) }})</span> @endif
                            @endif
                            @if ($m->motif) <span class="block text-xs text-amber-800">{{ $m->motif }}</span> @endif
                        </td>
                        <td class="px-4 py-3">{{ $m->auteur->nom }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($m->contrePassation === null && $m->type->value !== 'contre_passation')
                                <button type="button" wire:click="preparerContrePassation({{ $m->id }})" class="text-xs text-amber-800 hover:underline">Contre-passer</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-stone-500">Aucun mouvement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $mouvements->links() }}</div>
</div>
