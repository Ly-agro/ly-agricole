@php use App\Support\Format; @endphp
<x-vitrine.page titre="Évolution des prix bord-champ" description="Évolution des prix bord-champ affichés par LY AGRICOLE, par produit, campagne et période.">
    <section class="mx-auto max-w-5xl px-4 py-14 sm:px-6 sm:py-20">
        <a href="{{ route('accueil') }}#prix" class="v-lien text-sm">← Les prix du moment</a>
        <p class="mt-6 text-sm font-medium uppercase tracking-widest text-emerald-800">Prix bord-champ</p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight">Les prix, campagne après campagne</h1>
        <p class="v-texte-doux mt-2 max-w-2xl">D'abord le tableau d'ensemble de toutes nos cultures sur les sept dernières campagnes, puis, plus bas, la courbe détaillée de chaque produit. Chaque prix est daté et sourcé. Ces prix sont indicatifs : ils ne remplacent pas le prix officiel fixé pour la campagne.</p>

        {{-- Tableau d'ensemble : une ligne par culture, une colonne par campagne (la plus récente à droite). --}}
        <div class="mt-8">
            <h2 class="text-xl font-semibold">Toutes les cultures, {{ count($tableau['campagnes']) > 1 ? 'les '.count($tableau['campagnes']).' dernières campagnes' : 'la campagne connue' }}</h2>
            <p class="v-texte-doux mt-1 text-sm">Dernier prix publié de chaque campagne, en FCFA par kg, avec l'écart par rapport à la campagne précédente de la culture. Une case « — » : prix pas encore relevé. Les colonnes sont des années de campagne, d'octobre à septembre : la campagne de l'anacarde ouverte en février 2024 figure dans « 2023-2024 ». Quand un prix change en cours de campagne (campagne intermédiaire), c'est le dernier prix qui est indiqué.</p>
            @if (count($tableau['campagnes']) === 0)
                <p class="v-carte mt-4 rounded-2xl p-6">Aucune campagne commencée pour le moment : le tableau se remplira avec les campagnes et leurs prix.</p>
            @else
                <div class="v-carte mt-4 overflow-x-auto rounded-2xl">
                    <table class="w-full min-w-[640px] text-left text-sm" data-tableau-cultures>
                        <caption class="sr-only">Dernier prix par culture et par campagne</caption>
                        <thead>
                            <tr class="v-texte-doux text-xs">
                                <th scope="col" class="sticky left-0 z-10 bg-[#f3ebdc] px-4 py-3 font-medium">Culture</th>
                                @foreach ($tableau['campagnes'] as $code)
                                    <th scope="col" class="px-3 py-3 text-right font-medium">{{ $code }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tableau['lignes'] as $ligne)
                                <tr class="border-t border-[#34251a]/10 {{ $ligne['connu'] ? '' : 'v-texte-doux' }}">
                                    <th scope="row" class="sticky left-0 z-10 bg-[#f3ebdc] px-4 py-2 font-medium">
                                        @if ($ligne['connu'])
                                            <a href="{{ route('prix.evolution', ['produit' => $ligne['produit']->id]) }}" class="underline decoration-dotted">{{ $ligne['produit']->nom }}</a>
                                        @else
                                            {{ $ligne['produit']->nom }}
                                        @endif
                                    </th>
                                    @foreach ($tableau['campagnes'] as $code)
                                        @php($case = $ligne['cases'][$code])
                                        <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                                            @if ($case === null)
                                                <span aria-label="prix non relevé">—</span>
                                            @else
                                                <span class="font-medium">{{ Format::entier($case['prix']) }}</span>
                                                @if ($case['ecart'] !== null && $case['ecart'] !== 0)
                                                    <span class="ml-1 text-xs {{ $case['ecart'] > 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ $case['ecart'] > 0 ? '▲' : '▼' }} {{ Format::entier(abs($case['ecart'])) }}</span>
                                                @endif
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <h2 class="mt-12 text-xl font-semibold">Courbe détaillée par produit</h2>
        <p class="v-texte-doux mt-1 text-sm">Le prix reste le même jusqu'au changement suivant.</p>

        @if ($produits->isEmpty())
            <p class="v-carte mt-8 rounded-2xl p-6">Aucun prix publié pour le moment.</p>
        @else
            {{-- Filtres : une seule ligne, au-dessus des graphiques. --}}
            <form method="GET" action="{{ route('prix.evolution') }}" class="v-carte mt-8 flex flex-wrap items-end gap-4 rounded-2xl p-4 text-sm">
                <div>
                    <label for="produit" class="mb-1 block text-xs font-medium">Produit</label>
                    <select name="produit" id="produit" class="rounded-md border border-stone-300 bg-white px-3 py-2">
                        <option value="">Tous les produits</option>
                        @foreach ($produits as $p)
                            <option value="{{ $p->id }}" @selected($produitChoisi?->id === $p->id)>{{ $p->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="periode" class="mb-1 block text-xs font-medium">Période</label>
                    <select name="periode" id="periode" class="rounded-md border border-stone-300 bg-white px-3 py-2">
                        <option value="tout" @selected($periode === 'tout')>Toute la période</option>
                        <option value="6m" @selected($periode === '6m')>6 derniers mois</option>
                        <option value="12m" @selected($periode === '12m')>12 derniers mois</option>
                        @foreach ($campagnes as $c)
                            <option value="campagne-{{ $c->id }}" @selected($periode === 'campagne-'.$c->id)>Campagne {{ $c->produit->nom }} {{ $c->code }}</option>
                        @endforeach
                        <option value="perso" @selected($periode === 'perso')>Dates personnalisées</option>
                    </select>
                </div>
                <div>
                    <label for="du" class="mb-1 block text-xs font-medium">Du</label>
                    <input type="date" name="du" id="du" value="{{ $du }}" class="rounded-md border border-stone-300 bg-white px-3 py-2">
                </div>
                <div>
                    <label for="au" class="mb-1 block text-xs font-medium">Au</label>
                    <input type="date" name="au" id="au" value="{{ $au }}" class="rounded-md border border-stone-300 bg-white px-3 py-2">
                </div>
                <button type="submit" class="v-bouton rounded-md bg-emerald-800 px-4 py-2 font-medium text-white hover:bg-emerald-900">Afficher</button>
                <p class="v-texte-doux w-full text-xs">Les dates ne servent qu'avec « Dates personnalisées ».</p>
            </form>

            <div class="mt-8 space-y-8">
                @foreach ($graphiques as $g)
                    @php($c = $g['courbe'])
                    @php($r = $g['serie']['resume'])
                    <article class="v-carte rounded-2xl p-5 sm:p-6" aria-labelledby="titre-{{ $g['produit']->id }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="titre-{{ $g['produit']->id }}" class="text-xl font-semibold">{{ $g['produit']->nom }}</h2>
                            <p class="v-texte-doux text-xs">du {{ $g['serie']['debut']->format('d/m/Y') }} au {{ $g['serie']['fin']->format('d/m/Y') }} · FCFA par kilo</p>
                        </div>

                        @if ($c === null)
                            <p class="v-texte-doux mt-4">Aucun prix publié sur cette période.</p>
                        @else
                            <p class="mt-2 text-sm">
                                <span class="tabular-nums font-semibold">{{ Format::entier($r['premier']) }}</span> →
                                <span class="tabular-nums font-semibold">{{ Format::entier($r['dernier']) }}</span> FCFA/kg
                                @if ($r['variation'] !== 0)
                                    <span class="font-medium {{ $r['variation'] > 0 ? 'text-emerald-700' : 'text-red-700' }}">({{ $r['variation'] > 0 ? '▲ +' : '▼ −' }}{{ Format::entier(abs($r['variation'])) }})</span>
                                @endif
                                <span class="v-texte-doux">· plus bas {{ Format::entier($r['min']) }} · plus haut {{ Format::entier($r['max']) }} · {{ $r['changements'] }} changement{{ $r['changements'] > 1 ? 's' : '' }}</span>
                            </p>

                            <div class="relative mt-4" data-courbe>
                                <svg viewBox="0 0 {{ $c['largeur'] }} {{ $c['hauteur'] }}" class="w-full" role="img"
                                    aria-label="Évolution du prix {{ $g['produit']->nom }} : de {{ Format::entier($r['premier']) }} à {{ Format::entier($r['dernier']) }} FCFA par kilo, du {{ $g['serie']['debut']->format('d/m/Y') }} au {{ $g['serie']['fin']->format('d/m/Y') }}. Le tableau ci-dessous donne toutes les valeurs.">
                                    @foreach ($c['graduations_y'] as $grad)
                                        <line x1="{{ $c['zone']['gauche'] }}" x2="{{ $c['zone']['droite'] }}" y1="{{ $grad['y'] }}" y2="{{ $grad['y'] }}" stroke="#34251a" stroke-opacity="0.12" stroke-width="1" />
                                        <text x="{{ $c['zone']['gauche'] - 10 }}" y="{{ $grad['y'] + 4 }}" text-anchor="end" font-size="11" fill="#5c4632">{{ Format::entier($grad['valeur']) }}</text>
                                    @endforeach
                                    @foreach ($c['graduations_x'] as $grad)
                                        <text x="{{ $grad['x'] }}" y="{{ $c['zone']['bas'] + 20 }}" text-anchor="middle" font-size="11" fill="#5c4632">{{ $grad['date']->format('d/m/y') }}</text>
                                    @endforeach

                                    <path d="{{ $c['chemin'] }}" fill="none" stroke="#1f7a45" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                                    @foreach ($c['marqueurs'] as $m)
                                        @php($pt = $g['serie']['points'][$m['index']])
                                        {{-- Marque de 10 px, cerclée de la couleur du fond ; le prix déjà en vigueur au début est creux. --}}
                                        <circle cx="{{ $m['x'] }}" cy="{{ $m['y'] }}" r="5" fill="{{ $pt['report'] ? '#f3ebdc' : '#1f7a45' }}" stroke="{{ $pt['report'] ? '#1f7a45' : '#f3ebdc' }}" stroke-width="2" pointer-events="none" />
                                        <circle cx="{{ $m['x'] }}" cy="{{ $m['y'] }}" r="14" fill="transparent" tabindex="0" style="cursor: pointer; outline: none"
                                            data-x="{{ $m['x'] }}" data-y="{{ $m['y'] }}"
                                            data-date="{{ $pt['date']->format('d/m/Y') }}" data-prix="{{ Format::entier($pt['prix']) }} FCFA / kg"
                                            data-source="{{ $pt['report'] ? 'Prix déjà en vigueur au début de la période — '.$pt['source'] : $pt['source'] }}"
                                            aria-label="{{ $pt['date']->format('d/m/Y') }} : {{ Format::entier($pt['prix']) }} FCFA par kilo, source {{ $pt['source'] }}" />
                                    @endforeach

                                    {{-- Étiquette directe du dernier prix, à droite de la courbe. --}}
                                    <text x="{{ $c['fin']['x'] + 10 }}" y="{{ $c['fin']['y'] + 4 }}" font-size="12" font-weight="600" fill="#34251a">{{ Format::entier($r['dernier']) }}</text>
                                </svg>
                                <div data-info-bulle role="status" class="pointer-events-none absolute z-10 hidden max-w-[16rem] rounded-lg border border-[#34251a]/20 bg-[#fbf6ec] px-3 py-2 text-xs shadow-lg"></div>
                            </div>

                            <details class="mt-3 text-sm">
                                <summary class="v-lien inline cursor-pointer">Voir les valeurs en tableau</summary>
                                <table class="mt-2 w-full text-left text-xs">
                                    <thead><tr class="v-texte-doux"><th class="py-1 pr-3 font-medium">Date d'effet</th><th class="py-1 pr-3 text-right font-medium">Prix (FCFA/kg)</th><th class="py-1 font-medium">Source</th></tr></thead>
                                    <tbody>
                                        @foreach ($g['serie']['points'] as $pt)
                                            <tr class="border-t border-[#34251a]/10">
                                                <td class="py-1 pr-3 tabular-nums">{{ $pt['date']->format('d/m/Y') }}@if ($pt['report']) <span class="v-texte-doux">(déjà en vigueur)</span>@endif</td>
                                                <td class="py-1 pr-3 text-right tabular-nums">{{ Format::entier($pt['prix']) }}</td>
                                                <td class="py-1">@if ($pt['url'])<a href="{{ $pt['url'] }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $pt['source'] }}</a>@else{{ $pt['source'] }}@endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </details>
                        @endif

                        @if (count($g['campagnes']) > 0)
                            <div class="mt-5">
                                <h3 class="text-sm font-semibold">Les {{ count($g['campagnes']) > 1 ? count($g['campagnes']).' dernières campagnes' : 'campagnes connues' }}, une à une</h3>
                                <div class="mt-2 overflow-x-auto">
                                    <table class="w-full text-left text-xs">
                                        <thead><tr class="v-texte-doux"><th class="py-1 pr-3 font-medium">Campagne</th><th class="py-1 pr-3 text-right font-medium">Début</th><th class="py-1 pr-3 text-right font-medium">Fin</th><th class="py-1 pr-3 text-right font-medium">Plus bas</th><th class="py-1 pr-3 text-right font-medium">Plus haut</th><th class="py-1 pr-3 text-right font-medium">Évolution pendant</th><th class="py-1 text-right font-medium">Depuis la précédente</th></tr></thead>
                                        <tbody>
                                            @foreach ($g['campagnes'] as $l)
                                                <tr class="border-t border-[#34251a]/10">
                                                    <td class="py-1 pr-3"><a href="{{ route('prix.evolution', ['produit' => $g['produit']->id, 'periode' => 'campagne-'.$l['campagne']->id]) }}" class="underline">{{ $l['campagne']->code }}</a></td>
                                                    <td class="py-1 pr-3 text-right tabular-nums">{{ Format::entier($l['resume']['premier']) }}</td>
                                                    <td class="py-1 pr-3 text-right tabular-nums">{{ Format::entier($l['resume']['dernier']) }}</td>
                                                    <td class="py-1 pr-3 text-right tabular-nums">{{ Format::entier($l['resume']['min']) }}</td>
                                                    <td class="py-1 pr-3 text-right tabular-nums">{{ Format::entier($l['resume']['max']) }}</td>
                                                    <td class="py-1 pr-3 text-right tabular-nums {{ $l['resume']['variation'] > 0 ? 'text-emerald-700' : ($l['resume']['variation'] < 0 ? 'text-red-700' : '') }}">{{ $l['resume']['variation'] === 0 ? '—' : (($l['resume']['variation'] > 0 ? '+' : '−').Format::entier(abs($l['resume']['variation']))) }}</td>
                                                    @php($dp = $l['depuis_precedente'])
                                                    <td class="py-1 text-right tabular-nums {{ $dp === null ? '' : ($dp > 0 ? 'text-emerald-700' : ($dp < 0 ? 'text-red-700' : '')) }}">{{ $dp === null ? '—' : ($dp === 0 ? '=' : (($dp > 0 ? '▲ +' : '▼ −').Format::entier(abs($dp)))) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @verbatim
    <script>
        (function () {
            // Info-bulle au survol ou au focus d'une valeur ; le clavier (Tab) marche aussi.
            document.querySelectorAll('[data-courbe]').forEach(function (bloc) {
                var bulle = bloc.querySelector('[data-info-bulle]');
                var svg = bloc.querySelector('svg');
                function montrer(cible) {
                    bulle.innerHTML = '<strong class="block">' + cible.dataset.prix + '</strong><span class="block">' + cible.dataset.date + '</span><span class="block opacity-80"></span>';
                    bulle.querySelector('span.opacity-80').textContent = cible.dataset.source;
                    var r = svg.getBoundingClientRect();
                    var echelle = r.width / svg.viewBox.baseVal.width;
                    var x = parseFloat(cible.dataset.x) * echelle;
                    var y = parseFloat(cible.dataset.y) * echelle;
                    bulle.classList.remove('hidden');
                    var l = bulle.offsetWidth;
                    bulle.style.left = Math.max(4, Math.min(x - l / 2, r.width - l - 4)) + 'px';
                    bulle.style.top = Math.max(0, y - bulle.offsetHeight - 14) + 'px';
                }
                function cacher() { bulle.classList.add('hidden'); }
                bloc.querySelectorAll('circle[data-prix]').forEach(function (c) {
                    c.addEventListener('pointerenter', function () { montrer(c); });
                    c.addEventListener('pointerleave', cacher);
                    c.addEventListener('focus', function () { montrer(c); });
                    c.addEventListener('blur', cacher);
                });
            });
        })();
    </script>
    @endverbatim
</x-vitrine.page>
