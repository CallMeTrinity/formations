# Industrialisation et déploiement avancé

[← Chapitre précédent](11-tests-avances-qualite.md) · [Sommaire](README.md) · [Chapitre suivant →](13-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- pourquoi **conteneuriser** une application et comment écrire un `Dockerfile` Symfony ;
- orchestrer ton app, sa base et ses dépendances avec **Docker Compose** ;
- gérer proprement les **secrets** en production ;
- accélérer ton application avec le **cache HTTP** et le **cache applicatif** ;
- surveiller ce qui se passe en production grâce aux **logs** et au **monitoring**.

## Le problème : « ça marche sur ma machine »

La partie 1 t'a appris la checklist de mise en production (env `prod`, dépendances optimisées, cache,
migrations, `public/`). Mais une question reste : comment garantir que l'application tourne **de la
même façon** sur ta machine, sur celle d'un collègue, sur le serveur de test et en production ? Les
versions de PHP diffèrent, les extensions manquent, la configuration varie. C'est le fameux « ça
marche sur ma machine ».

La réponse moderne est la **conteneurisation** avec **Docker** : on emballe l'application **et son
environnement** (la version exacte de PHP, les extensions, la configuration) dans une **image**
reproductible. Cette image tourne à l'identique partout. Tu ne déploies plus « du code », tu déploies
un **environnement complet et figé**.

> **À retenir** — Un **conteneur** est une boîte qui contient ton application **et** tout ce dont elle
> a besoin pour tourner. Même boîte en dev, en test et en prod : fini les différences d'environnement.

## Installer Docker

Docker est la nouveauté machine de cette partie. Installe **Docker Desktop** (Windows, macOS) ou
**Docker Engine** (Linux) depuis le site officiel, puis vérifie :

```bash
$ docker --version
$ docker compose version
```

Deux notions à fixer :

- une **image** est un modèle figé (l'application + son environnement), construit à partir d'un
  `Dockerfile` ;
- un **conteneur** est une **instance** en cours d'exécution d'une image (on peut en lancer
  plusieurs à partir de la même image).

## Écrire un Dockerfile pour Symfony

Le `Dockerfile` décrit, étape par étape, comment construire l'image de ton application. En voici un
adapté à Symfony, commenté.

```dockerfile
# Dockerfile
# On part d'une image officielle PHP 8.4 avec le serveur FPM.
FROM php:8.4-fpm-alpine

# Installer les extensions PHP requises par Symfony et Doctrine.
RUN docker-php-ext-install pdo pdo_mysql opcache intl

# Installer Composer depuis son image officielle (copie de l'exécutable).
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copier d'abord les fichiers de dépendances, pour profiter du cache de build.
COPY composer.json composer.lock ./
# Installer les dépendances optimisées, sans les paquets de dev (comme la checklist prod).
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copier le reste du code.
COPY . .

# Préparer l'environnement de production.
ENV APP_ENV=prod
RUN php bin/console cache:clear

EXPOSE 9000
CMD ["php-fpm"]
```

Quelques points clés :

- on **part** d'une image officielle qui fixe la version de PHP : plus de surprise de version ;
- on **copie d'abord** `composer.json`/`composer.lock` avant le reste : tant que les dépendances ne
  changent pas, Docker réutilise cette couche en cache et le build est rapide ;
- on installe les dépendances en mode **production** (`--no-dev --optimize-autoloader`), exactement
  comme la checklist du chapitre 12 de la partie 1.

On construit l'image :

```bash
$ docker build -t blog-app .
# -t blog-app : on nomme l'image « blog-app »
```

> **Astuce** — Pour les projets sérieux, on utilise un **multi-stage build** (plusieurs `FROM`) :
> une étape pour construire (avec Composer et les outils), une étape finale minimale qui ne contient
> que le nécessaire à l'exécution. L'image finale est plus petite et plus sûre. Garde le nom en tête.

## Orchestrer avec Docker Compose

Ton application ne vit pas seule : elle a besoin d'une **base de données**, peut-être de **Redis** (pour
le cache ou Messenger), d'un **serveur web**. **Docker Compose** décrit tous ces **services** et leurs
liens dans un seul fichier, et les lance ensemble.

