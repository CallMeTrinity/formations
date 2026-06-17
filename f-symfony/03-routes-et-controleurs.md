# Routes et contrôleurs

[← Chapitre précédent](02-anatomie-projet.md) · [Sommaire](README.md) · [Chapitre suivant →](04-twig.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer un **contrôleur** et une **action** qui répondent à une URL ;
- définir une **route** avec l'attribut `#[Route]` (chemin, nom, méthode HTTP) ;
- renvoyer une **réponse** (`Response`) au navigateur ;
- ajouter des **paramètres dynamiques** dans une URL (par exemple un identifiant ou un *slug*) ;
- lire les données de la **requête** et **générer des URL** à partir du nom d'une route.

## Un contrôleur, c'est quoi

Souviens-toi du cycle MVC : le routeur dirige une URL vers un **contrôleur**. Concrètement, un
contrôleur est une **classe PHP**, et chaque page correspond à une **méthode** de cette classe qu'on
appelle une **action**. Le travail d'une action est simple : recevoir la requête, faire ce qu'il faut,
et renvoyer une **réponse**.

Le moyen le plus rapide d'en créer un est la commande `make:controller` du MakerBundle (déjà installé
avec `--webapp`). Génère le contrôleur de la page d'accueil :

```bash
$ php bin/console make:controller HomeController
```

