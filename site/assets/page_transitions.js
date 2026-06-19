/*
 * Transitions de page et defilement doux, branches sur Turbo Drive.
 *
 * Turbo etant actif, la navigation est deja "SPA-like" : on intercale donc une
 * animation GSAP de sortie/entree de <main> autour du swap Turbo. On gere aussi
 * le defilement doux des ancres internes via ScrollToPlugin.
 *
 * `prefers-reduced-motion` est respecte : on laisse alors Turbo operer sans
 * animation, et les ancres utilisent un saut instantane.
 */
import { gsap, ScrollTrigger, reduceMotion } from './lib/gsap.js';

const OUT = { autoAlpha: 0, y: -8, duration: 0.2, ease: 'power1.in' };
const IN = { autoAlpha: 1, y: 0, duration: 0.45, ease: 'power2.out' };

const main = () => document.querySelector('main');

// Sortie : rend le rendu Turbo asynchrone pour animer <main> avant le swap.
document.addEventListener('turbo:before-render', (event) => {
    if (reduceMotion()) {
        return;
    }
    const el = main();
    if (!el) {
        return;
    }
    event.preventDefault();
    gsap.to(el, { ...OUT, onComplete: () => event.detail.resume() });
});

// Entree : a chaque chargement de page (y compris le tout premier).
document.addEventListener('turbo:load', () => {
    if (!reduceMotion()) {
        const el = main();
        if (el) {
            gsap.fromTo(el, { autoAlpha: 0, y: 8 }, IN);
        }
    }
    // Les positions des ScrollTrigger peuvent avoir change avec le nouveau DOM.
    ScrollTrigger.refresh();
});

// Nettoie les styles inline avant la mise en cache pour ne pas figer un etat
// intermediaire dans le snapshot Turbo.
document.addEventListener('turbo:before-cache', () => {
    const el = main();
    if (el) {
        gsap.set(el, { clearProps: 'all' });
    }
});

// Defilement doux pour les ancres internes (#id).
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href^="#"]');
    if (!link) {
        return;
    }
    const hash = link.getAttribute('href');
    if (hash === '#' || !document.querySelector(hash)) {
        return;
    }
    event.preventDefault();
    gsap.to(window, {
        duration: reduceMotion() ? 0 : 0.6,
        ease: 'power2.inOut',
        scrollTo: { y: hash, offsetY: 80 },
    });
});