```yaml
# compose.yaml
services:
    php:
        build: .                       # construit l'image depuis le Dockerfile
        depends_on: [database]
        environment:
            DATABASE_URL: "mysql://app:app@database:3306/blog"

    database:
        image: mysql:8.4
        environment:
            MYSQL_DATABASE: blog
            MYSQL_USER: app
            MYSQL_PASSWORD: app
            MYSQL_ROOT_PASSWORD: root
        volumes:
            - db_data:/var/lib/mysql    # les données survivent à l'arrêt du conteneur

    web:
        image: nginx:alpine
        depends_on: [php]
        ports:
            - "8080:80"                 # le site est accessible sur http://localhost:8080
        volumes:
            - ./public:/var/www/public:ro
            - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro

volumes:
    db_data:
```

```bash
$ docker compose up -d          # démarre tous les services en arrière-plan
$ docker compose ps             # liste les conteneurs et leur état
$ docker compose exec php php bin/console doctrine:migrations:migrate   # commande DANS le conteneur
$ docker compose down           # arrête et supprime les conteneurs
```

Remarque l'élégance : `database:3306` dans l'`DATABASE_URL` — les services se parlent **par leur nom**
sur un réseau interne. Et le serveur web pointe sur **`public/`** uniquement (rappel de sécurité de la
partie 1). Tu démarres toute la pile — PHP, base, serveur web — en **une commande**.

> **À retenir** — Docker Compose orchestre **plusieurs services** liés. C'est ton environnement
> complet, versionné dans le dépôt : un nouveau membre de l'équipe lance `docker compose up` et a tout,
> sans rien installer à la main.

## Les secrets en production

Rappel de la partie 1 : **aucun secret dans Git**. En conteneurs, on injecte les secrets par
**variables d'environnement** au lancement (jamais en dur dans le `Dockerfile` ni dans `compose.yaml`
versionné). Symfony fournit aussi un **coffre de secrets** chiffré.

```bash
# Créer un secret chiffré pour l'environnement prod (la valeur est demandée puis chiffrée)
$ php bin/console secrets:set DATABASE_PASSWORD --env=prod

# La clé de déchiffrement de prod NE doit PAS être commitée ; on la fournit au serveur séparément.
```

Le principe : le coffre **chiffré** peut être versionné, mais la **clé** qui le déchiffre vit
uniquement sur le serveur (variable d'environnement ou fichier protégé). Sans la clé, le coffre est
illisible. Pour la clé privée **JWT** du chapitre 7, c'est le même réflexe : hors de Git, fournie au
serveur de façon sécurisée.

> **Attention** — Vérifie ton `.gitignore` : `.env.local`, `config/jwt/`, et toute clé de
> déchiffrement de secrets doivent en faire partie. Un secret poussé sur un dépôt, même privé, doit
> être considéré comme **compromis** et changé.

## Le cache HTTP : ne pas recalculer ce qui ne change pas

La page d'un article publié change rarement. La recalculer (requêtes SQL, rendu Twig) à **chaque**
visite est du gaspillage. Le **cache HTTP** permet de dire « cette réponse est valable X temps » :
le navigateur, ou un cache partagé (*reverse proxy*), la **réutilise** sans repasser par ton code.

```php
// Dans un contrôleur, sur une page publique peu changeante
public function show(Article $article): Response
{
    $response = $this->render('blog/show.html.twig', ['article' => $article]);

    $response->setPublic();                    // peut être mise en cache par un cache partagé
    $response->setMaxAge(3600);                // valable 1 heure
    // Alternative fine : validation par ETag / Last-Modified

    return $response;
}
```

Symfony fournit un **reverse proxy de cache** intégré (HttpCache) pour démarrer sans serveur dédié ; en
production, on met souvent un cache devant l'application (Varnish, ou le cache d'un CDN). Le composant
**ESI** permet même de mettre en cache une page **par morceaux** (le contenu de l'article longtemps, le
bloc « commentaires récents » brièvement).

