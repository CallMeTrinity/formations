import { Controller } from '@hotwired/stimulus';

/*
 * Notifications en bulle (toasts).
 *
 * Rendu côté serveur depuis les messages flash : ce contrôleur limite le
 * nombre de bulles visibles et gère la fermeture au clic.
 */
export default class extends Controller {
    static targets = ['item'];
    static values = { max: { type: Number, default: 3 } };

    connect() {
        // On ne garde que les `max` notifications les plus récentes.
        const overflow = this.itemTargets.length - this.maxValue;
        for (let i = 0; i < overflow; i++) {
            this.itemTargets[i].remove();
        }
    }

    dismiss(event) {
        const item = event.currentTarget;
        item.classList.add('toast--leaving');
        item.addEventListener('transitionend', () => item.remove(), { once: true });
    }
}
