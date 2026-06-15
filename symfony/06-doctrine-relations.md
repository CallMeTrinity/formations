# Manipuler les données et les relations

[← Chapitre précédent](05-doctrine-base-de-donnees.md) · [Sommaire](README.md) · [Chapitre suivant →](07-formulaires.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- **enregistrer** un objet en base avec l'`EntityManager` (`persist` / `flush`) ;
- **lire** des données via un **repository** (`find`, `findAll`, `findBy`, `findOneBy`) ;
- créer une **relation** entre deux entités (`ManyToOne` / `OneToMany`) ;
- remplir ta base avec des **fixtures** (données de test) ;
- brancher tes pages Twig sur de **vrais** articles issus de la base.

## Enregistrer : `persist` et `flush`

Pour créer une ligne en base, tu construis un objet entité, puis tu confies son enregistrement à
l'**EntityManager** (le « gestionnaire d'entités », pièce centrale de Doctrine). Symfony te le fournit
en argument du contrôleur, typé `EntityManagerInterface` :

```php
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/blog/nouveau-test', name: 'blog_test_create')]
public function createTest(EntityManagerInterface $em): Response
{
    $article = new Article();
    $article->setTitle('Mon premier article en base');
    $article->setSlug('mon-premier-article');
    $article->setContent('Cet article vient vraiment de la base de données.');
    $article->setCreatedAt(new \DateTimeImmutable());
    $article->setPublished(true);

    $em->persist($article);  // « prépare » l'enregistrement de cet objet
    $em->flush();            // exécute réellement les requêtes SQL en base

    return new Response('Article créé avec l\'id ' . $article->getId());
}
```

Deux étapes distinctes :

- **`persist($article)`** dit à Doctrine « je veux que cet objet soit suivi et enregistré ». Rien
  n'est encore écrit en base.
- **`flush()`** déclenche les requêtes SQL : c'est là que tout est réellement écrit. Tu peux
  `persist` plusieurs objets puis un seul `flush` : Doctrine regroupe tout efficacement.

Après le `flush`, l'`id` généré par la base est disponible (`$article->getId()`).

> **À retenir** — On **persiste** des objets, puis on **flush** une fois. `persist` planifie,
> `flush` exécute. Oublier le `flush`, c'est le bug classique du débutant : aucune erreur, mais rien
> en base.

## Lire : le repository

À chaque entité, `make:entity` a créé un **repository** : une classe dédiée à la **lecture** de cette
entité. Symfony te l'injecte par son type, ici `ArticleRepository`. Il fournit des méthodes prêtes à
l'emploi :

```php
use App\Repository\ArticleRepository;

#[Route('/blog', name: 'blog_index')]
public function index(ArticleRepository $articleRepository): Response
{
    $articles = $articleRepository->findAll();             // tous les articles
    // $article  = $articleRepository->find(42);           // par clé primaire (id)
    // $articles = $articleRepository->findBy(['published' => true], ['createdAt' => 'DESC']);
    // $article  = $articleRepository->findOneBy(['slug' => 'mon-premier-article']);

    return $this->render('blog/index.html.twig', [
        'articles' => $articles,
    ]);
}
```

Les quatre méthodes que tu utiliseras tout le temps :

| Méthode | Renvoie | Exemple |
| --- | --- | --- |
| `find($id)` | une entité par sa clé primaire (ou `null`) | `find(42)` |
| `findAll()` | toutes les entités (tableau) | `findAll()` |
| `findBy([...], [...])` | les entités correspondant à des critères (tableau), avec tri | `findBy(['published' => true], ['createdAt' => 'DESC'])` |
| `findOneBy([...])` | la première entité correspondant (ou `null`) | `findOneBy(['slug' => $slug])` |

Le premier tableau de `findBy`/`findOneBy` est un jeu de **critères** (`colonne => valeur`), le
deuxième (optionnel) un **tri** (`colonne => 'ASC' ou 'DESC'`).

> **Astuce** — Pour des requêtes plus complexes (jointures, conditions avancées), tu ajouteras des
> méthodes personnalisées au repository en utilisant le **QueryBuilder**. On en montre un exemple en
> exercice ; pour l'essentiel, les quatre méthodes ci-dessus suffisent.

### Récupérer une entité directement dans l'action

Pour la page d'un article, tu pourrais écrire `findOneBy(['slug' => $slug])`. Mais Symfony fait encore
plus simple : si tu **demandes l'entité en argument** et que le paramètre de route correspond à une de
ses propriétés, Symfony la **récupère automatiquement** :

```php
use App\Entity\Article;

#[Route('/blog/{slug}', name: 'blog_show')]
public function show(Article $article): Response
{
    // Symfony a cherché l'article dont le slug = {slug}. Introuvable => 404 automatique.
    return $this->render('blog/show.html.twig', [
        'article' => $article,
    ]);
}
```