> **À retenir** — Le cache HTTP, c'est **ne pas refaire le travail** pour une réponse identique. Le
> plus dur n'est pas de mettre en cache, c'est de savoir **combien de temps** et **quand invalider** :
> commence par les pages clairement statiques (article publié), avec une durée modérée.

## Le cache applicatif : mémoriser un calcul coûteux

Le **cache applicatif** mémorise un **résultat de calcul** côté serveur : une requête lourde, un appel
à une API externe, des statistiques. On utilise le composant **Cache** de Symfony, qui offre une API
unique quel que soit le stockage (fichiers, Redis…).

```php
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

public function popularTags(CacheInterface $cache, TagRepository $tags): array
{
    // Si la valeur est en cache, on la renvoie ; sinon on la calcule et on la stocke.
    return $cache->get('popular_tags', function (ItemInterface $item) use ($tags) {
        $item->expiresAfter(3600);          // recalcul au plus une fois par heure
        return $tags->findMostUsed(10);     // requête potentiellement coûteuse
    });
}
```

La première fois, la fonction s'exécute et le résultat est stocké sous la clé `popular_tags`. Les
appels suivants (pendant une heure) renvoient la valeur mémorisée, **sans toucher la base**. En
production, on configure un stockage rapide comme **Redis** pour ce cache.

