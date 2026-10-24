@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <a href="{{ route('fiabilite') }}" class="text-sm text-emerald-800 hover:underline">← Fiabilité des producteurs</a>
        <h1 class="mt-1 text-xl font-semibold">Groupes de producteurs</h1>
        <p class="mt-1 max-w-2xl text-sm text-stone-600">Exposition et retards de chaque groupe, pour la caution solidaire. Les groupes se créent dans Producteurs › Groupes.</p>
    </div>

    @if ($groupes->isEmpty())
        <div class="rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center">
            <p class="font-medium">Aucun groupe.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Groupe</th>
                        <th class="px-4 py-2">Village</th>
                        <th class="px-4 py-2 text-right">Membres</th>
                        <th class="px-4 py-2 text-right">Remis</th>
                        <th class="px-4 py-2 text-right">Restant dû</th>
                        <th class="px-4 py-2 text-right">Membres en retard</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($groupes as $g)
                        @php($s = $situations[$g->id])
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('fiabilite.groupe', $g) }}" class="text-emerald-800 hover:underline">{{ $g->nom }}</a></td>
                            <td class="px-4 py-2">{{ $g->village->nom }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $g->membres_count }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($s['totaux']['remis']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($s['totaux']['restant_du']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums {{ $s['totaux']['membres_en_retard'] > 0 ? 'font-medium text-red-700' : '' }}">{{ $s['totaux']['membres_en_retard'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div>{{ $groupes->links() }}</div>
    @endif
</div>
