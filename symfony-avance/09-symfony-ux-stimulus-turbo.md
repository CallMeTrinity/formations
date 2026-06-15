# Symfony UX : Stimulus et Turbo

[← Chapitre précédent](08-workflow.md) · [Sommaire](README.md) · [Chapitre suivant →](10-twig-live-components-mercure.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- ce qu'est **Symfony UX** et la philosophie « interactivité sans framework JS lourd » ;
- mettre en place les **assets** modernes avec **AssetMapper** ;
- écrire un **contrôleur Stimulus** pour ajouter du comportement à du HTML ;
- accélérer la navigation et les formulaires avec **Turbo**, sans recharger la page ;
- savoir quand utiliser Stimulus, Turbo, ou les deux.

## La philosophie Symfony UX

Pour rendre une interface dynamique (afficher/masquer un bloc, valider sans recharger, mettre à jour un
compteur en direct), le réflexe courant est de sortir un gros framework JavaScript (React, Vue). C'est
puissant, mais lourd : un second « projet » à maintenir, une duplication de la logique, et beaucoup de
JavaScript à écrire.

**Symfony UX** propose une autre voie : garder le **rendu côté serveur** (ton Twig, que tu maîtrises
déjà) et ajouter juste **ce qu'il faut** d'interactivité par-dessus. Tu écris **très peu de
JavaScript**, et tu restes dans l'écosystème Symfony. Deux briques de base dans ce chapitre :

- **Stimulus** : un petit framework JS qui attache du **comportement** à des éléments HTML existants.
- **Turbo** : accélère navigation et formulaires en évitant les rechargements de page complets.

> **À retenir** — Symfony UX, c'est de l'interactivité **progressive** : ton HTML fonctionne déjà sans
> JS, et le JS l'**améliore**. Tu n'abandonnes pas le rendu serveur, tu le complètes.

## Préparer le terrain : AssetMapper

Avant Symfony UX, il faut servir des fichiers JavaScript au navigateur. Historiquement on utilisait
Webpack Encore (un *bundler* à configurer). Symfony propose désormais **AssetMapper** : il sert tes
fichiers JS et CSS modernes **sans étape de compilation**, en s'appuyant sur les capacités natives des
navigateurs (les *import maps*). Plus simple à démarrer.

```bash
$ composer require symfony/asset-mapper symfony/stimulus-bundle
```

Cette installation crée :

- un dossier `assets/` avec un fichier `app.js` (ton point d'entrée JavaScript) ;
- un dossier `assets/controllers/` pour tes contrôleurs Stimulus ;
- la configuration pour que ton `base.html.twig` charge tout ça.

Dans ton layout, deux lignes branchent les assets (le maker les ajoute en général tout seul) :

```twig
{# templates/base.html.twig — dans le <head> #}
{% block javascripts %}
    {{ importmap('app') }}   {# charge app.js et la chaîne d'imports #}
{% endblock %}
```

> **Astuce** — `php bin/console debug:asset-map` liste les assets connus et leur chemin public.
> Pratique quand un fichier JS « n'est pas pris en compte » : tu vérifies qu'AssetMapper le voit.

## Stimulus : attacher du comportement au HTML

Stimulus part d'une idée simple : ton HTML est déjà rendu par Twig ; tu y **accroches** un peu de
JavaScript via des attributs `data-`. Trois concepts :

- un **controller** : une classe JS qui porte le comportement ;
- des **targets** : les éléments HTML que le controller manipule ;
- des **actions** : les événements (clic, saisie…) qui déclenchent ses méthodes.

Prenons un cas concret du blog : un bouton « Lire la suite » qui affiche/masque le reste d'un article.
Le contrôleur Stimulus vit dans `assets/controllers/` :

```js
// assets/controllers/toggle_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    // Les éléments que ce contrôleur manipule.
    static targets = ['content'];

    // Méthode déclenchée par une action (un clic).
    toggle() {
        this.contentTarget.hidden = !this.contentTarget.hidden;
    }
}
```

Côté Twig, on **branche** ce contrôleur avec des attributs `data-` (les helpers `stimulus_controller`,
`stimulus_target` et `stimulus_action` les génèrent proprement) :

```twig
{# Un extrait d'article repliable #}
<div {{ stimulus_controller('toggle') }}>
    <p>{{ article.excerpt }}</p>

    <div {{ stimulus_target('toggle', 'content') }} hidden>
        {{ article.content }}
    </div>

    <button {{ stimulus_action('toggle', 'toggle') }}>Lire la suite</button>
</div>
```

Décortiquons :

- `stimulus_controller('toggle')` attache le contrôleur `toggle` à ce `<div>` (sa « zone d'action »).
- `stimulus_target('toggle', 'content')` désigne le bloc que le contrôleur connaît sous le nom
  `content` (accessible en JS via `this.contentTarget`).
- `stimulus_action('toggle', 'toggle')` dit : au **clic** sur ce bouton, appelle la méthode `toggle()`.
  (Stimulus déduit `click` pour un `<button>` ; on peut préciser `click->toggle#toggle` au besoin.)

Au clic, le bloc apparaît ou disparaît. Aucune ligne de JavaScript à part le contrôleur, **réutilisable**
sur n'importe quel élément de n'importe quelle page.

> **À retenir** — Un contrôleur Stimulus est **générique et réutilisable** : il ne connaît pas *ton*
> article, juste « une cible à montrer/cacher ». Tu le branches partout où tu as ce besoin, via des
> attributs `data-` dans Twig.

## Des valeurs et des cibles multiples

Un contrôleur Stimulus peut recevoir des **valeurs** depuis le HTML (configuration) et gérer plusieurs
cibles. Exemple : un compteur de caractères restants dans la zone de commentaire.

```js
// assets/controllers/char_count_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'output'];
    static values = { max: Number };   // valeur lue depuis le HTML

    connect() {
        this.update();   // appelée automatiquement quand le contrôleur s'attache
    }

    update() {
        const reste = this.maxValue - this.inputTarget.value.length;
        this.outputTarget.textContent = `${reste} caractères restants`;
    }
}
```

```twig
<div {{ stimulus_controller('char_count', { max: 500 }) }}>
    <textarea {{ stimulus_target('char_count', 'input') }}
              {{ stimulus_action('char_count', 'update', 'input') }}></textarea>
    <small {{ stimulus_target('char_count', 'output') }}></small>
</div>
```

L'action `'update', 'input'` déclenche `update()` à chaque **saisie** (`input`). La valeur `max: 500`
passe du Twig au JS via `this.maxValue`. C'est tout l'esprit de Stimulus : du HTML déclaratif, du JS
minimal et générique.

## Turbo : la navigation sans rechargement

**Turbo** s'attaque à un autre problème : à chaque clic sur un lien ou envoi de formulaire, le
navigateur **recharge toute la page** (re-télécharge le CSS, le JS, reconstruit tout). C'est lent et
ça « clignote ». Turbo intercepte ces actions et ne remplace que **le contenu qui change**, donnant
une sensation d'**application** fluide — sans que tu écrives de JavaScript.

```bash
$ composer require symfony/ux-turbo
```

Une fois installé, **Turbo Drive** est actif automatiquement : tous tes liens et formulaires
deviennent « ajaxifiés » de manière transparente. Tu ne changes rien à ton code ; la navigation est
simplement plus rapide. C'est le gain « gratuit ».

Là où Turbo devient puissant, c'est avec les **Turbo Frames** : tu délimites une **portion** de page
qui se met à jour **indépendamment** du reste. Exemple : la liste des commentaires d'un article, qui
se recharge sans toucher au reste de la page.

```twig
{# La zone des commentaires, isolée dans une frame #}
<turbo-frame id="comments">
    {% for comment in article.comments %}
        <article>{{ comment.content }}</article>
    {% endfor %}

    {{ form_start(commentForm) }}
        {{ form_widget(commentForm) }}
        <button>Commenter</button>
    {{ form_end(commentForm) }}
</turbo-frame>
```

Quand le formulaire de commentaire est envoyé **dans cette frame**, Turbo n'attend du serveur que le
**nouveau contenu de la frame** (une `<turbo-frame id="comments">` mise à jour) et remplace juste cette
zone. Le reste de la page — l'article, le menu — ne bouge pas et ne clignote pas. Côté contrôleur, tu
re-rends simplement le template contenant la frame.

> **Astuce** — Pour qu'un formulaire dans une frame fonctionne bien, le contrôleur qui le traite doit
> renvoyer un HTML **contenant la même `<turbo-frame id="comments">`**. Turbo va y chercher le contenu
> de remplacement. En cas d'erreur de validation, re-rends le formulaire dans la frame : les messages
> s'affichent sans rechargement.

## Stimulus, Turbo, ou les deux ?

Les deux briques sont complémentaires :

| Besoin | Outil |
| --- | --- |
| Comportement local (afficher/masquer, compteur, menu) | **Stimulus** |
| Navigation et formulaires fluides, sans rechargement | **Turbo** |
| Mettre à jour une zone de page après une action serveur | **Turbo Frames** |
| Logique riche pilotée par le serveur (état réactif) | **Live Components** (chapitre 10) |

Tu peux parfaitement combiner : une page rendue par Twig, accélérée par Turbo, avec quelques
contrôleurs Stimulus pour les détails interactifs. C'est le combo « UX » par excellence.

> **À retenir** — Stimulus = comportement **côté navigateur**, générique. Turbo = navigation et
> rendus partiels **pilotés par le serveur**, sans JS. Ensemble, ils donnent une appli fluide en
> restant majoritairement en Twig.

## Résumé

- **Symfony UX** ajoute de l'interactivité **progressive** par-dessus ton rendu serveur, avec très peu
  de JavaScript.
- **AssetMapper** sert tes JS/CSS modernes **sans étape de build** ; `importmap('app')` les branche
  dans le layout.
- **Stimulus** attache du comportement au HTML via des **controllers**, **targets** et **actions**
  (attributs `data-` générés par les helpers Twig) ; les contrôleurs sont **réutilisables**.
- **Turbo Drive** accélère toute la navigation sans code ; les **Turbo Frames** mettent à jour une
  portion de page indépendamment.
- On choisit **Stimulus** pour le comportement local, **Turbo** pour la fluidité serveur, et on les
  combine.

## Exercices

### Exercice 1 — Un contrôleur Stimulus « confirmation »

Crée un contrôleur Stimulus `confirm` qui, au clic sur un bouton de suppression, demande une
confirmation (`window.confirm`) et annule l'action si l'utilisateur refuse.

<details>
<summary>Voir le corrigé</summary>

La démarche : on intercepte l'événement et on l'annule si la confirmation échoue.

```js
// assets/controllers/confirm_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { message: String };

    confirm(event) {
        if (!window.confirm(this.messageValue || 'Confirmer ?')) {
            event.preventDefault();   // on annule la soumission/le clic
        }
    }
}
```

```twig
<form method="post" action="{{ path('admin_article_delete', {id: article.id}) }}"
      {{ stimulus_controller('confirm', { message: 'Supprimer cet article ?' }) }}
      {{ stimulus_action('confirm', 'confirm', 'submit') }}>
    <button>Supprimer</button>
</form>
```

L'action sur l'événement `submit` du formulaire déclenche `confirm()` ; `preventDefault()` bloque
l'envoi si l'utilisateur refuse.

</details>

### Exercice 2 — Une Turbo Frame pour les commentaires

Entoure la liste et le formulaire de commentaires d'une `<turbo-frame id="comments">`. Vérifie que
poster un commentaire ne recharge que cette zone (le reste de la page ne clignote pas).

<details>
<summary>Voir le corrigé</summary>

La démarche : on isole la zone dans une frame et on s'assure que le contrôleur re-rend cette frame.

```twig
<turbo-frame id="comments">
    {% for comment in article.comments %}
        <article>{{ comment.content }}</article>
    {% endfor %}
    {{ form_start(commentForm) }}
        {{ form_widget(commentForm) }}
        <button>Commenter</button>
    {{ form_end(commentForm) }}
</turbo-frame>
```

Le contrôleur qui traite le commentaire re-rend le template de l'article (qui contient cette frame). En
cas de succès comme d'erreur de validation, Turbo remplace uniquement le contenu de
`<turbo-frame id="comments">`. Ouvre l'onglet réseau du navigateur : tu verras une requête qui ne
ramène que la frame, pas toute la page.

</details>

## Quiz

**1.** Quelle est la philosophie de Symfony UX ?
- A. Remplacer Twig par un framework JavaScript
- B. Ajouter de l'interactivité progressive par-dessus le rendu serveur, avec peu de JS
- C. Supprimer tout le JavaScript

**2.** Que fait AssetMapper ?
- A. Il compile le PHP
- B. Il sert les JS/CSS modernes sans étape de build
- C. Il gère la base de données

**3.** Dans Stimulus, qu'est-ce qu'une « action » ?
- A. Une route
- B. Un événement (clic, saisie…) qui déclenche une méthode du contrôleur
- C. Une migration

**4.** Que fait Turbo Drive une fois installé ?
- A. Rien sans configuration
- B. Il accélère automatiquement liens et formulaires en évitant les rechargements complets
- C. Il sécurise l'API

**5.** À quoi sert une Turbo Frame ?
- A. À paginer une API
- B. À mettre à jour une portion de page indépendamment du reste
- C. À écrire un voter

<details>
<summary>Voir les réponses</summary>

1. **B** — Interactivité progressive sur le rendu serveur.
2. **B** — Il sert les assets modernes sans build.
3. **B** — Une action relie un événement à une méthode du contrôleur.
4. **B** — Turbo Drive ajaxifie navigation et formulaires automatiquement.
5. **B** — La frame met à jour une zone isolée de la page.

</details>

## Projet fil rouge

1. Installe AssetMapper, StimulusBundle et UX Turbo.
2. Ajoute un contrôleur Stimulus `toggle` (extrait d'article repliable) et un `char_count` sur la zone
   de commentaire.
3. Isole les commentaires dans une **Turbo Frame** : poster un commentaire ne recharge que cette zone.
4. Ajoute un contrôleur `confirm` sur les boutons de suppression de l'admin (exercice 1).

Ton blog devient réactif sans framework JS lourd. Au prochain chapitre, on monte encore d'un cran avec
les Live Components et le temps réel via Mercure.

---

[← Chapitre précédent](08-workflow.md) · [Sommaire](README.md) · [Chapitre suivant →](10-twig-live-components-mercure.md)
