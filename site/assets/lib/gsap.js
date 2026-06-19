/*
 * Point d'entree unique de GSAP pour le site.
 *
 * Enregistre les plugins une seule fois, fixe les defauts d'animation et expose
 * un helper `reduceMotion()`. Tous les modules et controleurs Stimulus importent
 * GSAP d'ici, jamais directement, pour garantir un enregistrement unique.
 */
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { ScrollToPlugin } from 'gsap/ScrollToPlugin';

gsap.registerPlugin(ScrollTrigger, ScrollToPlugin);

gsap.defaults({ duration: 0.6, ease: 'power2.out' });

/** Vrai si l'utilisateur a demande a reduire les animations. */
export const reduceMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export { gsap, ScrollTrigger, ScrollToPlugin };
