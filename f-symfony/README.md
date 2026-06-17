# Symfony 8

Cette formation t'apprend à construire des **applications web** avec **Symfony**, le framework PHP le
plus utilisé en entreprise. Un *framework* (« cadre de travail ») est un ensemble d'outils et de
conventions qui te donnent une structure prête à l'emploi : tu n'écris plus la « plomberie » (router
les URL, parler à la base de données, gérer les formulaires, la sécurité), tu te concentres sur ce
qui fait la valeur de ton application.

Symfony est un standard du métier : on le retrouve dans d'innombrables sites et API, il sert de socle
à d'autres projets majeurs (Laravel, Drupal, Magento réutilisent ses composants), et savoir s'en
servir est une compétence très recherchée. Cette formation cible **Symfony 8**, sorti en novembre
2025, qui demande **PHP 8.4 ou supérieur**.

## Prérequis

Symfony est un framework PHP : il ne s'adresse pas à un débutant complet en programmation. Pour
suivre cette formation confortablement, tu dois être à l'aise avec :

- les **bases du langage PHP** (variables, fonctions, tableaux, structures de contrôle) et la
  **programmation orientée objet** (classes, objets, héritage, interfaces) ;
- la **ligne de commande** : ouvrir un terminal et lancer des commandes (si ce n'est pas le cas, la
  formation [Linux & Bash](../linux-bash/README.md) de ce dépôt t'y prépare) ;
- des notions de **HTML** et du fonctionnement du **web** (une requête, une réponse, une URL).

Tu n'as **pas** besoin de connaître un autre framework, ni d'avoir déjà touché à une base de données :
on part de zéro sur ces points.

Côté machine : un ordinateur sous Linux, macOS ou Windows, sur lequel on installera PHP 8.4, Composer
et l'outil en ligne de commande de Symfony au [chapitre 1](01-introduction.md).

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire : capable de développer seul une
application web Symfony et plus à l'aise avec le framework que la moitié des gens qui s'en servent.
Concrètement, tu sauras :

- **installer** Symfony et créer un nouveau projet propre, prêt à développer ;
- comprendre l'**architecture** d'un projet Symfony et le cycle requête → réponse ;
- définir des **routes** et écrire des **contrôleurs** qui répondent aux requêtes ;
- construire des pages avec le moteur de templates **Twig** (layout, héritage, filtres) ;
- modéliser et manipuler une **base de données** avec **Doctrine** (entités, migrations, relations,
  requêtes) ;
- créer et traiter des **formulaires** avec validation, et générer un CRUD complet ;
- comprendre l'**injection de dépendances** et écrire tes propres **services** ;
- mettre en place l'**authentification** et la **gestion des droits** (rôles, *voters*) ;
- écrire des **tests** automatisés pour fiabiliser ton application ;
- aller plus loin : **commandes** console, **événements**, **mails**, mini-**API** ;
- **déployer** ton application et connaître les bonnes pratiques et l'écosystème.

## Plan de la formation

1. [Introduction : qu'est-ce que Symfony, installation, premier projet](01-introduction.md)
2. [Anatomie d'un projet Symfony](02-anatomie-projet.md)
3. [Routes et contrôleurs](03-routes-et-controleurs.md)
4. [Les vues avec Twig](04-twig.md)
5. [La base de données avec Doctrine](05-doctrine-base-de-donnees.md)
6. [Manipuler les données et les relations](06-doctrine-relations.md)
7. [Les formulaires](07-formulaires.md)
8. [Services et injection de dépendances](08-services-injection.md)
9. [La sécurité : authentification et autorisations](09-securite.md)
10. [Les tests automatisés](10-tests.md)
11. [Aller plus loin : commandes, événements, mails, API](11-aller-plus-loin.md)
12. [Conclusion : déploiement, bonnes pratiques, écosystème](12-conclusion.md)

## Projet fil rouge

Tout au long de la formation, tu construis pas à pas un véritable **blog avec espace
d'administration**. Au fil des chapitres, l'application grandit :

- une **page d'accueil** et des **pages d'articles** servies par des routes et des contrôleurs ;
- un **affichage soigné** avec un layout Twig commun à toutes les pages ;
- une **base de données** où sont stockés les **articles**, leurs **catégories** et les
  **commentaires** des lecteurs, reliés par des relations ;
- des **formulaires** validés pour créer et éditer un article, et poster un commentaire ;
- un **service** maison (par exemple le calcul du temps de lecture d'un article) ;
- une **authentification** complète : inscription, connexion, **rôles** (visiteur, auteur, admin),
  un **espace d'administration** protégé et un *voter* qui n'autorise un auteur qu'à modifier ses
  propres articles ;
- des **tests** qui vérifient que les pages clés fonctionnent ;
- des finitions pro : une **commande** console, l'envoi d'un **mail**, un point d'**API**.

À la fin, tu disposes d'une application réelle, complète et déployable, que tu comprends de bout en
bout — et surtout de la méthode pour en construire d'autres.

---

Commencer par le [chapitre 1 →](01-introduction.md).
