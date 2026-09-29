<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>LY AGRICOLE — Du champ à l'acheteur</title>
        <meta name="description" content="LY AGRICOLE, entreprise agricole ivoirienne : achat bord-champ, stockage et commercialisation d'anacarde, de beurre de karité, de tomate et d'autres produits.">
        <meta name="theme-color" content="#123524">
        <link rel="icon" type="image/png" href="{{ asset('images/logo-yl-agro.png') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script>document.documentElement.classList.add('js');</script>
        <style>
            :root {
                --sable: #e8dcc8;
                --sable-fonce: #dccdb2;
                --sable-clair: #f3ebdc;
                --brun: #34251a;
                --brun-doux: #5c4632;
                --vert: #123524;
                --vert-vif: #1f7a45;
                --or: #f2b632;
            }
            html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
            body.v { background: var(--sable); color: var(--brun); }
            .v-alt { background: var(--sable-fonce); }
            .v-carte { background: var(--sable-clair); border: 1px solid rgba(52, 37, 26, .14); transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease; }
            .v-carte:hover { transform: translateY(-6px); box-shadow: 0 18px 32px -14px rgba(52, 37, 26, .35); border-color: rgba(31, 122, 69, .55); }
            .v-texte-doux { color: var(--brun-doux); }

            /* En-tête : se resserre et prend une ombre quand on défile. */
            .v-entete { background: rgba(232, 220, 200, .85); backdrop-filter: blur(8px); transition: padding .3s ease, box-shadow .3s ease, background .3s ease; }
            .v-entete.est-defile { background: rgba(232, 220, 200, .97); box-shadow: 0 8px 24px -14px rgba(52, 37, 26, .5); }
            .v-entete.est-defile .v-entete-in { padding-top: .35rem; padding-bottom: .35rem; }
            .v-entete-in { transition: padding .3s ease; }
            .v-lien { position: relative; }
            .v-lien::after { content: ''; position: absolute; left: .5rem; right: .5rem; bottom: .15rem; height: 2px; background: var(--vert-vif); transform: scaleX(0); transform-origin: left; transition: transform .3s ease; }
            .v-lien:hover::after { transform: scaleX(1); }

            /* Accueil */
            .v-hero { background: radial-gradient(60rem 32rem at 82% -5%, rgba(242, 182, 50, .38), transparent 60%), radial-gradient(48rem 30rem at -5% 105%, rgba(31, 122, 69, .55), transparent 62%), linear-gradient(160deg, #123524 0%, #1c2a1d 55%, #2b2118 100%); }
            .v-soleil { position: absolute; right: -6rem; top: -6rem; width: 26rem; height: 26rem; border-radius: 9999px; background: radial-gradient(circle, rgba(242, 182, 50, .55), rgba(242, 182, 50, 0) 65%); animation: v-pulse 7s ease-in-out infinite; }
            .v-logo-boite { animation: v-flotte 6s ease-in-out infinite; transition: transform .2s ease-out; will-change: transform; }
            .v-bouton { transition: transform .25s ease, background-color .25s ease, box-shadow .25s ease; }
            .v-bouton:hover { transform: translateY(-2px); box-shadow: 0 12px 22px -12px rgba(0, 0, 0, .55); }
            .v-fleche { display: inline-block; transition: transform .25s ease; }
            .v-bouton:hover .v-fleche { transform: translateX(5px); }

            /* Bandeau défilant */
            .v-bandeau { background: var(--vert); color: #f3ebdc; overflow: hidden; }
            .v-bandeau-piste { display: flex; width: max-content; animation: v-defile 32s linear infinite; }
            .v-bandeau:hover .v-bandeau-piste { animation-play-state: paused; }

            /* Vagues */
            .v-vague { display: block; width: 100%; height: 3.5rem; }

            /* Chaîne */
            .v-etapes { position: relative; }
            .v-ligne { position: absolute; left: 1.35rem; top: 1.5rem; bottom: 1.5rem; width: 3px; background: rgba(52, 37, 26, .15); border-radius: 3px; overflow: hidden; }
            .v-ligne::after { content: ''; position: absolute; inset: 0; background: linear-gradient(var(--vert-vif), var(--or)); transform: scaleY(0); transform-origin: top; transition: transform 1.6s ease-out .2s; }
            .v-etapes.est-visible .v-ligne::after { transform: scaleY(1); }
            .v-pastille { transition: transform .35s cubic-bezier(.3, 1.6, .5, 1), background-color .3s ease; }
            .v-etape:hover .v-pastille { transform: scale(1.18) rotate(-6deg); background: var(--or); color: var(--brun); }

            /* Apparition au défilement : seulement si JavaScript est actif. */
            .js [data-reveal] { opacity: 0; transform: translateY(26px); transition: opacity .8s ease, transform .8s cubic-bezier(.2, .7, .2, 1); transition-delay: var(--d, 0s); }
            .js [data-reveal="gauche"] { transform: translateX(-30px); }
            .js [data-reveal="droite"] { transform: translateX(30px); }
            .js [data-reveal].est-visible { opacity: 1; transform: none; }

            /* Entrée de l'accueil */
            .v-entree { opacity: 0; animation: v-monte .9s cubic-bezier(.2, .7, .2, 1) forwards; animation-delay: var(--d, 0s); }

            @keyframes v-monte { from { opacity: 0; transform: translateY(28px); } to { opacity: 1; transform: none; } }
            @keyframes v-flotte { 0%, 100% { translate: 0 0; } 50% { translate: 0 -12px; } }
            @keyframes v-pulse { 0%, 100% { transform: scale(1); opacity: .85; } 50% { transform: scale(1.12); opacity: 1; } }
            @keyframes v-defile { to { transform: translateX(-50%); } }

            @media (prefers-reduced-motion: reduce) {
                html { scroll-behavior: auto; }
                *, *::before, *::after { animation: none !important; transition: none !important; }
                .js [data-reveal] { opacity: 1; transform: none; }
                .v-entree { opacity: 1; }
                .v-ligne::after { transform: scaleY(1); }
            }
        </style>
    </head>
    <body class="v min-h-screen antialiased">
        <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2">Aller au contenu</a>

        <header id="entete" class="v-entete sticky top-0 z-40 border-b border-[#34251a]/10">
            <div class="v-entete-in mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('accueil') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-yl-agro.png') }}" alt="" width="48" height="48" class="h-12 w-12 rounded-lg bg-white/70 p-0.5">
                    <span class="leading-tight">
                        <span class="block text-base font-semibold tracking-wide">LY AGRICOLE</span>
                        <span class="v-texte-doux block text-xs">Cultiver – Élever – Durer</span>
                    </span>
                </a>

                <nav aria-label="Navigation" class="hidden items-center gap-1 text-sm md:flex">
                    <a href="#filieres" class="v-lien rounded-md px-3 py-1.5">Nos filières</a>
                    <a href="#chaine" class="v-lien rounded-md px-3 py-1.5">Du champ à l'acheteur</a>
                    <a href="#mission" class="v-lien rounded-md px-3 py-1.5">Mission</a>
                    <a href="#contact" class="v-lien rounded-md px-3 py-1.5">Nous trouver</a>
                    @auth
                        <a href="{{ route('tableau-de-bord') }}" class="v-bouton ml-2 rounded-md bg-emerald-800 px-4 py-2 font-medium text-white hover:bg-emerald-900">Mon espace</a>
                    @else
                        <a href="{{ route('login') }}" class="v-bouton ml-2 rounded-md bg-emerald-800 px-4 py-2 font-medium text-white hover:bg-emerald-900">Se connecter</a>
                    @endauth
                </nav>

                <div class="flex items-center gap-2 md:hidden">
                    @auth
                        <a href="{{ route('tableau-de-bord') }}" class="rounded-md bg-emerald-800 px-3 py-2 text-sm font-medium text-white">Mon espace</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md bg-emerald-800 px-3 py-2 text-sm font-medium text-white">Se connecter</a>
                    @endauth
                    <button type="button" id="menu-bouton" aria-expanded="false" aria-controls="menu-mobile" aria-label="Ouvrir le menu"
                        class="rounded-md border border-[#34251a]/25 p-2 transition-colors hover:bg-[#34251a]/10">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" class="h-5 w-5" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </button>
                </div>
            </div>
            <nav id="menu-mobile" aria-label="Navigation mobile" class="grid overflow-hidden px-4 transition-all duration-300 md:hidden" style="grid-template-rows: 0fr; opacity: 0;">
                <div class="min-h-0">
                    <ul class="space-y-1 pb-3 text-sm">
                        <li><a href="#filieres" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Nos filières</a></li>
                        <li><a href="#chaine" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Du champ à l'acheteur</a></li>
                        <li><a href="#mission" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Mission</a></li>
                        <li><a href="#contact" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Nous trouver</a></li>
                    </ul>
                </div>
            </nav>
        </header>

        <main id="contenu">
            {{-- Accueil --}}
            <section class="v-hero relative overflow-hidden text-white">
                <div class="v-soleil" aria-hidden="true"></div>
                <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.5fr_1fr]">
                    <div>
                        <p class="v-entree inline-block rounded-full border border-amber-300/50 px-3 py-1 text-xs font-medium uppercase tracking-widest text-amber-200" style="--d: .05s">Entreprise agricole ivoirienne</p>
                        <h1 class="v-entree mt-5 text-4xl font-semibold leading-[1.08] tracking-tight sm:text-6xl" style="--d: .2s">
                            Du champ<br class="hidden sm:block"> à l'acheteur.
                        </h1>
                        <p class="v-entree mt-6 max-w-xl text-lg leading-relaxed text-emerald-50/90" style="--d: .4s">
                            LY AGRICOLE achète au plus près des producteurs, stocke, et commercialise l'anacarde,
                            le beurre de karité, la tomate et d'autres produits de la terre ivoirienne.
                        </p>
                        <div class="v-entree mt-9 flex flex-wrap gap-3" style="--d: .6s">
                            <a href="#filieres" class="v-bouton rounded-md bg-amber-400 px-6 py-3 font-semibold text-[#123524] hover:bg-amber-300">Nos filières <span class="v-fleche" aria-hidden="true">→</span></a>
                            <a href="#chaine" class="v-bouton rounded-md border border-amber-200/60 px-6 py-3 font-medium text-white hover:bg-white/10">Comment nous travaillons</a>
                        </div>
                    </div>
                    <div class="v-entree flex justify-center lg:justify-end" style="--d: .35s">
                        <div id="logo-boite" class="v-logo-boite rounded-[2rem] bg-[#f3ebdc] p-6 shadow-2xl ring-8 ring-white/10">
                            <img src="{{ asset('images/logo-yl-agro.png') }}" alt="Logo de LY AGRO : un calao devant un soleil levant sur des champs, avec les lettres Y et L" width="200" height="200" class="h-48 w-48 sm:h-60 sm:w-60">
                        </div>
                    </div>
                </div>
            </section>

            {{-- Bandeau des produits --}}
            <div class="v-bandeau" aria-hidden="true">
                <div class="v-bandeau-piste text-sm font-medium uppercase tracking-[0.25em]">
                    @for ($k = 0; $k < 2; $k++)
                        <span class="flex shrink-0 items-center gap-8 px-4 py-3">
                            <span>Anacarde</span><span class="text-amber-300">✦</span>
                            <span>Beurre de karité</span><span class="text-amber-300">✦</span>
                            <span>Tomate</span><span class="text-amber-300">✦</span>
                            <span>Achat bord-champ</span><span class="text-amber-300">✦</span>
                            <span>Stockage</span><span class="text-amber-300">✦</span>
                            <span>Commercialisation</span><span class="text-amber-300">✦</span>
                        </span>
                    @endfor
                </div>
            </div>

            {{-- Filières --}}
            <section id="filieres" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
                <p data-reveal class="text-sm font-medium uppercase tracking-widest text-emerald-800">Nos filières</p>
                <h2 data-reveal style="--d: .1s" class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight">Des produits de la terre ivoirienne, suivis de la parcelle à la vente.</h2>

                <div class="mt-10 grid gap-5 lg:grid-cols-3">
                    <article data-reveal style="--d: .1s" class="v-carte rounded-2xl border-2 !border-emerald-800 p-6" >
                        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Filière principale</p>
                        <h3 class="mt-2 text-2xl font-semibold">Anacarde</h3>
                        <p class="v-texte-doux mt-3 leading-relaxed">
                            Noix de cajou achetées bord-champ, pesées et contrôlées (qualité, humidité), regroupées puis vendues
                            aux exportateurs, aux usines et aux grossistes.
                        </p>
                    </article>
                    <article data-reveal style="--d: .25s" class="v-carte rounded-2xl p-6">
                        <p class="v-texte-doux text-xs font-semibold uppercase tracking-widest">Filière</p>
                        <h3 class="mt-2 text-2xl font-semibold">Beurre de karité</h3>
                        <p class="v-texte-doux mt-3 leading-relaxed">Un produit du terroir, à collecter et à valoriser avec les producteurs de la zone.</p>
                    </article>
                    <article data-reveal style="--d: .4s" class="v-carte rounded-2xl p-6">
                        <p class="v-texte-doux text-xs font-semibold uppercase tracking-widest">Filière</p>
                        <h3 class="mt-2 text-2xl font-semibold">Tomate</h3>
                        <p class="v-texte-doux mt-3 leading-relaxed">Une culture de saison, suivie avec la même rigueur que les filières longues.</p>
                    </article>
                </div>

                <p data-reveal style="--d: .1s" class="v-carte mt-6 rounded-xl px-5 py-4">
                    <span class="font-medium">Et d'autres produits</span>, selon les saisons et les prix du marché : nous nous adaptons à ce que la terre et la demande offrent.
                </p>
            </section>

            <svg class="v-vague text-[#dccdb2]" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true"><path fill="currentColor" d="M0 60V28C180 4 360 4 540 26s360 34 540 12 270-30 360-14v38Z" /></svg>

            {{-- Chaîne --}}
            <section id="chaine" class="v-alt -mt-px">
                <div class="mx-auto max-w-6xl px-4 pb-16 pt-6 sm:px-6 sm:pb-20">
                    <p data-reveal class="text-sm font-medium uppercase tracking-widest text-emerald-800">Comment nous travaillons</p>
                    <h2 data-reveal style="--d: .1s" class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight">Chaque étape est suivie. Chaque kilo, chaque franc est tracé.</h2>

                    @php
                        $etapes = [
                            ['Producteurs partenaires', 'Nos agents rencontrent les producteurs dans les villages et relèvent leurs parcelles.'],
                            ['Avances de campagne', 'Un appui avant la récolte, remboursé en argent ou en kilos livrés. Sans intérêt.'],
                            ['Achat bord-champ', 'Chaque livraison est pesée et contrôlée sur place ; le producteur reçoit une confirmation.'],
                            ['Stockage', 'Les lots sont suivis en magasin, du premier kilo jusqu\'à la vente.'],
                            ['Commercialisation', 'Vente aux exportateurs, usines et grossistes, avec chaque encaissement suivi.'],
                        ];
                    @endphp
                    <ol id="etapes" class="v-etapes mt-12 space-y-6 lg:grid lg:grid-cols-5 lg:gap-5 lg:space-y-0">
                        <span class="v-ligne lg:hidden" aria-hidden="true"></span>
                        @foreach ($etapes as $i => [$titre, $texte])
                            <li data-reveal style="--d: {{ 0.12 * $i }}s" class="v-etape v-carte relative ml-12 rounded-2xl p-5 lg:ml-0">
                                <span class="v-pastille absolute -left-12 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-emerald-800 text-sm font-semibold text-white lg:static" aria-hidden="true">{{ $i + 1 }}</span>
                                <h3 class="mt-0 font-semibold lg:mt-4">{{ $titre }}</h3>
                                <p class="v-texte-doux mt-2 text-sm leading-relaxed">{{ $texte }}</p>
                            </li>
                        @endforeach
                    </ol>

                    <p data-reveal class="v-texte-doux mt-10 max-w-3xl leading-relaxed">
                        Nos équipes de terrain travaillent avec un outil qui fonctionne <strong class="font-medium text-[#34251a]">même sans réseau</strong> :
                        rien ne se perd entre le village et le bureau.
                    </p>
                </div>
            </section>

            <svg class="v-vague -mt-px rotate-180 text-[#dccdb2]" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true"><path fill="currentColor" d="M0 60V28C180 4 360 4 540 26s360 34 540 12 270-30 360-14v38Z" /></svg>

            {{-- Mission --}}
            <section id="mission" class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-16">
                <div class="grid gap-6 lg:grid-cols-2">
                    <div data-reveal="gauche" class="rounded-2xl p-8 text-white" style="background: linear-gradient(140deg, #123524, #1f4d33);">
                        <p class="text-sm font-medium uppercase tracking-widest text-amber-200">Notre mission</p>
                        <p class="mt-3 text-2xl font-semibold leading-snug">
                            Promouvoir et moderniser l'agriculture ivoirienne, et créer des emplois là où les produits sont cultivés.
                        </p>
                    </div>
                    <div data-reveal="droite" class="v-carte rounded-2xl p-8">
                        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">Notre vision</p>
                        <p class="mt-3 text-2xl font-semibold leading-snug">
                            Transformer localement nos produits et bâtir un réseau de commercialisation solide.
                        </p>
                    </div>
                </div>

                <div data-reveal class="v-carte mt-6 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-xl font-semibold">Un groupe, plusieurs activités</h2>
                    <p class="v-texte-doux mt-2 max-w-3xl leading-relaxed">
                        Au-delà des cultures et du négoce, LY AGRICOLE regroupe d'autres activités agricoles :
                        <strong class="font-medium text-[#34251a]">l'élevage</strong> et <strong class="font-medium text-[#34251a]">la pisciculture</strong>.
                        Toutes s'appuient sur les mêmes principes : sérieux, suivi, et durée.
                    </p>
                </div>
            </section>

            {{-- Contact --}}
            <section id="contact" class="v-alt">
                <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 sm:py-16 md:grid-cols-2">
                    <div data-reveal="gauche">
                        <h2 class="text-2xl font-semibold tracking-tight">Nous trouver</h2>
                        <address class="mt-4 not-italic leading-relaxed">
                            <span class="font-medium">LY AGRICOLE</span><br>
                            <span class="v-texte-doux">Siège : Yopougon Gesco, Abidjan<br>Côte d'Ivoire</span>
                        </address>
                    </div>
                    <div data-reveal="droite">
                        <h2 class="text-2xl font-semibold tracking-tight">Producteur, agent, partenaire ?</h2>
                        <p class="v-texte-doux mt-4 leading-relaxed">
                            Les équipes de LY AGRICOLE accèdent à leur espace de gestion avec leur compte.
                        </p>
                        @auth
                            <a href="{{ route('tableau-de-bord') }}" class="v-bouton mt-4 inline-block rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900">Ouvrir mon espace <span class="v-fleche" aria-hidden="true">→</span></a>
                        @else
                            <a href="{{ route('login') }}" class="v-bouton mt-4 inline-block rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900">Accéder à l'espace de gestion <span class="v-fleche" aria-hidden="true">→</span></a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer style="background: #2a1f16; color: #e8dcc8;">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm sm:px-6">
                <p>© {{ date('Y') }} LUNA YEO AGRICOLE SARL — LY AGRICOLE. Cultiver – Élever – Durer.</p>
                <a href="#contenu" class="underline transition-colors hover:text-amber-300">Haut de page ↑</a>
            </div>
        </footer>

        @verbatim
        <script>
            (function () {
                var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                // En-tête : se resserre quand on défile.
                var entete = document.getElementById('entete');
                function defile() { entete.classList.toggle('est-defile', window.scrollY > 24); }
                defile();
                window.addEventListener('scroll', defile, { passive: true });

                // Menu mobile.
                var bouton = document.getElementById('menu-bouton');
                var menu = document.getElementById('menu-mobile');
                function fermer() {
                    menu.style.gridTemplateRows = '0fr'; menu.style.opacity = '0';
                    bouton.setAttribute('aria-expanded', 'false');
                }
                bouton.addEventListener('click', function () {
                    var ouvert = bouton.getAttribute('aria-expanded') === 'true';
                    if (ouvert) { fermer(); return; }
                    menu.style.gridTemplateRows = '1fr'; menu.style.opacity = '1';
                    bouton.setAttribute('aria-expanded', 'true');
                });
                menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', fermer); });

                // Apparition au défilement.
                var cibles = document.querySelectorAll('[data-reveal], #etapes');
                if (reduit || !('IntersectionObserver' in window)) {
                    cibles.forEach(function (c) { c.classList.add('est-visible'); });
                } else {
                    var obs = new IntersectionObserver(function (entrees) {
                        entrees.forEach(function (e) {
                            if (e.isIntersecting) { e.target.classList.add('est-visible'); obs.unobserve(e.target); }
                        });
                    }, { threshold: 0.15, rootMargin: '0px 0px -8% 0px' });
                    cibles.forEach(function (c) { obs.observe(c); });
                }

                // Le logo suit doucement le pointeur (ordinateur seulement).
                var boite = document.getElementById('logo-boite');
                if (!reduit && boite && window.matchMedia('(hover: hover)').matches) {
                    var zone = boite.closest('section');
                    zone.addEventListener('pointermove', function (e) {
                        var r = zone.getBoundingClientRect();
                        var x = (e.clientX - r.left) / r.width - 0.5;
                        var y = (e.clientY - r.top) / r.height - 0.5;
                        boite.style.transform = 'perspective(700px) rotateY(' + (x * 10).toFixed(2) + 'deg) rotateX(' + (-y * 8).toFixed(2) + 'deg)';
                    });
                    zone.addEventListener('pointerleave', function () { boite.style.transform = ''; });
                }
            })();
        </script>
        @endverbatim
    </body>
</html>
