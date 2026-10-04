@php
    // L'anacarde, notre filière phare : de la noix semée à l'amande, en 9 étapes illustrées.
    // Le prix affiché est le dernier prix bord-champ publié (registre des prix), jamais inventé.
    $etapesAnacarde = [
        ['semis', 'La noix semée', 'Mai à juillet', 'Aux premières pluies, on sème directement des noix choisies sur les meilleurs arbres. Une pousse sort de terre en deux à trois semaines.'],
        ['croissance', 'Le jeune anacardier', '3 à 4 ans', 'L\'arbre grandit pendant trois à quatre ans avant sa première vraie récolte. Désherbage, taille et pare-feu le protègent.'],
        ['floraison', 'La floraison', 'Novembre à janvier', 'En saison sèche, l\'arbre se couvre de petites fleurs roses et blanches, visitées par les abeilles.'],
        ['pomme', 'La pomme et sa noix', 'Décembre à février', 'Chaque fleur donne une pomme de cajou, juteuse et colorée. La noix pousse sous la pomme : c\'est elle qui a de la valeur.'],
        ['recolte', 'La récolte', 'Février à mai', 'Les fruits mûrs tombent au sol. On les ramasse chaque jour et on sépare la noix de la pomme.'],
        ['sechage', 'Le séchage', '2 à 3 jours', 'Étalées au soleil sur des bâches, les noix sèchent jusqu\'à moins de 10 % d\'humidité : bien sèches, elles se gardent sans moisir.'],
        ['pesee', 'La pesée et le contrôle', 'Bord-champ', 'Au point de collecte, nos agents pèsent chaque livraison et mesurent la qualité : humidité, KOR (le rendement en amande) et nombre de noix au kilo. Le producteur reçoit aussitôt une confirmation.'],
        ['stockage', 'Le stockage', 'Sacs de 80 kg', 'Les noix rejoignent nos magasins en sacs de jute, lot par lot. Chaque kilo est suivi jusqu\'à la vente.'],
        ['transformation', 'La transformation', 'L\'amande', 'Cuite puis décortiquée, la noix livre son amande blanche : la noix de cajou que l\'on mange. Même la coque sert : on en tire une huile.'],
    ];
@endphp

