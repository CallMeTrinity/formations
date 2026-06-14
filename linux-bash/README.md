# Linux & Bash

Cette formation t'apprend à utiliser un ordinateur **en ligne de commande** sur un système de la
famille Unix (Linux et macOS), puis à **automatiser** ton travail avec des scripts *Bash* (le
programme qui interprète les commandes que tu tapes).

La ligne de commande fait peur au début : un écran noir, un curseur qui clignote, aucun bouton. Mais
c'est l'outil le plus puissant et le plus durable de l'informatique. Une fois à l'aise, tu fais en
trois mots ce qui prend dix clics à la souris, tu répètes une tâche sur mille fichiers d'un coup, et
tu travailles sur des serveurs qui n'ont aucune interface graphique. C'est un savoir-faire
indispensable pour tout développeur, administrateur système ou data scientist — et il ne se démode
pas : les commandes que tu apprends ici fonctionnent à l'identique depuis des décennies.

## Prérequis

Aucune connaissance préalable n'est requise. Il suffit de savoir utiliser un ordinateur au quotidien
(ouvrir une application, créer un fichier, naviguer dans des dossiers à la souris). Tu n'as **pas**
besoin de savoir programmer.

Côté matériel : un ordinateur sous Linux, macOS ou Windows. Le [chapitre 1](01-introduction.md)
explique comment obtenir un terminal sur chacun de ces systèmes.

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire : plus à l'aise au terminal que la
moitié des gens qui s'en servent. Concrètement, tu sauras :

- te déplacer dans le système de fichiers et manipuler fichiers et dossiers **sans souris** ;
- afficher, chercher et filtrer du texte (logs, fichiers de config, données) avec `grep`, `find`,
  `sort`, et les outils classiques ;
- enchaîner des commandes avec les **redirections** et les **tuyaux** (`pipes`) pour construire des
  traitements puissants en une ligne ;
- comprendre et modifier les **permissions** des fichiers, et utiliser `sudo` à bon escient ;
- personnaliser ton environnement (variables, `PATH`, alias, `.bashrc`) ;
- écrire des **scripts Bash** avec variables, conditions, boucles et fonctions pour automatiser tes
  tâches répétitives ;
- gérer les **processus** (lancer, surveiller, arrêter) et **planifier** l'exécution automatique de
  tes scripts avec `cron` ;
- lire un message d'erreur, formuler ton problème et trouver la solution dans la documentation.

## Plan de la formation

1. [Introduction : le terminal, le shell, Linux](01-introduction.md)
2. [Se repérer dans le système de fichiers](02-systeme-de-fichiers.md)
3. [Manipuler fichiers et dossiers](03-manipuler-fichiers.md)
4. [Lire et filtrer du texte](04-lire-et-filtrer.md)
5. [Redirections et tuyaux](05-redirections-et-tuyaux.md)
6. [Permissions, utilisateurs et `sudo`](06-permissions.md)
7. [Chercher et transformer du texte](07-chercher-et-transformer.md)
8. [Variables, environnement et personnalisation](08-environnement.md)
9. [Écrire son premier script Bash](09-premier-script.md)
10. [Logique dans les scripts](10-logique-scripts.md)
11. [Processus et automatisation](11-processus-et-cron.md)
12. [Conclusion et pour aller plus loin](12-conclusion.md)

## Projet fil rouge

Tout au long de la formation, tu construis pas à pas un véritable **outil de sauvegarde** :
`sauvegarde.sh`. Au début, tu organises et copies un dossier à la main, commande par commande. Puis,
chapitre après chapitre, tu transformes ces gestes en script :

- une **archive horodatée** d'un dossier de ton choix ;
- des **options** passées en arguments (quoi sauvegarder, où) ;
- un **journal** (`log`) de chaque opération ;
- des **vérifications** (le dossier existe-t-il ? la sauvegarde a-t-elle réussi ?) ;
- enfin, une **exécution automatique planifiée** avec `cron`, sans que tu aies à y penser.

À la fin, tu disposes d'un outil réel, réutilisable, que tu comprends de bout en bout — et surtout
de la méthode pour en écrire d'autres.

---

Commencer par le [chapitre 1 →](01-introduction.md).
