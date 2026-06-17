# Doctrine avancé et performance

[← Chapitre précédent](02-architecture-maintenable.md) · [Sommaire](README.md) · [Chapitre suivant →](04-messenger.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- écrire des requêtes sur mesure avec le **QueryBuilder** et le **DQL** ;
- diagnostiquer et corriger le **problème N+1**, la cause n°1 de lenteur Doctrine ;
- comprendre le chargement **lazy** (paresseux) et **eager** (immédiat) des relations ;
- **paginer** une liste proprement ;
- réagir aux changements d'une entité avec un **entity listener** ;
- stocker un **value object** grâce à un **type Doctrine personnalisé**.

## Au-delà des méthodes magiques du repository

En partie 1, tu interrogeais la base avec `findAll()`, `findBy()`, `findOneBy()`. C'est parfait pour
les cas simples. Mais dès que tu veux **filtrer, trier, joindre ou agréger** finement, il te faut
écrire la requête toi-même. Deux outils pour ça, dans le **repository** de l'entité.

Le **QueryBuilder** construit une requête pas à pas, en PHP :

```php
<?php
// src/Repository/ArticleRepository.php
namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    // Les articles publiés d'une catégorie, du plus récent au plus ancien.
    public function findPublishedByCategory(string $categorySlug): array
    {
        return $this->createQueryBuilder('a')           // 'a' est l'alias de l'entité Article
            ->andWhere('a.published = :published')
            ->andWhere('a.category = :slug')             // relation, comparée par sa clé
            ->setParameter('published', true)
            ->setParameter('slug', $categorySlug)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();                               // un tableau d'objets Article
    }
}
```

Le **DQL** (*Doctrine Query Language*) est un langage de requête qui ressemble au SQL, mais qui parle
en **objets et propriétés**, pas en tables et colonnes :

```php
// La même idée, écrite en DQL
$dql = 'SELECT a FROM App\Entity\Article a
        WHERE a.published = :published
        ORDER BY a.createdAt DESC';

$query = $this->getEntityManager()->createQuery($dql)
    ->setParameter('published', true);

return $query->getResult();
```

> **Attention** — **Toujours** passer les valeurs par `setParameter`, jamais en les concaténant dans
> la chaîne. C'est ce qui protège des **injections SQL**. Concaténer une variable utilisateur dans une
> requête est une faille de sécurité classique.

> **Astuce** — Préfère le **QueryBuilder** : il est typé, composable (tu peux ajouter des `andWhere`
> conditionnellement), et plus facile à relire. Garde le DQL brut pour les requêtes très statiques.

## Le problème N+1 : la lenteur invisible

C'est **le** piège de performance avec un ORM. Imagine la page d'accueil qui liste 20 articles, et
pour chacun affiche le nom de son auteur :

```twig
{# templates/blog/index.html.twig #}
{% for article in articles %}
    <h2>{{ article.title }}</h2>
    <p>par {{ article.author.name }}</p>   {# accès à la relation author #}
{% endfor %}
```

Par défaut, Doctrine charge la liste des articles avec **1 requête**. Mais `article.author` n'est pas
encore chargé : au premier accès, Doctrine déclenche **une requête de plus** pour aller chercher
l'auteur. Pour 20 articles, ça fait **1 + 20 = 21 requêtes**. Avec 100 articles, 101. C'est le
**problème N+1** : 1 requête pour la liste, puis N requêtes pour les relations.

Le profiler te le montre crûment : ouvre l'icône base de données, tu verras 21 requêtes presque
identiques. C'est le premier signal à surveiller (tu l'as noté au chapitre 1).

**La solution : la jointure avec récupération** (`JOIN ... addSelect`). Tu demandes à Doctrine de
charger les auteurs **en même temps** que les articles, en une seule requête :

```php
// src/Repository/ArticleRepository.php
public function findPublishedWithAuthor(): array
{
    return $this->createQueryBuilder('a')
        ->addSelect('author')                 // on récupère aussi l'auteur dans le résultat
        ->join('a.author', 'author')          // jointure sur la relation
        ->andWhere('a.published = :p')
        ->setParameter('p', true)
        ->orderBy('a.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

Maintenant, accéder à `article.author` dans Twig ne déclenche **aucune** requête supplémentaire :
l'auteur est déjà là. On passe de 21 à **1 requête**.

> **À retenir** — Le `join` seul filtre mais ne récupère pas les objets liés : c'est le
> **`addSelect`** qui les charge en mémoire et évite le N+1. Retiens le couple **`join` +
> `addSelect`**.

## Lazy vs eager : quand la relation est-elle chargée ?

Par défaut, Doctrine charge les relations en mode **lazy** (paresseux) : la relation n'est chargée
qu'au moment où tu y accèdes. C'est un bon défaut — on ne charge que ce dont on a besoin — mais c'est
précisément ce qui cause le N+1 dans une boucle.

L'alternative, le mode **eager** (immédiat), charge toujours la relation avec l'entité. On peut le
configurer sur la relation, mais c'est rarement une bonne idée : ça alourdit **toutes** les requêtes,
même celles qui n'ont pas besoin de la relation.

> **À retenir** — Garde le **lazy** par défaut, et résous le N+1 **au cas par cas** avec `join` +
> `addSelect` dans la requête qui en a besoin. C'est plus précis que de tout passer en eager.

Un cas particulier mérite attention : `findPublishedWithAuthor()` charge les auteurs, mais si Twig
accède aussi à `article.category`, tu retombes dans un N+1 sur les catégories. Ajoute alors une
deuxième jointure. Le bon réflexe : **regarde le profiler**, identifie les relations accédées dans la
boucle, et joins celles-là.

## Paginer une liste

Afficher 10 000 articles d'un coup est impensable : lent à charger, illisible. On **pagine** : on
affiche 10 articles par page, avec des liens « page suivante ». Doctrine fournit un **Paginator** prêt
à l'emploi.

```php
<?php
// src/Repository/ArticleRepository.php
use Doctrine\ORM\Tools\Pagination\Paginator;

