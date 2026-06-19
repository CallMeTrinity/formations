import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion } from '../lib/gsap.js';

/*
 * Remplissage anime d'une barre de progression.
 *
 * Applique sur un conteneur `.progress` : sa `.progress__bar` se remplit de 0 a
 * sa largeur cible (definie inline) quand elle entre dans le viewport. On anime
 * `scaleX` (transform) plutot que `width` : meilleure perf, et la largeur inline
 * reste la cible visuelle.
 *
 * `prefers-reduced-motion` : la barre reste pleine, sans animation.
 */
export default class extends Controller {
    connect() {
        const bar = this.element.querySelector('.progress__bar') ?? this.element;

        if (reduceMotion()) {
            return;
        }

        this.tween = gsap.fromTo(
            bar,
            { scaleX: 0 },
            {
                scaleX: 1,
                transformOrigin: 'left center',
                duration: 0.8,
                ease: 'power2.out',
                scrollTrigger: {
                    trigger: this.element,
                    start: 'top 90%',
                    once: true,
                },
            },
        );
    }

    disconnect() {
        this.tween?.scrollTrigger?.kill();
        this.tween?.kill();
    }
}