La commande crée deux fichiers : `src/Controller/HomeController.php` (le contrôleur) et
`templates/home/index.html.twig` (une vue, qu'on exploitera au chapitre suivant). Ouvre le
contrôleur :

```php
<?php
// src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}
```

Analysons cette classe ligne par ligne, car tout est important.

- **`namespace App\Controller;`** : on est dans `src/Controller/` (rappel du chapitre 2 sur
  l'autoloading).
- **`extends AbstractController`** : ta classe hérite d'`AbstractController`, une classe de base
  Symfony qui fournit des raccourcis bien pratiques (comme `$this->render(...)`).
- **`#[Route(...)]`** : c'est l'**attribut de route**. Il dit « cette action répond à l'URL `/home` ».
  On y revient juste après.
- **`public function index(): Response`** : l'action. Elle **doit retourner un objet `Response`** :
  c'est le contrat de tout contrôleur Symfony.
- **`return $this->render(...)`** : ici, on rend une vue Twig. On détaillera Twig au
  [chapitre 4](04-twig.md) ; pour l'instant retiens juste que `render()` produit une `Response`.

## L'attribut `#[Route]`

L'attribut `#[Route]`, placé juste au-dessus d'une action, associe une **URL** à cette action. C'est
le routage **par attributs**, la méthode standard depuis PHP 8.

```php
#[Route('/home', name: 'app_home')]
```

Deux informations principales :

- le **chemin** (le premier argument, `'/home'`) : l'URL qui déclenche l'action ;
- le **nom** (`name: 'app_home'`) : un identifiant interne unique de la route. Il ne change rien pour
  le visiteur, mais il te servira à **générer** cette URL ailleurs (dans un lien, une redirection)
  sans réécrire le chemin en dur.

> **À retenir** — Une route a toujours un **chemin** (pour le navigateur) et un **nom** (pour toi,
> dans le code). On manipule une route par son **nom**, jamais par son chemin recopié à la main.

Pour faire de cette action ta **vraie page d'accueil** (l'URL `/`), change le chemin :

```php
#[Route('/', name: 'app_home')]
```

Recharge `https://127.0.0.1:8000/` : ton action répond désormais à la racine du site. Vérifie aussi
avec la console :

```bash
$ php bin/console debug:router
```

Tu vois ta route `app_home` listée avec son chemin `/`.

### Restreindre la méthode HTTP

Une requête web a un **verbe** (une *méthode HTTP*) : `GET` pour afficher une page, `POST` pour
envoyer un formulaire, etc. Tu peux restreindre une route à certaines méthodes :

```php
#[Route('/articles', name: 'article_list', methods: ['GET'])]
```

Ici, l'action ne répond qu'aux requêtes `GET`. Une requête `POST` sur la même URL renverra une erreur
« 405 Method Not Allowed ». C'est utile pour séparer l'affichage d'un formulaire (`GET`) de son
traitement (`POST`), comme on le verra avec les formulaires.

## Renvoyer une réponse

Une action **doit** retourner un objet `Response`. La façon la plus directe, sans passer par une vue,
est de construire une `Response` à la main :

```php
<?php
// src/Controller/BlogController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    #[Route('/blog', name: 'blog_index')]
    public function index(): Response
    {
        return new Response('<h1>Le blog</h1><p>Bientôt, la liste des articles.</p>');
    }
}
```

Visite `https://127.0.0.1:8000/blog` : le HTML que tu as écrit s'affiche. C'est rudimentaire (on
n'écrit pas du HTML dans le code en vrai — Twig est là pour ça), mais ça illustre le contrat : **une
action retourne une `Response`**.

> **Astuce** — `AbstractController` fournit des raccourcis qui construisent la `Response` pour toi :
> `$this->render(...)` (rendre une vue Twig), `$this->json(...)` (renvoyer du JSON), ou
> `$this->redirectToRoute(...)` (rediriger). On les croisera tout au long de la formation.

## Les paramètres de route

Une URL contient souvent une partie **variable** : l'identifiant d'un article, son *slug* (un *slug*
est une version de titre lisible dans une URL, comme `mon-premier-article`). On déclare cette partie
entre **accolades** dans le chemin, et on la récupère en **argument** de l'action :

```php
#[Route('/blog/{slug}', name: 'blog_show')]
public function show(string $slug): Response
{
    return new Response('<h1>Article : ' . htmlspecialchars($slug) . '</h1>');
}
```

Le nom entre accolades (`{slug}`) doit correspondre au **nom de l'argument** de la méthode
(`$slug`) : Symfony fait la liaison automatiquement. Visite `https://127.0.0.1:8000/blog/bonjour` :
la page affiche « Article : bonjour ».

> **Attention** — Pense à protéger les valeurs venant de l'URL avant de les afficher (ici
> `htmlspecialchars`), sinon tu t'exposes à des injections de code. Avec Twig (chapitre suivant),
> cet échappement sera **automatique** : encore une bonne raison d'abandonner le HTML écrit à la main.

### Contraindre un paramètre

Tu peux exiger qu'un paramètre respecte un format, via une **expression régulière**. Pour n'accepter
qu'un identifiant numérique :

```php
#[Route('/blog/article/{id}', name: 'blog_article', requirements: ['id' => '\d+'])]
public function article(int $id): Response
{
    return new Response('Article numéro ' . $id);
}
```

`'\d+'` signifie « un ou plusieurs chiffres ». Avec cette contrainte, `/blog/article/42` fonctionne,
mais `/blog/article/abc` renvoie une erreur 404 (page non trouvée). Remarque aussi que l'argument est
typé `int` : Symfony convertit la chaîne de l'URL en entier pour toi.

## Lire la requête

Pour accéder à tout ce que contient la requête (paramètres de l'URL après le `?`, en-têtes, méthode
HTTP…), demande l'objet **`Request`** en argument de ton action. Symfony te le fournit
automatiquement (c'est un exemple d'**injection**, notion approfondie au chapitre 8) :

```php
use Symfony\Component\HttpFoundation\Request;

#[Route('/recherche', name: 'search')]
public function search(Request $request): Response
{
    // URL du type /recherche?q=symfony
    $terme = $request->query->get('q', '');
    return new Response('Tu cherches : ' . htmlspecialchars($terme));
}
```

`$request->query` regroupe les paramètres après le `?` dans l'URL (la *query string*). Le deuxième
argument de `get` (`''`) est la **valeur par défaut** si le paramètre est absent. Visite
`/recherche?q=symfony` pour voir le résultat.

## Générer des URL et rediriger

Tu te souviens du **nom** de route ? C'est maintenant qu'il sert. Plutôt que d'écrire une URL en dur
(fragile : si tu changes le chemin, tous tes liens cassent), tu **génères** l'URL à partir du nom.

- Pour **rediriger** vers une autre route :

  ```php
  return $this->redirectToRoute('blog_index');
  ```

- Pour générer une URL avec un **paramètre** :

  ```php
  $url = $this->generateUrl('blog_show', ['slug' => 'mon-article']);
  // donne : /blog/mon-article
  ```

> **À retenir** — Réfère-toi toujours à une route par son **nom** et laisse Symfony fabriquer l'URL.
> Si le chemin change un jour, tu n'as qu'un seul endroit à modifier : la route elle-même.

## Résumé

- Un **contrôleur** est une classe ; chaque page est une **action** (méthode) qui retourne une
  **`Response`**.
- `make:controller` génère un contrôleur prêt à l'emploi.
- L'attribut **`#[Route('/chemin', name: 'nom')]`** associe une URL à une action ; `methods:` la
  restreint à certains verbes HTTP.
- Une partie **variable** d'URL se note `{param}` et arrive en argument de même nom ; `requirements:`
  contraint son format.
- L'objet **`Request`** (demandé en argument) donne accès aux données de la requête, dont la *query
  string* via `$request->query`.
- On **génère** les URL par le **nom** de route (`generateUrl`, `redirectToRoute`), jamais en
  recopiant le chemin.

## Exercices

### Exercice 1 — Une page « À propos »

Crée un contrôleur `AboutController` avec une action répondant à l'URL `/a-propos` (nom de route
`app_about`) et affichant une courte présentation du blog. Vérifie que la route apparaît dans
`debug:router`.

