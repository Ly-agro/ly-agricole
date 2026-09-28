<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-xl font-semibold">Prêts de campagne</h1>
        <div class="flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label for="filtreCampagne" class="mb-1 block text-stone-600">Campagne</label>
                <select wire:model.live="filtreCampagne" id="filtreCampagne" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Toutes</option>
                    @foreach ($campagnes as $c)
                        <option value="{{ $c->id }}">{{ $c->produit->nom }} {{ $c->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filtreStatut" class="mb-1 block text-stone-600">Statut</label>
                <select wire:model.live="filtreStatut" id="filtreStatut" class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
                    <option value="">Tous</option>
                    @foreach ($statuts as $s)
                        <option value="{{ $s->value }}">{{ $s->libelle() }}</option>
                    @endforeach
                </select>
            </div>
            @can('saisir-prets')
                <a href="{{ route('prets.nouveau') }}" class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-800">Nouvelle demande</a>
            @endcan
        </div>
    </div>

    <dl class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <dt class="text-sm text-stone-500">Demandes en attente</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums" id="total-demandes">{{ $totaux['demandes'] }} · {{ \App\Support\Format::fcfa($totaux['montantDemande']) }}</dd>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <dt class="text-sm text-stone-500">Prêts accordés</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums" id="total-accordes">{{ $totaux['accordes'] }} · {{ \App\Support\Format::fcfa($totaux['montantAccorde']) }}</dd>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <dt class="text-sm text-stone-500">Remis (argent + intrants)</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums" id="total-decaisse">{{ \App\Support\Format::fcfa($totaux['decaisse']) }}</dd>
            <dd class="text-xs text-stone-500">reste à remettre {{ \App\Support\Format::fcfa($totaux['montantAccorde'] - $totaux['decaisse']) }}</dd>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4 shadow-sm">
            <dt class="text-sm text-stone-500">Kilos attendus (estimation)</dt>
            <dd class="mt-1 text-lg font-semibold tabular-nums" id="total-kilos">{{ \App\Support\Format::kg($totaux['grammesAttendus']) }}</dd>
            <dd class="text-xs text-stone-500">prêts avec prix de référence</dd>
        </div>
    </dl>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    <th class="px-4 py-3 font-medium">Référence</th>
                    <th class="px-4 py-3 font-medium">Producteur</th>
                    <th class="px-4 py-3 font-medium">Campagne</th>
                    <th class="px-4 py-3 text-right font-medium">Montant</th>
                    <th class="px-4 py-3 font-medium">Forme</th>
                    <th class="px-4 py-3 font-medium">Échéance</th>
                    <th class="px-4 py-3 font-medium">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($prets as $p)
                    <tr wire:key="pret-{{ $p->id }}">
                        <td class="whitespace-nowrap px-4 py-3"><a href="{{ route('prets.fiche', $p) }}" class="font-mono text-xs font-medium text-emerald-800 hover:underline">{{ $p->reference }}</a></td>
                        <td class="px-4 py-3">{{ $p->producteur->nomComplet() }} <span class="text-stone-400">({{ $p->producteur->village->nom }})</span></td>
                        <td class="px-4 py-3">{{ $p->campagne->produit->nom }} {{ $p->campagne->code }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums">{{ \App\Support\Format::fcfa($p->montant_fcfa) }}</td>
                        <td class="px-4 py-3">{{ $p->forme->libelle() }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $p->echeance->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            {{ $p->statut->libelle() }}
                            @if ($p->statut->value === 'demande')
                                <span class="block text-xs text-amber-700">{{ $p->validations->count() }}/{{ $p->validations_requises }} validation(s)</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-500">Aucun prêt.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $prets->links() }}</div>
</div>