> **Astuce** — Mesure d'abord (profiler, chapitre 1) **ce qui est réellement lent**, puis mets en
> cache **ça**. Mettre en cache au hasard ajoute de la complexité (et des bugs d'invalidation) sans
> bénéfice. Le cache répond à un problème mesuré, pas à une intuition.

## Surveiller la production : logs et monitoring

En production, tu ne vois plus le profiler. Tes yeux deviennent les **logs** et le **monitoring**.

Symfony journalise via **Monolog**. On configure des **canaux** et des **niveaux** : en prod, on garde
les erreurs et avertissements, on route les erreurs critiques vers une alerte (e-mail, Slack), et on
peut envoyer les logs vers un service centralisé.

```yaml
# config/packages/prod/monolog.yaml (extrait)
monolog:
    handlers:
        main:
            type: fingers_crossed       # n'écrit les logs que si une erreur survient
            action_level: error
            handler: nested
        nested:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
```

`fingers_crossed` est malin : en temps normal il ne pollue pas les logs, mais **dès qu'une erreur
survient**, il écrit aussi tout le contexte qui a précédé — précieux pour comprendre **pourquoi**.

Le **monitoring** va plus loin : des outils (Sentry pour les erreurs, ou un APM comme Blackfire pour
les performances) capturent automatiquement les exceptions et les lenteurs, avec le contexte, et
t'alertent. Tu sais qu'un problème existe **avant** que les utilisateurs ne se plaignent.

> **À retenir** — En production, **logs** (Monolog, `fingers_crossed`) et **monitoring** (Sentry,
> APM) remplacent le profiler. Sans eux, tu déploies à l'aveugle ; avec eux, tu vois les erreurs et
> les lenteurs en temps réel.

## Résumé

- **Docker** emballe l'application **et son environnement** dans une **image** reproductible :
  fini « ça marche sur ma machine ».
- Un **Dockerfile** décrit la construction (PHP figé, extensions, dépendances `--no-dev`) ;
  **Docker Compose** orchestre app + base + web en une commande.
- Les **secrets** restent hors de Git : variables d'environnement ou **coffre de secrets** chiffré,
  dont la **clé** vit sur le serveur.
- Le **cache HTTP** (`setPublic`, `setMaxAge`) évite de recalculer une réponse identique ; le **cache
  applicatif** (composant Cache + Redis) mémorise un calcul coûteux. Mesure avant de cacher.
- En production, **logs** (Monolog, `fingers_crossed`) et **monitoring** (Sentry/APM) sont tes yeux.

## Exercices

### Exercice 1 — Conteneuriser le blog

Écris un `Dockerfile` et un `compose.yaml` qui lancent ton blog avec sa base. Démarre la pile, applique
les migrations dans le conteneur, et ouvre le site.

<details>
<summary>Voir le corrigé</summary>

La démarche : on réutilise les fichiers de ce chapitre, puis on exécute les commandes **dans** le
conteneur PHP.

```bash
$ docker compose up -d --build
$ docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
# Ouvre http://localhost:8080
```

Si le site ne répond pas, vérifie : le conteneur `web` pointe bien sur `public/`, la base est démarrée
(`docker compose ps`), et `DATABASE_URL` utilise le **nom du service** (`database`) comme hôte.

</details>

### Exercice 2 — Mettre en cache une statistique

Mets en cache, avec le composant Cache, le **nombre total d'articles publiés** affiché en pied de page,
avec une expiration d'une heure. Vérifie au profiler que la requête de comptage ne s'exécute plus à
chaque visite.

<details>
<summary>Voir le corrigé</summary>

La démarche : on enveloppe le comptage dans `cache->get` avec une expiration.

```php
public function countPublished(CacheInterface $cache, ArticleRepository $articles): int
{
    return $cache->get('published_count', function (ItemInterface $item) use ($articles) {
        $item->expiresAfter(3600);
        return $articles->count(['published' => true]);
    });
}
```

Au premier appel, la requête `count` s'exécute ; ensuite, pendant une heure, la valeur vient du cache.
Le profiler ne montre plus la requête de comptage sur les visites suivantes.

</details>

## Quiz

**1.** Quel problème Docker résout-il principalement ?
- A. Il accélère PHP
- B. Il garantit le même environnement partout (« ça marche sur ma machine » disparaît)
- C. Il remplace la base de données

**2.** Quelle est la différence entre une image et un conteneur ?
- A. Aucune
- B. L'image est un modèle figé ; le conteneur est une instance en cours d'exécution
- C. L'image s'exécute, le conteneur se construit

**3.** Où doivent vivre les secrets en production ?
- A. En dur dans le Dockerfile
- B. Hors de Git : variables d'environnement ou coffre de secrets (clé sur le serveur)
- C. Dans `compose.yaml` versionné

**4.** Que fait le cache HTTP `setMaxAge(3600)` ?
- A. Il supprime la réponse après une heure
- B. Il déclare la réponse réutilisable pendant une heure sans repasser par ton code
- C. Il limite la base à 3600 lignes

**5.** Que remplace le profiler en production ?
- A. Rien, on déploie à l'aveugle
- B. Les logs (Monolog) et le monitoring (Sentry/APM)
- C. Les fixtures

<details>
<summary>Voir les réponses</summary>

1. **B** — Docker fige l'environnement, identique partout.
2. **B** — Image = modèle ; conteneur = instance en exécution.
3. **B** — Hors de Git, la clé de déchiffrement sur le serveur.
4. **B** — La réponse est réutilisable pendant la durée fixée.
5. **B** — Logs et monitoring sont tes yeux en production.

</details>

## Projet fil rouge

1. Écris le `Dockerfile` et le `compose.yaml` du blog (PHP, base, serveur web pointant sur `public/`)
   et lance la pile avec `docker compose up`.
2. Vérifie ton `.gitignore` (clés JWT, `.env.local`, clés de secrets) et mets un secret dans le
   **coffre** de Symfony.
3. Ajoute du **cache HTTP** sur la page d'un article publié et du **cache applicatif** sur une
   statistique (exercice 2) ; note l'effet au profiler dans `NOTES.md`.
4. Configure **Monolog** en prod avec `fingers_crossed` et repère où sont écrits les logs.

Ton blog est conteneurisé, mis en cache et observable : il est prêt pour un déploiement sérieux. Au
dernier chapitre, on prend de la hauteur sur l'architecture et on trace la route vers l'expertise.

---

[← Chapitre précédent](11-tests-avances-qualite.md) · [Sommaire](README.md) · [Chapitre suivant →](13-conclusion.md)
