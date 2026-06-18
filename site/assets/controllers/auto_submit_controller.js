import { Controller } from '@hotwired/stimulus';

/*
 * Soumet le formulaire dès qu'un champ change (ex. un <select>).
 *
 * Le bouton de repli (cible `submit`) est masqué quand JS est dispo : il ne sert
 * qu'aux navigateurs sans JavaScript.
 */
export default class extends Controller {
    static targets = ['submit'];

    connect() {
        this.submitTargets.forEach((el) => el.classList.add('hidden'));
    }

    submit() {
        this.element.requestSubmit();
    }
}
