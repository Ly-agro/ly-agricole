<x-vitrine.page :titre="$actualite->titre" :description="\Illuminate\Support\Str::limit($actualite->contenu, 150)">
    <article class="mx-auto max-w-3xl px-4 py-14 sm:px-6 sm:py-20">
        <a href="{{ route('actualites') }}" class="v-lien text-sm">← Toutes les actualités</a>
        <p class="v-texte-doux mt-6 text-xs font-medium uppercase tracking-widest">{{ $actualite->publie_le->translatedFormat('j F Y') }}</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">{{ $actualite->titre }}</h1>
        <div class="mt-6 whitespace-pre-line text-lg leading-relaxed">{{ $actualite->contenu }}</div>
        @if ($actualite->origine === 'externe' && $actualite->lien_source)
            <p class="v-texte-doux mt-8 text-sm">Source : {{ $actualite->source_nom }} —
                <a href="{{ $actualite->lien_source }}" rel="noopener noreferrer nofollow" target="_blank" class="v-lien underline">lire l'article d'origine</a></p>
        @endif
    </article>
</x-vitrine.page>
