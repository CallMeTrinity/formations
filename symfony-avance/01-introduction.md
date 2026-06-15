# Introduction : passer au niveau avancé

[Sommaire](README.md) · [Chapitre suivant →](02-architecture-maintenable.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- ce qui distingue le **niveau avancé** du niveau intermédiaire, et ce que cette partie 2 va t'apporter ;
- **récupérer le code du blog** de la partie 1 pour démarrer sur les mêmes bases ;
- vérifier que ton **environnement** est prêt (PHP, Composer, Symfony CLI) ;
- exploiter à fond les **outils de diagnostic** (profiler, `debug:*`) qui deviennent tes alliés quotidiens ;
- où on emmène le **projet fil rouge** dans cette partie.

## Du « ça marche » au « ça tient en production »

Dans la partie 1, tu as appris à faire fonctionner une application : afficher des pages, stocker des
données, gérer des utilisateurs. C'est déjà beaucoup. Mais une application réelle, en entreprise,
doit en plus :

- **rester rapide** même avec beaucoup de données et de trafic ;
- **ne jamais faire attendre** l'utilisateur pour une tâche lente (un e-mail, un traitement) ;
- s'**ouvrir à d'autres applications** via une API propre et sécurisée ;
- offrir une **interface réactive** sans recharger la page à chaque clic ;
- **rester lisible** quand le code grossit et que l'équipe change ;
- être **testée** et **déployée** de façon fiable et répétable.

C'est exactement le programme de cette partie. Chaque sujet répond à un besoin concret que tu
rencontreras dès que ton application sort du cadre du tutoriel.

> **À retenir** — Le niveau avancé n'est pas « connaître plus de fonctions ». C'est **faire les bons
> choix** : déporter ce qui est lent, séparer ce qui doit l'être, mesurer avant d'optimiser, et
> automatiser ce qui est répétitif.

## Récupérer le point de départ : le blog de la partie 1

Toute cette formation part du blog construit en partie 1. Deux cas de figure.

**Tu as fait la partie 1.** Reprends ton propre projet. Assure-toi qu'il est à jour et qu'il
fonctionne :

```bash
# Dans le dossier de ton blog
$ git status                          # ton code est-il propre et commité ?
$ composer install                    # dépendances à jour
$ php bin/console doctrine:migrations:migrate   # base à jour
$ symfony server:start                # l'application démarre-t-elle ?
```

**Tu n'as pas fait la partie 1** (ou tu veux repartir d'une base propre). Récupère le code du blog
terminé :

> **Code de départ** — Clone le dépôt du blog ici : **{{LIEN-CODE-BLOG-PARTIE-1}}** *(lien à venir)*.

```bash
# Récupération du blog terminé (remplace l'URL par le lien fourni)
$ git clone {{LIEN-CODE-BLOG-PARTIE-1}} blog
$ cd blog
$ composer install
$ php bin/console doctrine:migrations:migrate --no-interaction
$ php bin/console doctrine:fixtures:load --no-interaction   # données de démo, si présentes
$ symfony server:start
```

Quelle que soit ton entrée, tu dois aboutir au **même état** : un blog avec page d'accueil, pages
d'articles, commentaires, espace d'administration, authentification par rôles. C'est notre base de
travail commune.

> **Attention** — Travaille toujours dans une **branche Git** dédiée (`git switch -c partie-2`). Tu
> vas modifier en profondeur l'application : pouvoir revenir en arrière est indispensable.

## Vérifier ton environnement

Cette partie 2 réutilise l'environnement de la partie 1, avec une nouveauté (Docker) introduite au
chapitre 12. Vérifie tes versions :

```bash
$ php --version          # PHP 8.4 ou supérieur attendu
$ composer --version
$ symfony version        # la Symfony CLI
```

Au fil des chapitres, on installera des paquets supplémentaires avec Composer (Messenger, API
Platform, Symfony UX…). À chaque fois, on expliquera ce que le paquet apporte avant de l'ajouter :
pas d'installation « magique ».

> **Astuce** — La commande `symfony check:requirements` vérifie que ta machine remplit tous les
> prérequis de Symfony (extensions PHP, versions). Lance-la en cas de doute.

## Tes outils de diagnostic deviennent essentiels

En partie 1, le **profiler** (la barre de debug en bas de chaque page en développement) était un
confort. À partir de maintenant, c'est un **instrument de mesure**. Tu vas t'en servir pour :

- compter le **nombre de requêtes SQL** d'une page (chapitre 3, le problème N+1) ;
- inspecter les **messages** envoyés à Messenger (chapitres 4-5) ;
- vérifier la **sérialisation** d'une réponse d'API (chapitre 6) ;
- suivre les **transitions de workflow** (chapitre 8).

Les commandes `debug:*` sont l'autre moitié de ta boîte à outils. Elles répondent à « qu'est-ce que
Symfony connaît exactement à cet instant ? » :

```bash
$ php bin/console debug:router          # toutes les routes
$ php bin/console debug:container       # tous les services
$ php bin/console debug:autowiring      # ce que l'autowiring sait injecter
$ php bin/console debug:config framework # la config résolue d'un bundle
```

Tu les connais déjà ; on s'en servira beaucoup plus, et on en ajoutera de nouvelles propres à chaque
composant (`debug:messenger`, `messenger:stats`, etc.).

