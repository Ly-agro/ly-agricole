<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Ventes</h1>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="filtreStatut" class="mb-1 block text-stone-600">Statut</label>
                <select wire:model.live="filtreStatut" id="filtreStatut" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Tous</option>
                    @foreach ($statuts as $s)
                        <option value="{{ $s->value }}">{{ $s->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            @can('saisir-ventes')
                <a href="{{ route('ventes.nouvelle') }}" class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Nouvelle vente</a>
            @endcan
        </div>
    </div>

    @if ($aRefuser !== null)
        <form wire:submit="refuser" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5">
            <label for="motifRefus" class="block text-sm font-medium text-red-950">Motif du refus</label>
            <input wire:model="motifRefus" id="motifRefus" type="text" class="mt-1 block w-full rounded-md border border-red-300 px-3 py-2 focus:outline-none">
            @error('motifRefus') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Refuser la vente</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Référence</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Acheteur</th>
                    <th class="px-4 py-3 text-right font-medium">Poids net</th>
                    <th class="px-4 py-3 text-right font-medium">Prix</th>
                    <th class="px-4 py-3 text-right font-medium">Montant</th>
                    <th class="px-4 py-3 font-medium">Lot</th>
                    <th class="px-4 py-3 font-medium">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($ventes as $v)
                    <tr wire:key="vente-{{ $v->id }}">
                        <td class="whitespace-nowrap px-4 py-3">
                            <a href="{{ route('ventes.fiche', $v) }}" class="font-mono text-xs text-emerald-800 hover:underline">{{ $v->reference }}</a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $v->date_vente->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $v->acheteur_nom }} <span class="text-xs text-stone-400">({{ $v->type_acheteur->libelle() }})</span></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::kg($v->poids_net_g) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::fcfa($v->prix_kg_fcfa) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Format::fcfa($v->montant_fcfa) }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('lots.fiche', $v->lot) }}" class="text-emerald-800 hover:underline">{{ $v->lot->code }}</a></td>
                        <td class="px-4 py-3">
                            {{ $v->statut->libelle() }}
                            @if ($v->validateur) <span class="block text-xs text-stone-500">{{ $v->statut->value === 'refuse' ? 'refusée' : 'validée' }} par {{ $v->validateur->nom }}</span> @endif
                            @if ($v->motif_refus) <span class="block text-xs text-red-700">{{ $v->motif_refus }}</span> @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            @if ($peutValider && $v->statut->value === 'a_valider')
                                @if ($v->cree_par === $moi)
                                    <span class="text-xs text-stone-500">(à valider par un autre)</span>
                                @else
                                    <button type="button" wire:click="valider('{{ $v->id }}')" wire:confirm="Valider la vente {{ $v->reference }} et sortir {{ \App\Support\Format::kg($v->poids_net_g) }} du stock ?"
                                        class="rounded-md bg-emerald-700 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-800">Valider</button>
                                    <button type="button" wire:click="preparerRefus('{{ $v->id }}')" class="ml-1 rounded-md px-2 py-1 text-xs text-red-800 hover:bg-red-50">Refuser</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-stone-500">Aucune vente.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $ventes->links() }}</div>
</div>
