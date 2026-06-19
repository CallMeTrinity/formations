# Site de formations

Application web (Symfony 8 + MariaDB) pour consulter les formations du dépôt, suivre sa progression
et recevoir des recommandations. Le contenu pédagogique reste écrit en **markdown** dans les dossiers
de formations du dépôt parent ; une commande de synchronisation l'importe en base.

## Documentation

La documentation technique vit dans [`docs/`](docs/) :

- [`docs/modele-de-donnees.md`](docs/modele-de-donnees.md) — entités, énumérations, invariant de sync.
- [`docs/synchronisation-contenu.md`](docs/synchronisation-contenu.md) — la sync markdown → BDD.
- [`docs/parcours-utilisateur.md`](docs/parcours-utilisateur.md) — catalogue, progression, reco, admin.
- [`docs/securite-et-visibilite.md`](docs/securite-et-visibilite.md) — rôles, firewall, visibilité.

## Fonctionnalités

- **Catalogue** avec recherche plein-texte et filtres par tag / difficulté.
- **Lecture** chapitre par chapitre, navigation précédent / suivant, liens inter-chapitres réécrits.
- **Progression** : inscription, validation de chapitres, complétion, reprise (« recommencer »),
  tableau de bord « Mes formations » avec pourcentage d'avancement.
- **Recommandations** personnalisées (tags / niveau / popularité / fraîcheur) avec repli public.
- **Comptes** : inscription, connexion, profil, préférences de recommandation.
- **Espace admin** : métadonnées, visibilité (brouillon / beta / public), stats d'inscriptions et de
  complétions, resynchronisation du contenu.

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
symfony console doctrine:migrations:migrate
# symfony console doctrine:fixtures:load        # jeu de démo (optionnel)
# php bin/console app:formations:sync           # importer le vrai contenu markdown

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
| `symfony console doctrine:migrations:migrate` | Appliquer les migrations |
| `symfony console doctrine:fixtures:load` | Charger le jeu de démo (catalogue + comptes) |
| `php bin/console tailwind:build --watch` | Recompiler le CSS à chaud |
| `php bin/console debug:router` | Lister les routes |
| `php bin/console app:formations:sync` | Synchroniser le contenu markdown → BDD |

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

## Comptes de démo

Après `doctrine:fixtures:load`, deux comptes sont disponibles (à n'utiliser qu'en local) :

| Email | Mot de passe | Rôle |
|-------|--------------|------|
| `admin@formations.test` | `admin` | `ROLE_ADMIN` |
| `user@formations.test`  | `user`  | `ROLE_USER` |

## Structure

```
site/
├── src/
│   ├── Command/        app:formations:sync (CLI de synchronisation)
│   ├── Controller/     pages publiques, dashboard, profil ; Controller/Admin pour l'espace admin
│   ├── Dto/            objets de transfert du parsing (ParsedReadme, ParsedChapter…)
│   ├── Entity/         contenu (Formation, Chapter, Section, Tag), comptes, progression
│   ├── Enum/           Visibility, Difficulty, SectionType, Status
│   ├── Form/           formulaires (admin, profil, préférences, inscription)
│   ├── Repository/     requêtes (catalogue filtré, stats, recommandables…)
│   ├── Security/Voter/ FormationVoter (accès par visibilité)
│   └── Service/        sync + parsing markdown + rendu HTML + recommandation
├── templates/          base.html.twig (layout) + vues (dont *.stream.html.twig pour Turbo)
├── assets/styles/      app.css (entrée Tailwind v4) + tokens.css (design tokens)
├── config/             packages (security, doctrine…), services, routes
├── migrations/         migrations Doctrine
├── tests/              contrôleurs, services, commande ; fixtures/content pour la sync
├── docs/               documentation technique (voir plus haut)
└── compose.yaml        service MariaDB 11.4
```
