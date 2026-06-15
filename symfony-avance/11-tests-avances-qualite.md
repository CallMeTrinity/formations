# Tests avancés et qualité de code

[← Chapitre précédent](10-twig-live-components-mercure.md) · [Sommaire](README.md) · [Chapitre suivant →](12-industrialisation-deploiement.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- distinguer les **types de tests** (unitaire, intégration, fonctionnel) et savoir lequel écrire ;
- isoler une dépendance avec un **mock** (doublure) ;
- tester des **services**, des **voters**, l'**API** et l'**asynchrone** ;
- préparer une base de test propre avec des **fixtures** ;
- mesurer la **couverture** et faire respecter la **qualité** avec PHPStan et CS Fixer ;
- automatiser tout ça dans une **intégration continue** (GitHub Actions).

## Trois types de tests, trois usages

En partie 1, tu as écrit des **tests fonctionnels** (simuler une requête HTTP, vérifier la réponse).
C'est un type parmi d'autres. La pyramide des tests distingue :

- **Tests unitaires** : vérifient une **classe isolée** (un service, un voter, un value object), sans
  base ni HTTP. Rapides, nombreux. Idéaux pour la **logique métier**.
- **Tests d'intégration** : vérifient que **plusieurs composants** collaborent (un service + Doctrine,
  par exemple), souvent avec une vraie base de test.
- **Tests fonctionnels** : simulent une **requête HTTP complète** et vérifient la réponse. Plus lents,
  moins nombreux. Idéaux pour les **parcours** clés.

```text
        /\        peu de tests fonctionnels (parcours)
       /  \
      /    \      quelques tests d'intégration
     /______\     beaucoup de tests unitaires (logique)
```

> **À retenir** — Vise **beaucoup d'unitaires** (rapides, ciblés) et **peu de fonctionnels** (lents,
> globaux). La logique métier que tu as extraite en services (chapitre 2) se teste vite et bien en
> unitaire : c'est le retour sur investissement de cette architecture.

## Tester un service en unitaire avec un mock

Reprenons `CommentPublisher` (chapitre 2). Il dépend de `EntityManagerInterface` et de
`MessageBusInterface`. En test unitaire, on ne veut **ni vraie base, ni vrai bus** : on fournit des
**doublures** (*mocks*) qui imitent ces dépendances et permettent de **vérifier** comment elles sont
utilisées.

```php
<?php
// tests/Service/CommentPublisherTest.php
namespace App\Tests\Service;

use App\Entity\Article;
use App\Entity\Comment;
use App\Message\CommentPostedNotification;
use App\Service\CommentPublisher;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class CommentPublisherTest extends TestCase
{
    public function testPublishPersistsAndDispatches(): void
    {
        // On crée des doublures des dépendances.
        $em = $this->createMock(EntityManagerInterface::class);
        $bus = $this->createMock(MessageBusInterface::class);

        // On attend que le bus reçoive UN message de notification, et on simule sa réponse.
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CommentPostedNotification::class))
            ->willReturn(new Envelope(new \stdClass()));

        $publisher = new CommentPublisher($em, $bus);

        $comment = new Comment();
        $article = new Article();
        $publisher->publish($comment, $article);

        // La date a-t-elle été renseignée par le service ?
        $this->assertNotNull($comment->getCreatedAt());
        $this->assertSame($article, $comment->getArticle());
    }
}
```

