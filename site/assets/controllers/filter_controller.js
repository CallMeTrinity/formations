import { Controller } from '@hotwired/stimulus';

/*
 * Filtres du catalogue.
 *
 * Soumet automatiquement le formulaire dès qu'une case change, pour appliquer
 * les filtres sans clic supplémentaire. Le bouton « Filtrer » sert de repli
 * sans JavaScript : on le masque ici puisqu'il devient inutile.
 */
export default class extends Controller {
    static targets = ['submit'];

    connect() {
        this.submitTargets.forEach((el) => el.classList.add('hidden'));
    }

    submit() {
        this.element.requestSubmit();
    }

    // Soumission différée pour le champ de recherche : on attend une pause de
    // frappe pour éviter une requête à chaque caractère.
    submitDebounced() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => this.submit(), 300);
    }

    disconnect() {
        clearTimeout(this.debounceTimer);
    }
}
