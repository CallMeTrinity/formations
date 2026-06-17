# Les vues avec Twig

[← Chapitre précédent](03-routes-et-controleurs.md) · [Sommaire](README.md) · [Chapitre suivant →](05-doctrine-base-de-donnees.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre le rôle de **Twig**, le moteur de templates de Symfony ;
- afficher des **variables** transmises par le contrôleur et les **échapper** en sécurité ;
- utiliser les structures **`if`** et **`for`** dans une vue ;
- factoriser tes pages avec l'**héritage de templates** (un layout commun) ;
- générer des **liens** et des **URL** depuis une vue avec `path()`.

## Pourquoi Twig

Au chapitre précédent, tu as écrit du HTML directement dans le code PHP. C'est vite illisible,
dangereux (risque d'injection) et impossible à maintenir. **Twig** résout ça : c'est un **moteur de
templates**, un langage simple dédié à l'écriture de vues HTML, séparé de ta logique PHP.

Twig apporte trois bénéfices :

- une **syntaxe lisible** et concise, pensée pour le HTML ;
- l'**échappement automatique** des variables : le contenu affiché est sécurisé par défaut, fini les
  injections ;
- l'**héritage de templates** : tu écris la structure commune une seule fois.

Les fichiers Twig vivent dans `templates/` et portent l'extension `.html.twig`.

## La syntaxe en trois symboles

Tout Twig tient dans trois délimiteurs :

```twig
{{ ... }}   {# AFFICHER : insère la valeur d'une variable ou d'une expression #}
{% ... %}   {# FAIRE : exécute une instruction (if, for, extends, block...) #}
{# ... #}   {# COMMENTAIRE : ignoré, n'apparaît pas dans le HTML produit #}
```

Retiens la distinction : **`{{ }}` dit quelque chose** (affiche), **`{% %}` fait quelque chose**
(contrôle).

## Rendre une vue depuis le contrôleur

Le contrôleur transmet des données à la vue avec `$this->render()`. Premier argument : le chemin du
template (relatif à `templates/`). Deuxième argument : un tableau de variables.

```php
// src/Controller/BlogController.php
#[Route('/blog', name: 'blog_index')]
public function index(): Response
{
    $articles = [
        ['slug' => 'demarrer-symfony', 'titre' => 'Démarrer avec Symfony', 'vues' => 120],
        ['slug' => 'le-routage',        'titre' => 'Comprendre le routage',  'vues' => 0],
    ];

    return $this->render('blog/index.html.twig', [
        'articles' => $articles,
    ]);
}
```

Les clés du tableau (`articles`) deviennent des **variables** disponibles dans le template.

## Afficher des variables

Crée le fichier `templates/blog/index.html.twig` :

```twig
{# templates/blog/index.html.twig #}
<h1>Le blog</h1>
<p>Il y a {{ articles|length }} article(s).</p>
```

`{{ articles|length }}` affiche le nombre d'éléments. Le `|length` est un **filtre** : un filtre
transforme une valeur, en la plaçant après une barre verticale. Twig en fournit beaucoup :

```twig
{{ "bonjour"|upper }}          {# BONJOUR #}
{{ "Mon Titre"|lower }}        {# mon titre #}
{{ article.titre|capitalize }} {# Première lettre en majuscule #}
```

Pour accéder à une propriété, on utilise le **point**, que ce soit une clé de tableau
(`article.titre`) ou une propriété/méthode d'objet : Twig essaie les deux automatiquement. Cette
syntaxe uniforme te servira beaucoup quand on manipulera des objets au chapitre suivant.

> **À retenir** — Tout ce que tu mets dans `{{ }}` est **échappé automatiquement** : si une variable
> contient `<script>`, Twig l'affiche comme du texte inoffensif, pas comme du code. C'est la
> protection anti-injection que tu devais faire à la main en PHP pur.

## Conditions et boucles

Pour répéter un bloc, utilise `{% for %}` :

```twig
<ul>
{% for article in articles %}
    <li>{{ article.titre }} — {{ article.vues }} vues</li>
{% endfor %}
</ul>
```

Tu peux ajouter un `{% else %}` qui s'exécute si la liste est **vide** :

```twig
{% for article in articles %}
    <li>{{ article.titre }}</li>
{% else %}
    <li>Aucun article pour l'instant.</li>
{% endfor %}
```

Pour afficher conditionnellement, utilise `{% if %}` :

```twig
{% if article.vues > 100 %}
    <span>Article populaire</span>
{% elseif article.vues == 0 %}
    <span>Tout nouveau</span>
{% else %}
    <span>{{ article.vues }} vues</span>
{% endif %}
```

## Générer des liens : `path()`

Souviens-toi : on ne recopie jamais une URL en dur. Dans Twig, la fonction `path()` génère l'URL
d'une route à partir de son **nom** (exactement comme `generateUrl()` côté PHP) :

```twig
<a href="{{ path('blog_index') }}">Tous les articles</a>
```

Avec un **paramètre** de route, passe-le en deuxième argument :

```twig
{% for article in articles %}
    <li>
        <a href="{{ path('blog_show', { slug: article.slug }) }}">
            {{ article.titre }}
        </a>
    </li>
{% endfor %}
```

`{ slug: article.slug }` est la notation Twig d'un tableau associatif (clé : valeur). Le lien généré
sera par exemple `/blog/demarrer-symfony`.

> **Astuce** — `path()` produit une URL **relative** (`/blog`). Sa variante `url()` produit une URL
> **absolue** (`https://...`), utile dans les e-mails ou les flux RSS où le chemin seul ne suffit pas.

## L'héritage de templates

Toutes tes pages partagent une structure : `<html>`, `<head>`, un en-tête, un pied de page… Plutôt que
de la recopier partout, tu la définis **une fois** dans un **layout** (gabarit parent), et chaque page
n'écrit que son contenu spécifique.

Le projet `--webapp` fournit déjà `templates/base.html.twig`. Étoffe-le un peu :

```twig
{# templates/base.html.twig #}
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>{% block title %}Mon blog{% endblock %}</title>
    </head>
    <body>
        <header>
            <a href="{{ path('app_home') }}">Accueil</a> ·
            <a href="{{ path('blog_index') }}">Blog</a>
        </header>

        <main>
            {% block body %}{% endblock %}
        </main>

        <footer>
            <p>Blog réalisé avec Symfony.</p>
        </footer>
    </body>
</html>
```

Les `{% block nom %}{% endblock %}` sont des **emplacements** que les pages enfants pourront remplir
ou remplacer. Maintenant, fais hériter `index.html.twig` de ce layout :

```twig
{# templates/blog/index.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Le blog{% endblock %}

{% block body %}
    <h1>Le blog</h1>
    <ul>
    {% for article in articles %}
        <li>
            <a href="{{ path('blog_show', { slug: article.slug }) }}">{{ article.titre }}</a>
        </li>
    {% else %}
        <li>Aucun article pour l'instant.</li>
    {% endfor %}
    </ul>
{% endblock %}
```

Comment ça marche :

- `{% extends 'base.html.twig' %}` déclare que cette page **hérite** du layout.
- Chaque `{% block %}` de l'enfant **remplit** le bloc de même nom du parent. Le `block title` change
  le titre de l'onglet, le `block body` injecte le contenu dans le `<main>`.
- Tout ce qui n'est pas dans un bloc (le `<html>`, le `<header>`, le `<footer>`) vient du parent : tu
  ne l'écris qu'une fois.

> **À retenir** — Un layout parent définit la structure et des **blocs** ; chaque page enfant
> `extends` le parent et **remplit les blocs**. C'est le cœur de la réutilisation en Twig.

### Réutiliser un morceau : `include`

Pour un fragment réutilisable (une carte d'article, un menu), tu peux le sortir dans un fichier à part
et l'inclure avec `{% include %}` :

```twig
{% include 'blog/_carte.html.twig' with { article: article } %}
```

Par convention, on préfixe d'un underscore (`_carte.html.twig`) les templates partiels destinés à être
inclus.

## Résumé

- **Twig** est le moteur de templates de Symfony : lisible, sécurisé (échappement **automatique**),
  et basé sur l'héritage. Les fichiers vivent dans `templates/` en `.html.twig`.
- Trois délimiteurs : **`{{ }}`** affiche, **`{% %}`** exécute une instruction, **`{# #}`**
  commente.
- Le contrôleur passe des variables via `$this->render('chemin.html.twig', [...])`.
- On accède aux propriétés avec le **point** ; les **filtres** (`|length`, `|upper`…) transforment une
  valeur.
- `{% for %}` (avec `{% else %}` si vide) et `{% if %}/{% elseif %}/{% else %}` structurent la vue.
- **`path('nom_route')`** génère les URL par le nom de route.
- L'**héritage** (`{% extends %}` + `{% block %}`) factorise la structure commune ; `{% include %}`
  réutilise des fragments.

## Exercices

### Exercice 1 — Une page d'article propre

Crée le template `templates/blog/show.html.twig` qui hérite de `base.html.twig`, affiche le titre de
l'article dans le `block title` **et** dans un `<h1>`, et propose un lien « ← Retour au blog » vers la
route `blog_index`. Adapte l'action `show` du contrôleur pour rendre ce template avec un article en
dur.

<details>
<summary>Voir le corrigé</summary>

La démarche : le contrôleur passe l'article, le template hérite du layout et utilise `path()`.

Contrôleur :

```php
#[Route('/blog/{slug}', name: 'blog_show')]
public function show(string $slug): Response
{
    $article = ['slug' => $slug, 'titre' => 'Démarrer avec Symfony', 'contenu' => 'Texte...'];

    return $this->render('blog/show.html.twig', ['article' => $article]);
}
```

Template `templates/blog/show.html.twig` :

```twig
{% extends 'base.html.twig' %}

{% block title %}{{ article.titre }}{% endblock %}

{% block body %}
    <h1>{{ article.titre }}</h1>
    <p>{{ article.contenu }}</p>
    <a href="{{ path('blog_index') }}">← Retour au blog</a>
{% endblock %}
```

</details>

### Exercice 2 — Badge conditionnel

Dans la liste des articles (`index.html.twig`), affiche un badge `Populaire` à côté des articles ayant
plus de 100 vues, et `Nouveau` pour ceux à 0 vue. Les autres affichent simplement leur nombre de vues.

<details>
<summary>Voir le corrigé</summary>

La démarche : un `{% if %}/{% elseif %}/{% else %}` à l'intérieur de la boucle.

```twig
{% for article in articles %}
    <li>
        <a href="{{ path('blog_show', { slug: article.slug }) }}">{{ article.titre }}</a>
        {% if article.vues > 100 %}
            <strong>Populaire</strong>
        {% elseif article.vues == 0 %}
            <strong>Nouveau</strong>
        {% else %}
            ({{ article.vues }} vues)
        {% endif %}
    </li>
{% endfor %}
```

</details>

## Quiz

**1.** Quelle paire de symboles **affiche** la valeur d'une variable ?
- A. `{% variable %}`
- B. `{{ variable }}`
- C. `{# variable #}`

**2.** Pourquoi Twig protège-t-il par défaut contre les injections HTML ?
- A. Parce qu'il refuse les variables contenant des balises
- B. Parce qu'il **échappe automatiquement** ce qu'on affiche avec `{{ }}`
- C. Parce qu'il supprime tout le HTML

**3.** Comment une page Twig réutilise-t-elle la structure d'un layout commun ?
- A. Avec `{% include %}` du layout au début
- B. Avec `{% extends %}` puis en remplissant des `{% block %}`
- C. En copiant le contenu du layout

**4.** Comment générer dans Twig l'URL de la route `blog_show` pour un slug donné ?
- A. `{{ url_for('blog_show') }}`
- B. `{{ path('blog_show', { slug: article.slug }) }}`
- C. `<a href="/blog/{{ article.slug }}">`

<details>
<summary>Voir les réponses</summary>

1. **B** — `{{ }}` affiche ; `{% %}` exécute une instruction ; `{# #}` est un commentaire.
2. **B** — Tout ce qui passe par `{{ }}` est échappé automatiquement, ce qui neutralise les
   injections.
3. **B** — La page enfant `extends` le layout et remplit ses `block`.
4. **B** — `path()` génère l'URL par le nom de route, avec ses paramètres ; on évite l'URL en dur de
   la réponse C.

</details>

## Projet fil rouge

Tu rends ton blog présentable.

1. Étoffe `templates/base.html.twig` avec une structure HTML complète, un en-tête contenant des liens
   `path('app_home')` et `path('blog_index')`, et un pied de page.
2. Transforme la page `/blog` (`blog/index.html.twig`) pour qu'elle **hérite** du layout et **boucle**
   sur les articles en dur de ton contrôleur, chaque titre étant un lien vers `blog_show`.
3. Crée `templates/blog/show.html.twig` (hérite du layout) qui affiche le titre et le contenu d'un
   article, plus un lien retour vers `blog_index`.
4. Navigue entre l'accueil, la liste et une page d'article : tout doit s'enchaîner par des liens
   générés avec `path()`.

Tes données sont encore codées en dur. Au chapitre suivant, on installe une **vraie base de données**
avec Doctrine pour y stocker les articles pour de bon.

---

[← Chapitre précédent](03-routes-et-controleurs.md) · [Sommaire](README.md) · [Chapitre suivant →](05-doctrine-base-de-donnees.md)
