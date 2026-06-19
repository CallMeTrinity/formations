import { Controller } from '@hotwired/stimulus';

/*
 * Sélecteur de tags.
 *
 * Filtre les puces affichées au fil de la frappe dans le champ de recherche
 * (côté client, sans requête). Le champ de recherche vivant hors de la turbo
 * frame, `filter` est aussi rappelé sur `turbo:frame-load` pour ré-appliquer le
 * filtre après chaque rechargement de la liste. Affiche un message quand aucune
 * puce ne correspond.
 */
export default class extends Controller {
    static targets = ['search', 'option', 'empty'];

    filter() {
        const query = this.hasSearchTarget ? this.searchTarget.value.trim().toLowerCase() : '';
        let visible = 0;

        this.optionTargets.forEach((option) => {
            const match = '' === query || option.dataset.label.includes(query);
            option.classList.toggle('hidden', !match);
            if (match) {
                visible += 1;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('hidden', visible > 0);
        }
    }
}