<section id="anacarde" class="v-ana relative overflow-hidden text-white" aria-labelledby="titre-anacarde">
    <div class="relative mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-2xl">
                <p data-reveal class="text-sm font-medium uppercase tracking-widest text-amber-300">Notre filière phare</p>
                <h2 id="titre-anacarde" data-reveal style="--d: .1s" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">L'anacarde, de la noix à l'amande.</h2>
                <p data-reveal style="--d: .2s" class="mt-3 text-emerald-50/85">La Côte d'Ivoire est le premier producteur mondial de noix de cajou. Suivez le chemin d'une noix, du champ du producteur jusqu'à l'amande.</p>
            </div>
            @if ($prixAnacarde)
                <a data-reveal style="--d: .3s" href="{{ route('prix.evolution', ['produit' => $prixAnacarde['produit']->id]) }}" class="v-ana-prix rounded-2xl px-5 py-4">
                    <span class="block text-xs uppercase tracking-widest text-amber-200">Prix bord-champ</span>
                    <span class="mt-1 block text-3xl font-semibold tabular-nums">{{ \App\Support\Format::entier($prixAnacarde['prix']->prix_kg_fcfa) }} <span class="text-base font-normal text-emerald-50/80">FCFA / kg</span></span>
                    <span class="mt-1 block text-xs text-emerald-50/70">au {{ $prixAnacarde['prix']->date_effet->format('d/m/Y') }} · voir la courbe →</span>
                </a>
            @endif
        </div>

        <div class="mt-10 grid items-center gap-8 lg:grid-cols-[1.15fr_1fr]" data-ana>
            {{-- La scène : une illustration animée par étape, une seule visible à la fois. --}}
            <div class="v-ana-scene relative aspect-[4/3] w-full overflow-hidden rounded-[1.75rem]">
                @include('vitrine.anacarde-scenes')
            </div>

            <div class="min-w-0">
                @foreach ($etapesAnacarde as $i => [$cle, $titre, $quand, $texte])
                    <article class="v-ana-texte" data-ana-texte="{{ $i }}" @if ($i > 0) hidden @endif aria-live="polite">
                        <p class="text-sm font-medium text-amber-300"><span class="tabular-nums">{{ $i + 1 }}</span> / {{ count($etapesAnacarde) }} · {{ $quand }}</p>
                        <h3 class="mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">{{ $titre }}</h3>
                        <p class="mt-3 text-lg leading-relaxed text-emerald-50/90">{{ $texte }}</p>
                    </article>
                @endforeach

                <div class="mt-7 flex items-center gap-3">
                    <button type="button" data-ana-prec class="v-ana-fleche" aria-label="Étape précédente">←</button>
                    <button type="button" data-ana-suiv class="v-ana-fleche" aria-label="Étape suivante">→</button>
                    <button type="button" data-ana-lecture class="ml-1 rounded-full border border-white/25 px-4 py-2 text-sm hover:bg-white/10" aria-pressed="true">Pause</button>
                </div>
            </div>
        </div>

        {{-- Le chemin : chaque étape est cliquable ; la barre montre où l'on en est. --}}
        <ol class="v-ana-chemin mt-10 flex gap-2 overflow-x-auto pb-2" aria-label="Les étapes de l'anacarde">
            @foreach ($etapesAnacarde as $i => [$cle, $titre])
                <li class="min-w-[6.5rem] flex-1">
                    <button type="button" data-ana-aller="{{ $i }}" class="v-ana-pas w-full text-left" aria-current="{{ $i === 0 ? 'step' : 'false' }}">
                        <span class="v-ana-barre block h-1.5 overflow-hidden rounded-full bg-white/15"><span class="block h-full"></span></span>
                        <span class="mt-2 block text-xs leading-snug text-emerald-50/75">{{ $titre }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<script>
    (function () {
        var bloc = document.querySelector('[data-ana]');
        if (!bloc) { return; }
        var section = document.getElementById('anacarde');
        var scenes = section.querySelectorAll('[data-ana-scene]');
        var textes = section.querySelectorAll('[data-ana-texte]');
        var pas = section.querySelectorAll('[data-ana-aller]');
        var lecture = section.querySelector('[data-ana-lecture]');
        var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var DUREE = 6000, n = scenes.length, courant = 0, minuteur = null, enLecture = !reduit, visible = false;

        function montrer(i) {
            courant = (i + n) % n;
            scenes.forEach(function (s, k) { s.classList.toggle('est-active', k === courant); });
            textes.forEach(function (t, k) { t.hidden = k !== courant; });
            pas.forEach(function (p, k) {
                p.setAttribute('aria-current', k === courant ? 'step' : 'false');
                p.classList.toggle('est-passe', k < courant);
            });
            relancer();
        }
        function relancer() {
            clearTimeout(minuteur);
            section.style.setProperty('--ana-duree', DUREE + 'ms');
            section.classList.toggle('est-en-lecture', enLecture && visible);
            // Relance l'animation de la barre de l'étape courante.
            var barre = pas[courant].querySelector('.v-ana-barre span');
            barre.style.animation = 'none'; void barre.offsetWidth; barre.style.animation = '';
            if (enLecture && visible) { minuteur = setTimeout(function () { montrer(courant + 1); }, DUREE); }
        }
        function basculer(etat) {
            enLecture = etat;
            lecture.textContent = enLecture ? 'Pause' : 'Lecture';
            lecture.setAttribute('aria-pressed', enLecture ? 'true' : 'false');
            relancer();
        }
        section.querySelector('[data-ana-prec]').addEventListener('click', function () { montrer(courant - 1); });
        section.querySelector('[data-ana-suiv]').addEventListener('click', function () { montrer(courant + 1); });
        pas.forEach(function (p) { p.addEventListener('click', function () { montrer(parseInt(p.dataset.anaAller, 10)); }); });
        lecture.addEventListener('click', function () { basculer(!enLecture); });
        if (reduit) { basculer(false); }
        // Défile seulement quand la section est à l'écran.
        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (e) { visible = e[0].isIntersecting; relancer(); }, { threshold: 0.35 }).observe(bloc);
        } else { visible = true; }
        montrer(0);
    })();
</script>
