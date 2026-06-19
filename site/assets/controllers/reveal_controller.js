import { Controller } from '@hotwired/stimulus';
import { gsap, reduceMotion } from '../lib/gsap.js';

/*
 * Apparition au scroll, avec stagger.
 *
 * Applique sur un conteneur (grille de cartes, liste de chapitres, sections d'un
 * chapitre...) : ses enfants directs apparaissent en fondu/glisse, decales, une
 * seule fois quand le conteneur entre dans le viewport. Si le conteneur n'a qu'un
 * enfant, c'est lui-meme qui est anime.
 *
 * Valeurs :
 *  - stagger : decalage entre chaque enfant (s).
 *  - y       : translation verticale de depart (px).
 *  - start   : position de declenchement ScrollTrigger.
 *
 * `prefers-reduced-motion` : on ne touche a rien, le contenu reste visible.
 */
export default class extends Controller {
    static values = {
        stagger: { type: Number, default: 0.08 },
        y: { type: Number, default: 16 },
        start: { type: String, default: 'top 85%' },
    };

    connect() {
        if (reduceMotion()) {
            return;
        }

        const children = Array.from(this.element.children);
        const items = children.length > 1 ? children : [this.element];

        gsap.set(items, { autoAlpha: 0, y: this.yValue });

        this.tween = gsap.to(items, {
            autoAlpha: 1,
            y: 0,
            duration: 0.5,
            stagger: this.staggerValue,
            scrollTrigger: {
                trigger: this.element,
                start: this.startValue,
                once: true,
            },
        });
    }

    disconnect() {
        this.tween?.scrollTrigger?.kill();
        this.tween?.kill();
    }
}
