# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# Dépôt de formations

Ce dépôt a **deux facettes** :

1. **Le contenu pédagogique** — des formations techniques en markdown (un sous-dossier par formation,
   un fichier par chapitre). Voir le catalogue et les statuts dans [`README.md`](README.md).
2. **Le site** ([`site/`](site/)) — une application **Symfony 8.1 + MariaDB** qui importe ce contenu
   markdown en base pour le consulter, suivre sa progression et recevoir des recommandations.

Le contenu reste la source de vérité : il vit en markdown et est importé en base par une commande de
sync. Les deux facettes sont indépendantes — savoir laquelle on touche avant de commencer.

## Partie contenu — règle impérative

Toute création ou modification d'une formation **doit suivre les fichiers de consignes**, lus dans cet
ordre avant de rédiger quoi que ce soit :

1. [`consignes/charte-pedagogique.md`](consignes/charte-pedagogique.md) — public, objectif, ton.
2. [`consignes/structure-formation.md`](consignes/structure-formation.md) — arborescence, nommage,
   navigation, statuts.
3. [`consignes/format-chapitre.md`](consignes/format-chapitre.md) — anatomie d'un fichier chapitre.
4. [`consignes/style-redactionnel.md`](consignes/style-redactionnel.md) — langue, voix, conventions.
5. [`consignes/workflow-creation.md`](consignes/workflow-creation.md) — process pas à pas (point
   d'entrée opérationnel).

Conventions transverses (contenu) :

- Langue : **français**, tutoiement. **Aucun emoji**. Mise en valeur via titres explicites, **gras**,
  blockquotes `>` à libellé, blocs `<details>` pour masquer corrigés et réponses.
- Slugs en `kebab-case` sans accents ; fichiers chapitres préfixés `01-`, `02-`, etc.
- Objectif de chaque formation : mener un **novice absolu** à un **niveau intermédiaire**.
- Gabarits prêts à copier dans [`templates/`](templates/).

# Partie site (`site/`)

Symfony 8.1 sur PHP 8.4+, MariaDB 11.4 (via Docker), front en **AssetMapper + Tailwind v4** (pas de
build Node côté JS). Toutes les commandes ci-dessous s'exécutent **depuis `site/`**.

## Commandes

**Toujours préférer `symfony console …` à `php bin/console …`** pour tout ce qui touche la base : le
binaire Symfony détecte le conteneur Docker et injecte le bon port dans `DATABASE_URL`.

```bash
composer install
docker compose up -d                                     # MariaDB
symfony console doctrine:database:create --if-not-exists
symfony console doctrine:migrations:migrate              # appliquer les migrations
symfony server:start -d                                  # serveur de dev

php bin/console tailwind:build            # build CSS unique
php bin/console tailwind:build --watch    # mode watch
php bin/console app:formations:sync       # importer le markdown du dépôt parent → BDD
```

Qualité (mêmes outils que la CI) :

```bash
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix    # style (ruleset @Symfony)
vendor/bin/phpstan analyse                               # niveau défini dans phpstan.dist.neon
php bin/phpunit                                          # tests
php bin/phpunit tests/Controller/HomeControllerTest.php  # un seul fichier
php bin/phpunit --filter testNomDeLaMethode              # un seul test
```

> PHPStan utilise le container XML de dev (`var/cache/dev/...`) pour comprendre les services Symfony :
> lancer une commande `dev` au moins une fois avant l'analyse si le cache est vide.

## Architecture

**Flux de données central — la sync markdown → BDD.** Le contenu pédagogique n'est jamais saisi en
base à la main : la commande `app:formations:sync` lit les dossiers markdown du dépôt parent et fait un
**upsert idempotent par `slug`**. Le `ChapterParser` découpe chaque chapitre en `Section` selon les
titres `##`, mappés sur l'enum `SectionType`.

> **Invariant de sync à respecter impérativement** : la sync ne réécrit que les champs **contenu**
> (`title`, `description`, `chapters`, `sections`…). Les champs **admin** d'une `Formation`
> (`visibility`, `difficulty`, `tags`, `estimatedMinutes`) et toutes les données de suivi utilisateur
> **ne sont jamais écrasés**. Toute évolution du modèle ou de la commande doit préserver cette
> séparation.

**Trois groupes d'entités** (`src/Entity/`, détail dans [`site/docs/modele-de-donnees.md`](site/docs/modele-de-donnees.md)) :

- **Contenu** — `Formation` → `Chapter` → `Section`, plus `Tag` (ManyToMany). Alimenté par la sync.
- **Comptes & préférences** — `User`, `UserPreferences` (OneToOne, avec `preferredTags`).
- **Progression** — `Enrollment` (unique par `(user, formation)`) → `ChapterProgress`.

Enums dans `src/Enum/` : `Visibility` (`DRAFT`/`BETA`/`RELEASED`, contrôle l'accès via un
`FormationVoter` + filtrage repository), `Difficulty`, `SectionType`, `Status` (statut éditorial,
distinct de `visibility`).

**Design — ne pas écrire de CSS à la main.** Le design vient de **Claude Design** (système
« Devcurriculum ») exporté dans [`design/`](design/) à la racine (dossier gitignoré). Les tokens
(`assets/styles/tokens.css`, oklch, thèmes light/dark via `[data-theme]`) sont **synchronisés depuis
`/design/tokens.css`**. `assets/styles/app.css` les expose à Tailwind v4 via `@theme inline` et définit
une librairie de composants (`.btn`, `.card`, `.badge`, `.tag`, `.input`, `.callout`, `.progress`…).
Pour appliquer un nouvel export : recopier `tokens.css`, puis `php bin/console tailwind:build` — aucun
template à modifier.

## CI/CD

- **CI** (`.github/workflows/ci.yml`) — sur push/PR touchant `site/` : PHP-CS-Fixer (dry-run), PHPStan,
  PHPUnit, avec un service MariaDB.
- **Déploiement** (`.github/workflows/deploy.yml`) — sur tag `vX.Y.Z` (ou manuel) : SSH vers Infomaniak
  et exécution de [`deploy.sh`](deploy.sh) (racine du dépôt), qui checkout le dernier tag puis, dans
  `site/`, fait `composer install --no-dev`, migrations, build des assets et `cache:clear`. Cible :
  `formations.antoninpamart.fr` ; document root → `site/public`.
