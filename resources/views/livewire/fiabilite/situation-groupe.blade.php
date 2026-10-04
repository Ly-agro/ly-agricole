@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <a href="{{ route('fiabilite.groupes') }}" class="text-sm text-emerald-800 hover:underline">← Groupes</a>
        <h1 class="mt-1 text-xl font-semibold">Groupe {{ $groupe->nom }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ $groupe->village->nom }} · règle de caution solidaire : <strong class="font-medium">{{ $situation['regle']->libelle() }}</strong></p>
    </div>

    @php($t = $situation['totaux'])
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-xs text-stone-500">Remis aux membres</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($t['remis']) }}</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-xs text-stone-500">Remboursé</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $t['taux_pour_mille'] === null ? '—' : intdiv($t['taux_pour_mille'], 10).','.($t['taux_pour_mille'] % 10).' %' }}</p>
            <p class="mt-1 text-xs text-stone-500">{{ Format::fcfa($t['rembourse']) }}</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-xs text-stone-500">Restant dû (exposition du groupe)</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ Format::fcfa($t['restant_du']) }}</p>
        </div>
        <div class="rounded-xl border p-4 {{ $t['membres_en_retard'] > 0 ? 'border-red-200 bg-red-50' : 'border-stone-200 bg-white' }}">
            <p class="text-xs text-stone-500">Membres en retard</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $t['membres_en_retard'] }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs text-stone-500">
                <tr>
                    <th class="px-4 py-2">Membre</th>
                    <th class="px-4 py-2 text-right">Prêts</th>
                    <th class="px-4 py-2 text-right">Remis</th>
                    <th class="px-4 py-2 text-right">Remboursé</th>
                    <th class="px-4 py-2 text-right">Restant dû</th>
                    <th class="px-4 py-2 text-right">Retard</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($situation['membres'] as $m)
                    <tr>
                        <td class="px-4 py-2"><a href="{{ route('fiabilite.fiche', $m['producteur']) }}" class="text-emerald-800 hover:underline">{{ $m['producteur']->prenoms }} {{ $m['producteur']->nom }}</a></td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $m['nb_prets'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($m['remis']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $m['taux_pour_mille'] === null ? '—' : intdiv($m['taux_pour_mille'], 10).','.($m['taux_pour_mille'] % 10).' %' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($m['restant_du']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums {{ $m['nb_en_retard'] > 0 ? 'font-medium text-red-700' : '' }}">{{ $m['retard_max_jours'] > 0 ? $m['retard_max_jours'].' j' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-stone-500">Ce groupe n'a aucun membre.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
