<x-vitrine.page titre="Actualités" description="Les actualités de LY AGRICOLE.">
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 sm:py-20">
        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">Actualités</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Les nouvelles de LY AGRICOLE</h1>

        @if ($actualites->isEmpty())
            <p class="v-carte mt-8 rounded-2xl p-6">Aucune actualité pour le moment.</p>
        @else
            <ul class="mt-8 space-y-5">
                @foreach ($actualites as $a)
                    <li>
                        <a href="{{ route('actualites.voir', $a) }}" class="v-carte block rounded-2xl p-6">
                            <p class="v-texte-doux text-xs font-medium uppercase tracking-widest">{{ $a->publie_le->translatedFormat('j F Y') }}</p>
                            <h2 class="mt-1 text-xl font-semibold">{{ $a->titre }}</h2>
                            <p class="v-texte-doux mt-2 leading-relaxed">{{ \Illuminate\Support\Str::limit($a->contenu, 220) }}</p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-vitrine.page>
