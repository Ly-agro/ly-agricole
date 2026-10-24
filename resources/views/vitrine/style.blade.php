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

            .v-entete { background: rgba(232, 220, 200, .85); backdrop-filter: blur(8px); transition: padding .3s ease, box-shadow .3s ease, background .3s ease; }
            .v-entete.est-defile { background: rgba(232, 220, 200, .97); box-shadow: 0 8px 24px -14px rgba(52, 37, 26, .5); }
            .v-entete.est-defile .v-entete-in { padding-top: .35rem; padding-bottom: .35rem; }
            .v-entete-in { transition: padding .3s ease; }
            .v-lien { position: relative; }
            .v-lien::after { content: ''; position: absolute; left: .5rem; right: .5rem; bottom: .15rem; height: 2px; background: var(--vert-vif); transform: scaleX(0); transform-origin: left; transition: transform .3s ease; }
            .v-lien:hover::after { transform: scaleX(1); }

            .v-hero { background: radial-gradient(60rem 32rem at 82% -5%, rgba(242, 182, 50, .38), transparent 60%), radial-gradient(48rem 30rem at -5% 105%, rgba(31, 122, 69, .55), transparent 62%), linear-gradient(160deg, #123524 0%, #1c2a1d 55%, #2b2118 100%); }
            .v-soleil { position: absolute; right: -6rem; top: -6rem; width: 26rem; height: 26rem; border-radius: 9999px; background: radial-gradient(circle, rgba(242, 182, 50, .55), rgba(242, 182, 50, 0) 65%); animation: v-pulse 7s ease-in-out infinite; }
            .v-logo-boite { animation: v-flotte 6s ease-in-out infinite; transition: transform .2s ease-out; will-change: transform; }
            .v-bouton { transition: transform .25s ease, background-color .25s ease, box-shadow .25s ease; }
            .v-bouton:hover { transform: translateY(-2px); box-shadow: 0 12px 22px -12px rgba(0, 0, 0, .55); }
            .v-fleche { display: inline-block; transition: transform .25s ease; }
            .v-bouton:hover .v-fleche { transform: translateX(5px); }

            .v-bandeau { background: var(--vert); color: #f3ebdc; overflow: hidden; }
            .v-bandeau-piste { display: flex; width: max-content; animation: v-defile 32s linear infinite; }
            .v-bandeau:hover .v-bandeau-piste { animation-play-state: paused; }

            .v-haut { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 50; display: flex; height: 3rem; width: 3rem; align-items: center; justify-content: center; border-radius: 9999px; background: var(--vert); color: #f3ebdc; box-shadow: 0 10px 24px -8px rgba(18, 53, 36, .7); opacity: 0; visibility: hidden; transform: translateY(16px) scale(.9); transition: opacity .3s ease, transform .3s ease, visibility .3s, background-color .25s ease; }
            .v-haut.est-visible { opacity: 1; visibility: visible; transform: none; }
            .v-haut:hover { background: var(--vert-vif); transform: translateY(-3px); }
            .v-haut:focus-visible { outline: 3px solid var(--or); outline-offset: 3px; }
            @media (max-width: 640px) { .v-haut { right: 1rem; bottom: 1rem; } }

            .v-vague { display: block; width: 100%; height: 3.5rem; }

            .v-etapes { position: relative; }
            .v-ligne { position: absolute; left: 1.35rem; top: 1.5rem; bottom: 1.5rem; width: 3px; background: rgba(52, 37, 26, .15); border-radius: 3px; overflow: hidden; }
            .v-ligne::after { content: ''; position: absolute; inset: 0; background: linear-gradient(var(--vert-vif), var(--or)); transform: scaleY(0); transform-origin: top; transition: transform 1.6s ease-out .2s; }
            .v-etapes.est-visible .v-ligne::after { transform: scaleY(1); }
            .v-pastille { transition: transform .35s cubic-bezier(.3, 1.6, .5, 1), background-color .3s ease; }
            .v-etape:hover .v-pastille { transform: scale(1.18) rotate(-6deg); background: var(--or); color: var(--brun); }

            .v-ana { background: radial-gradient(50rem 26rem at 90% 0%, rgba(242, 182, 50, .28), transparent 60%), linear-gradient(165deg, #123524 0%, #173f2a 60%, #2b2118 100%); }
            .v-ana-prix { display: block; background: rgba(255, 255, 255, .08); border: 1px solid rgba(242, 182, 50, .4); transition: background .25s ease, transform .25s ease; }
            .v-ana-prix:hover { background: rgba(255, 255, 255, .14); transform: translateY(-3px); }
            .v-ana-scene { background: #fdf4dc; box-shadow: 0 30px 60px -30px rgba(0, 0, 0, .7); }
            .v-ana-svg { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; transform: scale(1.04); transition: opacity .7s ease, transform .9s ease; }
            .v-ana-svg.est-active { opacity: 1; transform: none; }
            .v-ana-fleche { display: inline-flex; height: 2.75rem; width: 2.75rem; align-items: center; justify-content: center; border-radius: 9999px; background: var(--or); color: var(--vert); font-size: 1.15rem; font-weight: 700; transition: transform .2s ease, background .2s ease; }
            .v-ana-fleche:hover { transform: scale(1.08); background: #f7c95a; }
            .v-ana-fleche:focus-visible, .v-ana-pas:focus-visible { outline: 3px solid var(--or); outline-offset: 3px; }
            .v-ana-pas .v-ana-barre span { width: 0; background: var(--or); }
            .v-ana-pas.est-passe .v-ana-barre span { width: 100%; }
            .v-ana-pas[aria-current="step"] .v-ana-barre span { width: 100%; }
            .v-ana.est-en-lecture .v-ana-pas[aria-current="step"] .v-ana-barre span { animation: ana-remplit var(--ana-duree, 6s) linear forwards; }
            .v-ana-pas[aria-current="step"] span:last-child { color: #fff; font-weight: 600; }
            .v-ana-chemin { scrollbar-width: thin; }
            @keyframes ana-remplit { from { width: 0; } to { width: 100%; } }

            .v-ana-svg * { transform-box: fill-box; }
            .est-active .ana-pluie rect { animation: ana-pluie 1.1s linear infinite; }
            .est-active .ana-pluie rect:nth-child(2n) { animation-delay: .4s; }
            .est-active .ana-pluie rect:nth-child(3n) { animation-delay: .75s; }
            .ana-pousse { transform-origin: 50% 100%; }
            .est-active .ana-pousse { animation: ana-pousse 2.4s cubic-bezier(.2, .8, .2, 1) both; }
            .ana-feuille-g { transform-origin: 100% 50%; } .ana-feuille-d { transform-origin: 0% 50%; }
            .est-active .ana-feuille-g { animation: ana-deplie-g 1.2s 1.6s ease-out both; }
            .est-active .ana-feuille-d { animation: ana-deplie-d 1.2s 1.8s ease-out both; }
            .ana-grandit { transform-box: view-box; transform-origin: 200px 232px; }
            .est-active .ana-grandit { animation: ana-grandit 2.6s cubic-bezier(.2, .8, .2, 1) both, ana-vent 4s 2.6s ease-in-out infinite; }
            .est-active .ana-soleil-doux { animation: v-pulse 4s ease-in-out infinite; }
            .ana-fleurs circle { transform-origin: center; }
            .est-active .ana-fleurs circle { animation: ana-eclot .6s cubic-bezier(.3, 1.6, .5, 1) both; }
            @for ($k = 1; $k <= 8; $k++)
                .est-active .ana-fleurs circle:nth-child({{ $k }}) { animation-delay: {{ 0.25 * $k }}s; }
            @endfor
            .ana-abeille { transform-box: view-box; }
            .est-active .ana-abeille { animation: ana-abeille 5s ease-in-out infinite; }
            .ana-balance { transform-box: view-box; transform-origin: 200px 40px; }
            .est-active .ana-balance { animation: ana-balance 3.2s ease-in-out infinite; }
            .est-active .ana-chute { animation: ana-chute 2.4s cubic-bezier(.5, 0, .9, .6) infinite; }
            .est-active .ana-chute-2 { animation-delay: .8s; } .est-active .ana-chute-3 { animation-delay: 1.6s; }
            .ana-rayons { transform-box: view-box; transform-origin: 320px 70px; }
            .est-active .ana-rayons { animation: ana-tourne 12s linear infinite; }
            .est-active .ana-chaleur path { animation: ana-chaleur 2.4s ease-in infinite; }
            .est-active .ana-chaleur path:nth-child(2) { animation-delay: .8s; } .est-active .ana-chaleur path:nth-child(3) { animation-delay: 1.6s; }
            .ana-aiguille { transform-box: view-box; transform-origin: 200px 74px; }
            .est-active .ana-aiguille { animation: ana-aiguille 2.6s ease-out both; }
            .est-active .ana-sac { animation: ana-sac 2.6s ease-out both; }
            .est-active .ana-fiche { animation: v-monte .7s 2.2s ease-out both; }
            .est-active .ana-empile { animation: ana-empile .7s cubic-bezier(.3, 1.4, .5, 1) both; }
            @for ($k = 1; $k <= 6; $k++)
                .est-active .ana-empile-{{ $k }} { animation-delay: {{ 0.35 * ($k - 1) }}s; }
            @endfor
            .est-active .ana-coque-g { animation: ana-ouvre-g 1.4s .6s ease-in-out both; }
            .est-active .ana-coque-d { animation: ana-ouvre-d 1.4s .6s ease-in-out both; }
            .ana-amande { transform-origin: center; }
            .est-active .ana-amande { animation: ana-amande 1s 1.6s cubic-bezier(.3, 1.5, .5, 1) both; }
            .est-active .ana-etiquette { animation: v-monte .7s 2.2s ease-out both; }

            @keyframes ana-pluie { from { transform: translateY(-40px); opacity: 0; } 20% { opacity: 1; } to { transform: translateY(230px); opacity: .2; } }
            @keyframes ana-pousse { from { transform: scaleY(0); } to { transform: scaleY(1); } }
            @keyframes ana-deplie-g { from { transform: rotate(60deg) scale(0); } to { transform: none; } }
            @keyframes ana-deplie-d { from { transform: rotate(-60deg) scale(0); } to { transform: none; } }
            @keyframes ana-grandit { from { transform: scale(.35); } to { transform: scale(1); } }
            @keyframes ana-vent { 0%, 100% { transform: rotate(0); } 50% { transform: rotate(1.6deg); } }
            @keyframes ana-eclot { from { transform: scale(0); } to { transform: scale(1); } }
            @keyframes ana-abeille { 0% { transform: translate(60px, 200px); } 25% { transform: translate(150px, 110px) rotate(-10deg); } 50% { transform: translate(250px, 90px); } 75% { transform: translate(300px, 170px) rotate(10deg); } 100% { transform: translate(60px, 200px); } }
            @keyframes ana-balance { 0%, 100% { transform: rotate(-7deg); } 50% { transform: rotate(7deg); } }
            @keyframes ana-chute { 0% { transform: translateY(0); opacity: 1; } 70% { transform: translateY(78px); opacity: 1; } 100% { transform: translateY(78px); opacity: 0; } }
            @keyframes ana-tourne { to { transform: rotate(360deg); } }
            @keyframes ana-chaleur { from { transform: translateY(10px); opacity: 0; } 40% { opacity: .9; } to { transform: translateY(-30px); opacity: 0; } }
            @keyframes ana-aiguille { 0% { transform: rotate(-80deg); } 35% { transform: rotate(60deg); } 55% { transform: rotate(10deg); } 75% { transform: rotate(38deg); } 100% { transform: rotate(28deg); } }
            @keyframes ana-sac { 0% { transform: translateY(-14px); } 35% { transform: translateY(10px); } 60% { transform: translateY(2px); } 100% { transform: translateY(5px); } }
            @keyframes ana-empile { from { transform: translateY(-180px); opacity: 0; } 60% { opacity: 1; } to { transform: none; opacity: 1; } }
            @keyframes ana-ouvre-g { to { transform: translate(-56px, 10px) rotate(-14deg); } }
            @keyframes ana-ouvre-d { to { transform: translate(56px, 10px) rotate(14deg); } }
            @keyframes ana-amande { from { transform: scale(.7); } to { transform: scale(1.06); } }

            .js [data-reveal] { opacity: 0; transform: translateY(26px); transition: opacity .8s ease, transform .8s cubic-bezier(.2, .7, .2, 1); transition-delay: var(--d, 0s); }
            .js [data-reveal="gauche"] { transform: translateX(-30px); }
            .js [data-reveal="droite"] { transform: translateX(30px); }
            .js [data-reveal].est-visible { opacity: 1; transform: none; }

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