> **À retenir** — Avant de modifier ou d'optimiser quoi que ce soit, **observe**. Le profiler et les
> commandes `debug:*` te montrent la réalité de ton application, pas ce que tu imagines.

## Le projet fil rouge : du blog à la plateforme

Voici où on emmène le blog, chapitre par chapitre. Garde cette vue d'ensemble en tête : chaque
notion sert le projet.

| Chapitre | Ce que le blog gagne |
| --- | --- |
| 2 — Architecture | Du code réorganisé (DTO, services métier) qui reste lisible |
| 3 — Doctrine perf | Des pages rapides, sans requêtes SQL en trop |
| 4-5 — Messenger | E-mails et tâches lentes traités en arrière-plan, tâches planifiées |
| 6 — API | Une API des articles, documentée |
| 7 — Sécurité | Cette API protégée par jetons JWT, des droits fins |
| 8 — Workflow | Un cycle éditorial : brouillon → relecture → publié → archivé |
| 9-10 — Symfony UX | Commentaires et « j'aime » sans rechargement, temps réel |
| 11 — Tests/CI | Une suite de tests et une intégration continue |
| 12 — Déploiement | L'application conteneurisée et mise en cache |

> **Astuce** — Tu peux suivre la formation sans tout coder, mais le bénéfice vient de la **pratique**.
> Code au moins le fil rouge : c'est lui qui transforme la lecture en compétence.

## Résumé

- Le niveau avancé, c'est **faire les bons choix** (déporter, séparer, mesurer, automatiser), pas
  empiler des fonctionnalités.
- On part du **blog de la partie 1** ; si tu ne l'as pas, récupère le code via le lien fourni et mets
  la base à jour.
- Travaille dans une **branche Git** dédiée.
- L'environnement de la partie 1 suffit (**PHP 8.4+**, Composer, Symfony CLI) ; Docker arrive au
  chapitre 12.
- Le **profiler** et les commandes **`debug:*`** deviennent tes instruments de mesure quotidiens :
  observe avant d'agir.

## Exercices

### Exercice 1 — Remettre le blog en route

Récupère ton blog (ou le code de départ), démarre-le, et ouvre la page d'accueil. Vérifie que tu peux
te connecter à l'espace d'administration. Note la version exacte de Symfony et de PHP utilisées.

<details>
<summary>Voir le corrigé</summary>

La démarche : on s'assure que la base de travail est saine avant de la modifier.

```bash
$ composer install
$ php bin/console doctrine:migrations:migrate --no-interaction
$ symfony server:start
# Ouvre l'URL affichée (souvent https://127.0.0.1:8000)
```

Pour les versions :

```bash
$ php --version
$ php bin/console about    # affiche la version de Symfony, l'environnement, les chemins
```

La commande `about` est le réflexe pour connaître d'un coup d'œil l'état du projet.

</details>

### Exercice 2 — Explorer avec le profiler

Sur la page d'un article, ouvre la barre de debug et trouve : le nombre de requêtes SQL exécutées, le
temps de rendu, et la route appelée. Note le nombre de requêtes SQL : on le surveillera au chapitre 3.

<details>
<summary>Voir le corrigé</summary>

La démarche : la barre de debug (en bas de page en `dev`) résume chaque requête HTTP.

- Le **temps** total s'affiche à gauche.
- L'icône **base de données** indique le nombre de requêtes ; clique dessus pour voir le détail de
  chaque requête SQL et sa durée.
- L'icône **route** donne le nom de la route et le contrôleur appelé.

Si la page d'un article déclenche déjà plusieurs requêtes pour charger l'auteur, la catégorie et les
commentaires, garde ce chiffre en tête : c'est précisément ce qu'on optimisera.

</details>

## Quiz

**1.** Que désigne « le niveau avancé » dans cette formation ?
- A. Connaître davantage de fonctions de Symfony
- B. Faire les bons choix : déporter, séparer, mesurer, automatiser
- C. Réécrire l'application sans framework

**2.** Avant de modifier ou d'optimiser une page, quel est le bon réflexe ?
- A. Optimiser immédiatement les requêtes
- B. Observer avec le profiler et les commandes `debug:*`
- C. Réinstaller les dépendances

**3.** Pourquoi travailler dans une branche Git dédiée pour la partie 2 ?
- A. Pour aller plus vite
- B. Pour pouvoir revenir en arrière, car on modifie l'application en profondeur
- C. C'est obligatoire pour lancer Symfony

<details>
<summary>Voir les réponses</summary>

1. **B** — L'avancé, c'est le jugement, pas le volume de fonctions connues.
2. **B** — On observe la réalité de l'application avant d'agir.
3. **B** — Une branche dédiée protège ton travail face à des changements profonds.

</details>

## Projet fil rouge

1. Récupère le blog (le tien ou le code de départ) et démarre-le.
2. Crée une **branche** `partie-2` : `git switch -c partie-2`.
3. Ouvre le profiler sur la page d'accueil et sur la page d'un article ; **note le nombre de requêtes
   SQL** de chaque page dans un fichier `NOTES.md` à la racine du projet.

Tu as une base saine et observée : on est prêts à la faire grandir. Au prochain chapitre, on
réorganise le code pour qu'il reste lisible à mesure qu'on ajoute des fonctionnalités.

---

[Sommaire](README.md) · [Chapitre suivant →](02-architecture-maintenable.md)
