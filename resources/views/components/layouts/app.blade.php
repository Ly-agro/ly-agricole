<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-900 antialiased">
        <header class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route('tableau-de-bord') }}" class="font-semibold tracking-wide text-emerald-800">
                        LY AGRICOLE
                    </a>

                    {{-- Chaque lien n'apparaît qu'avec le droit correspondant ; la route le revérifie. --}}
                    <nav class="flex items-center gap-1 text-sm">
                        @can('voir-producteurs')
                            <a href="{{ route('producteurs') }}"
                                @class([
                                    'rounded-md px-3 py-1.5',
                                    'bg-emerald-50 text-emerald-900' => request()->routeIs('producteurs*'),
                                    'text-stone-700 hover:bg-stone-100' => ! request()->routeIs('producteurs*'),
                                ])>
                                Producteurs
                            </a>
                        @endcan

                        @can('gerer-utilisateurs')
                            <a href="{{ route('utilisateurs') }}"
                                @class([
                                    'rounded-md px-3 py-1.5',
                                    'bg-emerald-50 text-emerald-900' => request()->routeIs('utilisateurs'),
                                    'text-stone-700 hover:bg-stone-100' => ! request()->routeIs('utilisateurs'),
                                ])>
                                Utilisateurs
                            </a>
                        @endcan

                        @can('gerer-tresorerie')
                            <a href="{{ route('tresorerie') }}"
                                @class([
                                    'rounded-md px-3 py-1.5',
                                    'bg-emerald-50 text-emerald-900' => request()->routeIs('tresorerie*'),
                                    'text-stone-700 hover:bg-stone-100' => ! request()->routeIs('tresorerie*'),
                                ])>
                                Trésorerie
                            </a>
                        @endcan

                        @canany(['saisir-depenses', 'valider-depenses'])
                            <a href="{{ route('depenses') }}"
                                @class([
                                    'rounded-md px-3 py-1.5',
                                    'bg-emerald-50 text-emerald-900' => request()->routeIs('depenses*'),
                                    'text-stone-700 hover:bg-stone-100' => ! request()->routeIs('depenses*'),
                                ])>
                                Dépenses
                            </a>
                        @endcanany

                        @canany(['gerer-referentiels', 'gerer-campagnes', 'gerer-parametres', 'gerer-tresorerie'])
                            <a href="{{ route('referentiels') }}"
                                @class([
                                    'rounded-md px-3 py-1.5',
                                    'bg-emerald-50 text-emerald-900' => request()->routeIs('referentiels*'),
                                    'text-stone-700 hover:bg-stone-100' => ! request()->routeIs('referentiels*'),
                                ])>
                                Référentiels
                            </a>
                        @endcanany

                        @can('voir-journal')
                            <a href="{{ route('journal') }}"
                                @class([
                                    'rounded-md px-3 py-1.5',
                                    'bg-emerald-50 text-emerald-900' => request()->routeIs('journal'),
                                    'text-stone-700 hover:bg-stone-100' => ! request()->routeIs('journal'),
                                ])>
                                Journal
                            </a>
                        @endcan
                    </nav>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <span class="text-stone-600">
                        {{ auth()->user()->nom }}
                        <span class="text-stone-400">· {{ auth()->user()->role?->libelle() ?? 'Aucun rôle' }}</span>
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md px-3 py-1.5 text-stone-700 hover:bg-stone-100">
                            Se déconnecter
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
