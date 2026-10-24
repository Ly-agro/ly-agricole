<x-layouts.app :title="$tableau->titre">
    <div class="mb-4">
        <a href="{{ route('rapports') }}" class="text-sm text-emerald-800 hover:underline">← Rapports</a>
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-stone-900">{{ $tableau->titre }}</h1>
            <p class="mt-1 text-sm text-stone-500">Au {{ now()->format('d/m/Y à H:i') }}</p>
        </div>
        <div class="flex items-center gap-2">
            @if ($campagnes->isNotEmpty())
                <form method="GET" class="flex items-center gap-2">
                    <select name="campagne" onchange="this.form.submit()" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm">
                        <option value="">Toutes les campagnes</option>
                        @foreach ($campagnes as $c)
                            <option value="{{ $c->id }}" @selected($campagneId === $c->id)>{{ $c->code }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ route('rapports.voir', [$rapport, 'format' => 'pdf', 'campagne' => $campagneId]) }}" target="_blank"
                class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-50">PDF</a>
            <a href="{{ route('rapports.voir', [$rapport, 'format' => 'excel', 'campagne' => $campagneId]) }}"
                class="rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm hover:bg-stone-50">Excel</a>
        </div>
    </div>

    @if ($tableau->note)
        <p class="mt-3 text-sm text-stone-600">{{ $tableau->note }}</p>
    @endif

    <div class="mt-4 overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-stone-50 text-left text-stone-600">
                <tr>
                    @foreach ($tableau->colonnes as [$libelle, $type])
                        <th class="whitespace-nowrap px-4 py-3 font-medium @if (in_array($type, ['fcfa', 'kg', 'pour_mille', 'nombre'], true)) text-right @endif">{{ $libelle }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($tableau->lignes as $ligne)
                    <tr>
                        @foreach ($tableau->colonnes as $i => [$libelle, $type])
                            <td class="px-4 py-2.5 @if (in_array($type, ['fcfa', 'kg', 'pour_mille', 'nombre'], true)) whitespace-nowrap text-right tabular-nums @endif @if (is_int($ligne[$i]) && $ligne[$i] < 0) text-red-700 @endif">
                                {{ \App\Support\Tableau::afficher($ligne[$i], $type) }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($tableau->colonnes) }}" class="px-4 py-8 text-center text-stone-500">Rien à signaler.</td></tr>
                @endforelse
            </tbody>
            @if ($tableau->totaux && $tableau->lignes !== [])
                <tfoot class="border-t-2 border-stone-300 font-semibold">
                    <tr>
                        @foreach ($tableau->colonnes as $i => [$libelle, $type])
                            <td class="whitespace-nowrap px-4 py-3 @if (in_array($type, ['fcfa', 'kg', 'pour_mille', 'nombre'], true)) text-right tabular-nums @endif">
                                {{ $tableau->totaux[$i] === null ? '' : \App\Support\Tableau::afficher($tableau->totaux[$i], $type) }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-layouts.app>
