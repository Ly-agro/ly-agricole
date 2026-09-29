@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Fiabilité des producteurs</h1>
            <p class="mt-1 max-w-2xl text-sm text-stone-600">
                Historique de remboursement des producteurs qui ont reçu un prêt, et plafond proposé pour la campagne suivante.
                Ce sont des repères : <strong class="font-medium">la direction décide</strong>.
            </p>
        </div>
        <div class="text-sm">
            <label for="recherche" class="mb-1 block text-stone-600">Producteur</label>
            <input wire:model.live.debounce.300ms="recherche" id="recherche" type="search" placeholder="Nom, prénoms ou code"
                class="rounded-md border border-stone-300 px-3 py-1.5 focus:border-emerald-600 focus:outline-none">
        </div>
    </div>

    @if ($producteurs->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun producteur à afficher.</p>
            <p class="mt-1 text-sm text-stone-600">Seuls les producteurs qui ont reçu un prêt (versé ou soldé) apparaissent ici.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Producteur</th>
                        <th class="px-4 py-2 text-right">Prêts</th>
                        <th class="px-4 py-2 text-right">Remis</th>
                        <th class="px-4 py-2 text-right">Remboursé</th>
                        <th class="px-4 py-2 text-right">Retard</th>
                        <th class="px-4 py-2 text-right">Plafond proposé</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($producteurs as $p)
                        @php($s = $fiches[$p->id]['synthese'])
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('fiabilite.fiche', $p) }}" class="text-emerald-800 hover:underline">{{ $p->prenoms }} {{ $p->nom }}</a></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $s['nb_prets'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($s['total_remis']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $s['taux_pour_mille'] === null ? '—' : intdiv($s['taux_pour_mille'], 10).','.($s['taux_pour_mille'] % 10).' %' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums {{ $s['nb_en_retard'] > 0 ? 'font-medium text-red-700' : '' }}">{{ $s['retard_max_jours'] > 0 ? $s['retard_max_jours'].' j' : '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $fiches[$p->id]['plafond_propose'] === null ? 'Aucun' : Format::fcfa($fiches[$p->id]['plafond_propose']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $producteurs->links() }}</div>
    @endif
</div>
