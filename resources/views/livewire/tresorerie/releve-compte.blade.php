<div>
    <div class="mb-6">
        <a href="{{ route('tresorerie') }}" class="text-sm text-emerald-800 hover:underline">← Trésorerie</a>
        <div class="mt-2 flex flex-wrap items-end justify-between gap-3">
            <h1 class="text-xl font-semibold">{{ $compte->nom }}</h1>
            <p class="text-lg">Solde : <span class="font-semibold tabular-nums" id="solde">{{ \App\Support\Format::fcfa($solde) }}</span></p>
        </div>
    </div>

    @if ($aContrePasser !== null)
        <form wire:submit="contrePasser" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="font-semibold text-amber-950">Contre-passer le mouvement n° {{ $aContrePasser }}</h2>
            <p class="mt-1 text-sm text-amber-900">
                Un mouvement inverse est ajouté ; l'original reste visible. Pour un virement, les deux comptes sont corrigés.
            </p>
            <label for="motif" class="mt-3 block text-sm font-medium text-amber-950">Motif</label>
            <input wire:model="motif" id="motif" type="text" class="mt-1 block w-full rounded-md border border-amber-300 px-3 py-2 focus:outline-none" placeholder="ex. Montant saisi deux fois">
            @error('motif') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="mt-4 flex gap-3">
                <button type="submit" class="rounded-md bg-amber-700 px-4 py-2 text-sm font-medium text-white hover:bg-amber-800">Contre-passer</button>
                <button type="button" wire:click="annuler" class="rounded-md px-4 py-2 text-sm text-stone-700 hover:bg-amber-100">Annuler</button>
            </div>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">N°</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Libellé</th>
                    <th class="px-4 py-3 font-medium">Nature</th>
                    <th class="px-4 py-3 text-right font-medium">Entrée</th>
                    <th class="px-4 py-3 text-right font-medium">Sortie</th>
                    <th class="px-4 py-3 text-right font-medium">Solde après</th>
                    <th class="px-4 py-3 font-medium">Par</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($mouvements as $m)
                    <tr wire:key="mvt-{{ $m->id }}" @class(['text-stone-400 line-through decoration-stone-300' => $m->contrePassation !== null])>
                        <td class="px-4 py-3 tabular-nums">{{ $m->id }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $m->date_operation->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            {{ $m->libelle }}
                            @if ($m->reference_externe) <span class="text-stone-500">(réf. {{ $m->reference_externe }})</span> @endif
                            @if ($m->motif) <span class="block text-xs text-amber-800">Motif : {{ $m->motif }}</span> @endif
                        </td>
                        <td class="px-4 py-3">{{ $m->nature->libelle() }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $m->sens->value === 'entree' ? \App\Support\Format::fcfa($m->montant_fcfa) : '' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ $m->sens->value === 'sortie' ? \App\Support\Format::fcfa($m->montant_fcfa) : '' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::fcfa($soldesApres[$m->id]) }}</td>
                        <td class="px-4 py-3">{{ $m->auteur->nom }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($m->contrePassation === null && $m->nature->value !== 'contre_passation')
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
</div>
