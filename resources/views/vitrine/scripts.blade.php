        @verbatim
        <script>
            (function () {
                var reduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                // En-tête : se resserre quand on défile.
                var entete = document.getElementById('entete');
                var haut = document.getElementById('haut');
                function defile() {
                    entete.classList.toggle('est-defile', window.scrollY > 24);
                    haut.classList.toggle('est-visible', window.scrollY > 500);
                }
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