Ce test est **rapide** (aucune base) et **précis** : il vérifie le **comportement** du service (il
relie le commentaire à l'article, renseigne la date, dispatche une notification) sans dépendre de
l'infrastructure. `expects($this->once())` impose que `dispatch` soit appelé exactement une fois : si
quelqu'un casse cette logique plus tard, le test échoue.

> **À retenir** — Un **mock** remplace une dépendance pour **isoler** la classe testée et **vérifier
> les interactions**. Tu testes *ta* logique, pas Doctrine ni Messenger (eux sont déjà testés par
> leurs auteurs).

## Tester un voter

Les voters (chapitre 7) portent des règles d'autorisation critiques : ils méritent des tests
unitaires. Pas besoin de HTTP : on appelle directement la logique.

```php
<?php
// tests/Security/ArticleVoterTest.php
namespace App\Tests\Security;

use App\Entity\Article;
use App\Entity\User;
use App\Security\ArticleVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ArticleVoterTest extends TestCase
{
    public function testAuthorCanEditOwnArticle(): void
    {
        $author = new User();
        $article = (new Article())->setAuthor($author);

        $token = new UsernamePasswordToken($author, 'main', $author->getRoles());
        $voter = new ArticleVoter();

        $result = $voter->vote($token, $article, [ArticleVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testStrangerCannotEditArticle(): void
    {
        $article = (new Article())->setAuthor(new User());
        $stranger = new User();

        $token = new UsernamePasswordToken($stranger, 'main', $stranger->getRoles());
        $voter = new ArticleVoter();

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, $article, [ArticleVoter::EDIT])
        );
    }
}
```

Deux cas, deux vérités à protéger : l'auteur peut éditer, un inconnu non. Si un jour la règle est
modifiée par erreur, le test t'avertit avant la mise en production.

## Tester l'API et l'asynchrone

Pour l'**API**, on écrit un test fonctionnel qui interroge un point d'API et vérifie le JSON renvoyé. Le
`KernelBrowser` (le client de test vu en partie 1) sait envoyer des en-têtes — dont le JWT.

```php
<?php
// tests/Api/ArticleApiTest.php
namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ArticleApiTest extends WebTestCase
{
    public function testListIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles');

        $this->assertResponseIsSuccessful();                 // code 2xx
        $this->assertResponseHeaderSame('content-type', 'application/json; charset=utf-8');
    }

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/articles', server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['title' => 'Test', 'content' => 'Contenu']));

        $this->assertResponseStatusCodeSame(401);            // refusé sans JWT
    }
}
```

Pour l'**asynchrone** (Messenger), l'astuce est de router les messages vers un transport **`in-memory`**
dans l'environnement de test : les messages ne partent pas dans une vraie file, ils sont **collectés**
et tu peux **vérifier** qu'ils ont bien été dispatchés.

```yaml
# config/packages/messenger.yaml — section réservée aux tests
when@test:
    framework:
        messenger:
            transports:
                async: 'in-memory://'   # en test, on collecte les messages au lieu de les envoyer
```

```php
// Dans un test fonctionnel qui poste un commentaire
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

$transport = $this->getContainer()->get('messenger.transport.async');
\assert($transport instanceof InMemoryTransport);

$this->assertCount(1, $transport->getSent());   // une notification a bien été dispatchée
```

> **À retenir** — En test, remplace l'infrastructure réelle par des **doublures** : `in-memory` pour
> Messenger, une base de test dédiée pour Doctrine. Tes tests deviennent rapides, reproductibles et
> indépendants de l'environnement.

## Une base de test propre : les fixtures

Un test d'intégration ou fonctionnel qui touche la base a besoin de **données connues** : un article,
un auteur, quelques commentaires. Les **fixtures** (jeux de données) créent cet état de départ.

```bash
$ composer require --dev doctrine/doctrine-fixtures-bundle
```

```php
<?php
// src/DataFixtures/ArticleFixtures.php
namespace App\DataFixtures;

use App\Entity\Article;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ArticleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $author = (new User())->setEmail('alice@blog.test')->setName('Alice');
        $manager->persist($author);

        for ($i = 1; $i <= 5; $i++) {
            $article = (new Article())
                ->setTitle("Article de test $i")
                ->setContent('Contenu de démonstration.')
                ->setAuthor($author)
                ->setPublished(true);
            $manager->persist($article);
        }

        $manager->flush();
    }
}
```

On charge les fixtures dans une **base de test séparée** (jamais la base de développement !) :

```bash
$ php bin/console doctrine:database:create --env=test
$ php bin/console doctrine:migrations:migrate --env=test --no-interaction
$ php bin/console doctrine:fixtures:load --env=test --no-interaction
```

> **Astuce** — Le paquet **DAMADoctrineTestBundle** enveloppe chaque test dans une **transaction**
> annulée à la fin : la base revient à son état initial après chaque test, automatiquement. C'est le
> standard pour des tests d'intégration rapides et isolés.

## Mesurer la couverture

La **couverture de code** (*code coverage*) mesure quelle proportion de ton code est exécutée par les
tests. Ce n'est pas une note de qualité absolue, mais un **indicateur des angles morts**.

```bash
# Nécessite Xdebug ou PCOV activé
$ php bin/phpunit --coverage-text         # résumé en console
$ php bin/phpunit --coverage-html var/coverage   # rapport navigable
```

> **Attention** — 100 % de couverture ne signifie pas « zéro bug » : on peut exécuter une ligne sans
> vraiment tester son comportement. Vise une **bonne couverture de la logique métier** (services,
> voters, workflow) plutôt qu'un chiffre global. La couverture montre **ce qui n'est pas testé** ;
> c'est sa vraie utilité.

## Garder la qualité : PHPStan et CS Fixer

Deux outils complètent les tests, sur un autre axe : la **qualité statique** du code (sans l'exécuter).

**PHPStan** analyse ton code et repère des erreurs **avant l'exécution** : appel d'une méthode qui
n'existe pas, type incompatible, variable potentiellement nulle. Il a des **niveaux** (0 à 9 ou
`max`) : plus le niveau est élevé, plus il est strict.

```bash
$ composer require --dev phpstan/phpstan
$ vendor/bin/phpstan analyse src --level=6
# Sortie : la liste des problèmes détectés, fichier par fichier
```

**PHP CS Fixer** (ou `php-cs-fixer`) corrige automatiquement le **style** du code (indentation, ordre
des imports, conventions) pour qu'il soit uniforme dans tout le projet.

```bash
$ composer require --dev friendsofphp/php-cs-fixer
$ vendor/bin/php-cs-fixer fix src --dry-run   # montre ce qui serait corrigé
$ vendor/bin/php-cs-fixer fix src             # applique les corrections
```

> **À retenir** — Les **tests** vérifient le **comportement**, **PHPStan** vérifie la **cohérence des
> types**, **CS Fixer** vérifie le **style**. Les trois ensemble forment un filet de sécurité complet
> et automatisable.

## Automatiser : l'intégration continue

L'**intégration continue** (*CI*) exécute automatiquement tests et contrôles qualité **à chaque
push** sur le dépôt. Plus personne ne peut « oublier » de lancer les tests : la machine le fait, et
signale tout échec avant la fusion. Avec **GitHub Actions**, on décrit ça dans un fichier YAML.

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
    tests:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v4

            - name: Installer PHP 8.4
              uses: shivammathur/setup-php@v2
              with:
                  php-version: '8.4'

            - name: Installer les dépendances
              run: composer install --no-interaction --prefer-dist

            - name: Analyse statique (PHPStan)
              run: vendor/bin/phpstan analyse src --level=6

            - name: Vérifier le style (CS Fixer)
              run: vendor/bin/php-cs-fixer fix src --dry-run --diff

            - name: Lancer les tests
              run: php bin/phpunit
```

À chaque `push`, GitHub exécute ces étapes sur une machine propre. Si une étape échoue (un test rouge,
une erreur PHPStan), tu le vois immédiatement dans l'onglet « Actions » et sur la *pull request*. C'est
le filet qui garde le projet sain à plusieurs.

> **À retenir** — La CI transforme tes vérifications locales en **garde-fou automatique** : tests +
> PHPStan + style à chaque push. Un projet sans CI repose sur la discipline ; un projet avec CI repose
> sur une machine qui ne se fatigue jamais.

## Résumé

- **Unitaire** (logique isolée, rapide), **intégration** (composants ensemble), **fonctionnel**
  (requête HTTP) : beaucoup d'unitaires, peu de fonctionnels.
- Un **mock** isole la classe testée et vérifie ses interactions (ex. `dispatch` appelé une fois).
- Teste services, **voters**, **API** (JWT inclus) ; pour l'asynchrone, route Messenger vers
  **`in-memory`** en test et vérifie les messages collectés.
- Les **fixtures** créent une base de test connue ; **DAMADoctrineTestBundle** isole chaque test dans
  une transaction.
- La **couverture** révèle les angles morts (pas une garantie de qualité).
- **PHPStan** (types), **CS Fixer** (style) et les tests forment un filet complet, automatisé par une
  **CI** (GitHub Actions) qui s'exécute à chaque push.

## Exercices

### Exercice 1 — Tester le LikeButton (ou un service)

Écris un test unitaire pour la logique d'incrément du « j'aime » (chapitre 10) ou pour
`ArticlePublisher::create` : vérifie que la date est renseignée et que la dépendance attendue est
appelée, à l'aide de mocks.

<details>
<summary>Voir le corrigé</summary>

La démarche : mêmes principes que `CommentPublisherTest`. Pour `ArticlePublisher` :

```php
public function testCreateSetsSlugAndAuthor(): void
{
    $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
    $slugger = new \Symfony\Component\String\Slugger\AsciiSlugger();   // pas besoin de mock ici

    $publisher = new \App\Service\ArticlePublisher($em, $slugger);

    $article = (new \App\Entity\Article())->setTitle('Mon Article');
    $author = new \App\Entity\User();

    $publisher->create($article, $author);

    $this->assertSame('mon-article', $article->getSlug());
    $this->assertSame($author, $article->getAuthor());
    $this->assertNotNull($article->getCreatedAt());
}
```

On vérifie le résultat observable de la logique métier, sans vraie base.

</details>

### Exercice 2 — Vérifier qu'une notification est dispatchée

Écris un test fonctionnel qui poste un commentaire et vérifie qu'**un** message a bien été envoyé au
transport `in-memory`.

<details>
<summary>Voir le corrigé</summary>

La démarche : on configure `async: 'in-memory://'` en `when@test`, puis on inspecte le transport.

```php
public function testPostingCommentDispatchesNotification(): void
{
    $client = static::createClient();
    // ... soumettre le formulaire de commentaire sur un article existant (fixtures)

    $transport = $this->getContainer()->get('messenger.transport.async');
    $this->assertCount(1, $transport->getSent());
}
```

`getSent()` retourne les messages collectés par le transport en mémoire : on vérifie qu'il y en a
exactement un. Aucun e-mail réel n'est envoyé pendant le test.

</details>

## Quiz

**1.** Quel type de test privilégier pour la logique métier d'un service ?
- A. Fonctionnel
- B. Unitaire (rapide, isolé, avec mocks si besoin)
- C. Aucun, c'est inutile

**2.** À quoi sert un mock ?
- A. À accélérer la base de données
- B. À remplacer une dépendance pour isoler la classe testée et vérifier ses interactions
- C. À générer une migration

**3.** Comment tester l'envoi d'un message Messenger sans rien envoyer pour de vrai ?
- A. C'est impossible
- B. En routant le transport vers `in-memory` en test et en inspectant les messages collectés
- C. En désactivant Messenger

**4.** Que vérifie PHPStan ?
- A. Le comportement à l'exécution
- B. La cohérence des types et des appels, sans exécuter le code
- C. Le style d'indentation

**5.** Qu'apporte une intégration continue (CI) ?
- A. Elle déploie automatiquement en production
- B. Elle exécute tests et contrôles qualité à chaque push, comme garde-fou automatique
- C. Elle remplace les tests

<details>
<summary>Voir les réponses</summary>

1. **B** — La logique métier se teste en unitaire, rapidement.
2. **B** — Le mock isole et permet de vérifier les interactions.
3. **B** — Transport `in-memory` + inspection des messages.
4. **B** — PHPStan analyse les types sans exécuter le code.
5. **B** — La CI automatise tests et qualité à chaque push.

</details>

## Projet fil rouge

1. Écris des **tests unitaires** pour `ArticleVoter` (auteur OK, inconnu refusé) et pour un service
   métier (`ArticlePublisher` ou `CommentPublisher`) avec des mocks.
2. Ajoute des **fixtures** et une **base de test** ; écris un test **fonctionnel** d'API (lecture
   publique, création refusée sans JWT).
3. Configure le transport **`in-memory`** en test et vérifie qu'un commentaire posté dispatche une
   notification.
4. Installe **PHPStan** et **CS Fixer**, corrige ce qu'ils signalent, et crée un workflow
   **GitHub Actions** qui lance les trois à chaque push.

Ton blog est désormais protégé par un vrai filet de sécurité automatisé. Au dernier chapitre
technique, on l'industrialise et on le déploie : Docker, cache, secrets, monitoring.

---

[← Chapitre précédent](10-twig-live-components-mercure.md) · [Sommaire](README.md) · [Chapitre suivant →](12-industrialisation-deploiement.md)
