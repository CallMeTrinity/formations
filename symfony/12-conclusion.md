# Conclusion : déploiement, bonnes pratiques, écosystème

[← Chapitre précédent](11-aller-plus-loin.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- préparer ton application pour la **production** ;
- connaître les grandes **bonnes pratiques** d'un projet Symfony ;
- te repérer dans l'**écosystème** (ressources, bundles, projets liés) ;
- savoir **où aller** pour passer au niveau avancé.

## Préparer la production

En développement, tu privilégies le confort (erreurs détaillées, cache rafraîchi). En **production**,
tu vises la **vitesse** et la **discrétion**. Le passage en prod tient en quelques étapes.

1. **Passer en environnement `prod`.** Dans la configuration du serveur, règle `APP_ENV=prod` (et
   `APP_DEBUG=0`). Les erreurs détaillées et la barre de debug disparaissent.

2. **Installer les dépendances optimisées**, sans les paquets de dev :

   ```bash
   $ composer install --no-dev --optimize-autoloader
   ```

   `--no-dev` exclut les outils de développement (tests, makers) ; `--optimize-autoloader` accélère
   le chargement des classes.

3. **Chauffer le cache** pour l'environnement de prod :

   ```bash
   $ php bin/console cache:clear --env=prod
   ```

4. **Appliquer les migrations** sur la base de production :

   ```bash
   $ php bin/console doctrine:migrations:migrate --no-interaction
   ```

5. **Servir uniquement le dossier `public/`.** Le serveur web (Nginx, Apache) doit pointer sa racine
   sur `public/`, jamais sur la racine du projet : c'est ce qui protège ton code et tes secrets
   (rappel du chapitre 2).

> **Attention** — Les **secrets** (mots de passe de base, clés d'API) ne doivent jamais être dans Git.
> En production, utilise les **variables d'environnement** du serveur ou le **coffre de secrets** de
> Symfony (`secrets:set`). Le fichier `.env` versionné ne contient que des valeurs par défaut, non
> sensibles.

### Où déployer

Plusieurs voies, du plus simple au plus contrôlé :

- une **plateforme gérée** (comme Platform.sh, partenaire historique de Symfony, ou des hébergeurs
  PaaS) : tu pousses ton code, la plateforme construit et déploie ;
- un **conteneur Docker** que tu déploies où tu veux ;
- un **serveur classique** (VPS) que tu configures toi-même (PHP, serveur web, base de données).

La Symfony CLI fournit aussi des commandes d'aide au déploiement. Quelle que soit la cible, les cinq
étapes ci-dessus restent la base.

## Les bonnes pratiques à retenir

Au fil de la formation, tu as croisé les règles qui font un projet Symfony sain. Les voici réunies :

- **Suis les conventions.** L'arborescence standard (`src/`, `templates/`, `config/`) et le nommage
  attendu rendent ton code lisible par n'importe quel développeur Symfony.
- **Laisse les makers travailler**, puis lis et adapte le code généré. Tu vas plus vite et tu
  apprends en lisant.
- **Type tout** (arguments, propriétés, retours) : c'est ce qui rend l'autowiring et les outils
  efficaces.
- **Manipule les routes par leur nom**, jamais par l'URL recopiée (`path()`, `generateUrl()`).
- **Sépare les responsabilités** : la donnée dans les **entités**, le travail dans les **services**,
  l'orchestration légère dans les **contrôleurs**, l'affichage dans **Twig**. Garde les contrôleurs
  minces.
- **Valide les données** (`#[Assert\...]`) et **ne fais jamais confiance** aux entrées utilisateur ;
  laisse Twig échapper l'affichage.
- **Ne stocke jamais un mot de passe en clair**, ni de secret dans Git.
- **Écris des tests** pour les parties importantes et lance-les souvent.
- **Fais évoluer la base par migrations**, jamais à la main.

> **À retenir** — Un bon projet Symfony n'est pas le plus malin, c'est le plus **prévisible** : il
> suit les conventions, sépare clairement les rôles, et se laisse comprendre et tester sans surprise.

## L'écosystème Symfony

Symfony n'est pas qu'un framework : c'est un écosystème riche sur lequel t'appuyer.

- **Les composants Symfony** sont réutilisables seuls (Routing, Console, Validator, Mailer…) :
  d'autres projets PHP majeurs (Laravel, Drupal) en dépendent.
- **Doctrine** (base de données) et **Twig** (templates) sont des projets à part entière, très
  documentés.
- **API Platform** construit des API complètes sur Symfony, presque sans code.
- **Symfony UX** apporte des outils modernes côté navigateur (interactivité, composants) en restant
  côté PHP.
- **Le MakerBundle** que tu as tant utilisé continue de te faire gagner du temps.

Pour apprendre et te débloquer :

- la **documentation officielle** (`symfony.com/doc`) : précise, à jour, avec un tutoriel guidé « The
  Fast Track » ;
- **SymfonyCasts** : des cours vidéo de référence, du débutant à l'avancé ;
- le **profiler** : ton meilleur outil de diagnostic en développement (rappelle-toi le chapitre 2) ;
- la **communauté** (forums, Slack, conférences SymfonyCon, *meetups*) : Symfony est réputé pour son
  accueil.

## Pour aller vers le niveau avancé

Tu maîtrises les fondations. Pour continuer à progresser, explore dans cet ordre :

1. **Messenger** : traitements asynchrones et files de messages (envois d'e-mails, tâches longues).
2. **Les formulaires avancés** : types personnalisés, *data transformers*, et les **formulaires
   multi-étapes** de Symfony 8.
3. **La sécurité approfondie** : authentificateurs personnalisés, gestion fine des rôles, jetons
   d'API (JWT).
4. **Le composant Workflow** : modéliser des cycles de vie (un article : brouillon → relu → publié).
5. **La performance** : cache HTTP, cache applicatif, optimisation des requêtes Doctrine.
6. **Symfony UX** et les interfaces modernes (Stimulus, Turbo, Live Components).

Le bon réflexe : pars d'un **besoin réel** de ton projet, lis la doc du composant concerné, et teste.
C'est exactement la méthode que tu as appliquée tout au long de cette formation.

## Récapitulatif de la formation

En douze chapitres, tu es parti de zéro et tu sais maintenant :

- **installer** Symfony et comprendre son **architecture** (MVC, cycle requête → réponse) ;
- créer des **routes** et des **contrôleurs**, et des vues avec **Twig** ;
- modéliser et manipuler une **base de données** avec **Doctrine** (entités, relations, migrations) ;
- construire des **formulaires** validés et un **CRUD** ;
- écrire des **services** et comprendre l'**injection de dépendances** ;
- mettre en place l'**authentification** et les **autorisations** (rôles, voters) ;
- **tester** ton application ;
- ajouter **commandes**, **événements**, **mails** et **API** ;
- **déployer** et appliquer les **bonnes pratiques**.

C'est très exactement le bagage du niveau **intermédiaire** : tu es désormais autonome pour construire
une application Symfony et apprendre le reste par toi-même.

## Résumé

- La **production** demande : `APP_ENV=prod`, `composer install --no-dev --optimize-autoloader`,
  `cache:clear`, migrations appliquées, et le serveur web pointé sur **`public/`** uniquement.
- Les **secrets** restent hors de Git (variables d'environnement ou coffre de secrets).
- Les **bonnes pratiques** tiennent en un mot : suivre les conventions et **séparer les
  responsabilités**.
- L'**écosystème** (composants, Doctrine, Twig, API Platform, Symfony UX, doc officielle,
  SymfonyCasts) t'accompagne pour la suite.
- Pour le niveau avancé : **Messenger**, sécurité approfondie, **Workflow**, performance, Symfony UX.

## Exercices

### Exercice 1 — Checklist de mise en production

Sans regarder le chapitre, liste de mémoire les étapes pour préparer ton blog à la production. Compare
ensuite avec la section « Préparer la production ».

<details>
<summary>Voir le corrigé</summary>

Les étapes attendues :

1. Passer en `APP_ENV=prod` (et `APP_DEBUG=0`).
2. `composer install --no-dev --optimize-autoloader`.
3. `php bin/console cache:clear --env=prod`.
4. `php bin/console doctrine:migrations:migrate --no-interaction`.
5. Configurer le serveur web pour servir **`public/`** uniquement.
6. Gérer les **secrets** hors de Git (variables d'environnement / coffre de secrets).

Si tu en as oublié, c'est normal : garde cette checklist sous la main pour tes premiers déploiements.

</details>

### Exercice 2 — Plan d'évolution de ton blog

Choisis **une** fonctionnalité avancée (parmi : recherche d'articles, pagination, e-mails asynchrones
via Messenger, espace auteur avec workflow brouillon/publié) et écris en quelques lignes par quels
composants tu commencerais et quelle page de doc tu lirais.

<details>
<summary>Voir le corrigé</summary>

Il n'y a pas de réponse unique : l'objectif est d'appliquer la **méthode**. Exemple pour la
pagination :

- **Besoin** : afficher les articles par pages de 10.
- **Piste** : une requête limitée dans le `ArticleRepository` (`setMaxResults` / `setFirstResult`),
  ou un paquet de pagination (KnpPaginatorBundle).
- **Doc** : la section Doctrine sur les requêtes, et le README du paquet choisi.
- **Test** : un test fonctionnel qui vérifie qu'une deuxième page existe et affiche d'autres articles.

C'est exactement ce cheminement « besoin → composant → doc → test » qui te fera progresser.

</details>

## Quiz

**1.** Quelle commande installe les dépendances optimisées pour la production ?
- A. `composer install --dev`
- B. `composer install --no-dev --optimize-autoloader`
- C. `composer update`

**2.** Sur quel dossier le serveur web doit-il pointer en production ?
- A. La racine du projet
- B. `src/`
- C. `public/`

**3.** Où doivent vivre les secrets (mots de passe, clés d'API) ?
- A. Dans le `.env` versionné par Git
- B. Hors de Git : variables d'environnement ou coffre de secrets
- C. Dans le code des contrôleurs

**4.** Quel composant gère les traitements asynchrones (e-mails différés, tâches longues) ?
- A. Twig
- B. Messenger
- C. Doctrine

<details>
<summary>Voir les réponses</summary>

1. **B** — `--no-dev` retire les outils de dev, `--optimize-autoloader` accélère le chargement.
2. **C** — Seul `public/` est exposé ; le reste du projet reste protégé.
3. **B** — Les secrets restent hors de Git, dans l'environnement ou le coffre de secrets.
4. **B** — Messenger traite les messages en arrière-plan.

</details>

## Projet fil rouge

C'est l'aboutissement : ton blog est complet, il est temps de le finaliser.

1. Relis ton code à la lumière des **bonnes pratiques** : contrôleurs minces, logique dans les
   services, routes appelées par leur nom, données validées.
2. Vérifie que toute la suite de **tests** passe (`php bin/phpunit`).
3. Applique la **checklist de production** sur une copie : `prod`, dépendances optimisées, cache,
   migrations, et secrets hors de Git.
4. Choisis une **évolution** (pagination, recherche, workflow de publication…) et lance-toi : tu as
   désormais la méthode pour l'ajouter seul.

Félicitations : tu as construit une application Symfony réelle, de la page d'accueil au déploiement.
Le plus important n'est pas le blog lui-même, mais la **démarche** que tu sais maintenant reproduire
sur n'importe quel projet.

---

[← Chapitre précédent](11-aller-plus-loin.md) · [Sommaire](README.md)
