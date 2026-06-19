import { Controller } from '@hotwired/stimulus';

/*
 * Menu mobile (burger).
 *
 * Ouvre / ferme le panneau de navigation sous la barre d'en-tête sur petit
 * écran, et permute les icônes burger / croix. Le panneau vit dans le <header>,
 * donc une navigation Turbo le ré-affiche fermé : `connect()` garantit l'état
 * fermé par sécurité.
 */
export default class extends Controller {
    static targets = ['panel', 'open', 'close', 'button'];

    connect() {
        this.close();
    }

    toggle() {
        if (this.panelTarget.classList.contains('hidden')) {
            this.open();
        } else {
            this.close();
        }
    }

    open() {
        this.panelTarget.classList.remove('hidden');
        this.openTarget.classList.add('hidden');
        this.closeTarget.classList.remove('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.panelTarget.classList.add('hidden');
        this.openTarget.classList.remove('hidden');
        this.closeTarget.classList.add('hidden');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }
}
