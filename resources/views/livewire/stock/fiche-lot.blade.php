@php($champ = 'block w-full rounded-md border border-stone-300 px-3 py-2 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 focus:outline-none')
<div>
    <div class="mb-6">
        <a href="{{ route('lots') }}" class="text-sm text-emerald-800 hover:underline">← Lots</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Lot <span class="font-mono">{{ $lot->code }}</span></h1>
                <p class="text-stone-600">{{ $lot->produit->nom }} · campagne {{ $lot->campagne->code }} · {{ $lot->statut->libelle() }}</p>
            </div>
            <p class="text-lg">Stock : <span class="font-semibold tabular-nums" id="stock-total">{{ \App\Support\Format::kg($total) }}</span></p>
        </div>
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-2 text-sm">
        @foreach ($parMagasin as $magasinId => $g)
            <span class="rounded-full border border-stone-200 bg-white px-3 py-1">{{ $magasins->firstWhere('id', $magasinId)?->nom }} : <strong class="tabular-nums">{{ \App\Support\Format::kg($g) }}</strong></span>
        @endforeach
        <span class="flex-1"></span>
        <button type="button" wire:click="ouvrir('transfert')" class="rounded-md border border-stone-300 bg-white px-3 py-2 hover:bg-stone-50">Transfert</button>
        <button type="button" wire:click="ouvrir('perte')" class="rounded-md border border-stone-300 bg-white px-3 py-2 hover:bg-stone-50">Perte</button>
        <button type="button" wire:click="ouvrir('inventaire')" class="rounded-md border border-stone-300 bg-white px-3 py-2 hover:bg-stone-50">Inventaire</button>
        <button type="button" wire:click="basculerStatut" class="rounded-md px-3 py-2 text-stone-700 hover:bg-stone-100">{{ $lot->statut->value === 'ouvert' ? 'Fermer le lot' : 'Rouvrir le lot' }}</button>
    </div>

    @if ($formulaire !== null)
        <form wire:submit="enregistrer" class="mb-6 rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 font-semibold">{{ ['transfert' => 'Transfert entre magasins', 'perte' => 'Perte', 'inventaire' => 'Inventaire : poids compté'][$formulaire] }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="magasinId" class="mb-1 block text-sm font-medium text-stone-700">{{ $formulaire === 'transfert' ? 'Depuis le magasin' : 'Magasin' }}</label>
                    <select wire:model="magasinId" id="magasinId" class="{{ $champ }}">
                        <option value="">— Choisir —</option>
                        @foreach ($magasins as $m)
                            <option value="{{ $m->id }}">{{ $m->nom }}</option>
                        @endforeach
                    </select>
                    @error('magasinId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                @if ($formulaire === 'transfert')
                    <div>
                        <label for="magasinDestinationId" class="mb-1 block text-sm font-medium text-stone-700">Vers le magasin</label>
                        <select wire:model="magasinDestinationId" id="magasinDestinationId" class="{{ $champ }}">
                            <option value="">— Choisir —</option>
                            @foreach ($magasins->where('actif', true) as $m)
                                <option value="{{ $m->id }}">{{ $m->nom }}</option>
                            @endforeach
                        </select>
                        @error('magasinDestinationId') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div>
                    <label for="poidsKg" class="mb-1 block text-sm font-medium text-stone-700">{{ $formulaire === 'inventaire' ? 'Poids compté (kg)' : 'Poids (kg)' }}</label>
                    <input wire:model="poidsKg" id="poidsKg" type="text" inputmode="decimal" class="{{ $champ }} text-right">
                    @error('poidsKg') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="dateMouvement" class="mb-1 block text-sm font-medium text-stone-700">Date</label>
                    <input wire:model="dateMouvement" id="dateMouvement" type="date" class="{{ $champ }}">
                    @error('dateMouvement') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="motif" class="mb-1 block text-sm font-medium text-stone-700">Motif {{ $formulaire === 'transfert' ? '(facultatif)' : '' }}</label>
                    <input wire:model="motif" id="motif" type="text" class="{{ $champ }}" placeholder="{{ $formulaire === 'inventaire' ? 'ex. Inventaire mensuel, séchage' : '' }}">
                    @error('motif') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:opacity-60">Enregistrer</button>
                <button type="button" wire:click="annuler" class="rounded-md px-4 py-2 text-sm text-stone-700 hover:bg-stone-100">Annuler</button>
            </div>
        </form>
    @endif

    @if ($aContrePasser !== null)
        <form wire:submit="contrePasser" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-950">Contre-passer le mouvement n° {{ $aContrePasser }}</h2>
            <label for="motifContrePassation" class="mt-2 block text-sm font-medium text-amber-950">Motif</label>
            <input wire:model="motifContrePassation" id="motifContrePassation" type="text" class="mt-1 block w-full rounded-md border border-amber-300 px-3 py-2 focus:outline-none">
            @error('motifContrePassation') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-md bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800">Contre-passer</button>
        </form>
    @endif

    <h2 class="mb-3 font-semibold">Mouvements</h2>
    <div class="mb-8 overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">N°</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Type</th>
                    <th class="px-4 py-3 font-medium">Magasin</th>
                    <th class="px-4 py-3 text-right font-medium">Poids</th>
                    <th class="px-4 py-3 font-medium">Motif</th>
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
                        <td class="px-4 py-3">{{ $m->magasin->nom }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $m->grammes > 0 ? '+' : '' }}{{ \App\Support\Format::kg($m->grammes) }}</td>
                        <td class="px-4 py-3 text-xs">{{ $m->motif }}</td>
                        <td class="px-4 py-3">{{ $m->auteur->nom }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($m->contrePassation === null && ! in_array($m->type->value, ['contre_passation', 'entree_achat'], true))
                                <button type="button" wire:click="preparerContrePassation({{ $m->id }})" class="text-xs text-amber-800 hover:underline">Contre-passer</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-stone-500">Aucun mouvement.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <h2 class="mb-3 font-semibold">Achats du lot</h2>
    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Achat</th>
                    <th class="px-4 py-3 font-medium">Fournisseur</th>
                    <th class="px-4 py-3 text-right font-medium">Poids net</th>
                    <th class="px-4 py-3 text-right font-medium">Humidité</th>
                    <th class="px-4 py-3 text-right font-medium">KOR</th>
                    <th class="px-4 py-3 text-right font-medium">Grainage</th>
                    <th class="px-4 py-3 font-medium">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($achats as $a)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs">{{ $a->reference }}</td>
                        <td class="px-4 py-3">{{ $a->nomFournisseur() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::kg($a->poids_net_g) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $a->humidite_pour_mille === null ? '—' : \App\Support\Mesure::versSaisie($a->humidite_pour_mille, 1).' %' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $a->kor_centieme_lbs === null ? '—' : \App\Support\Mesure::versSaisie($a->kor_centieme_lbs, 2).' lbs' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $a->grainage_noix_kg ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $a->statut->libelle() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-stone-500">Aucun achat dans ce lot.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
