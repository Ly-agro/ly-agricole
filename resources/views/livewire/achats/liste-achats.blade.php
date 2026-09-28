<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Achats bord-champ</h1>
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
            @can('saisir-achats')
                <a href="{{ route('achats.nouveau') }}" class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Nouvel achat</a>
            @endcan
        </div>
    </div>

    @if ($statut !== '')
        <p class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ $statut }}</p>
    @endif
    @error('action')
        <p class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">{{ $message }}</p>
    @enderror

    @if ($aRefuser !== null)
        <form wire:submit="refuser" class="mb-6 rounded-xl border border-red-200 bg-red-50 p-5">
            <label for="motifRefus" class="block text-sm font-medium text-red-950">Motif du refus</label>
            <input wire:model="motifRefus" id="motifRefus" type="text" class="mt-1 block w-full rounded-md border border-red-300 px-3 py-2 focus:outline-none">
            @error('motifRefus') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="submit" class="mt-3 rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Refuser l'achat</button>
        </form>
    @endif

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Référence</th>
                    <th class="px-4 py-3 font-medium">Date</th>
                    <th class="px-4 py-3 font-medium">Fournisseur</th>
                    <th class="px-4 py-3 text-right font-medium">Poids net</th>
                    <th class="px-4 py-3 text-right font-medium">Prix</th>
                    <th class="px-4 py-3 text-right font-medium">Valeur</th>
                    <th class="px-4 py-3 font-medium">Prêt</th>
                    <th class="px-4 py-3 text-right font-medium">Espèces</th>
                    <th class="px-4 py-3 font-medium">Lot</th>
                    <th class="px-4 py-3 font-medium">Statut</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($achats as $a)
                    <tr wire:key="achat-{{ $a->id }}">
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $a->reference }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $a->date_achat->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $a->nomFournisseur() }} <span class="text-xs text-stone-400">({{ $a->fournisseur_type->libelle() }})</span></td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::kg($a->poids_net_g) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::fcfa($a->prix_kg_fcfa) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ \App\Support\Format::fcfa($a->montant_fcfa) }}</td>
                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($a->pret)
                                <a href="{{ route('prets.fiche', $a->pret) }}" class="text-emerald-800 hover:underline">{{ $a->pret->reference }}</a>
                                <span class="block text-xs text-stone-500">{{ \App\Support\Format::kg($a->grammes_rembourses) }} retenus</span>
                            @else — @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Format::fcfa($a->montant_especes_fcfa) }}</td>
                        <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('lots.fiche', $a->lot) }}" class="text-emerald-800 hover:underline">{{ $a->lot->code }}</a></td>
                        <td class="px-4 py-3">
                            {{ $a->statut->libelle() }}
                            @if ($a->validateur) <span class="block text-xs text-stone-500">{{ $a->statut->value === 'refuse' ? 'refusé' : 'validé' }} par {{ $a->validateur->nom }}</span> @endif
                            @if ($a->motif_refus) <span class="block text-xs text-red-700">{{ $a->motif_refus }}</span> @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right">
                            <a href="{{ route('achats.bon', $a) }}" target="_blank" class="mr-1 rounded-md px-2 py-1 text-xs text-emerald-800 hover:bg-emerald-50">Bon PDF</a>
                            @if ($peutValider && $a->statut->value === 'a_valider')
                                @if ($a->cree_par === $moi)
                                    <span class="text-xs text-stone-500">(à valider par un autre)</span>
                                @else
                                    <button type="button" wire:click="valider('{{ $a->id }}')" wire:confirm="Valider l'achat {{ $a->reference }} et payer {{ \App\Support\Format::fcfa($a->montant_especes_fcfa) }} ?"
                                        class="rounded-md bg-emerald-700 px-2 py-1 text-xs font-medium text-white hover:bg-emerald-800">Valider</button>
                                    <button type="button" wire:click="preparerRefus('{{ $a->id }}')" class="ml-1 rounded-md px-2 py-1 text-xs text-red-800 hover:bg-red-50">Refuser</button>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="px-4 py-8 text-center text-stone-500">Aucun achat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $achats->links() }}</div>
</div>