Le paramètre de route `{slug}` est rapproché de la propriété `slug` de l'entité `Article` : Symfony
exécute la recherche pour toi, et renvoie une **404** si rien ne correspond. C'est la façon idiomatique
d'écrire une page de détail.

> **Attention** — Cette récupération automatique exige que le nom du paramètre d'URL corresponde à une
> propriété de l'entité (`{slug}` ↔ `slug`, ou `{id}` ↔ `id`). Si les noms diffèrent, utilise plutôt
> `findOneBy` explicitement, ou l'attribut `#[MapEntity(...)]` pour préciser la correspondance.

## Les relations entre entités

Un blog relie naturellement les données : un article appartient à une **catégorie**, et reçoit des
**commentaires**. C'est le cœur d'une base relationnelle.

### `ManyToOne` : un article dans une catégorie

Plusieurs articles peuvent partager **une** catégorie : c'est une relation **plusieurs-à-un**
(*ManyToOne*) du point de vue de l'article. Ajoute-la avec `make:entity` :

```bash
$ php bin/console make:entity Article
# New property name: category
# Field type: relation
# What class should this entity be related to? Category
# Relation type? ManyToOne
# Is the Article.category property allowed to be null? no
# Do you want to add a new property to Category so that you can access/update
#   Article objects from it? yes
# New field name inside Category [articles]: articles
```

Le maker ajoute deux choses :

- dans `Article`, une propriété `category` (type `Category`) avec l'attribut `#[ORM\ManyToOne]` ;
- dans `Category`, une propriété `articles` (une **collection** d'articles) avec
  `#[ORM\OneToMany]` — c'est l'**autre côté** de la même relation.

Côté base, Doctrine ajoute une colonne `category_id` dans la table `article` : une **clé étrangère**
qui pointe vers la catégorie. Tu manipules tout cela en objets :

```php
$article->setCategory($category);     // affecter une catégorie
$nom = $article->getCategory()->getName();
foreach ($category->getArticles() as $article) { /* ... */ }
```

N'oublie pas le cycle migration après chaque relation ajoutée :

```bash
$ php bin/console make:migration
$ php bin/console doctrine:migrations:migrate
```

### `OneToMany` : les commentaires d'un article

Crée maintenant l'entité `Comment` (champs `author` string, `content` text, `createdAt`
datetime_immutable), puis ajoute-lui une relation `ManyToOne` vers `Article` (plusieurs commentaires
pour un article), en demandant à ajouter `comments` du côté `Article`. Tu obtiens la relation
réciproque : `Article.comments` (OneToMany) ↔ `Comment.article` (ManyToOne).

> **À retenir** — Une relation a **deux côtés**. Le côté `ManyToOne` (ici `Article.category`,
> `Comment.article`) porte la **clé étrangère** en base : c'est le côté « propriétaire ». Le côté
> `OneToMany` (`Category.articles`, `Article.comments`) est une **collection** pratique pour parcourir
> les éléments liés.

## Remplir la base avec des fixtures

Tester avec une base vide est pénible. Les **fixtures** sont des données de démonstration que tu
charges en une commande. Installe le paquet (en *dev* seulement) :

```bash
$ composer require --dev orm-fixtures
```

Génère une classe de fixtures :

```bash
$ php bin/console make:fixtures ArticleFixtures
```

Remplis `src/DataFixtures/ArticleFixtures.php` :

```php
<?php
// src/DataFixtures/ArticleFixtures.php
namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ArticleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $categorie = new Category();
        $categorie->setName('Symfony');
        $categorie->setSlug('symfony');
        $manager->persist($categorie);

        for ($i = 1; $i <= 5; $i++) {
            $article = new Article();
            $article->setTitle('Article de démonstration ' . $i);
            $article->setSlug('article-demo-' . $i);
            $article->setContent('Contenu de l\'article ' . $i . '.');
            $article->setCreatedAt(new \DateTimeImmutable());
            $article->setPublished(true);
            $article->setCategory($categorie);
            $manager->persist($article);
        }

        $manager->flush();
    }
}
```