<details>
<summary>Voir le corrigé</summary>

La démarche : générer le contrôleur, puis ajuster le chemin et la réponse.

```bash
$ php bin/console make:controller AboutController
```

Puis dans `src/Controller/AboutController.php` :

```php
#[Route('/a-propos', name: 'app_about')]
public function index(): Response
{
    return new Response('<h1>À propos</h1><p>Un blog pour apprendre Symfony.</p>');
}
```

Vérifie :

```bash
$ php bin/console debug:router
```

La route `app_about` doit apparaître avec le chemin `/a-propos`. Visite `https://127.0.0.1:8000/a-propos`.

</details>

### Exercice 2 — Page d'article avec paramètre contraint

Ajoute à `BlogController` une action sur `/blog/categorie/{id}` (nom `blog_category`) qui n'accepte
qu'un `id` **numérique** et affiche « Catégorie numéro X ». Que se passe-t-il si tu visites
`/blog/categorie/php` ?

<details>
<summary>Voir le corrigé</summary>

La démarche : un paramètre `{id}` contraint à des chiffres avec `requirements`.

```php
#[Route('/blog/categorie/{id}', name: 'blog_category', requirements: ['id' => '\d+'])]
public function category(int $id): Response
{
    return new Response('Catégorie numéro ' . $id);
}
```

`/blog/categorie/7` affiche « Catégorie numéro 7 ». `/blog/categorie/php` renvoie une **erreur 404** :
la valeur `php` ne respecte pas la contrainte `\d+`, donc aucune route ne correspond.

</details>

## Quiz

**1.** Que doit obligatoirement retourner une action de contrôleur ?
- A. Une chaîne de caractères
- B. Un objet `Response`
- C. Un tableau

**2.** À quoi sert le `name:` d'une route ?
- A. À afficher un titre dans le navigateur
- B. À identifier la route pour générer son URL depuis le code
- C. À définir la méthode HTTP

**3.** Comment déclare-t-on une partie variable dans le chemin d'une route ?
- A. Avec des crochets `[param]`
- B. Avec des accolades `{param}`
- C. Avec un dollar `$param`

**4.** Comment rediriger proprement vers la route nommée `blog_index` ?
- A. `header('Location: /blog')`
- B. `return new Response('/blog')`
- C. `return $this->redirectToRoute('blog_index')`

<details>
<summary>Voir les réponses</summary>

1. **B** — Toute action retourne un objet `Response` (éventuellement via un raccourci comme
   `render`).
2. **B** — Le nom sert à générer l'URL de la route depuis le code, sans recopier le chemin.
3. **B** — Les accolades `{param}` marquent une partie variable, liée à l'argument de même nom.
4. **C** — `redirectToRoute` génère l'URL à partir du nom et renvoie la redirection.

</details>

## Projet fil rouge

Tu poses les routes principales de ton blog. Pour l'instant, les données sont **codées en dur** dans
le contrôleur ; on les remplacera par la base de données au chapitre 5.

1. Crée (ou complète) `BlogController` avec :
   - une action `index` sur `/blog` (nom `blog_index`) ;
   - une action `show` sur `/blog/{slug}` (nom `blog_show`).
2. Dans `index`, prépare un petit tableau d'articles en dur, par exemple :

   ```php
   $articles = [
       ['slug' => 'demarrer-symfony', 'titre' => 'Démarrer avec Symfony'],
       ['slug' => 'le-routage',        'titre' => 'Comprendre le routage'],
   ];
   ```

   Pour l'instant, retourne une `Response` qui liste simplement les titres (tu rendras tout ça beau
   avec Twig au chapitre suivant).
3. Dans `show`, affiche le `slug` reçu pour vérifier que le paramètre arrive bien.
4. Contrôle le tout avec `php bin/console debug:router`.

Au chapitre suivant, on remplace ce HTML bricolé par de vraies **vues Twig** : layout commun, boucle
sur les articles, et liens propres entre les pages.

---

[← Chapitre précédent](02-anatomie-projet.md) · [Sommaire](README.md) · [Chapitre suivant →](04-twig.md)
