# Sécurité avancée : authenticators, JWT, voters

[← Chapitre précédent](06-serialisation-api-platform.md) · [Sommaire](README.md) · [Chapitre suivant →](08-workflow.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre le **système de sécurité** moderne de Symfony (passeport, authenticator) ;
- authentifier une **API** sans session, avec des **jetons JWT** ;
- écrire un **authenticator personnalisé** (cas d'une clé d'API) ;
- écrire des **voters** avancés pour des autorisations fines ;
- protéger les opérations d'API Platform et **limiter le débit** (*rate limiting*).

## Rappel : authentification vs autorisation

Deux notions à ne pas confondre, vues en partie 1 :

- L'**authentification** répond à « **qui es-tu ?** » : prouver son identité (login/mot de passe,
  jeton…).
- L'**autorisation** répond à « **as-tu le droit ?** » : une fois identifié, peux-tu faire cette
  action (rôles, voters) ?

La partie 1 utilisait un formulaire de connexion et des **sessions** (un cookie qui garde l'utilisateur
connecté de page en page). C'est parfait pour un site web classique. Mais une **API** consommée par
une appli mobile ou un autre service ne fonctionne pas par session : chaque requête doit prouver son
identité **par elle-même**. C'est là qu'interviennent les **jetons**.

## Le modèle moderne : authenticator et passeport

Depuis Symfony 5.3, la sécurité repose sur des **authenticators**. Le principe, pour chaque requête :

1. Un authenticator décide s'il **supporte** la requête (y a-t-il de quoi authentifier ?).
2. Si oui, il fabrique un **passeport** (*passport*) : l'identité revendiquée + des « badges »
   (preuves : un mot de passe à vérifier, un jeton à valider…).
3. Symfony valide le passeport. S'il est valide, l'utilisateur est authentifié pour cette requête.

Tu n'as pas eu à écrire d'authenticator en partie 1 car `form_login` en fournit un tout prêt. Pour une
API, on en utilise (ou on en écrit) d'autres.

> **À retenir** — Un **authenticator** = « comment on prouve l'identité pour ce type de requête ». Tu
> peux en avoir plusieurs en parallèle : un pour le formulaire web (session), un pour l'API (jeton).

## Authentifier une API avec JWT

Un **JWT** (*JSON Web Token*, « jeton web JSON ») est une chaîne signée qui contient l'identité de
l'utilisateur et une date d'expiration. Le principe :

1. Le client s'authentifie **une fois** (login + mot de passe) sur un point d'entrée et reçoit un
   **jeton**.
2. À chaque requête suivante, il envoie ce jeton dans l'en-tête `Authorization: Bearer <jeton>`.
3. Le serveur **vérifie la signature** du jeton (sans interroger la base) et sait qui parle.

Le jeton est **auto-porteur** : il contient l'info et il est signé, donc infalsifiable sans la clé
secrète du serveur. Pas de session côté serveur : idéal pour une API qui doit passer à l'échelle.

On installe le bundle qui gère tout ça, **LexikJWTAuthenticationBundle** :

```bash
$ composer require lexik/jwt-authentication-bundle
$ php bin/console lexik:jwt:generate-keypair   # génère les clés de signature
```

La commande crée une paire de clés (privée pour signer, publique pour vérifier) dans `config/jwt/`.

> **Attention** — La **clé privée** ne doit **jamais** être versionnée dans Git (rappel du chapitre
> 12 sur les secrets). C'est elle qui permet de signer des jetons : si elle fuite, n'importe qui peut
> se faire passer pour n'importe quel utilisateur. `config/jwt/` doit être dans `.gitignore`.

On configure un point de **login** qui échange identifiants contre jeton, et un *firewall* qui
protège l'API en exigeant un JWT valide :

```yaml
# config/packages/security.yaml (extrait)
security:
    firewalls:
        login:
            pattern: ^/api/login
            stateless: true
            json_login:
                check_path: /api/login          # POST {username, password} ici → renvoie un jeton
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure

        api:
            pattern: ^/api
            stateless: true                      # pas de session : chaque requête porte son jeton
            jwt: ~                               # exige un JWT valide
```

```yaml
# config/routes.yaml
api_login:
    path: /api/login
    methods: [POST]
```

Désormais :

```text
POST /api/login   { "username": "alice@blog.test", "password": "..." }
→ 200 { "token": "eyJ0eXAiOiJKV1Qi..." }

GET /api/articles
Authorization: Bearer eyJ0eXAiOiJKV1Qi...
→ 200 [...]      (sans le jeton : 401 Unauthorized)
```

`stateless: true` est la clé : ce firewall ne crée pas de session, il s'attend à un jeton à **chaque**
requête. Ton API est maintenant fermée par défaut et ouverte sur présentation d'un JWT valide.

## Écrire un authenticator personnalisé : la clé d'API

Le JWT couvre le cas « un utilisateur se connecte ». Mais parfois tu veux authentifier un **service**
par une simple **clé d'API** (un secret partagé envoyé dans un en-tête). C'est l'occasion d'écrire un
**authenticator personnalisé**, pour comprendre le mécanisme du passeport.

