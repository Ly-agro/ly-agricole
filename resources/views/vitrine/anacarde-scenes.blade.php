{{-- Illustrations de l'anacarde (SVG, animées en CSS quand l'étape est active : voir vitrine/style). --}}
<svg width="0" height="0" class="absolute" aria-hidden="true" focusable="false">
    <defs>
        <linearGradient id="ana-ciel" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fbe3a6" /><stop offset="1" stop-color="#fdf4dc" /></linearGradient>
        <linearGradient id="ana-pomme" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f6c12e" /><stop offset=".55" stop-color="#ef7d22" /><stop offset="1" stop-color="#d23a24" /></linearGradient>
        <linearGradient id="ana-sol" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7a5434" /><stop offset="1" stop-color="#5b3c23" /></linearGradient>
        <symbol id="ana-noix" viewBox="0 0 44 30"><path d="M4 15C4 5 16 1 24 4c6 3 6 8 12 8 6 0 8 8 2 12-8 6-34 5-34-9Z" /></symbol>
        <symbol id="ana-amande" viewBox="0 0 44 30"><path d="M6 15C6 7 16 3 23 6c5 2 5 7 11 7 5 0 7 6 2 10-7 5-30 4-30-8Z" /></symbol>
        <symbol id="ana-feuille" viewBox="0 0 40 16"><path d="M0 8C10 0 30 0 40 8 30 16 10 16 0 8Z" /></symbol>
        <g id="ana-arbre">
            <path d="M196 232c2-30 0-52-6-74h20c-6 22-8 44-6 74Z" fill="#6b4a2f" />
            <g fill="#2f8a4e">
                <circle cx="200" cy="120" r="52" /><circle cx="160" cy="140" r="38" /><circle cx="242" cy="138" r="40" />
                <circle cx="182" cy="96" r="34" /><circle cx="222" cy="98" r="32" />
            </g>
            <g fill="#3fa461" opacity=".8"><circle cx="186" cy="104" r="16" /><circle cx="232" cy="124" r="14" /><circle cx="160" cy="132" r="12" /></g>
        </g>
    </defs>
</svg>

{{-- 1. La noix semée : la pluie tombe, une pousse sort de terre. --}}
<svg data-ana-scene="0" class="v-ana-svg est-active" viewBox="0 0 400 300" role="img" aria-label="Une noix de cajou dans la terre, d'où sort une jeune pousse sous la pluie">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <g class="ana-pluie" fill="#7fb3d5">
        <rect x="60" y="20" width="3" height="12" rx="1.5" /><rect x="120" y="0" width="3" height="12" rx="1.5" /><rect x="250" y="30" width="3" height="12" rx="1.5" />
        <rect x="310" y="8" width="3" height="12" rx="1.5" /><rect x="180" y="40" width="3" height="12" rx="1.5" /><rect x="350" y="50" width="3" height="12" rx="1.5" />
    </g>
    <rect y="228" width="400" height="72" fill="url(#ana-sol)" />
    <use href="#ana-noix" x="178" y="244" width="44" height="30" fill="#8b8a52" />
    <g class="ana-pousse">
        <path d="M200 248V178" stroke="#3a8f52" stroke-width="5" stroke-linecap="round" fill="none" />
        <use class="ana-feuille-g" href="#ana-feuille" x="160" y="170" width="40" height="16" fill="#3fa461" />
        <use class="ana-feuille-d" href="#ana-feuille" x="200" y="164" width="40" height="16" fill="#2f8a4e" />
    </g>
</svg>

{{-- 2. Le jeune anacardier : il grandit et se balance au vent. --}}
<svg data-ana-scene="1" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Un jeune anacardier qui grandit">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <circle class="ana-soleil-doux" cx="330" cy="60" r="28" fill="#f2b632" />
    <rect y="228" width="400" height="72" fill="url(#ana-sol)" />
    <g class="ana-grandit"><use href="#ana-arbre" /></g>
    <g fill="#4b7a3a"><path d="M40 232c6-14 10-14 16 0Z" /><path d="M330 232c6-12 9-12 14 0Z" /><path d="M90 232c4-9 7-9 10 0Z" /></g>
</svg>

