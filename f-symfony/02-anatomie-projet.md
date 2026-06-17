# Anatomie d'un projet Symfony

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-routes-et-controleurs.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- te repérer dans l'**arborescence** d'un projet Symfony et dire à quoi sert chaque dossier ;
- comprendre le rôle du **front controller** (`public/index.php`) et de l'**autoloading** ;
- distinguer les **environnements** (`dev`, `prod`, `test`) et le fichier `.env` ;
- utiliser la **barre de debug** et le **profiler** pour inspecter une page ;
- lancer la **console** `bin/console` et lister les commandes disponibles.

## L'arborescence d'un projet

Ouvre le dossier `blog` créé au chapitre précédent dans ton éditeur. Au premier coup d'œil, il y a
beaucoup de dossiers. Pas de panique : tu ne toucheras qu'à une petite partie d'entre eux. Voici la
carte.

```text
blog/
├── bin/            outils en ligne de commande (dont la console : bin/console)
├── config/         configuration de l'application (routes, services, paquets)
├── migrations/     scripts de modification de la base de données (chapitre 5)
├── public/         seul dossier exposé au web ; contient index.php (le point d'entrée)
├── src/            TON code : contrôleurs, entités, services… le cœur du travail
├── templates/      les vues Twig (le HTML)
├── tests/          les tests automatisés (chapitre 10)
├── translations/   fichiers de traduction
├── var/            fichiers générés : cache et logs (jamais édités à la main)
├── vendor/         les dépendances installées par Composer (jamais éditées)
├── .env            variables d'environnement (configuration par valeurs)
└── composer.json   liste des dépendances du projet
```

Deux principes pour s'y retrouver :

- **Tu écris presque tout dans `src/` et `templates/`.** Le reste, c'est de la configuration ou des
  fichiers générés.
- **Tu ne modifies jamais `vendor/` ni `var/`.** `vendor/` appartient à Composer, `var/` est
  regénéré par Symfony. Ils ne sont d'ailleurs pas versionnés dans Git.

> **À retenir** — `src/` = ton code. `templates/` = tes vues. `config/` = les réglages.
> `public/` = la porte d'entrée web. Le reste sert l'infrastructure.

## Le front controller : `public/index.php`

Quand quelqu'un visite ton site, **toutes** les URL passent par un seul et même fichier :
`public/index.php`. On l'appelle le **front controller** (« contrôleur frontal ») : c'est l'unique
porte d'entrée de l'application.

Pourquoi un seul fichier ? Parce que cela centralise tout : Symfony peut alors, à chaque requête,
initialiser le framework, lire la configuration, router l'URL et préparer la réponse de façon
uniforme. Ouvre `public/index.php` : il est tout petit. Il se contente de démarrer le **Kernel**, le
noyau qui assemble Symfony.

C'est aussi pour cette raison que **seul le dossier `public/` est exposé au web**. Tout le reste de
ton projet (ton code, ta config, tes secrets) reste à l'abri, hors de portée du navigateur.

## L'autoloading : tes classes chargées toutes seules

En PHP « à la main », il faut écrire `require 'chemin/vers/MaClasse.php';` pour chaque classe utilisée.
C'est vite ingérable. Symfony et Composer mettent en place l'**autoloading** (« chargement
automatique ») : dès que tu utilises une classe, le bon fichier est inclus tout seul.

