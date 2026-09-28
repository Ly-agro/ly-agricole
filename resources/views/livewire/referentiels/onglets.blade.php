{{-- Onglets des référentiels : seulement ceux que l'utilisateur a le droit d'ouvrir. --}}
<nav class="mb-6 flex flex-wrap gap-1 border-b border-stone-200 text-sm">
    @foreach (\App\Livewire\Referentiels\EcranReferentiel::onglets() as $route => $onglet)
        @can($onglet['droit'])
            <a href="{{ route($route) }}"
                @class([
                    '-mb-px border-b-2 px-3 py-2',
                    'border-emerald-700 font-medium text-emerald-900' => $routePage === $route,
                    'border-transparent text-stone-600 hover:text-stone-900' => $routePage !== $route,
                ])>
                {{ $onglet['titre'] }}
            </a>
        @endcan
    @endforeach
</nav>