```php
<?php
// src/Security/ApiKeyAuthenticator.php
namespace App\Security;

use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(private UserRepository $users) {}

    // 1. Cette requête nous concerne-t-elle ? (présence de l'en-tête X-API-KEY)
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-API-KEY');
    }

    // 2. On fabrique le passeport : l'identité revendiquée et comment la résoudre.
    public function authenticate(Request $request): Passport
    {
        $apiKey = $request->headers->get('X-API-KEY');

        if (!$apiKey) {
            throw new AuthenticationException('Clé d\'API manquante.');
        }

        // UserBadge : « voici comment retrouver l'utilisateur à partir de cette clé ».
        return new SelfValidatingPassport(
            new UserBadge($apiKey, fn (string $key) => $this->users->findOneBy(['apiKey' => $key]))
        );
    }

    // 3a. Authentification réussie : on laisse la requête continuer (null = pas de réponse imposée).
    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    // 3b. Échec : on renvoie une erreur claire.
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Authentification échouée.'], Response::HTTP_UNAUTHORIZED);
    }
}
```

On l'active sur un firewall :

```yaml
# config/packages/security.yaml (extrait)
firewalls:
    api:
        pattern: ^/api
        stateless: true
        custom_authenticators:
            - App\Security\ApiKeyAuthenticator
```

`SelfValidatingPassport` signifie « le seul badge est l'identité ; il n'y a pas de mot de passe à
vérifier ». C'est adapté à une clé d'API : la clé **est** la preuve. Pour un mot de passe, on
utiliserait un `Passport` avec un `PasswordCredentials`.

> **À retenir** — Un authenticator répond à trois questions : **est-ce pour moi** (`supports`),
> **qui est-ce** (`authenticate` → passeport), et **que faire** en cas de succès/échec. Le passeport
> porte l'identité et les preuves à valider.

## Voters avancés : des autorisations fines

En partie 1, tu as écrit un voter simple (« un auteur ne modifie que ses propres articles »). Un
**voter** est la bonne réponse dès que l'autorisation dépend de **l'objet concerné** et pas seulement
d'un rôle global. Approfondissons avec plusieurs permissions et une règle qui combine rôle et
propriété.

```php
<?php
// src/Security/ArticleVoter.php
namespace App\Security;

use App\Entity\Article;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ArticleVoter extends Voter
{
    public const VIEW = 'ARTICLE_VIEW';
    public const EDIT = 'ARTICLE_EDIT';
    public const DELETE = 'ARTICLE_DELETE';

    // Ce voter ne se prononce que sur ces attributs et sur des objets Article.
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;   // pas connecté : aucun droit
        }

        /** @var Article $article */
        $article = $subject;

        // Un admin peut tout faire.
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $article->isPublished() || $article->getAuthor() === $user,
            self::EDIT => $article->getAuthor() === $user,             // seulement son auteur
            self::DELETE => $article->getAuthor() === $user,
            default => false,
        };
    }
}
```

Dans un contrôleur, un service ou un template, tu vérifies la permission **sur l'objet** :

```php
// Dans un contrôleur
$this->denyAccessUnlessGranted(ArticleVoter::EDIT, $article);

// Dans Twig
{% if is_granted('ARTICLE_EDIT', article) %}
    <a href="...">Modifier</a>
{% endif %}
```

> **Astuce** — Centralise toujours la logique d'autorisation dans des **voters**, jamais dispersée en
> `if ($user->getId() === ...)` dans les contrôleurs. Un voter est testable isolément (chapitre 11) et
> réutilisable partout : contrôleur, Twig, et même API Platform (ci-dessous).

## Sécuriser API Platform et limiter le débit

API Platform s'appuie sur le même système. Tu protèges une opération avec l'option `security`, qui
accepte une expression `is_granted(...)` — donc tes voters :

```php
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;

#[ApiResource(
    operations: [
        new GetCollection(),                                    // public
        new Get(),                                              // public
        new Post(security: "is_granted('ROLE_AUTHOR')"),        // réservé aux auteurs connectés
        new Patch(security: "is_granted('ARTICLE_EDIT', object)"), // ton voter, sur l'objet ciblé
    ],
)]
class Article { /* ... */ }
```

`object` désigne la ressource concernée par l'opération : API Platform appelle ton `ArticleVoter` avec
elle. Tu réutilises **exactement** la même logique d'autorisation que sur le site web.

Enfin, le **rate limiting** (limitation du débit) protège login et API contre les abus (force brute,
surcharge). Le composant `RateLimiter` plafonne le nombre de requêtes par client.

```bash
$ composer require symfony/rate-limiter
```

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        api:
            policy: 'sliding_window'   # fenêtre glissante
            limit: 100                 # 100 requêtes
            interval: '1 minute'       # par minute et par client
