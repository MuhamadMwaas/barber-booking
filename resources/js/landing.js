/**
 * LOOK UP FRISEUR — landing page behaviour.
 *
 * Deliberately dependency-free: the marketing site loads no Alpine, no Livewire
 * and no framework runtime, so a first visit is a single small script. Three
 * jobs, nothing else:
 *
 *   1. Condense the navbar once the visitor scrolls past the hero.
 *   2. Open/close the mobile menu.
 *   3. Reveal sections as they enter the viewport.
 *   4. Mark the nav link of the section currently on screen.
 */

(function () {
    'use strict';

    /* ------------------------------------------------------------------
     | 1 + 4. Navbar: condense on scroll, highlight the current section
     ------------------------------------------------------------------ */

    const navbar = document.querySelector('[data-lp-navbar]');

    if (navbar) {
        const condense = () => {
            navbar.classList.toggle('is-scrolled', window.scrollY > 24);
        };

        condense();
        window.addEventListener('scroll', condense, { passive: true });
    }

    const anchorLinks = Array.from(
        document.querySelectorAll('[data-lp-nav-link][href^="#"]')
    );

    if (anchorLinks.length && 'IntersectionObserver' in window) {
        // Map "#services" -> the <section id="services"> it points at, skipping
        // links whose target section is switched off in the admin panel.
        const targets = new Map();

        anchorLinks.forEach((link) => {
            const id = link.getAttribute('href').slice(1);
            const section = id ? document.getElementById(id) : null;

            if (section) {
                targets.set(section, link);
            }
        });

        const spy = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) {
                        return;
                    }

                    anchorLinks.forEach((link) => link.classList.remove('is-active'));
                    targets.get(entry.target)?.classList.add('is-active');
                });
            },
            // A band across the upper-middle of the viewport: the section that
            // occupies it is the one the visitor is actually reading.
            { rootMargin: '-45% 0px -50% 0px', threshold: 0 }
        );

        targets.forEach((_link, section) => spy.observe(section));
    }

    /* ------------------------------------------------------------------
     | 2. Mobile menu
     ------------------------------------------------------------------ */

    const menuToggle = document.querySelector('[data-lp-menu-toggle]');
    const menuPanel = document.querySelector('[data-lp-menu]');

    if (menuToggle && menuPanel) {
        const setMenu = (open) => {
            menuPanel.classList.toggle('hidden', !open);
            menuToggle.setAttribute('aria-expanded', String(open));
            // Stop the page scrolling behind the open overlay.
            document.body.style.overflow = open ? 'hidden' : '';
        };

        menuToggle.addEventListener('click', () => {
            setMenu(menuPanel.classList.contains('hidden'));
        });

        // Any navigation inside the menu closes it.
        menuPanel.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                setMenu(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !menuPanel.classList.contains('hidden')) {
                setMenu(false);
                menuToggle.focus();
            }
        });
    }

    /* ------------------------------------------------------------------
     | 3. Reveal on scroll
     ------------------------------------------------------------------ */

    const revealables = document.querySelectorAll('.lp-reveal');

    if (!revealables.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        // Leave everything visible — .lp-reveal only hides content once the
        // class below is present on <html>.
        return;
    }

    document.documentElement.classList.add('lp-reveal-ready');

    const reveal = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.08 }
    );

    revealables.forEach((element, index) => {
        // Stagger siblings slightly so a grid of cards cascades in rather than
        // snapping as one block. Capped so long lists never feel sluggish.
        const delay = Math.min(index % 6, 5) * 70;
        element.style.transitionDelay = `${delay}ms`;
        reveal.observe(element);
    });
})();
