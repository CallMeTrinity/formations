import { Controller } from '@hotwired/stimulus';

/*
 * Bascule clair / sombre.
 *
 * Le thème est porté par l'attribut `data-theme` sur <html> (lu par les tokens
 * du design system). La valeur initiale est posée avant le rendu par un petit
 * script inline dans <head> (anti-flash). Ce contrôleur ne gère que la bascule
 * au clic, la persistance dans localStorage et l'icône affichée.
 *
 * L'attribut sur <html> survit aux navigations Turbo : pas besoin de le rejouer.
 */
export default class extends Controller {
    static targets = ['toDark', 'toLight'];

    connect() {
        this.render();
    }

    toggle() {
        const next = this.current === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try {
            localStorage.setItem('theme', next);
        } catch (e) {
            // Mode navigation privée : on ignore, le thème reste pour la session.
        }
        this.render();
    }

    get current() {
        return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    }

    // On affiche l'icône de la cible vers laquelle on bascule : lune en clair,
    // soleil en sombre.
    render() {
        const dark = this.current === 'dark';
        this.toDarkTarget.classList.toggle('hidden', dark);
        this.toLightTarget.classList.toggle('hidden', !dark);
        this.element.setAttribute('aria-label', dark ? 'Passer en thème clair' : 'Passer en thème sombre');
    }
}
