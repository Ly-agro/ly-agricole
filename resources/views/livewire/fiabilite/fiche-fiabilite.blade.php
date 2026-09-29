@php use App\Support\Format; @endphp

<div class="space-y-6">
    <div>
        <a href="{{ route('fiabilite') }}" class="text-sm text-emerald-800 hover:underline">← Fiabilité des producteurs</a>
        <h1 class="mt-1 text-xl font-semibold">{{ $producteur->prenoms }} {{ $producteur->nom }}</h1>
        <p class="mt-1 text-sm text-stone-600">Historique de remboursement et plafond proposé. <strong class="font-medium">La direction décide.</strong></p>
    </div>

    @php($s = $fiche['synthese'])
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-xs text-stone-500">Prêts versés</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $s['nb_prets'] }}</p>
            <p class="mt-1 text-xs text-stone-500">Remis : {{ Format::fcfa($s['total_remis']) }}</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-xs text-stone-500">Remboursé</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $s['taux_pour_mille'] === null ? '—' : intdiv($s['taux_pour_mille'], 10).','.($s['taux_pour_mille'] % 10).' %' }}</p>
            <p class="mt-1 text-xs text-stone-500">{{ Format::fcfa($s['total_rembourse']) }}</p>
        </div>
        <div class="rounded-xl border border-stone-200 bg-white p-4">
            <p class="text-xs text-stone-500">Soldés à l'échéance ou avant</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $s['nb_soldes_a_temps'] }} / {{ $s['nb_prets'] }}</p>
        </div>
        <div class="rounded-xl border p-4 {{ $s['nb_en_retard'] > 0 ? 'border-red-200 bg-red-50' : 'border-stone-200 bg-white' }}">
            <p class="text-xs text-stone-500">Prêts en retard</p>
            <p class="mt-1 text-lg font-semibold tabular-nums">{{ $s['nb_en_retard'] }}</p>
            <p class="mt-1 text-xs text-stone-500">Plus long retard : {{ $s['retard_max_jours'] > 0 ? $s['retard_max_jours'].' j' : 'aucun' }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
        <p class="text-sm text-emerald-900">Plafond proposé pour la campagne suivante</p>
        <p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-950">{{ $fiche['plafond_propose'] === null ? 'Aucun plafond proposé' : Format::fcfa($fiche['plafond_propose']) }}</p>
        <p class="mt-2 text-sm text-emerald-900">{{ $fiche['raison'] }}</p>
        <p class="mt-2 text-xs text-emerald-800">Proposition indicative : elle n'accorde ni ne refuse aucun prêt. La règle est à valider par la direction.</p>
    </div>

    @if ($fiche['prets'] !== [])
        <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 text-left text-xs text-stone-500">
                    <tr>
                        <th class="px-4 py-2">Prêt</th>
                        <th class="px-4 py-2">Campagne</th>
                        <th class="px-4 py-2">Échéance</th>
                        <th class="px-4 py-2 text-right">Remis</th>
                        <th class="px-4 py-2 text-right">Remboursé</th>
                        <th class="px-4 py-2">Situation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach ($fiche['prets'] as $l)
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('prets.fiche', $l['pret']) }}" class="text-emerald-800 hover:underline">{{ $l['pret']->reference }}</a></td>
                            <td class="px-4 py-2">{{ $l['pret']->campagne->produit->nom }} · {{ $l['pret']->campagne->code }}</td>
                            <td class="px-4 py-2 tabular-nums">{{ $l['pret']->echeance->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ Format::fcfa($l['remis']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ intdiv($l['taux_pour_mille'], 10).','.($l['taux_pour_mille'] % 10).' %' }}</td>
                            <td class="px-4 py-2 text-xs">
                                @if ($l['a_temps'])
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800">Soldé à temps</span>
                                @elseif ($l['solde'])
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-800">Soldé avec {{ $l['retard_jours'] }} j de retard</span>
                                @elseif ($l['retard_jours'] > 0)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-red-800">En retard de {{ $l['retard_jours'] }} j</span>
                                @else
                                    <span class="rounded-full bg-stone-100 px-2 py-0.5 text-stone-700">En cours</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <p class="text-sm"><a href="{{ route('rendements.producteur', $producteur) }}" class="text-emerald-800 hover:underline">Voir l'évolution de son rendement →</a></p>
</div>
