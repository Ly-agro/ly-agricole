<x-layouts.app title="Rapports">
    <h1 class="text-xl font-semibold text-stone-900">Rapports</h1>
    <p class="mt-1 text-sm text-stone-500">Chiffres du {{ now()->format('d/m/Y à H:i') }}, recalculés à partir des registres.</p>

    {{-- Les trois questions de la direction, sans aide (livrable de la semaine 10). --}}
    <div class="mt-6 grid gap-4 md:grid-cols-3">
        <a href="{{ route('rapports.voir', 'prets') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-600">
            <p class="text-sm text-stone-500">Combien reste dû ?</p>
            <p id="restant-du" class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Support\Format::fcfa($synthese['restant_du']) }}</p>
            <p class="mt-1 text-xs text-stone-500">
                {{ $synthese['prets_en_cours'] }} prêt(s) en cours
                @if ($synthese['prets_echus'] > 0)
                    — <span class="text-red-700">{{ $synthese['prets_echus'] }} échu(s)</span>
                @endif
            </p>
        </a>
        <a href="{{ route('rapports.voir', 'stock') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-600">
            <p class="text-sm text-stone-500">Combien en stock ?</p>
            @forelse ($synthese['stock'] as $s)
                <p class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Support\Format::kg($s['grammes']) }} <span class="text-sm font-normal text-stone-500">{{ $s['produit'] }}</span></p>
            @empty
                <p class="mt-1 text-2xl font-semibold">0 kg</p>
            @endforelse
        </a>
        <a href="{{ route('rapports.voir', 'caisses') }}" class="rounded-xl border border-stone-200 bg-white p-5 hover:border-emerald-600">
            <p class="text-sm text-stone-500">Combien en caisse ?</p>
            <p id="en-caisse" class="mt-1 text-2xl font-semibold tabular-nums">{{ \App\Support\Format::fcfa($synthese['tresorerie']) }}</p>
            <p class="mt-1 text-xs text-stone-500">caisses, banques et Mobile Money actifs</p>
        </a>
    </div>

    <h2 class="mt-8 text-sm font-medium uppercase tracking-wider text-stone-500">Détail et exports</h2>
    <ul class="mt-3 divide-y divide-stone-100 rounded-xl border border-stone-200 bg-white">
        @foreach ($liste as $cle => $libelle)
            <li class="flex items-center justify-between px-5 py-3">
                <a href="{{ route('rapports.voir', $cle) }}" class="font-medium text-emerald-800 hover:underline">
                    {{ $libelle }}
                    @if ($cle === 'alertes' && $synthese['alertes'] > 0)
                        <span class="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">{{ $synthese['alertes'] }}</span>
                    @endif
                </a>
                <span class="space-x-3 text-sm">
                    <a href="{{ route('rapports.voir', [$cle, 'format' => 'pdf']) }}" target="_blank" class="text-stone-600 hover:underline">PDF</a>
                    <a href="{{ route('rapports.voir', [$cle, 'format' => 'excel']) }}" class="text-stone-600 hover:underline">Excel</a>
                </span>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
