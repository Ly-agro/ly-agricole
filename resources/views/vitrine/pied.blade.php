        <footer style="background: #2a1f16; color: #e8dcc8;">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-6 text-sm sm:px-6">
                <p>© {{ date('Y') }} LUNA YEO AGRICOLE SARL — LY AGRICOLE. Cultiver – Élever – Durer.</p>
                <p class="flex flex-wrap gap-x-4">
                    <a href="mailto:{{ config('vitrine.contact.email') }}" class="hover:underline">{{ config('vitrine.contact.email') }}</a>
                    <a href="tel:+225{{ config('vitrine.contact.telephone') }}" class="tabular-nums hover:underline">{{ \App\Support\Telephone::afficher(config('vitrine.contact.telephone')) }}</a>
                </p>
            </div>
        </footer>

        <a href="#contenu" id="haut" aria-label="Retour en haut de la page" class="v-haut">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" /></svg>
        </a>