public function findPublishedPaginated(int $page, int $perPage = 10): Paginator
{
    $query = $this->createQueryBuilder('a')
        ->andWhere('a.published = :p')
        ->setParameter('p', true)
        ->orderBy('a.createdAt', 'DESC')
        ->setFirstResult(($page - 1) * $perPage)   // on saute les pages précédentes
        ->setMaxResults($perPage)                   // on limite au nombre par page
        ->getQuery();

    return new Paginator($query);   // sait compter le total et itérer la page courante
}
```

Dans le contrôleur, on lit le numéro de page et on passe le tout à la vue :

```php
#[Route('/articles', name: 'blog_index')]
public function index(ArticleRepository $repo, Request $request): Response
{
    $page = max(1, $request->query->getInt('page', 1));   // ?page=2 dans l'URL, défaut 1
    $paginator = $repo->findPublishedPaginated($page);

    $total = count($paginator);                           // total d'articles (le Paginator sait compter)
    $pages = (int) ceil($total / 10);

    return $this->render('blog/index.html.twig', [
        'articles' => $paginator,   // itérable dans Twig comme une liste
        'page' => $page,
        'pages' => $pages,
    ]);
}
```

> **Astuce** — Pour une pagination plus riche (liens « 1 2 3 … »), des paquets comme
> **KnpPaginatorBundle** ou **Pagerfanta** font le travail. Mais comprendre `setFirstResult` /
> `setMaxResults` te suffit pour démarrer et pour savoir ce qui se passe dessous.

## Réagir aux changements : les entity listeners

Souvent, tu veux exécuter du code **automatiquement** quand une entité est créée ou modifiée :
renseigner une date, calculer un champ, invalider un cache. Doctrine déclenche des **événements de
cycle de vie** (*lifecycle events*) à ces moments : `prePersist` (avant la première sauvegarde),
`preUpdate` (avant une mise à jour), `postLoad` (après chargement), etc.

Le moyen propre d'y réagir est un **entity listener** : une classe dédiée, à l'écart de l'entité.
Exemple : renseigner `createdAt` automatiquement à la création.

```php
<?php
// src/Doctrine/ArticleTimestampListener.php
namespace App\Doctrine;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, entity: Article::class)]
class ArticleTimestampListener
{
    // Appelée juste avant le premier enregistrement de l'article.
    public function prePersist(Article $article): void
    {
        if ($article->getCreatedAt() === null) {
            $article->setCreatedAt(new \DateTimeImmutable());
        }
    }
}
```

L'attribut `#[AsEntityListener]` suffit : Symfony l'enregistre tout seul. Tu n'as plus à penser à
renseigner `createdAt` dans chaque service.

> **Attention** — Les listeners ne doivent contenir qu'une logique **liée à la persistance**
> (dates techniques, champs dérivés). Ne mets pas de logique métier importante dedans : elle devient
> invisible et difficile à tester. Pour une vraie règle métier, garde-la dans un **service**
> (chapitre 2).

## Stocker un value object : un type Doctrine personnalisé

Au chapitre 2, on a créé un value object `Email`. Comment le stocker en base, sachant que Doctrine ne
connaît que des types comme `string` ou `integer` ? On crée un **type Doctrine personnalisé** : il
explique à Doctrine comment convertir `Email` en `string` (pour la base) et inversement (au
chargement).

```php
<?php
// src/Doctrine/EmailType.php
namespace App\Doctrine;

use App\ValueObject\Email;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class EmailType extends Type
{
    public const NAME = 'email';

    public function getName(): string
    {
        return self::NAME;
    }

    // Type de colonne en base : ici une chaîne classique.
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getStringTypeDeclarationSQL($column);
    }

    // De l'objet PHP vers la base : on stocke la chaîne.
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        return $value instanceof Email ? $value->value() : null;
    }

    // De la base vers l'objet PHP : on reconstruit l'Email.
    public function convertToPHPValue($value, AbstractPlatform $platform): ?Email
    {
        return $value === null ? null : new Email($value);
    }
}
```

