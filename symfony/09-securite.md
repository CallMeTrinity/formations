# La sécurité : authentification et autorisations

[← Chapitre précédent](08-services-injection.md) · [Sommaire](README.md) · [Chapitre suivant →](10-tests.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- distinguer **authentification** (qui es-tu ?) et **autorisation** (as-tu le droit ?) ;
- créer une entité **`User`** et un **formulaire de connexion** ;
- gérer l'**inscription** avec un mot de passe **haché** ;
- protéger des pages par **rôle** (`access_control`, `#[IsGranted]`) ;
- écrire un **voter** pour des règles fines (modifier **ses propres** articles).

## Authentification et autorisation

Deux questions distinctes se cachent derrière le mot « sécurité » :

- l'**authentification** répond à « **qui es-tu ?** » : prouver son identité (se connecter) ;
- l'**autorisation** répond à « **as-tu le droit de faire ça ?** » : vérifier les permissions une fois
  identifié.

On traite les deux dans l'ordre : d'abord identifier l'utilisateur, ensuite contrôler ses droits.

> **À retenir** — **Authentification = identité** (login). **Autorisation = permissions** (rôles,
> voters). Une personne connectée n'a pas forcément tous les droits.

## Créer l'utilisateur

L'utilisateur est une entité comme une autre, avec quelques exigences que le maker met en place. Lance :

```bash
$ php bin/console make:user
# The name of the security user class [User]: User
# Do you want to store user data in the database (via Doctrine)? yes
# Enter a property name that will be the unique display name for the user [email]: email
# Will this app need to hash/check user passwords? yes
```

Le maker crée `src/Entity/User.php` (avec `email`, `roles`, `password`) et l'enregistre comme
**fournisseur d'utilisateurs** dans `config/packages/security.yaml`. L'entité implémente deux
interfaces clés :

- **`UserInterface`** : le contrat d'un utilisateur Symfony (son identifiant, ses rôles) ;
- **`PasswordAuthenticatedUserInterface`** : pour les utilisateurs avec mot de passe.

N'oublie pas la migration pour créer la table :

```bash
$ php bin/console make:migration
$ php bin/console doctrine:migrations:migrate
```

## Le fichier `security.yaml`

Toute la configuration de sécurité tient dans `config/packages/security.yaml`. Les sections
principales :

```yaml
security:
    # Comment hacher les mots de passe (algorithme choisi automatiquement, robuste)
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    # Où trouver les utilisateurs (ici : par email, dans la base via Doctrine)
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    # Qui protège quoi : le pare-feu intercepte les requêtes
    firewalls:
        main:
            lazy: true
            provider: app_user_provider

    # Règles d'accès par URL (autorisation)
    access_control:
        # - { path: ^/admin, roles: ROLE_ADMIN }
```

Le **firewall** (« pare-feu ») est le poste de contrôle : il intercepte chaque requête, détermine si
un utilisateur est connecté, et applique les règles. Tu n'auras presque jamais à écrire ce fichier à
la main : les makers le complètent pour toi.

## L'inscription

Génère un formulaire d'inscription complet :

```bash
$ php bin/console make:registration-form
```

Le maker crée un `RegistrationController`, un `RegistrationFormType` et le template associé. Le point
crucial est le **hachage du mot de passe**. On ne stocke **jamais** un mot de passe en clair : on
enregistre une empreinte irréversible. Symfony s'en charge via `UserPasswordHasherInterface` :

```php
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// dans l'action d'inscription, après soumission valide :
$user->setPassword(
    $hasher->hashPassword($user, $form->get('plainPassword')->getData())
);
$em->persist($user);
$em->flush();
```

`hashPassword` transforme le mot de passe saisi (`plainPassword`, jamais stocké) en empreinte sûre,
rangée dans la colonne `password`.

> **Attention** — Un mot de passe ne se stocke **jamais** en clair, ni « chiffré » de façon
> réversible. On stocke un **hachage** : une empreinte qu'on ne peut pas inverser. Symfony le fait
> correctement pour toi ; ne réinvente jamais ce mécanisme.

## La connexion

Génère le formulaire de connexion :

```bash
$ php bin/console make:auth
# 1) Login form authenticator
# Class name of the authenticator: LoginFormAuthenticator
# Choose a name for the controller class [SecurityController]: SecurityController
```

Le maker crée un `SecurityController` (avec les routes `app_login` et `app_logout`), un
authentificateur, le template de login, et **met à jour `security.yaml`** pour activer tout ça. Une
fois en place, une page `/login` affiche le formulaire ; à la soumission, Symfony vérifie l'e-mail et
le mot de passe, et connecte l'utilisateur.

Dans n'importe quel contrôleur, tu récupères l'utilisateur connecté avec `$this->getUser()` (ou
`null` si personne n'est connecté). En Twig, c'est `app.user` :

```twig
{% if app.user %}
    Connecté en tant que {{ app.user.email }} —
    <a href="{{ path('app_logout') }}">Se déconnecter</a>
{% else %}
    <a href="{{ path('app_login') }}">Se connecter</a>
{% endif %}
```

## Les rôles

Un utilisateur porte des **rôles** : des étiquettes de permission, par convention préfixées `ROLE_`.
Tout utilisateur connecté a au moins `ROLE_USER`. Pour distinguer un administrateur, on lui ajoute
`ROLE_ADMIN`. Les rôles sont stockés dans la propriété `roles` (un tableau) de l'entité `User`.

### Protéger par URL : `access_control`

Pour réserver tout l'espace d'administration aux admins, ajoute une règle dans `security.yaml` :

```yaml
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

Désormais, toute URL commençant par `/admin` exige le rôle `ROLE_ADMIN`. Un visiteur non connecté est
redirigé vers la page de connexion ; un utilisateur connecté sans le rôle reçoit une erreur **403
Accès refusé**.

### Protéger par action : `#[IsGranted]`

Tu peux aussi protéger une action précise avec un attribut, plus localisé :

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/article/nouveau', name: 'admin_article_new')]
#[IsGranted('ROLE_ADMIN')]
public function new(/* ... */): Response { /* ... */ }
```

Et dans Twig, pour afficher un lien seulement aux ayants droit :

```twig
{% if is_granted('ROLE_ADMIN') %}
    <a href="{{ path('admin_article_new') }}">Nouvel article</a>
{% endif %}
```

> **À retenir** — `access_control` protège par **motif d'URL** (large), `#[IsGranted]` protège une
> **action** précise, et `is_granted(...)` / `isGranted(...)` testent un droit dans une vue ou un
> contrôleur. Tu combineras les trois selon le besoin.

## Les voters : des règles fines

Les rôles répondent à « est-il admin ? ». Mais certaines règles dépendent de **l'objet concerné** :
« cet auteur peut-il modifier **cet** article ? » — oui seulement s'il en est l'auteur. C'est trop fin
pour un rôle : on utilise un **voter** (« votant »).

Un voter est un service qui **vote** pour autoriser ou refuser une action sur un objet donné. Génère-le :

```bash
$ php bin/console make:voter ArticleVoter
```

Complète `src/Security/Voter/ArticleVoter.php` :

```php
<?php
// src/Security/Voter/ArticleVoter.php
namespace App\Security\Voter;

use App\Entity\Article;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ArticleVoter extends Voter
{
    public const EDIT = 'ARTICLE_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Ce voter ne traite que l'action EDIT sur un Article
        return $attribute === self::EDIT && $subject instanceof Article;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;  // pas connecté : refusé
        }

        /** @var Article $article */
        $article = $subject;

        // Autorisé si l'utilisateur est l'auteur de l'article
        return $article->getAuthor() === $user;
    }
}
```

Deux méthodes :

- **`supports`** : ce voter est-il concerné par ce couple (action, objet) ? Ici, uniquement l'action
  `ARTICLE_EDIT` sur un `Article`.
- **`voteOnAttribute`** : la décision. On autorise si l'utilisateur connecté est l'auteur de l'article.

(Cela suppose une relation `Article.author` vers `User` ; ajoute-la avec `make:entity` si besoin.)

On l'utilise comme un rôle, mais en passant **l'objet** :

```php
#[IsGranted('ARTICLE_EDIT', subject: 'article')]
public function edit(Article $article /* ... */): Response { /* ... */ }
```

Ou explicitement dans le code : `$this->denyAccessUnlessGranted('ARTICLE_EDIT', $article);`. En Twig :
`{% if is_granted('ARTICLE_EDIT', article) %}`.

> **À retenir** — Un **rôle** est global (« est-il admin ? »). Un **voter** décide en fonction de
> **l'objet** (« peut-il modifier **cet** article-ci ? »). Dès qu'une permission dépend des données,
> écris un voter.

## Résumé

- **Authentification** = prouver son identité (login) ; **autorisation** = vérifier ses droits
  (rôles, voters).
- `make:user`, `make:registration-form`, `make:auth` génèrent l'entité `User`, l'inscription et la
  connexion, et configurent `security.yaml`.
- Un mot de passe se stocke **haché** (`hashPassword`), jamais en clair.
- Les **rôles** (`ROLE_USER`, `ROLE_ADMIN`) se protègent par `access_control` (URL), `#[IsGranted]`
  (action) et `is_granted` (vue).
- Un **voter** décide selon **l'objet** concerné (modifier ses propres articles), pour les règles que
  les rôles ne couvrent pas.
- On récupère l'utilisateur connecté avec `$this->getUser()` (PHP) ou `app.user` (Twig).

## Exercices

### Exercice 1 — Réserver l'administration

Protège tout l'espace `/admin` pour qu'il n'accepte que les utilisateurs ayant `ROLE_ADMIN`. Vérifie
qu'un visiteur non connecté est bien redirigé vers la page de connexion.

<details>
<summary>Voir le corrigé</summary>

La démarche : une règle `access_control` sur le motif d'URL `^/admin`.

```yaml
# config/packages/security.yaml
    access_control:
        - { path: ^/admin, roles: ROLE_ADMIN }
```

Visite `/admin/article/nouveau` sans être connecté : tu es redirigé vers `/login`. Connecté mais sans
`ROLE_ADMIN`, tu obtiens une erreur **403**. Pour te donner le rôle en base, tu peux éditer la colonne
`roles` de ton utilisateur (par exemple via une fixture ou la console).

</details>

### Exercice 2 — Lien d'édition réservé à l'auteur

Sur la page d'un article, n'affiche le lien « Modifier » que si l'utilisateur connecté a le droit
`ARTICLE_EDIT` sur cet article précis.

<details>
<summary>Voir le corrigé</summary>

La démarche : `is_granted` avec l'objet article en second argument déclenche le voter.

```twig
{% if is_granted('ARTICLE_EDIT', article) %}
    <a href="{{ path('admin_article_edit', { id: article.id }) }}">Modifier</a>
{% endif %}
```

Le voter `ArticleVoter` reçoit l'action `ARTICLE_EDIT` et l'objet `article`, et renvoie `true`
seulement si l'utilisateur connecté en est l'auteur. Le lien n'apparaît donc qu'à l'auteur (et tu peux
aussi autoriser les admins dans le voter si tu le souhaites).

</details>

## Quiz

**1.** Quelle est la différence entre authentification et autorisation ?
- A. Aucune
- B. L'authentification prouve l'identité ; l'autorisation vérifie les droits
- C. L'autorisation se fait avant l'authentification

**2.** Comment stocke-t-on un mot de passe ?
- A. En clair, c'est plus simple
- B. Chiffré de façon réversible
- C. Sous forme de **hachage** irréversible

**3.** Quel outil utiliser pour « cet auteur peut-il modifier **cet** article ? »
- A. Un rôle `ROLE_ADMIN`
- B. Un **voter**
- C. Une migration

**4.** Que fait la règle `access_control: { path: ^/admin, roles: ROLE_ADMIN }` ?
- A. Elle supprime l'espace admin
- B. Elle réserve toutes les URL commençant par `/admin` aux utilisateurs `ROLE_ADMIN`
- C. Elle crée un utilisateur admin

<details>
<summary>Voir les réponses</summary>

1. **B** — Identité d'abord (authentification), droits ensuite (autorisation).
2. **C** — On stocke un hachage irréversible, jamais le mot de passe en clair.
3. **B** — Un voter décide selon l'objet ; un rôle est trop global pour ce cas.
4. **B** — La règle protège le motif d'URL `^/admin` par le rôle `ROLE_ADMIN`.

</details>

## Projet fil rouge

Ton blog devient une vraie application sécurisée.

1. Crée l'entité `User` (`make:user`), l'inscription (`make:registration-form`) et la connexion
   (`make:auth`). Applique les migrations.
2. Ajoute une relation `Article.author` vers `User`, et renseigne l'auteur à la création d'un article
   (`$article->setAuthor($this->getUser())`).
3. Réserve `/admin` à `ROLE_ADMIN` via `access_control`.
4. Écris `ArticleVoter` avec l'action `ARTICLE_EDIT` (auteur de l'article, ou admin), et protège
   l'action d'édition avec `#[IsGranted('ARTICLE_EDIT', subject: 'article')]`.
5. Affiche dans le layout un lien « Connexion / Déconnexion » selon `app.user`, et n'autorise le
   formulaire de commentaire qu'aux utilisateurs connectés.

Ton application est fonctionnelle et sécurisée. Au chapitre suivant, on apprend à la **tester
automatiquement** pour la fiabiliser et oser la faire évoluer sans rien casser.

---

[← Chapitre précédent](08-services-injection.md) · [Sommaire](README.md) · [Chapitre suivant →](10-tests.md)