```

Tu appliques ensuite ce limiteur sur le firewall de login (Symfony propose `login_throttling` clé en
main) ou dans un *listener*/middleware sur l'API. L'idée à retenir : **plafonner** ce qui peut être
abusé.

> **À retenir** — La sécurité d'une API tient en quatre couches : **authentifier** (JWT / clé),
> **fermer par défaut** (`stateless`, firewall), **autoriser finement** (voters, `security:`), et
> **limiter le débit** (rate limiter).

## Résumé

- **Authentification** = qui es-tu ; **autorisation** = as-tu le droit. Une API s'authentifie par
  **jeton**, pas par session (`stateless`).
- Le modèle moderne repose sur des **authenticators** qui fabriquent un **passeport** (identité +
  badges/preuves).
- **JWT** (LexikJWTAuthenticationBundle) : login → jeton signé, renvoyé dans
  `Authorization: Bearer`. La **clé privée** reste hors de Git.
- Un **authenticator personnalisé** (`supports` / `authenticate` / succès / échec) gère un cas sur
  mesure comme une clé d'API.
- Les **voters** centralisent les autorisations fines (par objet) et se réutilisent dans contrôleurs,
  Twig et API Platform (`security: "is_granted(..., object)"`).
- Le **rate limiter** plafonne les requêtes pour contrer force brute et surcharge.

## Exercices

### Exercice 1 — Ouvrir l'API en lecture, fermer en écriture

Configure ton API Platform pour que `GetCollection` et `Get` restent publics, mais que `Post` exige le
rôle `ROLE_AUTHOR` et `Patch` passe par ton `ArticleVoter` (`ARTICLE_EDIT`).

<details>
<summary>Voir le corrigé</summary>

La démarche : on ajoute l'option `security` opération par opération.

```php
#[ApiResource(operations: [
    new GetCollection(),
    new Get(),
    new Post(security: "is_granted('ROLE_AUTHOR')"),
    new Patch(security: "is_granted('ARTICLE_EDIT', object)"),
])]
class Article { /* ... */ }
```

Teste depuis `/api` : sans authentification, `POST` et `PATCH` renvoient 401/403, tandis que les `GET`
restent accessibles. Avec un JWT d'auteur, `POST` passe ; `PATCH` ne passe que sur ses propres
articles, grâce au voter.

</details>

### Exercice 2 — Un voter pour les commentaires

Écris un `CommentVoter` avec une permission `COMMENT_DELETE` : un commentaire peut être supprimé par
son auteur **ou** par un admin.

<details>
<summary>Voir le corrigé</summary>

La démarche : même structure que `ArticleVoter`, sur l'entité `Comment`.

```php
<?php
// src/Security/CommentVoter.php
namespace App\Security;

use App\Entity\Comment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CommentVoter extends Voter
{
    public const DELETE = 'COMMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::DELETE && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Comment $comment */
        $comment = $subject;

        return in_array('ROLE_ADMIN', $user->getRoles(), true)
            || $comment->getAuthor() === $user;
    }
}
```

Utilise-le avec `$this->denyAccessUnlessGranted(CommentVoter::DELETE, $comment)` avant toute
suppression.

</details>

## Quiz

**1.** Pourquoi une API utilise-t-elle des jetons plutôt qu'une session ?
- A. Les sessions sont interdites en PHP
- B. Chaque requête doit prouver son identité par elle-même, sans état serveur (`stateless`)
- C. Les jetons sont plus rapides à écrire

**2.** Que contient un passeport (passport) dans le système de sécurité Symfony ?
- A. Une réponse HTTP
- B. L'identité revendiquée et des badges (preuves à valider)
- C. Une migration de base

**3.** Où la clé privée JWT doit-elle vivre ?
- A. Dans Git, pour la partager
- B. Hors de Git (jamais versionnée) ; c'est elle qui signe les jetons
- C. Dans un template Twig

**4.** Quand préférer un voter à un simple contrôle de rôle ?
- A. Jamais
- B. Quand l'autorisation dépend de l'objet concerné (ex. « ses propres articles »)
- C. Uniquement pour l'API

**5.** À quoi sert le rate limiter ?
- A. À accélérer les requêtes
- B. À plafonner le nombre de requêtes pour contrer force brute et surcharge
- C. À sérialiser les réponses

<details>
<summary>Voir les réponses</summary>

1. **B** — `stateless` : l'identité voyage avec chaque requête.
2. **B** — Identité + badges (preuves) à valider.
3. **B** — La clé privée signe les jetons ; elle reste secrète, hors de Git.
4. **B** — Le voter brille quand le droit dépend de l'objet.
5. **B** — Il limite les abus en plafonnant les requêtes.

</details>

## Projet fil rouge

1. Installe LexikJWTAuthenticationBundle, génère les clés (et ajoute `config/jwt/` à `.gitignore`).
2. Mets en place le point `/api/login` et un firewall `^/api` `stateless` exigeant un JWT.
3. Sécurise les opérations d'API : lecture publique, `Post` réservé aux auteurs, `Patch`/`Delete` via
   `ArticleVoter`.
4. Ajoute un `CommentVoter` (exercice 2) et un **rate limiter** sur le login.

Ton API est maintenant fermée par défaut, ouverte par jeton, et finement autorisée. Au prochain
chapitre, on modélise le cycle de vie éditorial d'un article avec le composant Workflow.

---

[← Chapitre précédent](06-serialisation-api-platform.md) · [Sommaire](README.md) · [Chapitre suivant →](08-workflow.md)