On enregistre le type, puis on l'utilise sur une propriété d'entité :

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            email: App\Doctrine\EmailType
```

```php
// Dans une entité
#[ORM\Column(type: 'email')]
private Email $email;
```

Désormais, la propriété `$email` est **toujours un objet `Email` valide** dans ton code, et une
simple chaîne en base. La validité est garantie par construction (chapitre 2), et la conversion est
transparente.

> **À retenir** — Un type Doctrine personnalisé fait le **pont** entre un value object riche côté PHP
> et une colonne simple côté base. C'est ce qui rend les value objects pratiques au quotidien.

## Résumé

- **QueryBuilder** (préféré, composable) et **DQL** permettent des requêtes sur mesure ; passe
  toujours les valeurs par **`setParameter`** contre les injections.
- Le **problème N+1** (1 requête + N pour les relations dans une boucle) se diagnostique au profiler
  et se corrige avec **`join` + `addSelect`**.
- Garde le chargement **lazy** par défaut et résous le N+1 au cas par cas plutôt que de tout passer
  en **eager**.
- **Pagine** avec `setFirstResult` / `setMaxResults` et le **Paginator** de Doctrine.
- Un **entity listener** (`#[AsEntityListener]`) réagit aux événements de cycle de vie : réserve-le à
  la logique de persistance.
- Un **type Doctrine personnalisé** stocke un value object en faisant la conversion objet ↔ colonne.

## Exercices

### Exercice 1 — Traquer un N+1

Sur la page d'accueil du blog (liste d'articles affichant l'auteur et la catégorie), ouvre le
profiler et compte les requêtes SQL. Écris une méthode de repository qui charge articles, auteurs et
catégories en une seule requête, et vérifie que le compteur retombe.

<details>
<summary>Voir le corrigé</summary>

La démarche : on joint et on récupère les deux relations accédées dans la boucle Twig.

```php
public function findPublishedWithAuthorAndCategory(): array
{
    return $this->createQueryBuilder('a')
        ->addSelect('author', 'category')      // on récupère les deux relations
        ->join('a.author', 'author')
        ->join('a.category', 'category')
        ->andWhere('a.published = :p')
        ->setParameter('p', true)
        ->orderBy('a.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}
```

Utilise cette méthode dans le contrôleur d'accueil. Le profiler doit afficher **1 requête** au lieu de
1 + 2×N. Compare avec le chiffre noté au chapitre 1.

</details>

### Exercice 2 — Renseigner updatedAt automatiquement

Ajoute un champ `updatedAt` à l'entité `Article` et un entity listener qui le met à jour
automatiquement avant chaque modification (`preUpdate`).

<details>
<summary>Voir le corrigé</summary>

La démarche : on écoute l'événement `preUpdate`, déclenché avant une mise à jour.

```php
<?php
// src/Doctrine/ArticleUpdatedAtListener.php
namespace App\Doctrine;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::preUpdate, entity: Article::class)]
class ArticleUpdatedAtListener
{
    public function preUpdate(Article $article): void
    {
        $article->setUpdatedAt(new \DateTimeImmutable());
    }
}
```

N'oublie pas la propriété `#[ORM\Column(nullable: true)] private ?\DateTimeImmutable $updatedAt` et la
migration (`make:migration` puis `doctrine:migrations:migrate`).

</details>

## Quiz

**1.** Qu'est-ce que le problème N+1 ?
- A. Une erreur de syntaxe DQL
- B. 1 requête pour une liste, puis N requêtes pour charger une relation dans une boucle
- C. Un problème de pagination

**2.** Comment éviter un N+1 sur une relation affichée en boucle ?
- A. `join` sans `addSelect`
- B. `join` + `addSelect` pour charger la relation en une requête
- C. Passer toutes les relations en lazy

**3.** Pourquoi passer les valeurs par `setParameter` ?
- A. Pour aller plus vite
- B. Pour se protéger des injections SQL
- C. C'est obligatoire pour trier

**4.** À quoi sert un type Doctrine personnalisé ?
- A. À paginer une liste
- B. À convertir un value object en colonne et inversement
- C. À écouter les événements de cycle de vie

<details>
<summary>Voir les réponses</summary>

1. **B** — Une requête pour la liste, puis une par élément pour la relation.
2. **B** — `addSelect` charge effectivement la relation en mémoire.
3. **B** — Les paramètres liés protègent des injections SQL.
4. **B** — Il fait le pont objet ↔ colonne pour un value object.

</details>

## Projet fil rouge

1. Corrige le **N+1** de la page d'accueil (exercice 1) ; note dans `NOTES.md` le nombre de requêtes
   avant/après.
2. Ajoute la **pagination** à la liste des articles (10 par page) avec le `Paginator`.
3. Remplace la gestion manuelle de `createdAt`/`updatedAt` par des **entity listeners**.
4. (Optionnel) Transforme l'e-mail de l'utilisateur en value object `Email` stocké via un **type
   Doctrine personnalisé**.

Ton blog est plus rapide et plus propre côté données. Au prochain chapitre, on déporte les
traitements lents en arrière-plan avec Messenger.

---

[← Chapitre précédent](02-architecture-maintenable.md) · [Sommaire](README.md) · [Chapitre suivant →](04-messenger.md)
