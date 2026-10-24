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
                    <a href="{{ $ancre ?? '' }}#filieres" class="v-lien rounded-md px-3 py-1.5">Nos filières</a>
                    <a href="{{ $ancre ?? '' }}#chaine" class="v-lien rounded-md px-3 py-1.5">Du champ à l'acheteur</a>
                    <a href="{{ $ancre ?? '' }}#prix" class="v-lien rounded-md px-3 py-1.5">Prix</a>
                    <a href="{{ $ancre ?? '' }}#actualites" class="v-lien rounded-md px-3 py-1.5">Actualités</a>
                    <a href="{{ $ancre ?? '' }}#mission" class="v-lien rounded-md px-3 py-1.5">Mission</a>
                    <a href="{{ $ancre ?? '' }}#contact" class="v-lien rounded-md px-3 py-1.5">Nous trouver</a>
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
                        <li><a href="{{ $ancre ?? '' }}#filieres" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Nos filières</a></li>
                        <li><a href="{{ $ancre ?? '' }}#chaine" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Du champ à l'acheteur</a></li>
                        <li><a href="{{ $ancre ?? '' }}#prix" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Prix</a></li>
                        <li><a href="{{ $ancre ?? '' }}#actualites" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Actualités</a></li>
                        <li><a href="{{ $ancre ?? '' }}#mission" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Mission</a></li>
                        <li><a href="{{ $ancre ?? '' }}#contact" class="block rounded-md px-3 py-2 hover:bg-[#34251a]/10">Nous trouver</a></li>
                    </ul>
                </div>
            </nav>
        </header>