La règle qui relie un **nom de classe** à un **fichier** s'appelle **PSR-4**. Dans un projet Symfony,
le préfixe `App\` correspond au dossier `src/`. Donc :

```text
Classe  App\Controller\BlogController
Fichier src/Controller/BlogController.php
```

Le **namespace** (« espace de noms », le `App\Controller` devant le nom de la classe) suit donc
l'arborescence des dossiers. Tu n'as rien à configurer : respecte cette correspondance et tes classes
sont trouvées automatiquement.

> **Attention** — Le nom du fichier et le nom de la classe doivent être **identiques**, casse
> comprise : la classe `BlogController` vit dans `BlogController.php`. Une faute de casse provoque une
> erreur « class not found » (classe introuvable).

## Les environnements : `dev`, `prod`, `test`

Une application ne se comporte pas pareil pendant que tu la développes et une fois en ligne. En
développement, tu veux des **messages d'erreur détaillés** et des outils de debug. En production, tu
veux de la **vitesse** et des erreurs discrètes. Symfony gère ça avec des **environnements** :

- `dev` — le développement : erreurs détaillées, cache rafraîchi automatiquement, barre de debug.
- `prod` — la production : optimisé pour la performance, erreurs masquées aux visiteurs.
- `test` — utilisé pour exécuter les tests automatisés (chapitre 10).

L'environnement actif est défini par la variable `APP_ENV`, dans le fichier `.env` à la racine :

```bash
# .env
APP_ENV=dev
APP_SECRET=...
```

Le fichier `.env` contient les **variables d'environnement** : des réglages sous forme de
`CLÉ=valeur` (l'environnement actif, l'adresse de la base de données, des clés secrètes…). En
développement, laisse `APP_ENV=dev`.

> **Attention** — Ne mets jamais de vrai secret (mot de passe de base de données de production, clé
> d'API) dans `.env` versionné par Git. Pour les valeurs sensibles, Symfony fournit un fichier local
> `.env.local` (ignoré par Git) et un coffre de secrets. On y reviendra au chapitre déploiement.

## La barre de debug et le profiler

C'est l'un des plus grands plaisirs de Symfony en développement. Avec ton serveur lancé, retourne sur
une page de ton site : en bas de l'écran, une **barre de debug** (*web debug toolbar*) s'affiche.
Elle résume une foule d'informations sur la page en cours :

- le **code de statut** HTTP (200 si tout va bien) ;
- le **temps** de génération et la **mémoire** consommée ;
- le **contrôleur** qui a répondu et la **route** empruntée ;
- le nombre de **requêtes en base de données**, les éventuelles erreurs, etc.

Clique sur n'importe quel élément de cette barre : tu ouvres le **profiler**, un tableau de bord
complet qui détaille tout ce qui s'est passé pendant la requête. C'est ton meilleur allié pour
comprendre un comportement ou traquer un bug.

> **À retenir** — La barre de debug n'apparaît qu'en environnement `dev`, jamais en `prod`. Si tu ne
> la vois pas, vérifie que `APP_ENV=dev`.

## La console : `bin/console`

Symfony fournit un outil en ligne de commande très riche : la **console**, qu'on appelle avec
`php bin/console` depuis la racine du projet. Elle sert à tout : générer du code, inspecter la
configuration, jouer avec la base de données.

Lance-la sans argument pour voir la liste de toutes les commandes :

```bash
$ php bin/console
```

Quelques commandes utiles dès maintenant :

```bash
# Afficher des infos sur le projet (version de Symfony, environnement, etc.)
$ php bin/console about

