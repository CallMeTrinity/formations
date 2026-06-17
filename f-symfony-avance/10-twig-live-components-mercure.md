# Twig Components, Live Components et temps réel avec Mercure

[← Chapitre précédent](09-symfony-ux-stimulus-turbo.md) · [Sommaire](README.md) · [Chapitre suivant →](11-tests-avances-qualite.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- factoriser ton interface en **Twig Components** réutilisables ;
- rendre une portion de page **réactive** avec un **Live Component**, presque sans JavaScript ;
- comprendre les **actions** et le **data binding** d'un Live Component ;
- diffuser des mises à jour **temps réel** au navigateur avec **Mercure** ;
- choisir entre rafraîchissement par interaction (Live) et diffusion (Mercure).

## Twig Components : des composants d'interface réutilisables

Au fil des chapitres, tes templates se répètent : une carte d'article apparaît sur l'accueil, dans les
résultats de recherche, dans la page d'un auteur. Copier-coller ce HTML est fragile. Un **Twig
Component** encapsule un morceau d'interface — son HTML **et** sa petite logique — dans un objet
réutilisable, qu'on appelle comme une balise.

```bash
$ composer require symfony/ux-twig-component
```

Un composant a deux parties : une **classe PHP** (les données et la logique) et un **template**.

```php
<?php
// src/Twig/Components/ArticleCard.php
namespace App\Twig\Components;

use App\Entity\Article;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ArticleCard
{
    public Article $article;   // propriété publique = paramètre du composant

    // Un peu de logique propre au composant.
    public function readingTime(): int
    {
        return max(1, (int) ceil(str_word_count($this->article->getContent()) / 200));
    }
}
```

```twig
{# templates/components/ArticleCard.html.twig #}
<article class="card">
    <h2>{{ article.title }}</h2>
    <p>par {{ article.author.name }} — {{ this.readingTime }} min de lecture</p>
    <a href="{{ path('blog_show', { slug: article.slug }) }}">Lire</a>
</article>
```

On l'utilise partout comme une balise HTML, en passant l'article :

```twig
{% for article in articles %}
    <twig:ArticleCard :article="article" />
{% endfor %}
```

`:article="article"` passe la variable Twig `article` à la propriété `$article` du composant (le `:`
signifie « c'est une expression, pas une chaîne »). `this.readingTime` appelle la méthode de la classe.
Tu as désormais **un seul endroit** à modifier pour changer l'apparence d'une carte d'article.

> **À retenir** — Un Twig Component = HTML + logique réutilisables, appelés comme une balise
> `<twig:NomDuComposant />`. C'est l'équivalent serveur des « composants » des frameworks front, sans
> quitter Twig.

## Live Components : la réactivité pilotée par le serveur

Un **Live Component** est un Twig Component qui se **met à jour tout seul** quand son état change, en
faisant des allers-retours **avec le serveur**, sans que tu écrives de JavaScript. C'est l'outil idéal
quand l'interactivité dépend de **données** ou de **logique métier** : une recherche instantanée, un
formulaire qui réagit à la saisie, un compteur de « j'aime ».

```bash
$ composer require symfony/ux-live-component
```

Construisons un **bouton « j'aime »** sur un article : un clic incrémente le compteur, qui se met à
jour à l'écran sans recharger la page.

```php
<?php
// src/Twig/Components/LikeButton.php
namespace App\Twig\Components;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class LikeButton
{
    use DefaultActionTrait;

    #[LiveProp]                       // propriété d'état, conservée entre les allers-retours
    public Article $article;

    public function __construct(private EntityManagerInterface $em) {}

    #[LiveAction]                     // méthode appelable depuis le navigateur
    public function like(): void
    {
        $this->article->setLikes($this->article->getLikes() + 1);
        $this->em->flush();           // on persiste l'incrément
    }
}
```

```twig
{# templates/components/LikeButton.html.twig #}
<div {{ attributes }}>
    <button data-action="live#action" data-live-action-param="like">
        J'aime ({{ article.likes }})
    </button>
</div>
```

À l'utilisation :

```twig
<twig:LikeButton :article="article" />
```

Décortiquons la mécanique, car elle est nouvelle :

- `#[LiveProp]` marque l'**état** du composant (ici l'article). Cet état est conservé d'un
  rafraîchissement à l'autre : le composant « se souvient » de quel article il s'agit.
- `#[LiveAction]` expose la méthode `like()` au navigateur. Le bouton, avec
  `data-action="live#action"` et `data-live-action-param="like"`, l'appelle au clic.
- Au clic, le navigateur envoie une requête au serveur, qui exécute `like()`, **re-rend** le composant
  avec la nouvelle valeur, et renvoie le HTML mis à jour. Seul le composant est remplacé à l'écran.

Tu n'as écrit **aucun JavaScript** : la logique est en PHP, le rendu en Twig, et Live Component
orchestre les allers-retours. Le compteur s'incrémente à l'écran comme dans une application moderne.

> **À retenir** — Un Live Component, c'est de la **réactivité avec la logique côté serveur** : tu
> codes en PHP/Twig, l'état (`LiveProp`) survit aux rafraîchissements, et les actions (`LiveAction`)
> déclenchent un re-rendu. Idéal quand l'interactivité dépend de tes données.

## Data binding : réagir à la saisie

Les Live Components brillent aussi pour les formulaires réactifs. Avec `data-model`, une valeur saisie
est **liée** à une `LiveProp` : à chaque frappe, le composant se re-rend. Cas typique : une recherche
d'articles instantanée.

```php
#[AsLiveComponent]
final class ArticleSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]       // writable : le navigateur peut modifier cette valeur
    public string $query = '';

    public function __construct(private ArticleRepository $articles) {}

    // Recalculée à chaque rafraîchissement, donc à chaque frappe.
    public function getResults(): array
    {
        if (trim($this->query) === '') {
            return [];
        }
        return $this->articles->search($this->query);   // une méthode de repository (chapitre 3)
    }
}
```

```twig
<div {{ attributes }}>
    <input type="search" data-model="query" placeholder="Rechercher un article...">

    <ul>
        {% for article in this.results %}
            <li>{{ article.title }}</li>
        {% endfor %}
    </ul>
</div>
```

`data-model="query"` lie le champ à la `LiveProp` `query`. À chaque saisie, le composant interroge le
serveur, qui recalcule `getResults()` et renvoie la liste mise à jour. Une recherche instantanée, en
quelques lignes, **sans framework front**.

> **Attention** — Chaque frappe peut déclencher une requête. Pour une recherche, ajoute un délai
> (`data-model="debounce(300)|query"`) afin de ne pas marteler le serveur à chaque caractère. Et veille
> à ce que `search()` soit une requête **performante** (chapitre 3) : un Live Component met ta couche
> données à l'épreuve.

## Mercure : pousser des mises à jour en temps réel

Live Components répond à « **l'utilisateur agit**, l'interface réagit ». Mais comment mettre à jour
l'écran quand **un autre** utilisateur agit ? Si Alice poste un commentaire, comment l'écran de Bob,
déjà ouvert sur le même article, l'affiche-t-il **sans qu'il recharge** ?

C'est le rôle de **Mercure** : un protocole qui permet au **serveur de pousser** des mises à jour vers
les navigateurs abonnés, en temps réel. Le navigateur **s'abonne** à un sujet (ex.
« commentaires de l'article 12 ») ; le serveur **publie** une mise à jour ; tous les abonnés la
reçoivent instantanément.

```text
   Alice poste un commentaire
            │
            ▼
   ┌──────────────┐   publie sur le sujet « article/12/comments »
   │  Ton serveur │ ─────────────────────────────────────────────┐
   └──────────────┘                                               ▼
                                                          ┌───────────────┐
   Bob (déjà sur l'article 12) ◄──── pousse en temps réel ─┤ Hub Mercure   │
   Carole (idem)              ◄────────────────────────────┤               │
                                                          └───────────────┘
```

Mercure s'appuie sur un **hub** (un petit serveur dédié, fourni par Symfony en développement). Côté
Symfony :

```bash
$ composer require symfony/mercure-bundle
```

```bash
# .env — l'URL du hub (la Symfony CLI peut en lancer un en dev)
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
```

**Publier** une mise à jour depuis ton code (par exemple dans le handler du commentaire, chapitre 5) :

```php
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

public function publishComment(HubInterface $hub, Comment $comment): void
{
    $update = new Update(
        sprintf('article/%d/comments', $comment->getArticle()->getId()),   // le sujet
        json_encode(['author' => $comment->getAuthor()->getName(), 'content' => $comment->getContent()]),
    );

    $hub->publish($update);   // tous les abonnés à ce sujet reçoivent la mise à jour
}
```

**S'abonner** côté navigateur, en pratique, se fait élégamment avec **Turbo + Mercure** : une
`<turbo-stream-source>` abonne la page à un sujet, et le serveur pousse des *Turbo Streams* (des
fragments HTML) qui s'insèrent tout seuls dans la page. C'est la combinaison recommandée — tu publies
un fragment HTML, il apparaît chez tous les abonnés sans JavaScript applicatif.

> **À retenir** — **Live Component** rafraîchit l'écran **de celui qui agit**. **Mercure** pousse des
> mises à jour **vers tous les abonnés**, déclenchées par le serveur. Les deux se combinent souvent :
> Live pour l'interaction locale, Mercure pour la diffusion temps réel.

## Quel outil pour quel besoin ?

Récapitulons les briques UX des deux chapitres :

| Besoin | Outil |
| --- | --- |
| Comportement local pur navigateur (afficher/masquer) | Stimulus (ch. 9) |
| Navigation/formulaires fluides | Turbo Drive / Frames (ch. 9) |
| Bloc d'interface réutilisable | Twig Component |
| Interactivité dépendant des données, sans JS | Live Component |
| Mises à jour en temps réel vers plusieurs utilisateurs | Mercure (+ Turbo Streams) |

Le bon réflexe : **commence par le plus simple** qui répond au besoin. Beaucoup d'interactions se
règlent avec Stimulus ou une Turbo Frame ; ne sors un Live Component que si l'état serveur l'exige, et
Mercure que si tu as vraiment besoin de pousser vers plusieurs clients.

## Résumé

- Un **Twig Component** (`#[AsTwigComponent]`, balise `<twig:Nom />`) factorise HTML + logique
  d'interface réutilisables.
- Un **Live Component** (`#[AsLiveComponent]`) rend une portion de page **réactive côté serveur** :
  l'état vit dans des **`LiveProp`**, les **`LiveAction`** déclenchent un re-rendu, sans JavaScript.
- `data-model` **lie** un champ à une `LiveProp` (recherche instantanée) ; pense à `debounce` et à des
  requêtes performantes.
- **Mercure** permet au **serveur de pousser** des mises à jour temps réel vers les navigateurs
  abonnés ; combiné à **Turbo Streams**, on diffuse des fragments HTML sans JS applicatif.
- Choisis l'outil le plus simple : Stimulus/Turbo d'abord, Live Component si l'état serveur l'exige,
  Mercure pour diffuser vers plusieurs clients.

## Exercices

### Exercice 1 — Extraire un Twig Component

Transforme la « carte d'article » répétée dans tes templates en un Twig Component `ArticleCard`, et
utilise-le sur la page d'accueil avec `<twig:ArticleCard :article="article" />`.

<details>
<summary>Voir le corrigé</summary>

La démarche : on déplace le HTML répété dans un composant et on l'appelle comme une balise.

```php
// src/Twig/Components/ArticleCard.php
#[AsTwigComponent]
final class ArticleCard
{
    public Article $article;
}
```

```twig
{# templates/components/ArticleCard.html.twig #}
<article class="card">
    <h2><a href="{{ path('blog_show', { slug: article.slug }) }}">{{ article.title }}</a></h2>
    <p>par {{ article.author.name }}</p>
</article>
```

Sur l'accueil : `{% for article in articles %}<twig:ArticleCard :article="article" />{% endfor %}`. Le
HTML de carte n'existe plus qu'à un seul endroit.

</details>

### Exercice 2 — Un Live Component compteur de likes

Crée le Live Component `LikeButton` qui incrémente et affiche le nombre de « j'aime » d'un article sans
rechargement. Ajoute la propriété `likes` à l'entité (avec migration).

<details>
<summary>Voir le corrigé</summary>

La démarche : un `LiveProp` pour l'article, un `LiveAction` qui incrémente et persiste.

Classe et template comme dans la section « Live Components » ci-dessus. Points de vigilance :

- ajouter `private int $likes = 0;` avec getter/setter à `Article`, puis `make:migration` et
  `doctrine:migrations:migrate` ;
- le `use DefaultActionTrait;` est nécessaire ;
- le `<div {{ attributes }}>` racine est indispensable : c'est lui que Live Component remplace au
  rafraîchissement.

Clique sur le bouton : le compteur monte sans que la page se recharge, et la valeur est bien
enregistrée en base.

</details>

## Quiz

**1.** Qu'est-ce qu'un Twig Component ?
- A. Une route
- B. Un morceau d'interface (HTML + logique) réutilisable, appelé comme une balise
- C. Un type Doctrine

**2.** Dans un Live Component, à quoi sert `#[LiveProp]` ?
- A. À définir une route
- B. À marquer un état conservé entre les rafraîchissements
- C. À hacher un mot de passe

**3.** Que se passe-t-il quand on déclenche une `#[LiveAction]` ?
- A. Rien sans JavaScript écrit à la main
- B. Le serveur exécute la méthode, re-rend le composant et renvoie le HTML mis à jour
- C. La page entière se recharge

**4.** À quoi sert Mercure ?
- A. À paginer une liste
- B. À pousser des mises à jour temps réel du serveur vers les navigateurs abonnés
- C. À sécuriser l'API

**5.** Live Component vs Mercure : quelle différence ?
- A. Aucune
- B. Live rafraîchit l'écran de celui qui agit ; Mercure pousse vers tous les abonnés
- C. Mercure remplace Twig

<details>
<summary>Voir les réponses</summary>

1. **B** — Un bloc d'interface réutilisable, appelé comme une balise.
2. **B** — `LiveProp` porte l'état conservé entre rafraîchissements.
3. **B** — Le serveur exécute l'action et renvoie le composant re-rendu.
4. **B** — Mercure diffuse des mises à jour temps réel aux abonnés.
5. **B** — Interaction locale (Live) vs diffusion multi-clients (Mercure).

</details>

## Projet fil rouge

1. Installe `ux-twig-component` et extrais un composant `ArticleCard` réutilisé sur l'accueil et la
   page auteur.
2. Installe `ux-live-component` et ajoute le `LikeButton` réactif (compteur de « j'aime » sans
   rechargement).
3. Ajoute une **recherche instantanée** d'articles avec un Live Component (`data-model` + `debounce`).
4. (Optionnel) Installe `mercure-bundle` : quand un commentaire est posté, **pousse** son apparition en
   temps réel sur la page de l'article via une Turbo Stream.

Ton blog a maintenant une interface vivante. Au prochain chapitre, on garantit que tout ça reste
fiable avec des tests avancés et une intégration continue.

---

[← Chapitre précédent](09-symfony-ux-stimulus-turbo.md) · [Sommaire](README.md) · [Chapitre suivant →](11-tests-avances-qualite.md)
