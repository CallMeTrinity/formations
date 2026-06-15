# Sérialisation et API moderne avec API Platform

[← Chapitre précédent](05-messenger-production-scheduler.md) · [Sommaire](README.md) · [Chapitre suivant →](07-securite-avancee.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre ce qu'est la **sérialisation** et maîtriser les **groupes** de sérialisation ;
- distinguer une API « bricolée » d'une **API REST** structurée ;
- exposer une entité en API complète avec **API Platform**, presque sans code ;
- choisir les **opérations** exposées (lister, lire, créer, modifier, supprimer) ;
- contrôler les **champs** lus et écrits, **paginer** et **filtrer**.

## Rappel : qu'est-ce qu'une API et pourquoi ?

En partie 1, tu as renvoyé du JSON avec `$this->json()`. Une **API** (*Application Programming
Interface*) permet à **d'autres programmes** — une application mobile, un site front en JavaScript, un
autre service — de consommer tes données dans un format machine (souvent **JSON**) plutôt que des
pages HTML.

Le standard dominant est **REST** : on expose des **ressources** (les articles, les commentaires)
identifiées par des **URL**, et on agit dessus avec les **verbes HTTP** :

| Verbe HTTP | Sens | Exemple |
| --- | --- | --- |
| `GET` | Lire | `GET /api/articles` (liste), `GET /api/articles/12` (un article) |
| `POST` | Créer | `POST /api/articles` |
| `PUT` / `PATCH` | Modifier | `PATCH /api/articles/12` |
| `DELETE` | Supprimer | `DELETE /api/articles/12` |

Construire tout ça à la main (une route par opération, sérialisation, validation, pagination,
documentation) représente énormément de code répétitif. On va d'abord comprendre la brique de base —
la **sérialisation** — puis laisser **API Platform** générer le reste.

## La sérialisation et les groupes

**Sérialiser**, c'est transformer un objet PHP en un format transportable (JSON). **Désérialiser**,
c'est l'inverse : reconstruire un objet à partir de JSON reçu. Le composant **Serializer** de Symfony
fait les deux.

Le problème immédiat : si tu sérialises une entité `User`, tu risques d'exposer son **mot de passe
haché**, ses relations entières, des champs internes. Tu veux **choisir** ce qui sort. C'est le rôle
des **groupes de sérialisation** : tu étiquettes chaque propriété avec un ou plusieurs groupes, et tu
précises à l'appel quel groupe utiliser.

```php
<?php
// src/Entity/Article.php (extrait)
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Column(length: 200)]
#[Groups(['article:read', 'article:write'])]   // exposé en lecture ET en écriture
private string $title;

#[ORM\Column(type: 'text')]
#[Groups(['article:read', 'article:write'])]
private string $content;

#[ORM\Column]
#[Groups(['article:read'])]                     // exposé en lecture seule (jamais écrit par le client)
private \DateTimeImmutable $createdAt;

// Pas de groupe : ce champ ne sortira dans aucune réponse d'API.
#[ORM\Column(nullable: true)]
private ?string $internalNote = null;
```

À la sérialisation, on précise le groupe voulu :

```php
// Avec le service Serializer injecté
$json = $serializer->serialize($article, 'json', ['groups' => ['article:read']]);
// Seuls title, content et createdAt apparaissent ; internalNote est exclu.
```

> **À retenir** — Les **groupes** séparent ce qu'on **lit** (`article:read`) de ce qu'on **écrit**
> (`article:write`). Une propriété sans groupe n'est **jamais exposée**. C'est ta première ligne de
> défense contre les fuites de données.

## API Platform : l'API sans la plomberie

Écrire à la main toutes les opérations REST est fastidieux. **API Platform** est un projet bâti sur
Symfony qui **génère** une API REST complète à partir de tes entités : routes, sérialisation,
validation, pagination, filtres, et même une **documentation interactive**. Tu décris **quoi** exposer,
il s'occupe du **comment**.

```bash
$ composer require api
```

Cette commande installe API Platform et sa configuration. Une fois installé, visite **`/api`** dans ton
navigateur : tu y trouves une interface (Swagger UI) qui documente et permet de **tester** ton API.
Pour l'instant elle est vide ; déclarons une ressource.

## Exposer une entité en une annotation

On transforme `Article` en **ressource d'API** avec l'attribut `#[ApiResource]` :

```php
<?php
// src/Entity/Article.php (extrait)
namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['article:read']],    // groupes utilisés en lecture
    denormalizationContext: ['groups' => ['article:write']], // groupes acceptés en écriture
)]
class Article
{
    // ... propriétés annotées avec #[Groups(...)] comme ci-dessus
}
```

C'est tout. API Platform crée automatiquement **toutes** les opérations REST :

```text
GET    /api/articles         liste paginée des articles
GET    /api/articles/{id}    un article
POST   /api/articles         créer un article
PUT    /api/articles/{id}    remplacer un article
PATCH  /api/articles/{id}    modifier partiellement
DELETE /api/articles/{id}    supprimer
```

Recharge `/api` : la ressource `Article` apparaît avec toutes ses opérations, testables depuis le
navigateur. La **validation** que tu as posée en partie 1 (`#[Assert\...]`) est appliquée
automatiquement aux créations et modifications. La **pagination** est active par défaut (30 éléments
par page).

> **À retenir** — Une entité + `#[ApiResource]` + des groupes = une API REST complète, documentée et
> validée. Tu n'écris ni route, ni contrôleur, ni sérialiseur. C'est exactement le genre de plomberie
> qu'un framework doit t'épargner.

## Choisir les opérations exposées

Exposer **toutes** les opérations est rarement souhaitable. Peut-être veux-tu que l'API permette de
**lire** les articles mais pas de les **supprimer** par ce biais. Tu listes alors explicitement les
opérations voulues :

```php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    operations: [
        new GetCollection(),   // GET /api/articles
        new Get(),             // GET /api/articles/{id}
        new Post(),            // POST /api/articles
        // pas de Put, Patch ni Delete : ces opérations n'existeront pas
    ],
    normalizationContext: ['groups' => ['article:read']],
    denormalizationContext: ['groups' => ['article:write']],
)]
class Article { /* ... */ }
```

Chaque opération peut aussi recevoir sa **propre configuration** (groupes, sécurité, etc.). On
verra au chapitre 7 comment restreindre une opération à certains rôles (`security: "is_granted(...)"`).

## Paginer et filtrer

La **pagination** est automatique. Le client demande une page via l'URL :

```text
GET /api/articles?page=2          deuxième page
GET /api/articles?itemsPerPage=10 dix éléments par page (si autorisé)
```

La réponse inclut les liens vers les pages suivantes/précédentes, sans que tu écrives quoi que ce soit.

Pour les **filtres**, tu déclares lesquels sont autorisés sur la ressource. Exemple : filtrer les
articles par titre et trier par date.

```php
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;

#[ApiResource(/* ... */)]
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]   // recherche partielle sur le titre
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]             // tri par date
class Article { /* ... */ }
```

Le client peut alors écrire :

```text
GET /api/articles?title=symfony            articles dont le titre contient « symfony »
GET /api/articles?order[createdAt]=desc    triés du plus récent au plus ancien
```

> **Astuce** — N'expose **que** les filtres dont tu as besoin. Chaque filtre ouvert est une surface
> de requête possible : un filtre mal pensé peut permettre des requêtes coûteuses. Reste minimal.

## Exposer un DTO plutôt que l'entité

Parfois, tu ne veux pas exposer l'entité directement (couplage trop fort entre la base et le contrat
d'API). API Platform sait exposer un **DTO** (chapitre 2) et le relier à ta logique via un *state
processor* (pour écrire) et un *state provider* (pour lire). C'est plus avancé, mais retiens le
principe : **le contrat d'API est public et stable, ton modèle interne peut évoluer librement**. Pour
un blog, exposer l'entité avec des groupes bien choisis suffit largement ; le DTO devient utile quand
l'API et la base divergent.

> **À retenir** — Commence simple (entité + groupes). Passe au DTO le jour où le contrat d'API et ton
> modèle interne ont des raisons de diverger. Ne te complique pas la vie par anticipation.

## Résumé

- **Sérialiser** = objet → JSON ; **désérialiser** = JSON → objet. Le **Serializer** fait les deux.
- Les **groupes** (`#[Groups(['x:read', 'x:write'])]`) contrôlent les champs lus/écrits ; sans groupe,
  un champ n'est pas exposé.
- Une **API REST** expose des ressources via les verbes HTTP (`GET`, `POST`, `PATCH`, `DELETE`).
- **API Platform** (`composer require api`) génère une API complète à partir de `#[ApiResource]` :
  routes, validation, pagination, doc interactive sur `/api`.
- On choisit les **opérations** (`Get`, `GetCollection`, `Post`…) et on déclare les **filtres**
  (`#[ApiFilter]`) à exposer.
- Exposer un **DTO** (via state provider/processor) découple le contrat d'API du modèle interne, quand
  c'est nécessaire.

## Exercices

### Exercice 1 — Exposer les articles en lecture

Transforme `Article` en ressource API exposant uniquement `GetCollection` et `Get`, avec un groupe
`article:read` sur `title`, `content`, `createdAt`. Vérifie sur `/api` que la suppression n'est pas
proposée et que `internalNote` (ou tout champ non groupé) n'apparaît pas.

<details>
<summary>Voir le corrigé</summary>

La démarche : on limite les opérations et on n'expose que les champs voulus.

```php
#[ApiResource(
    operations: [new GetCollection(), new Get()],
    normalizationContext: ['groups' => ['article:read']],
)]
class Article { /* title, content, createdAt en #[Groups(['article:read'])] */ }
```

Sur `/api`, seules les opérations `GET` apparaissent. La réponse d'un article ne contient que les
trois champs groupés : tout le reste est masqué. La validation et la pagination sont déjà en place.

</details>

### Exercice 2 — Filtrer et trier

Ajoute un filtre de recherche partielle sur `title` et un filtre de tri sur `createdAt`. Teste
`GET /api/articles?title=...&order[createdAt]=desc` depuis l'interface `/api`.

<details>
<summary>Voir le corrigé</summary>

La démarche : on déclare les deux filtres sur la ressource.

```php
#[ApiFilter(SearchFilter::class, properties: ['title' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['createdAt'])]
```

Sur `/api`, les paramètres `title` et `order[createdAt]` apparaissent dans le formulaire de l'opération
`GET /api/articles`. Une recherche partielle remonte les articles dont le titre contient le terme ; le
tri réordonne la liste. Vérifie au passage, dans le profiler, que la requête SQL générée est saine.

</details>

## Quiz

**1.** À quoi servent les groupes de sérialisation ?
- A. À paginer une liste
- B. À contrôler quels champs sont lus et écrits (et donc exposés)
- C. À hacher les mots de passe

**2.** Que génère `#[ApiResource]` sur une entité ?
- A. Une migration de base de données
- B. Les routes, la sérialisation, la validation et la doc des opérations REST
- C. Un template Twig

**3.** Comment empêcher la suppression via l'API ?
- A. Supprimer l'entité
- B. Ne pas inclure l'opération `Delete` dans la liste des opérations
- C. C'est impossible

**4.** Un champ d'entité sans aucun groupe de sérialisation…
- A. apparaît toujours dans les réponses
- B. n'apparaît dans aucune réponse d'API
- C. provoque une erreur

<details>
<summary>Voir les réponses</summary>

1. **B** — Les groupes pilotent les champs lus/écrits et donc exposés.
2. **B** — API Platform génère toute la plomberie REST.
3. **B** — On liste explicitement les opérations et on omet `Delete`.
4. **B** — Sans groupe, le champ reste interne.

</details>

## Projet fil rouge

1. Installe API Platform (`composer require api`) et visite `/api`.
2. Expose `Article` en `GetCollection` + `Get` avec un groupe `article:read` sur les champs publics
   (titre, contenu, date, auteur via un sous-ensemble de champs).
3. Ajoute les filtres de recherche par titre et de tri par date.
4. (Optionnel) Expose `Comment` en lecture et en création (`Post`), pour préparer la pose de
   commentaires depuis l'API.

Ton blog a maintenant une API documentée et navigable. Mais elle est **ouverte à tous** : n'importe
qui pourrait créer ou modifier. Au prochain chapitre, on la sécurise — authenticators, JWT, voters.

---

[← Chapitre précédent](05-messenger-production-scheduler.md) · [Sommaire](README.md) · [Chapitre suivant →](07-securite-avancee.md)
