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

            /* Bouton flottant « haut de page » */
            .v-haut { position: fixed; right: 1.25rem; bottom: 1.25rem; z-index: 50; display: flex; height: 3rem; width: 3rem; align-items: center; justify-content: center; border-radius: 9999px; background: var(--vert); color: #f3ebdc; box-shadow: 0 10px 24px -8px rgba(18, 53, 36, .7); opacity: 0; visibility: hidden; transform: translateY(16px) scale(.9); transition: opacity .3s ease, transform .3s ease, visibility .3s, background-color .25s ease; }
            .v-haut.est-visible { opacity: 1; visibility: visible; transform: none; }
            .v-haut:hover { background: var(--vert-vif); transform: translateY(-3px); }
            .v-haut:focus-visible { outline: 3px solid var(--or); outline-offset: 3px; }
            @media (max-width: 640px) { .v-haut { right: 1rem; bottom: 1rem; } }

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
