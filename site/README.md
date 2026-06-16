# Site de formations

Application web (Symfony 8 + MariaDB) pour consulter les formations du dépôt, suivre sa progression
et recevoir des recommandations. Le contenu pédagogique reste écrit en **markdown** dans les dossiers
de formations du dépôt parent ; une commande de synchronisation l'importe en base.

Roadmap et architecture : voir les **milestones** et **issues** GitHub (M0 → M7).

## Prérequis

- PHP 8.4+ (testé avec 8.5)
- Composer 2
- Docker (pour MariaDB)
- [Symfony CLI](https://symfony.com/download) (serveur de dev + détection auto du port Docker)

## Installation

```bash
cd site
composer install

# Base de données (MariaDB via Docker)
docker compose up -d
symfony console doctrine:database:create --if-not-exists
# symfony console doctrine:migrations:migrate   # quand des migrations existent

# Assets (Tailwind v4 via AssetMapper)
php bin/console tailwind:build        # build unique
# php bin/console tailwind:build --watch   # mode watch en développement

# Lancer le serveur
symfony server:start -d
symfony open:local
```

> **Astuce** — Utilise toujours `symfony console ...` (et non `php bin/console`) pour les commandes qui
> touchent la base : le binaire Symfony détecte le conteneur Docker et injecte automatiquement le bon
> port dans `DATABASE_URL`.

## Commandes utiles

| Commande | Rôle |
|----------|------|
| `symfony console dbal:run-sql "SELECT 1"` | Vérifier la connexion DB |
| `php bin/console tailwind:build --watch` | Recompiler le CSS à chaud |
| `php bin/console debug:router` | Lister les routes |
| `php bin/console app:formations:sync` | (M1) Synchroniser le contenu markdown → BDD |

## Design

Le design provient de **Claude Design** (système « Devcurriculum ») exporté dans le dossier
`/design` à la racine du dépôt. On ne code pas le CSS à la main.

- **`assets/styles/tokens.css`** est **synchronisé depuis `/design/tokens.css`** : variables CSS
  brutes (`--bg`, `--text`, `--accent`, échelle typo/spacing/radius), en oklch, avec thèmes
  **light/dark** via `[data-theme]`. Polices **IBM Plex Sans / Mono** (chargées dans `base.html.twig`).
- **`assets/styles/app.css`** fait le pont vers Tailwind v4 via un bloc `@theme inline` : il expose
  ces tokens comme tokens de thème, ce qui génère les utilitaires correspondants (`bg-accent`,
  `text-fg`, `text-muted`, `border-line`, `rounded-md`, `text-md`, `font-mono`…). Le mot-clé
  `inline` préserve le basculement light/dark.
- Une **librairie de composants préchargés** (`@layer components`) reproduit le design system :
  `.btn`/`.btn--primary|secondary|ghost|danger`, `.card`, `.badge`, `.tag`, `.input`, `.callout`,
  `.progress`, `.eyebrow`, `.container-page`, `.prose-chapter`…

Pour ré-appliquer un nouvel export Claude Design : recopier `/design/tokens.css` dans
`assets/styles/tokens.css` (mêmes noms de tokens) puis `php bin/console tailwind:build`. Aucun
template n'a besoin d'être modifié.

## Qualité & déploiement (CI/CD)

- **CI** (`.github/workflows/ci.yml`) — sur push/PR touchant `site/` : PHP-CS-Fixer (dry-run),
  PHPStan (niveau 6), PHPUnit, avec un service MariaDB.
- **Déploiement** (`.github/workflows/deploy.yml`) — sur tag `vX.Y.Z` (ou lancement manuel) : SSH vers
  Infomaniak et exécution de `deploy.sh` (à la racine du dépôt), qui checkout le dernier tag puis,
  dans `site/`, fait composer install --no-dev, migrations, build des assets et cache:clear.
  Cible : `formations.antoninpamart.fr`. Variables GitHub : `SSH_HOST`, `SSH_PORT`, `SSH_USER`,
  `APP_DIR` ; secret : `SSH_PRIVATE_KEY`. Le document root du domaine doit pointer vers `site/public`.

Outils en local :

```bash
PHP_CS_FIXER_IGNORE_ENV=1 vendor/bin/php-cs-fixer fix    # corriger le style
vendor/bin/phpstan analyse                               # analyse statique
php bin/phpunit                                          # tests
```

## Structure (en construction)

- `src/Controller/` — contrôleurs (HomeController pour l'instant)
- `templates/` — `base.html.twig` (layout) + vues
- `assets/styles/` — `app.css` (entrée) + `tokens.css` (design tokens)
- `compose.yaml` — service MariaDB 11.4
