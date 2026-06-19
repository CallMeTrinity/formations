import { Controller } from '@hotwired/stimulus';

/*
 * Panneau repliable (disclosure).
 *
 * Sert sur mobile a masquer un bloc derriere un bouton (ex. les filtres du
 * catalogue) pour laisser les resultats visibles d'emblee. Sur grand ecran, le
 * panneau est force visible en CSS (`lg:block`) : le bouton est masque et l'etat
 * `hidden` qu'on bascule ici n'a plus d'effet.
 */
export default class extends Controller {
    static targets = ['panel', 'button'];

    toggle() {
        const isHidden = this.panelTarget.classList.toggle('hidden');
        this.buttonTarget.setAttribute('aria-expanded', String(!isHidden));
    }
}