# Lister toutes les routes de l'application
$ php bin/console debug:router
```

`debug:router` est précieux : il te montre, sous forme de tableau, toutes les URL que ton application
sait gérer. Pour l'instant la liste est courte ; elle grandira à chaque route que tu ajouteras.

> **Astuce** — Si tu utilises la Symfony CLI, tu peux écrire `symfony console` au lieu de
> `php bin/console` : c'est la même chose, mais la CLI choisit automatiquement la bonne version de
> PHP. Les deux formes sont équivalentes dans toute la formation.

## Résumé

- L'essentiel de ton travail se fait dans **`src/`** (ton code) et **`templates/`** (tes vues) ;
  **`config/`** contient les réglages, **`public/`** est la porte d'entrée web.
- Tu ne touches jamais à **`vendor/`** (géré par Composer) ni à **`var/`** (cache et logs générés).
- Toutes les requêtes passent par le **front controller** `public/index.php`, seule porte exposée.
- L'**autoloading** PSR-4 relie `App\` à `src/` : le namespace suit l'arborescence, les classes sont
  chargées automatiquement.
- Les **environnements** (`dev`, `prod`, `test`) changent le comportement de l'app ; `APP_ENV` dans
  `.env` choisit l'actif.
- La **barre de debug** et le **profiler** (en `dev`) t'aident à inspecter chaque page.
- La **console** `php bin/console` est ton couteau suisse : `about`, `debug:router`, et bien d'autres.

## Exercices

### Exercice 1 — Explorer la console

Dans ton projet `blog`, affiche les informations générales du projet, puis la liste des routes
existantes. Quelle version de Symfony est installée ?

<details>
<summary>Voir le corrigé</summary>

La démarche : `about` donne le résumé du projet, `debug:router` liste les routes.

```bash
$ php bin/console about
$ php bin/console debug:router
```

Dans la sortie de `about`, repère la ligne « Symfony » sous la section *Symfony* : elle affiche la
version (par exemple `8.0.x`). `debug:router` montre déjà quelques routes internes (comme celles du
profiler en `dev`).

</details>

### Exercice 2 — Lire la barre de debug

Avec le serveur lancé, ouvre la page d'accueil et utilise la barre de debug pour répondre : quel est
le **code de statut HTTP** de la page, et combien de temps a-t-elle mis à se générer ?

<details>
<summary>Voir le corrigé</summary>

La démarche : tout est lisible directement dans la barre en bas de page, sans rien coder.

Le **code de statut** s'affiche à gauche de la barre (un `200` vert signifie « tout va bien »). Le
**temps** de génération est indiqué juste à côté (par exemple `45 ms`). Clique sur le temps pour
ouvrir le profiler et voir le détail du *timeline*.

Si la barre n'apparaît pas, vérifie dans `.env` que `APP_ENV=dev`.

</details>

## Quiz

**1.** Quel dossier contient le code que tu écris (contrôleurs, entités…) ?
- A. `vendor/`
- B. `src/`
- C. `var/`

**2.** Pourquoi seul le dossier `public/` est-il exposé au web ?
- A. Parce que c'est le plus gros dossier
- B. Pour que le reste du code (et les secrets) reste hors de portée du navigateur
- C. Parce que Twig l'exige

**3.** À quoi correspond le préfixe `App\` dans l'autoloading PSR-4 ?
- A. Au dossier `vendor/`
- B. Au dossier `src/`
- C. Au dossier `public/`

**4.** En environnement `prod`, que devient la barre de debug ?
- A. Elle s'affiche en rouge
- B. Elle disparaît (elle n'existe qu'en `dev`)
- C. Elle reste mais sans informations

<details>
<summary>Voir les réponses</summary>

1. **B** — Ton code vit dans `src/` (et tes vues dans `templates/`).
2. **B** — Isoler `public/` protège ton code et tes secrets ; seul le front controller est joignable.
3. **B** — `App\` pointe vers `src/` ; le namespace suit l'arborescence des dossiers.
4. **B** — La barre de debug n'existe qu'en `dev`, jamais en production.

</details>

## Projet fil rouge

On ne code pas encore, on prend ses marques dans le projet `blog`.

1. Ouvre le dossier `blog` dans ton éditeur et repère `src/`, `templates/`, `config/`, `public/`.
2. Ouvre `public/index.php` et constate à quel point il est court : c'est la seule porte d'entrée.
3. Lance la console et explore : `php bin/console about` puis `php bin/console debug:router`.
4. Charge la page d'accueil et clique dans la barre de debug pour ouvrir le profiler.

Au chapitre suivant, tu écris ton premier vrai bout de code : une **route** et un **contrôleur** qui
afficheront la page d'accueil de ton blog.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-routes-et-controleurs.md)
