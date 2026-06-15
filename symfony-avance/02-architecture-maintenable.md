# Garder une application maintenable : DTO, value objects, services métier

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-doctrine-avance-performance.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- reconnaître un **contrôleur trop gros** et savoir où déplacer sa logique ;
- séparer les responsabilités avec des **services métier** (cas d'usage) ;
- utiliser un **DTO** pour transporter des données proprement entre les couches ;
- créer un **value object** pour fiabiliser une donnée (ex. une adresse e-mail valide par
  construction) ;
- dépendre d'une **interface** plutôt que d'une implémentation, et savoir pourquoi.

## Le problème : le code qui grossit

En partie 1, tes contrôleurs faisaient un peu de tout : lire la requête, valider, parler à Doctrine,
préparer l'affichage. Tant que l'application est petite, ça passe. Mais quand chaque action grossit,
le contrôleur devient un fourre-tout impossible à tester et à relire. Voici un cas typique, la
création d'un article :

```php
// src/Controller/ArticleController.php — version « tout dans le contrôleur »
#[Route('/admin/article/new', name: 'admin_article_new')]
public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
{
    $article = new Article();
    $form = $this->createForm(ArticleType::class, $article);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $article->setSlug(strtolower($slugger->slug($article->getTitle())));   // logique métier
        $article->setAuthor($this->getUser());                                  // logique métier
        $article->setCreatedAt(new \DateTimeImmutable());                       // logique métier
        $em->persist($article);
        $em->flush();
        // ... et bientôt : envoyer un mail, notifier, journaliser...

        return $this->redirectToRoute('admin_articles');
    }

    return $this->render('admin/article/new.html.twig', ['form' => $form]);
}
```

Le problème n'est pas qu'il « marche pas » : il marche. Le problème est que la **logique métier**
(comment on crée un article) est mélangée à la **plomberie web** (lire la requête, rendre une vue).
Demain, si tu veux créer un article depuis une commande console ou depuis l'API, tu devras
**recopier** cette logique. Et tu ne peux pas la tester sans simuler une requête HTTP complète.

> **À retenir** — Un contrôleur doit **orchestrer**, pas **décider**. Il lit l'entrée, appelle un
> service qui fait le travail, et renvoie une réponse. La règle informelle : si un contrôleur fait
> plus de quelques lignes de « vraie logique », cette logique a sa place ailleurs.

## Extraire un service métier (cas d'usage)

Déplaçons la création d'un article dans un **service** dédié. Un service métier porte un **verbe** :
il représente une action de ton application (« créer un article », « publier », « poster un
commentaire »). On parle aussi de *use case* (cas d'usage).

```php
<?php
// src/Service/ArticlePublisher.php
namespace App\Service;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArticlePublisher
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
    ) {}

    // Crée et enregistre un article. Toute la logique « comment on crée un article » vit ici.
    public function create(Article $article, User $author): Article
    {
        $article->setSlug(strtolower($this->slugger->slug($article->getTitle())));
        $article->setAuthor($author);
        $article->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }
}
```

Le contrôleur redevient mince : il lit le formulaire et délègue.

```php
// src/Controller/ArticleController.php — version « contrôleur mince »
#[Route('/admin/article/new', name: 'admin_article_new')]
public function new(Request $request, ArticlePublisher $publisher): Response
{
    $article = new Article();
    $form = $this->createForm(ArticleType::class, $article);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $publisher->create($article, $this->getUser());   // une ligne : on délègue

        return $this->redirectToRoute('admin_articles');
    }

    return $this->render('admin/article/new.html.twig', ['form' => $form]);
}
```

Grâce à l'**autowiring** (vu en partie 1), tu n'as rien à configurer : tu demandes
`ArticlePublisher` en argument, Symfony l'injecte. Et maintenant, créer un article depuis une commande
ou l'API revient à appeler **le même service** : la logique n'existe qu'à un seul endroit.

> **Astuce** — Nomme tes services par l'action qu'ils réalisent : `ArticlePublisher`,
> `CommentNotifier`, `ReadingTimeCalculator`. Un nom en « -er » ou « -or » qui décrit un travail est
> souvent un bon service.

## Le DTO : transporter des données sans trimballer une entité

Un **DTO** (*Data Transfer Object*, « objet de transfert de données ») est une classe simple, sans
logique, dont le seul rôle est de **transporter des données** d'un point à un autre. Pas de méthode
métier, pas de lien avec la base : juste des propriétés.

Pourquoi en a-t-on besoin ? Parce qu'une **entité Doctrine** porte des contraintes (elle est liée à la
base, à des relations, à un état). L'utiliser pour autre chose que la persistance crée des couplages
gênants. Exemple : un formulaire de contact n'a aucune raison de manipuler une entité ; il manipule un
DTO.

```php
<?php
// src/Dto/ContactRequest.php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

// Un simple porteur de données, validé par les contraintes Symfony.
class ContactRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 10)]
    public string $message = '';
}
```

Tu peux **brancher un formulaire directement sur ce DTO** (au lieu d'une entité) :

```php
// Dans le contrôleur
$contact = new ContactRequest();
$form = $this->createForm(ContactType::class, $contact);   // ContactType::getParent() pointe sur ce DTO
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    // $contact->email et $contact->message sont remplis et validés
    $messageSender->send($contact);   // on passe le DTO à un service
}
```

Le DTO sert aussi à **recevoir les données d'une API** (chapitre 6) ou à **exposer** une vue précise
d'une entité sans révéler tous ses champs. Retiens l'idée : **l'entité parle à la base, le DTO parle
au reste du monde.**

> **À retenir** — Utilise un DTO dès que des données traversent une frontière (formulaire, API,
> service) et que tu ne veux pas y faire transiter une entité Doctrine avec toutes ses dépendances.

## Le value object : une donnée correcte par construction

Un **value object** (« objet-valeur ») représente une **valeur** du domaine, définie par son
contenu et non par une identité. Une adresse e-mail, un montant d'argent, une plage de dates : ce
sont des valeurs. L'idée clé : un value object est **valide dès sa création** et **immuable** (on ne
le modifie pas, on en crée un autre).

Prenons l'e-mail. Au lieu de balader une `string` qu'on doit re-valider partout, on crée un type :

```php
<?php
// src/ValueObject/Email.php
namespace App\ValueObject;

final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        // La validation est dans le constructeur : impossible de créer un Email invalide.
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(sprintf('Adresse e-mail invalide : "%s".', $value));
        }

        $this->value = strtolower($value);   // on normalise au passage
    }

    public function value(): string
    {
        return $this->value;
    }

    public function domain(): string
    {
        // Comportement métier : le domaine de l'adresse.
        return substr($this->value, strpos($this->value, '@') + 1);
    }
}
```

L'intérêt est énorme : partout où tu reçois un `Email`, tu **sais** qu'il est valide — la vérification
a eu lieu une fois, à la construction. Tu ne te demandes plus « est-ce que cette chaîne est un vrai
e-mail ? » à chaque usage. Et tu peux y attacher du comportement (`domain()`) au lieu de l'éparpiller.

> **Attention** — Ne transforme pas *tout* en value object. Réserve-les aux valeurs **avec des règles**
> (format, plage, invariants). Une simple chaîne libre n'en a pas besoin. On verra au chapitre 3
> comment stocker un value object en base avec un **type Doctrine personnalisé**.

## Dépendre d'une interface, pas d'une implémentation

Dernier pilier : programmer **contre une interface**. Une *interface* est un contrat : elle déclare
**ce qu'un service sait faire**, sans dire **comment**. Tes services dépendent du contrat, pas de la
classe concrète.

Reprenons la notification d'un commentaire. Aujourd'hui par e-mail, demain peut-être par SMS ou
notification push. Définis le contrat :

```php
<?php
// src/Notifier/CommentNotifierInterface.php
namespace App\Notifier;

use App\Entity\Comment;

interface CommentNotifierInterface
{
    public function notifyNewComment(Comment $comment): void;
}
```

Une implémentation concrète :

```php
<?php
// src/Notifier/EmailCommentNotifier.php
namespace App\Notifier;

use App\Entity\Comment;
use Symfony\Component\Mailer\MailerInterface;

class EmailCommentNotifier implements CommentNotifierInterface
{
    public function __construct(private MailerInterface $mailer) {}

    public function notifyNewComment(Comment $comment): void
    {
        // ... compose et envoie l'e-mail (vu en partie 1)
    }
}
```

Tes autres services dépendent de l'**interface** :

```php
public function __construct(private CommentNotifierInterface $notifier) {}
```

Symfony fait l'**autowiring par interface** automatiquement quand il n'existe qu'une implémentation :
il injecte `EmailCommentNotifier`. Le jour où tu ajoutes `SmsCommentNotifier`, tu choisis lequel
injecter dans `config/services.yaml`, **sans toucher** aux services qui dépendent du contrat. Tes
tests, eux, peuvent fournir une fausse implémentation triviale (chapitre 11).

```yaml
# config/services.yaml — si plusieurs implémentations existent, on désigne celle par défaut
services:
    App\Notifier\CommentNotifierInterface: '@App\Notifier\EmailCommentNotifier'
```

> **À retenir** — Dépendre d'une interface, c'est rendre ton code **remplaçable** et **testable**. Le
> reste de l'application ne connaît que le **quoi** (le contrat), jamais le **comment** (la classe).

## Vue d'ensemble : qui fait quoi

Voici la répartition des rôles qu'on vise désormais :

| Couche | Rôle | Exemple |
| --- | --- | --- |
| Contrôleur | Lire l'entrée, déléguer, renvoyer une réponse | `ArticleController::new` |
| Service métier | Réaliser une action du domaine | `ArticlePublisher::create` |
| DTO | Transporter des données entre couches | `ContactRequest` |
| Value object | Représenter une valeur valide et immuable | `Email` |
| Entité | Représenter une donnée persistée | `Article` |
| Interface | Définir un contrat remplaçable | `CommentNotifierInterface` |

Ce n'est pas une religion : on applique ces outils **quand ils résolvent un problème**, pas par
principe. Mais à mesure que le blog grandit, ils gardent le code lisible et testable.

## Résumé

- Un **contrôleur** orchestre ; il ne contient pas la logique métier. Déplace-la dans un **service**.
- Un **service métier** porte une action du domaine (`ArticlePublisher`) et n'existe qu'à un seul
  endroit : on peut l'appeler depuis le web, une commande ou l'API.
- Un **DTO** transporte des données entre couches sans trimballer une entité Doctrine.
- Un **value object** est valide par construction et immuable : il fiabilise une valeur à règles
  (e-mail, montant).
- Dépendre d'une **interface** rend le code remplaçable et testable ; Symfony l'autowire quand il n'y
  a qu'une implémentation.

## Exercices

### Exercice 1 — Extraire un service

Dans le blog, identifie une action où le contrôleur fait de la « vraie logique » (par exemple poster
un commentaire). Crée un service métier (`CommentPublisher`) qui encapsule cette logique, et allège le
contrôleur pour qu'il délègue.

<details>
<summary>Voir le corrigé</summary>

La démarche : on isole le « comment on poste un commentaire » dans un service.

```php
<?php
// src/Service/CommentPublisher.php
namespace App\Service;

use App\Entity\Article;
use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;

class CommentPublisher
{
    public function __construct(private EntityManagerInterface $em) {}

    public function publish(Comment $comment, Article $article): Comment
    {
        $comment->setArticle($article);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($comment);
        $this->em->flush();

        return $comment;
    }
}
```

Le contrôleur n'a plus qu'à appeler `$commentPublisher->publish($comment, $article)`. La logique est
maintenant réutilisable (par l'API au chapitre 6) et testable isolément (chapitre 11).

</details>

### Exercice 2 — Un value object Slug

Crée un value object `Slug` qui n'accepte que des chaînes en minuscules, chiffres et tirets (motif
`^[a-z0-9-]+$`), et lève une exception sinon. Ajoute une méthode `value()`.

<details>
<summary>Voir le corrigé</summary>

La démarche : la validation vit dans le constructeur, l'objet est donc toujours valide.

```php
<?php
// src/ValueObject/Slug.php
namespace App\ValueObject;

final class Slug
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^[a-z0-9-]+$/', $value)) {
            throw new \InvalidArgumentException(sprintf('Slug invalide : "%s".', $value));
        }
        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }
}
```

Tout code qui reçoit un `Slug` est garanti d'avoir une valeur conforme : plus besoin de re-vérifier.

</details>

## Quiz

**1.** Quel est le rôle d'un contrôleur dans une architecture saine ?
- A. Contenir toute la logique métier
- B. Orchestrer : lire l'entrée, déléguer à un service, renvoyer une réponse
- C. Parler directement à la base de données pour chaque action

**2.** Quand utiliser un DTO plutôt qu'une entité ?
- A. Pour stocker des données en base
- B. Pour transporter des données entre couches (formulaire, API, service) sans trimballer une entité
- C. Jamais, l'entité suffit toujours

**3.** Qu'est-ce qui caractérise un value object ?
- A. Il est valide par construction et immuable
- B. Il est forcément lié à une table en base
- C. Il contient toute la logique de l'application

**4.** Pourquoi dépendre d'une interface plutôt que d'une classe concrète ?
- A. Pour écrire moins de code
- B. Pour rendre l'implémentation remplaçable et le code testable
- C. C'est obligatoire pour l'autowiring

<details>
<summary>Voir les réponses</summary>

1. **B** — Le contrôleur orchestre et délègue.
2. **B** — Le DTO transporte des données à travers les frontières, l'entité parle à la base.
3. **A** — Valide à la construction, immuable ensuite.
4. **B** — On dépend du contrat (le quoi), pas de l'implémentation (le comment).

</details>

## Projet fil rouge

1. Crée le service `ArticlePublisher` et fais déléguer le contrôleur d'admin (création d'article).
2. Crée le service `CommentPublisher` (exercice 1) et branche-le sur le contrôleur public.
3. Introduis l'interface `CommentNotifierInterface` avec une implémentation `EmailCommentNotifier`
   (même corps que l'envoi de mail de la partie 1) ; injecte l'**interface** là où tu notifies.
4. Vérifie avec `php bin/console debug:autowiring Notifier` que Symfony résout bien l'interface.

Ton code est réorganisé : chaque action a son service, les frontières sont claires. Au prochain
chapitre, on s'attaque à la performance des requêtes Doctrine.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-doctrine-avance-performance.md)
