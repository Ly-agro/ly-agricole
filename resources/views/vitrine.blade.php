<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>LY AGRICOLE — Du champ à l'acheteur</title>
        <meta name="description" content="LY AGRICOLE, entreprise agricole ivoirienne : achat bord-champ, stockage et commercialisation d'anacarde, de beurre de karité, de tomate et d'autres produits.">
        <link rel="icon" type="image/png" href="{{ asset('images/logo-yl-agro.png') }}">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-white text-stone-900 antialiased">
        <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2">Aller au contenu</a>

        <header class="sticky top-0 z-40 border-b border-stone-200 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                <a href="{{ route('accueil') }}" class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-yl-agro.png') }}" alt="" width="48" height="48" class="h-12 w-12">
                    <span class="leading-tight">
                        <span class="block text-base font-semibold tracking-wide">LY AGRICOLE</span>
                        <span class="block text-xs text-stone-500">Cultiver – Élever – Durer</span>
                    </span>
                </a>
                <nav aria-label="Navigation" class="flex items-center gap-1 text-sm sm:gap-3">
                    <a href="#filieres" class="hidden rounded-md px-2 py-1.5 text-stone-700 hover:text-emerald-800 md:inline">Nos filières</a>
                    <a href="#chaine" class="hidden rounded-md px-2 py-1.5 text-stone-700 hover:text-emerald-800 md:inline">Du champ à l'acheteur</a>
                    <a href="#mission" class="hidden rounded-md px-2 py-1.5 text-stone-700 hover:text-emerald-800 md:inline">Mission</a>
                    <a href="#contact" class="hidden rounded-md px-2 py-1.5 text-stone-700 hover:text-emerald-800 md:inline">Nous trouver</a>
                    @auth
                        <a href="{{ route('tableau-de-bord') }}" class="rounded-md bg-emerald-800 px-4 py-2 font-medium text-white hover:bg-emerald-900">Mon espace</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md bg-emerald-800 px-4 py-2 font-medium text-white hover:bg-emerald-900">Se connecter</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="contenu">
            {{-- Accueil --}}
            <section class="relative overflow-hidden bg-emerald-950 text-white">
                <div class="absolute inset-0 opacity-30" aria-hidden="true"
                    style="background: radial-gradient(60rem 30rem at 85% 10%, #facc15 0%, transparent 55%), radial-gradient(50rem 30rem at 0% 100%, #16a34a 0%, transparent 60%);"></div>
                <div class="relative mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.5fr_1fr]">
                    <div>
                        <p class="inline-block rounded-full border border-emerald-400/40 px-3 py-1 text-xs font-medium uppercase tracking-widest text-emerald-200">Entreprise agricole ivoirienne</p>
                        <h1 class="mt-5 text-4xl font-semibold leading-[1.1] tracking-tight sm:text-6xl">
                            Du champ<br class="hidden sm:block"> à l'acheteur.
                        </h1>
                        <p class="mt-6 max-w-xl text-lg leading-relaxed text-emerald-50/90">
                            LY AGRICOLE achète au plus près des producteurs, stocke, et commercialise l'anacarde,
                            le beurre de karité, la tomate et d'autres produits de la terre ivoirienne.
                        </p>
                        <div class="mt-9 flex flex-wrap gap-3">
                            <a href="#filieres" class="rounded-md bg-amber-400 px-6 py-3 font-semibold text-emerald-950 hover:bg-amber-300">Nos filières</a>
                            <a href="#chaine" class="rounded-md border border-emerald-300/60 px-6 py-3 font-medium text-white hover:bg-emerald-900">Comment nous travaillons</a>
                        </div>
                    </div>
                    <div class="flex justify-center lg:justify-end">
                        <div class="rounded-[2rem] bg-white p-6 shadow-2xl ring-8 ring-white/10">
                            <img src="{{ asset('images/logo-yl-agro.png') }}" alt="Logo de LY AGRO : un calao devant un soleil levant sur des champs, avec les lettres Y et L" width="200" height="200" class="h-48 w-48 sm:h-60 sm:w-60">
                        </div>
                    </div>
                </div>
            </section>

            {{-- Filières --}}
            <section id="filieres" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
                <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">Nos filières</p>
                <h2 class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight">Des produits de la terre ivoirienne, suivis de la parcelle à la vente.</h2>

                <div class="mt-10 grid gap-5 lg:grid-cols-3">
                    <article class="rounded-2xl border-2 border-emerald-800 bg-emerald-50 p-6 lg:row-span-1">
                        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Filière principale</p>
                        <h3 class="mt-2 text-2xl font-semibold">Anacarde</h3>
                        <p class="mt-3 leading-relaxed text-stone-700">
                            Noix de cajou achetées bord-champ, pesées et contrôlées (qualité, humidité), regroupées puis vendues
                            aux exportateurs, aux usines et aux grossistes.
                        </p>
                    </article>
                    <article class="rounded-2xl border border-stone-200 p-6">
                        <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Filière</p>
                        <h3 class="mt-2 text-2xl font-semibold">Beurre de karité</h3>
                        <p class="mt-3 leading-relaxed text-stone-700">Un produit du terroir, à collecter et à valoriser avec les producteurs de la zone.</p>
                    </article>
                    <article class="rounded-2xl border border-stone-200 p-6">
                        <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">Filière</p>
                        <h3 class="mt-2 text-2xl font-semibold">Tomate</h3>
                        <p class="mt-3 leading-relaxed text-stone-700">Une culture de saison, suivie avec la même rigueur que les filières longues.</p>
                    </article>
                </div>

                <p class="mt-6 rounded-xl bg-stone-50 px-5 py-4 text-stone-700">
                    <span class="font-medium text-stone-900">Et d'autres produits</span>, selon les saisons et les prix du marché : nous nous adaptons à ce que la terre et la demande offrent.
                </p>
            </section>

            {{-- Chaîne --}}
            <section id="chaine" class="bg-stone-50">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
                    <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">Comment nous travaillons</p>
                    <h2 class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight">Chaque étape est suivie. Chaque kilo, chaque franc est tracé.</h2>

                    <ol class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                        @php
                            $etapes = [
                                ['Producteurs partenaires', 'Nos agents rencontrent les producteurs dans les villages et relèvent leurs parcelles.'],
                                ['Avances de campagne', 'Un appui avant la récolte, remboursé en argent ou en kilos livrés. Sans intérêt.'],
                                ['Achat bord-champ', 'Chaque livraison est pesée et contrôlée sur place ; le producteur reçoit une confirmation.'],
                                ['Stockage', 'Les lots sont suivis en magasin, du premier kilo jusqu\'à la vente.'],
                                ['Commercialisation', 'Vente aux exportateurs, usines et grossistes, avec chaque encaissement suivi.'],
                            ];
                        @endphp
                        @foreach ($etapes as $i => [$titre, $texte])
                            <li class="relative rounded-2xl border border-stone-200 bg-white p-5">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-emerald-800 text-sm font-semibold text-white" aria-hidden="true">{{ $i + 1 }}</span>
                                <h3 class="mt-4 font-semibold">{{ $titre }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $texte }}</p>
                            </li>
                        @endforeach
                    </ol>

                    <p class="mt-8 max-w-3xl leading-relaxed text-stone-700">
                        Nos équipes de terrain travaillent avec un outil qui fonctionne <strong class="font-medium text-stone-900">même sans réseau</strong> :
                        rien ne se perd entre le village et le bureau.
                    </p>
                </div>
            </section>

            {{-- Mission --}}
            <section id="mission" class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
                <div class="grid gap-10 lg:grid-cols-2">
                    <div class="rounded-2xl bg-emerald-900 p-8 text-white">
                        <p class="text-sm font-medium uppercase tracking-widest text-emerald-200">Notre mission</p>
                        <p class="mt-3 text-2xl font-semibold leading-snug">
                            Promouvoir et moderniser l'agriculture ivoirienne, et créer des emplois là où les produits sont cultivés.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-stone-200 p-8">
                        <p class="text-sm font-medium uppercase tracking-widest text-emerald-800">Notre vision</p>
                        <p class="mt-3 text-2xl font-semibold leading-snug">
                            Transformer localement nos produits et bâtir un réseau de commercialisation solide.
                        </p>
                    </div>
                </div>

                <div class="mt-10 rounded-2xl bg-stone-50 p-6 sm:p-8">
                    <h2 class="text-xl font-semibold">Un groupe, plusieurs activités</h2>
                    <p class="mt-2 max-w-3xl leading-relaxed text-stone-700">
                        Au-delà des cultures et du négoce, LY AGRICOLE regroupe d'autres activités agricoles :
                        <strong class="font-medium text-stone-900">l'élevage</strong> et <strong class="font-medium text-stone-900">la pisciculture</strong>.
                        Toutes s'appuient sur les mêmes principes : sérieux, suivi, et durée.
                    </p>
                </div>
            </section>

            {{-- Contact --}}
            <section id="contact" class="border-t border-stone-200">
                <div class="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 sm:py-16 md:grid-cols-2">
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">Nous trouver</h2>
                        <address class="mt-4 not-italic leading-relaxed text-stone-700">
                            <span class="font-medium text-stone-900">LY AGRICOLE</span><br>
                            Siège : Yopougon Gesco, Abidjan<br>
                            Côte d'Ivoire
                        </address>
                    </div>
                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">Producteur, agent, partenaire ?</h2>
                        <p class="mt-4 leading-relaxed text-stone-700">
                            Les équipes de LY AGRICOLE accèdent à leur espace de gestion avec leur compte.
                        </p>
                        @auth
                            <a href="{{ route('tableau-de-bord') }}" class="mt-4 inline-block rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900">Ouvrir mon espace</a>
                        @else
                            <a href="{{ route('login') }}" class="mt-4 inline-block rounded-md bg-emerald-800 px-5 py-3 font-medium text-white hover:bg-emerald-900">Accéder à l'espace de gestion</a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-stone-200 bg-stone-50">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm text-stone-500 sm:px-6">
                <p>© {{ date('Y') }} LUNA YEO AGRICOLE SARL — LY AGRICOLE. Cultiver – Élever – Durer.</p>
                <a href="#contenu" class="text-stone-600 underline hover:text-emerald-800">Haut de page</a>
            </div>
        </footer>
    </body>
</html>
