# Les tests automatisés

[← Chapitre précédent](09-securite.md) · [Sommaire](README.md) · [Chapitre suivant →](11-aller-plus-loin.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer pourquoi écrire des **tests automatisés** ;
- installer et lancer **PHPUnit** dans un projet Symfony ;
- distinguer **tests unitaires**, **d'intégration** et **fonctionnels** ;
- tester un **service** (test unitaire) et une **page** (test fonctionnel) ;
- faire des **assertions** sur une réponse HTTP.

## Pourquoi tester

À chaque nouvelle fonctionnalité, tu risques de casser une fonctionnalité existante sans t'en rendre
compte. Vérifier tout à la main à chaque changement est impossible. Un **test automatisé** est un bout
de code qui vérifie qu'une partie de l'application fait ce qu'elle doit. Tu le lances en une commande,
autant de fois que tu veux.

Les tests t'apportent :

- de la **confiance** pour modifier ton code sans tout casser (la fameuse *non-régression*) ;
- une **documentation vivante** : un test montre comment une fonction est censée se comporter ;
- un **gain de temps** : la machine vérifie en quelques secondes ce qui te prendrait des heures.

> **À retenir** — Un test n'est pas une perte de temps : c'est une assurance. Plus ton application
> grandit, plus les tests deviennent indispensables pour la faire évoluer sereinement.

## Installer PHPUnit

**PHPUnit** est l'outil standard de test en PHP. Installe le pack de test de Symfony :

```bash
$ composer require --dev symfony/test-pack
```

Il apporte PHPUnit, des classes de base pratiques, et un fichier de configuration `phpunit.xml.dist`.
Les tests vivent dans le dossier `tests/`. Lance la suite (vide pour l'instant) :

```bash
$ php bin/phpunit
```

> **Astuce** — Symfony utilise l'environnement **`test`** pour les tests (rappel du chapitre 2),
> isolé du `dev`. Tu peux lui donner une base de données séparée via `.env.test`, pour ne jamais
> polluer tes données de développement.

## Les trois familles de tests

| Type | Ce qu'il vérifie | Vitesse |
| --- | --- | --- |
| **Unitaire** | une classe isolée (un service), sans base ni HTTP | très rapide |
| **Intégration** | plusieurs services ensemble, avec le conteneur Symfony | moyen |
| **Fonctionnel** | une page complète, comme un navigateur (requête → réponse) | plus lent |

On commence par les deux extrêmes, les plus utiles au quotidien : l'unitaire et le fonctionnel.

## Tester un service (test unitaire)

Un test unitaire vérifie une classe **seule**, sans Symfony autour. Idéal pour notre service
`ReadingTime` du chapitre 8. Crée `tests/Service/ReadingTimeTest.php` :

```php
<?php
// tests/Service/ReadingTimeTest.php
namespace App\Tests\Service;

use App\Service\ReadingTime;
use PHPUnit\Framework\TestCase;

class ReadingTimeTest extends TestCase
{
    public function testCourtTexteVautUneMinute(): void
    {
        $service = new ReadingTime();

        $minutes = $service->minutesPour('Trois petits mots.');

        $this->assertSame(1, $minutes);  // au moins 1 minute
    }

    public function testTexteLongPlusieursMinutes(): void
    {
        $service = new ReadingTime();
        $texte = str_repeat('mot ', 600);  // 600 mots

        $minutes = $service->minutesPour($texte);

        $this->assertSame(3, $minutes);  // 600 / 200 = 3
    }
}
```

Anatomie d'un test :

- la classe **étend `TestCase`** (la classe de base PHPUnit pour un test pur) ;
- chaque **méthode `test...`** est un test indépendant ;
- on suit le schéma **AAA** : *Arrange* (préparer l'objet), *Act* (appeler la méthode), *Assert*
  (vérifier le résultat) ;
- une **assertion** comme `assertSame(attendu, obtenu)` réussit si les deux valeurs sont identiques,
  et fait échouer le test sinon.

Lance :

```bash
$ php bin/phpunit tests/Service/ReadingTimeTest.php
```

Une sortie verte « OK (2 tests) » signifie que tout passe.

> **À retenir** — Un test unitaire n'a besoin ni de base de données ni de serveur : il instancie la
> classe avec `new` et vérifie son comportement. C'est rapide et précis.

## Tester une page (test fonctionnel)

Un test fonctionnel simule un navigateur : il envoie une requête HTTP à ton application et inspecte la
réponse. La classe de base est **`WebTestCase`**. Crée `tests/Controller/BlogControllerTest.php` :

```php
<?php
// tests/Controller/BlogControllerTest.php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BlogControllerTest extends WebTestCase
{
    public function testPageBlogRepond(): void
    {
        $client = static::createClient();           // un faux navigateur
        $crawler = $client->request('GET', '/blog'); // visite la page

        $this->assertResponseIsSuccessful();        // statut HTTP 2xx
        $this->assertSelectorTextContains('h1', 'Le blog'); // le <h1> contient ce texte
    }

    public function testPageInexistanteRenvoie404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/blog/cet-article-nexiste-pas');

        $this->assertResponseStatusCodeSame(404);
    }
}
```

Ce qui se passe :

- **`createClient()`** démarre l'application en mémoire et te donne un **client** (un navigateur
  simulé) ;
- **`request('GET', '/blog')`** envoie une vraie requête à travers tout le framework (routeur,
  contrôleur, Twig) ;
- les **assertions spécialisées** vérifient la réponse : `assertResponseIsSuccessful` (statut 2xx),
  `assertResponseStatusCodeSame(404)`, `assertSelectorTextContains('h1', ...)` (un élément HTML
  contient un texte).

Lance toute la suite :

```bash
$ php bin/phpunit
```

> **Attention** — Un test fonctionnel touche à la **base de données de test**. Pour qu'il soit fiable
> et répétable, on prépare un jeu de données connu avant chaque test (souvent en rechargeant des
> fixtures dans l'environnement `test`) et on travaille sur une base dédiée via `.env.test`. Sinon, un
> test peut passer ou échouer selon l'état de la base.

## Tester une page protégée

Pour tester une page réservée aux utilisateurs connectés, le client peut **simuler une connexion**
sans passer par le formulaire, grâce à `loginUser()` :

```php
public function testAdminAccessibleAuxAdmins(): void
{
    $client = static::createClient();

    // On crée/récupère un utilisateur admin de test, puis on le connecte
    $admin = /* ... récupérer un User avec ROLE_ADMIN ... */;
    $client->loginUser($admin);

    $client->request('GET', '/admin/article/nouveau');
    $this->assertResponseIsSuccessful();
}
```

`loginUser($admin)` connecte l'utilisateur pour la durée du test : pratique pour vérifier les pages
protégées sans rejouer tout le scénario de login.

## Résumé

- Un **test automatisé** vérifie qu'une partie de l'app fonctionne ; il protège contre les
  **régressions** et sert de documentation.
- **PHPUnit** (via `symfony/test-pack`) est l'outil standard ; les tests sont dans `tests/`, lancés
  par `php bin/phpunit`, en environnement **`test`**.
- **Unitaire** : une classe isolée (`extends TestCase`), schéma **AAA**, assertions comme
  `assertSame`.
- **Fonctionnel** : une page complète (`extends WebTestCase`), via un **client** simulant un
  navigateur, avec `assertResponseIsSuccessful`, `assertSelectorTextContains`, etc.
- On teste une page protégée en simulant la connexion avec **`loginUser()`**.
- Les tests fonctionnels ont besoin d'une **base de test** dédiée et de données connues pour être
  fiables.

## Exercices

### Exercice 1 — Tester le comptage de mots

Écris un test unitaire pour le service `WordCounter` (exercice du chapitre 8) : un texte de trois mots
doit renvoyer `3`.

<details>
<summary>Voir le corrigé</summary>

La démarche : test unitaire pur, schéma AAA, `assertSame`.

```php
<?php
// tests/Service/WordCounterTest.php
namespace App\Tests\Service;

use App\Service\WordCounter;
use PHPUnit\Framework\TestCase;

class WordCounterTest extends TestCase
{
    public function testCompteLesMots(): void
    {
        $counter = new WordCounter();

        $this->assertSame(3, $counter->compter('un deux trois'));
    }
}
```

Lance `php bin/phpunit tests/Service/WordCounterTest.php` : tu dois obtenir « OK (1 test) ».

</details>

### Exercice 2 — Tester la page d'accueil

Écris un test fonctionnel qui vérifie que la page d'accueil (`/`) répond avec un statut de succès et
contient un lien vers le blog.

<details>
<summary>Voir le corrigé</summary>

La démarche : `WebTestCase`, une requête `GET /`, puis des assertions sur la réponse.

```php
<?php
// tests/Controller/HomeControllerTest.php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
{
    public function testAccueilRepond(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a');  // au moins un lien (vers le blog, etc.)
    }
}
```

`assertSelectorExists('a')` vérifie qu'un lien existe dans la page. Tu peux affiner avec
`assertSelectorTextContains` si ton lien porte un libellé précis.

</details>

## Quiz

**1.** Quel est le principal intérêt des tests automatisés ?
- A. Rendre le code plus rapide à l'exécution
- B. Détecter les régressions quand on modifie le code
- C. Remplacer la documentation officielle

**2.** Quelle classe de base utilise un test **unitaire** pur ?
- A. `WebTestCase`
- B. `TestCase`
- C. `KernelTestCase`

**3.** Que fait `static::createClient()` dans un test fonctionnel ?
- A. Il crée un utilisateur
- B. Il démarre l'application et fournit un navigateur simulé
- C. Il vide la base de données

**4.** Quelle assertion vérifie qu'une page a renvoyé un statut HTTP de succès ?
- A. `assertTrue(true)`
- B. `assertResponseIsSuccessful()`
- C. `assertSame(200)`

<details>
<summary>Voir les réponses</summary>

1. **B** — Les tests protègent contre les régressions lors des modifications.
2. **B** — Un test unitaire pur étend `TestCase` ; `WebTestCase` sert au fonctionnel.
3. **B** — `createClient()` démarre l'app et renvoie un client (navigateur simulé).
4. **B** — `assertResponseIsSuccessful()` vérifie un statut 2xx.

</details>

## Projet fil rouge

Tu fiabilises ton blog avec une première suite de tests.

1. Installe `symfony/test-pack` si ce n'est pas déjà fait.
2. Écris un **test unitaire** pour le service `ReadingTime` (temps de lecture).
3. Écris des **tests fonctionnels** : la page `/blog` répond et affiche son titre ; une URL d'article
   inexistant renvoie 404 ; l'espace `/admin` est refusé à un visiteur non connecté.
4. Lance `php bin/phpunit` : toute la suite doit être verte.

Ton application est testée. Au chapitre suivant, on ajoute des finitions de pro : une **commande**
console, l'envoi d'un **e-mail**, la gestion d'**événements** et un point d'**API**.

---

[← Chapitre précédent](09-securite.md) · [Sommaire](README.md) · [Chapitre suivant →](11-aller-plus-loin.md)