Charge-les (attention : la commande **vide** d'abord la base) :

```bash
$ php bin/console doctrine:fixtures:load
```

Confirme par `yes`. Ta base contient maintenant une catégorie et cinq articles.

## Brancher les pages sur la base

Tout est en place pour supprimer les données en dur. La page `/blog` utilise déjà
`$articleRepository->findAll()` (vu plus haut), et la page `/blog/{slug}` reçoit l'`Article`
automatiquement. Côté Twig, **rien ne change** : tu accèdes aux propriétés avec le point, exactement
comme avec les tableaux du chapitre 4.

```twig
{# templates/blog/index.html.twig #}
{% for article in articles %}
    <li>
        <a href="{{ path('blog_show', { slug: article.slug }) }}">{{ article.title }}</a>
        <small>dans {{ article.category.name }}</small>
    </li>
{% endfor %}
```

`article.title` appelle en réalité `getTitle()`, et `article.category.name` enchaîne
`getCategory().getName()`. Recharge `/blog` : tes vrais articles s'affichent, tirés de la base.

## Résumé

- Pour **écrire** : construire l'objet, `$em->persist($objet)` puis `$em->flush()`. `persist`
  planifie, `flush` exécute.
- Pour **lire** : le **repository** de l'entité, avec `find`, `findAll`, `findBy` (critères + tri) et
  `findOneBy`.
- Symfony peut **injecter directement l'entité** dans l'action si le paramètre de route correspond à
  une propriété (404 automatique si absente).
- Une **relation** se crée avec `make:entity` : `ManyToOne` (porte la clé étrangère) ↔ `OneToMany`
  (collection réciproque). Toujours suivi du cycle migration.
- Les **fixtures** (`orm-fixtures` + `doctrine:fixtures:load`) remplissent la base de données de test.
- Côté Twig, on accède aux propriétés et relations avec le **point** (`article.category.name`).

## Exercices

### Exercice 1 — Articles publiés, du plus récent au plus ancien

Modifie l'action `index` pour n'afficher que les articles **publiés** (`published = true`), triés du
plus récent au plus ancien.

<details>
<summary>Voir le corrigé</summary>

La démarche : `findBy` accepte des critères **et** un tri.

```php
$articles = $articleRepository->findBy(
    ['published' => true],
    ['createdAt' => 'DESC']
);
```

Le premier tableau filtre (`published = true`), le second trie par `createdAt` décroissant. Inutile
d'écrire du SQL.

</details>

### Exercice 2 — Une requête personnalisée dans le repository

Ajoute à `ArticleRepository` une méthode `findRecents(int $limite)` qui renvoie les `$limite` derniers
articles publiés. Utilise le **QueryBuilder**.

<details>
<summary>Voir le corrigé</summary>

La démarche : le QueryBuilder construit une requête pas à pas, en objets.

```php
// src/Repository/ArticleRepository.php
public function findRecents(int $limite): array
{
    return $this->createQueryBuilder('a')
        ->andWhere('a.published = :etat')
        ->setParameter('etat', true)
        ->orderBy('a.createdAt', 'DESC')
        ->setMaxResults($limite)
        ->getQuery()
        ->getResult();
}
```

`createQueryBuilder('a')` donne un alias `a` à l'entité. `andWhere` ajoute une condition (la valeur
passe par `setParameter`, ce qui protège des injections SQL). `setMaxResults` limite le nombre de
résultats. On l'appelle ensuite : `$articleRepository->findRecents(3)`.

</details>

## Quiz

**1.** Quelle paire de méthodes enregistre un nouvel objet en base ?
- A. `save()` puis `commit()`
- B. `persist()` puis `flush()`
- C. `insert()` puis `update()`

**2.** Quelle méthode du repository renvoie la première entité correspondant à des critères ?
- A. `findAll()`
- B. `find()`
- C. `findOneBy()`

**3.** Dans une relation `ManyToOne` / `OneToMany`, quel côté porte la clé étrangère en base ?
- A. Le côté `OneToMany`
- B. Le côté `ManyToOne`
- C. Les deux

**4.** À quoi servent les fixtures ?
- A. À corriger des bugs de Doctrine
- B. À remplir la base de données avec des données de test/démo
- C. À créer les tables

<details>
<summary>Voir les réponses</summary>

1. **B** — `persist` planifie l'enregistrement, `flush` exécute les requêtes SQL.
2. **C** — `findOneBy` renvoie une seule entité (ou `null`) selon des critères.
3. **B** — Le côté `ManyToOne` porte la clé étrangère (`category_id` dans `article`).
4. **B** — Les fixtures chargent des données de démonstration pour travailler sur une base remplie.

</details>

## Projet fil rouge

Ton blog affiche enfin de vraies données.

1. Ajoute la relation **`ManyToOne`** `Article` → `Category` (avec `articles` côté `Category`).
2. Crée l'entité **`Comment`** (`author`, `content`, `createdAt`) reliée à `Article` en `ManyToOne`
   (avec `comments` côté `Article`). Génère et applique les migrations.
3. Installe `orm-fixtures`, écris des fixtures qui créent une ou deux catégories et plusieurs articles
   publiés, puis charge-les.
4. Branche la page `/blog` sur `findBy(['published' => true], ['createdAt' => 'DESC'])` et la page
   `/blog/{slug}` sur la récupération automatique de l'`Article`. Affiche la catégorie de chaque
   article dans Twig.

Tu sais lire et écrire des données, mais l'écriture se fait encore en dur dans le code. Au chapitre
suivant, on crée des **formulaires** pour saisir et valider un article depuis le navigateur.

---

[← Chapitre précédent](05-doctrine-base-de-donnees.md) · [Sommaire](README.md) · [Chapitre suivant →](07-formulaires.md)
