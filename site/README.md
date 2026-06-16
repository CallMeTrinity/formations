# Site de formations

Application web (Symfony 8 + MariaDB) pour consulter les formations du dépôt, suivre sa progression
et recevoir des recommandations. Le contenu pédagogique reste écrit en **markdown** dans les dossiers
de formations du dépôt parent ; une commande de synchronisation l'importe en base.

Roadmap et architecture : voir les **milestones** et **issues** GitHub (M0 → M7).

## Prérequis

- PHP 8.3+ (testé avec 8.5)
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

Le design provient de **Claude Design**. On ne code pas le CSS à la main : les **design tokens**
(couleurs, typographie, rayons…) sont déclarés dans `assets/styles/tokens.css` via la directive
`@theme` de Tailwind v4, et les templates n'utilisent que des classes utilitaires dérivées de ces
tokens (`bg-brand-600`, `text-ink`, `bg-canvas`, `rounded-card`…).

`tokens.css` est aujourd'hui un **placeholder neutre**. Pour appliquer un thème :

1. Exporter les tokens depuis Claude Design dans le dossier `/design` à la racine du dépôt.
2. Reporter/importer ces tokens dans `assets/styles/tokens.css` en **conservant les mêmes noms**.
3. `php bin/console tailwind:build`.

Aucun template HTML n'a besoin d'être modifié pour changer l'habillage.

## Structure (en construction)

- `src/Controller/` — contrôleurs (HomeController pour l'instant)
- `templates/` — `base.html.twig` (layout) + vues
- `assets/styles/` — `app.css` (entrée) + `tokens.css` (design tokens)
- `compose.yaml` — service MariaDB 11.4