{{-- 3. La floraison : les fleurs s'ouvrent, une abeille passe. --}}
<svg data-ana-scene="2" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="L'anacardier en fleurs, visité par une abeille">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <rect y="228" width="400" height="72" fill="url(#ana-sol)" />
    <use href="#ana-arbre" />
    <g class="ana-fleurs">
        <circle cx="168" cy="110" r="6" fill="#f7b8c8" /><circle cx="214" cy="84" r="6" fill="#fff" /><circle cx="246" cy="132" r="6" fill="#f7b8c8" />
        <circle cx="150" cy="150" r="6" fill="#fff" /><circle cx="196" cy="140" r="6" fill="#f39bb4" /><circle cx="232" cy="104" r="6" fill="#fff" />
        <circle cx="186" cy="80" r="6" fill="#f39bb4" /><circle cx="262" cy="150" r="6" fill="#fff" />
    </g>
    <g class="ana-abeille">
        <ellipse cx="0" cy="0" rx="9" ry="6" fill="#f2b632" /><path d="M-3-6v12M3-6v12" stroke="#34251a" stroke-width="2.5" />
        <ellipse cx="-2" cy="-8" rx="5" ry="3.5" fill="#fff" opacity=".85" />
    </g>
</svg>

{{-- 4. La pomme et sa noix : le fruit se balance sous sa branche. --}}
<svg data-ana-scene="3" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Une pomme de cajou rouge et jaune, avec sa noix accrochée dessous">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <path d="M0 40C90 30 200 46 400 30" stroke="#6b4a2f" stroke-width="10" stroke-linecap="round" fill="none" />
    <use href="#ana-feuille" x="60" y="40" width="70" height="28" fill="#2f8a4e" transform="rotate(18 95 54)" />
    <use href="#ana-feuille" x="270" y="26" width="70" height="28" fill="#3fa461" transform="rotate(-14 305 40)" />
    <g class="ana-balance">
        <path d="M200 40v26" stroke="#5a7a3a" stroke-width="4" />
        <path d="M200 64c30 0 40 26 37 56-3 36-14 62-37 62s-34-26-37-62c-3-30 7-56 37-56Z" fill="url(#ana-pomme)" />
        <ellipse cx="186" cy="96" rx="8" ry="16" fill="#fff" opacity=".25" />
        <use href="#ana-noix" x="174" y="176" width="54" height="36" fill="#8b8a52" transform="rotate(8 200 194)" />
    </g>
</svg>

{{-- 5. La récolte : les fruits tombent, on remplit le panier. --}}
<svg data-ana-scene="4" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Des noix de cajou tombent de l'arbre et sont ramassées dans un panier">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <rect y="228" width="400" height="72" fill="url(#ana-sol)" />
    <g transform="translate(-70 0)"><use href="#ana-arbre" /></g>
    <use class="ana-chute ana-chute-1" href="#ana-noix" x="110" y="150" width="26" height="18" fill="#8b8a52" />
    <use class="ana-chute ana-chute-2" href="#ana-noix" x="150" y="140" width="26" height="18" fill="#8b8a52" />
    <use class="ana-chute ana-chute-3" href="#ana-noix" x="180" y="160" width="26" height="18" fill="#8b8a52" />
    <g transform="translate(270 186)">
        <path d="M0 10h90l-10 46H10Z" fill="#c48a3f" /><path d="M0 10h90" stroke="#8d5e26" stroke-width="5" />
        <path d="M14 22h62M18 34h54M22 46h46" stroke="#a87132" stroke-width="2" />
        <use href="#ana-noix" x="16" y="-6" width="26" height="18" fill="#8b8a52" /><use href="#ana-noix" x="40" y="-10" width="26" height="18" fill="#9a8f55" /><use href="#ana-noix" x="56" y="-4" width="26" height="18" fill="#8b8a52" />
    </g>
</svg>

{{-- 6. Le séchage : le soleil tourne, la chaleur monte des noix étalées. --}}
<svg data-ana-scene="5" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Des noix étalées sur une bâche sèchent au soleil">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <g class="ana-rayons" transform="translate(320 70)" stroke="#f2b632" stroke-width="5" stroke-linecap="round">
        <path d="M0-52v-14M0 52v14M-52 0h-14M52 0h14M-37-37l-10-10M37 37l10 10M-37 37l-10 10M37-37l10-10" />
    </g>
    <circle cx="320" cy="70" r="34" fill="#f2b632" />
    <rect y="228" width="400" height="72" fill="url(#ana-sol)" />
    <path d="M30 236h340l-20 34H50Z" fill="#e9dcc0" />
    <g fill="#9a7b4f">
        @foreach ([[70, 240], [110, 246], [150, 240], [190, 248], [230, 240], [270, 246], [310, 240], [90, 254], [130, 258], [170, 254], [210, 258], [250, 254], [290, 258]] as [$x, $y])
            <use href="#ana-noix" x="{{ $x }}" y="{{ $y }}" width="24" height="16" />
        @endforeach
    </g>
    <g class="ana-chaleur" stroke="#e0a14a" stroke-width="3" fill="none" stroke-linecap="round">
        <path d="M120 222c-6-8 6-14 0-22s6-14 0-22" /><path d="M200 222c-6-8 6-14 0-22s6-14 0-22" /><path d="M280 222c-6-8 6-14 0-22s6-14 0-22" />
    </g>
</svg>

{{-- 7. La pesée : l'aiguille oscille puis se fixe ; la qualité est notée. --}}
<svg data-ana-scene="6" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Un sac de noix pesé sur une balance, avec le contrôle de qualité">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <rect y="248" width="400" height="52" fill="url(#ana-sol)" />
    <path d="M120 248 200 30l80 218" stroke="#5c4632" stroke-width="8" fill="none" stroke-linejoin="round" />
    <circle cx="200" cy="74" r="30" fill="#fff" stroke="#34251a" stroke-width="4" />
    <path d="M178 74a22 22 0 0 1 44 0" stroke="#d8cbb3" stroke-width="3" fill="none" />
    <line class="ana-aiguille" x1="200" y1="74" x2="200" y2="52" stroke="#d23a24" stroke-width="3" stroke-linecap="round" />
    <path d="M200 104v26" stroke="#5c4632" stroke-width="4" />
    <g class="ana-sac">
        <path d="M168 134h64l10 92c0 10-12 16-42 16s-42-6-42-16Z" fill="#c9a66b" />
        <path d="M168 134h64" stroke="#8d6a3a" stroke-width="5" />
        <text x="200" y="196" text-anchor="middle" font-size="18" font-weight="700" fill="#5c4632">80 kg</text>
    </g>
    <g class="ana-fiche" transform="translate(286 120)">
        <rect width="94" height="74" rx="10" fill="#fff" stroke="#d8cbb3" />
        <text x="12" y="24" font-size="12" fill="#5c4632">Humidité 8 %</text>
        <text x="12" y="44" font-size="12" fill="#5c4632">KOR 48</text>
        <text x="12" y="64" font-size="12" fill="#1f7a45" font-weight="700">✓ Validé</text>
    </g>
</svg>

{{-- 8. Le stockage : les sacs arrivent et s'empilent au magasin. --}}
<svg data-ana-scene="7" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Des sacs de noix empilés dans un magasin">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <path d="M40 120 200 50l160 70v130H40Z" fill="#efe2c8" stroke="#8d6a3a" stroke-width="5" stroke-linejoin="round" />
    <path d="M40 120 200 50l160 70" stroke="#5c4632" stroke-width="9" fill="none" stroke-linejoin="round" stroke-linecap="round" />
    <rect y="250" width="400" height="50" fill="url(#ana-sol)" />
    @foreach ([[120, 210, 1], [180, 210, 2], [240, 210, 3], [150, 172, 4], [210, 172, 5], [180, 134, 6]] as [$x, $y, $k])
        <g class="ana-empile ana-empile-{{ $k }}">
            <rect x="{{ $x - 26 }}" y="{{ $y }}" width="52" height="38" rx="12" fill="#c9a66b" stroke="#8d6a3a" stroke-width="2" />
            <text x="{{ $x }}" y="{{ $y + 25 }}" text-anchor="middle" font-size="12" font-weight="700" fill="#6b4a2f">LY</text>
        </g>
    @endforeach
</svg>

{{-- 9. La transformation : la coque s'ouvre, l'amande apparaît. --}}
<svg data-ana-scene="8" class="v-ana-svg" viewBox="0 0 400 300" role="img" aria-label="Une noix décortiquée laisse apparaître l'amande de cajou">
    <rect width="400" height="300" fill="url(#ana-ciel)" />
    <ellipse cx="200" cy="236" rx="140" ry="18" fill="#e9dcc0" />
    <use class="ana-amande" href="#ana-amande" x="140" y="130" width="120" height="82" fill="#fff6e4" stroke="#e6cfa2" stroke-width="1.5" />
    <g class="ana-coque-g"><path d="M128 172c0-34 40-50 72-38v80c-36 6-72-6-72-42Z" fill="#9a7b4f" /></g>
    <g class="ana-coque-d"><path d="M200 134c22 8 26 22 44 22 22 0 30 28 8 44-12 9-30 14-52 14Z" fill="#8a6c42" /></g>
    <text class="ana-etiquette" x="200" y="276" text-anchor="middle" font-size="16" font-weight="700" fill="#5c4632">L'amande de cajou</text>
</svg>
