# Sécurité et visibilité

Ce document décrit l'authentification, les rôles, le firewall, et la mécanique de **visibilité des
formations** (qui voit quoi). Les flux applicatifs concernés sont détaillés dans
[`parcours-utilisateur.md`](parcours-utilisateur.md).

## Comptes et rôles

- L'entité `User` implémente `UserInterface` + `PasswordAuthenticatedUserInterface`. L'**email** est
  l'identifiant de connexion (unique, contrainte `UNIQ_IDENTIFIER_EMAIL` + `UniqueEntity`).
- `getRoles()` garantit toujours `ROLE_USER`. `ROLE_ADMIN` est ajouté à la main (fixtures, ou en
  base).
- Mots de passe hashés avec l'algorithme `auto` (`security.password_hashers`). En test, le coût est
  réduit pour accélérer la suite.
- Astuce Symfony 7.3+ : `User::__serialize()` remplace le hash du mot de passe par un CRC32C dans la
  session, pour ne pas y stocker le hash réel.

## Firewall et authentification

Configuration dans `config/packages/security.yaml`, firewall `main` :

- **`form_login`** sur `app_login` (login et check), **CSRF activé**.
- **`logout`** sur `app_logout`.
- **`remember_me`** : cookie signé avec `kernel.secret`, durée 7 jours (604800 s).
- Firewall `dev` désactivé pour le profiler et les assets.

L'inscription connecte automatiquement le nouvel utilisateur (`Security::login`), de même qu'un
changement de mot de passe re-connecte l'utilisateur courant.

## Contrôle d'accès

Deux niveaux complémentaires :

### 1. `access_control` (grain large, par URL)

```yaml
access_control:
    - { path: ^/profile, roles: ROLE_USER }
    - { path: ^/admin,   roles: ROLE_ADMIN }
```

Seule la **première règle qui matche** s'applique. Tout ce qui est sous `/admin` exige `ROLE_ADMIN`,
tout ce qui est sous `/profile` exige `ROLE_USER`.

### 2. Attributs `#[IsGranted]` (grain fin, par action)

Les actions qui écrivent de la progression (`enroll`, `unenroll`, `complete`, `restart`,
`chapterNext`, `toggleChapterComplete`) portent `#[IsGranted('ROLE_USER')]`. Le contrôleur admin
porte `#[IsGranted('ROLE_ADMIN')]` au niveau de la classe — **en plus** de la règle `access_control`
sur `^/admin` (ceinture + bretelles).

## Visibilité des formations

C'est le cœur du « qui voit quoi ». L'énumération `Visibility` a trois valeurs :

| Visibilité | Accessible à |
|------------|--------------|
| `PUBLIC`   | tout le monde, anonyme compris |
| `BETA`     | tout utilisateur connecté (`ROLE_USER`) |
| `DRAFT`    | admin uniquement (`ROLE_ADMIN`) |

> **Une seule règle, deux points d'application.** La même logique vit à deux endroits qui doivent
> toujours donner le même verdict :
>
> - **`FormationVoter`** (`VIEW`) — décision unitaire sur **une** formation (page de détail, lecture
>   d'un chapitre).
> - **`FormationRepository::visibilitiesFor()`** — liste des visibilités autorisées, utilisée pour
>   **filtrer les listes** (catalogue, recommandations) directement en SQL.
>
> Si tu modifies l'une, vérifie l'autre.

### Refus d'accès et fuite d'information

`FormationController::denyAccessUnlessVisible()` distingue deux cas de refus :

- une formation en **`DRAFT`** renvoie **404** — pour ne pas révéler son existence ;
- les autres refus renvoient **403** — qu'un visiteur anonyme verra comme une redirection vers le
  login (via l'entry point `form_login`).

## Protection CSRF

- Le `form_login` a `enable_csrf: true`.
- Les formulaires Symfony (inscription, profil, préférences, métadonnées admin) sont protégés par
  défaut.
- Les **actions POST hors formulaire** valident un jeton CSRF dédié à la main, avec un identifiant lié
  à la ressource pour éviter le rejeu. Exemples :
  - `enroll<formationId>`, `unenroll<formationId>`, `complete_formation<formationId>`,
    `restart_formation<formationId>` ;
  - `chapter_next<chapterId>`, `complete<chapterId>` ;
  - `admin_visibility<formationId>`, `admin_resync`.

Un jeton invalide lève une `AccessDeniedException`.

## Comptes de démonstration (fixtures)

`AppFixtures` crée deux comptes pratiques pour le développement local
(`symfony console doctrine:fixtures:load`) :

| Email | Mot de passe | Rôle |
|-------|--------------|------|
| `admin@formations.test` | `admin` | `ROLE_ADMIN` |
| `user@formations.test`  | `user`  | `ROLE_USER` (avec préférences pré-remplies) |

> Identifiants de démo, jamais à utiliser en production. Les fixtures créent aussi des apprenants
> « fantômes » dont le seul rôle est de gonfler la popularité de certaines formations pour tester le
> tri des recommandations.

## Voir aussi

- [`parcours-utilisateur.md`](parcours-utilisateur.md) — les flux qui s'appuient sur ces règles.
- [`modele-de-donnees.md`](modele-de-donnees.md) — entités `User`, `UserPreferences`, et l'énumération
  `Visibility`.
