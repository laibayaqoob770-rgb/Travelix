/*
 * Shared lightweight "3D" interaction layer — mousemove tilt for
 * .tilt-card elements and mousemove parallax for .parallax-hero elements.
 * Pure CSS-transform based, no WebGL. Pairs with
 * assets/css/travelix_3d_effects.css. Auto-inits on DOMContentLoaded and
 * also exposes window.travelixInit3D() for content rendered later
 * (e.g. cards built dynamically from Firestore data).
 */
(function () {
    const prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    const MAX_TILT_DEG = 10;
    const initedTilt = new WeakSet();
    const initedParallax = new WeakSet();

    // Like querySelectorAll(selector), but also matches the root element
    // itself — needed when callers pass a freshly-created card (e.g. a
    // dynamically injected hotel result) that already carries the class
    // instead of a container wrapping it.
    function queryIncludingSelf(root, selector) {
        const scope = root || document;
        const matches = Array.from(scope.querySelectorAll(selector));
        if (scope.nodeType === 1 && scope.matches && scope.matches(selector)) {
            matches.unshift(scope);
        }
        return matches;
    }

    function initTiltCards(root) {
        queryIncludingSelf(root, '.tilt-card').forEach((card) => {
            if (initedTilt.has(card)) return;
            initedTilt.add(card);

            let rafId = null;

            card.addEventListener('mouseenter', () => {
                card.classList.add('tilt-active');
            });

            card.addEventListener('mousemove', (e) => {
                if (rafId) return;
                rafId = requestAnimationFrame(() => {
                    rafId = null;
                    const rect = card.getBoundingClientRect();
                    const px = (e.clientX - rect.left) / rect.width;
                    const py = (e.clientY - rect.top) / rect.height;

                    const rotateY = (px - 0.5) * MAX_TILT_DEG * 2;
                    const rotateX = (0.5 - py) * MAX_TILT_DEG * 2;

                    card.style.setProperty('--tilt-x', rotateX.toFixed(2) + 'deg');
                    card.style.setProperty('--tilt-y', rotateY.toFixed(2) + 'deg');
                    card.style.setProperty('--glare-x', (px * 100).toFixed(1) + '%');
                    card.style.setProperty('--glare-y', (py * 100).toFixed(1) + '%');
                    card.style.setProperty('--glare-opacity', '1');
                });
            });

            card.addEventListener('mouseleave', () => {
                card.classList.remove('tilt-active');
                card.style.setProperty('--tilt-x', '0deg');
                card.style.setProperty('--tilt-y', '0deg');
                card.style.setProperty('--glare-opacity', '0');
            });
        });
    }

    function initParallaxHero(root) {
        queryIncludingSelf(root, '.parallax-hero').forEach((hero) => {
            if (initedParallax.has(hero)) return;
            initedParallax.add(hero);

            const layers = Array.from(hero.querySelectorAll('.parallax-layer'));
            if (!layers.length) return;

            let rafId = null;

            hero.addEventListener('mousemove', (e) => {
                if (rafId) return;
                rafId = requestAnimationFrame(() => {
                    rafId = null;
                    const rect = hero.getBoundingClientRect();
                    const px = (e.clientX - rect.left) / rect.width - 0.5;
                    const py = (e.clientY - rect.top) / rect.height - 0.5;

                    layers.forEach((layer) => {
                        const depth = parseFloat(layer.dataset.depth || '10');
                        const x = (-px * depth).toFixed(2);
                        const y = (-py * depth).toFixed(2);
                        layer.style.transform = `translate3d(${x}px, ${y}px, 0)`;
                    });
                });
            });

            hero.addEventListener('mouseleave', () => {
                layers.forEach((layer) => {
                    layer.style.transform = 'translate3d(0, 0, 0)';
                });
            });
        });
    }

    window.travelixInit3D = function (root) {
        initTiltCards(root);
        initParallaxHero(root);
    };

    document.addEventListener('DOMContentLoaded', () => window.travelixInit3D());
})();
