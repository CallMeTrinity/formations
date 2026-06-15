# Symfony avancé

Cette formation est la **suite directe** de la formation [Symfony 8](../symfony/README.md). La partie 1
t'a appris à construire une application web complète : routes, Twig, Doctrine, formulaires, services,
sécurité de base, tests et un tour d'horizon des finitions. Ici, on monte d'un cran.

L'objectif n'est plus de « savoir faire marcher » une application, mais de la rendre **robuste,
performante, maintenable et moderne** : traitements en arrière-plan, API exposée et sécurisée,
interface dynamique sans framework JavaScript lourd, architecture propre, qualité de code et
déploiement industrialisé. C'est le bagage qui sépare un développeur qui « connaît Symfony » d'un
développeur sur qui une équipe peut s'appuyer en production.

On reprend exactement là où la partie 1 s'arrête : le **blog avec espace d'administration**, qu'on
fait évoluer en une véritable **plateforme de publication multi-auteurs**.

> **Tu n'as pas suivi la partie 1 ?** Tu peux récupérer le code du blog terminé ici :
> **{{LIEN-CODE-BLOG-PARTIE-1}}** *(lien à venir)*. Clone-le, installe les dépendances
> (`composer install`), crée la base (`php bin/console doctrine:migrations:migrate`) et tu démarres
> cette partie 2 sur les mêmes bases que tout le monde.

## Prérequis

Cette formation suppose que tu as le niveau de sortie de la partie 1. Concrètement, tu dois être à
l'aise avec :

- créer des **routes** et des **contrôleurs**, rendre des vues **Twig** ;
- modéliser une base avec **Doctrine** (entités, relations, migrations) et écrire des requêtes
  simples ;
- construire des **formulaires** validés ;
- l'**injection de dépendances** et l'écriture de **services** ;
- les bases de la **sécurité** Symfony (authentification, rôles, un *voter* simple) ;
- lancer des **tests** fonctionnels.

Si l'un de ces points est flou, reprends le chapitre correspondant de la
[partie 1](../symfony/README.md) avant de continuer. Côté machine, tu gardes l'environnement de la
partie 1 : **PHP 8.4+**, **Composer**, la **Symfony CLI**, et — nouveauté de cette partie — **Docker**
au chapitre 12 (on t'accompagne pour l'installer).

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras à l'aise sur les sujets qui font la différence en entreprise.
Concrètement, tu sauras :

- **structurer** une application maintenable : DTO, *value objects*, services métier, interfaces ;
- écrire des requêtes **Doctrine performantes** et traquer le problème **N+1** ;
- déporter des traitements lents en arrière-plan avec **Messenger** (transports, *workers*, *retries*)
  et planifier des tâches avec le **Scheduler** ;
- exposer une **API** propre avec le **Serializer** et **API Platform** ;
- sécuriser cette API : **authenticators** personnalisés, jetons **JWT**, *voters* avancés,
  *rate limiting* ;
- modéliser un cycle de vie métier avec le composant **Workflow** ;
- rendre tes interfaces **dynamiques** sans framework JS lourd grâce à **Symfony UX** (Stimulus,
  Turbo, Twig & Live Components) et pousser des mises à jour temps réel avec **Mercure** ;
- écrire des **tests avancés** et mettre en place une **intégration continue** (PHPStan, CS Fixer,
  GitHub Actions) ;
- **industrialiser et déployer** : Docker, secrets, **cache HTTP et applicatif**, monitoring ;
- savoir **où aller ensuite** : architecture hexagonale, DDD, découpage en services.

## Plan de la formation

1. [Introduction : passer au niveau avancé](01-introduction.md)
2. [Garder une application maintenable : DTO, value objects, services métier](02-architecture-maintenable.md)
3. [Doctrine avancé et performance](03-doctrine-avance-performance.md)
4. [Le bus de messages : Messenger](04-messenger.md)
5. [Messenger en production : transports, workers et Scheduler](05-messenger-production-scheduler.md)
6. [Sérialisation et API moderne avec API Platform](06-serialisation-api-platform.md)
7. [Sécurité avancée : authenticators, JWT, voters](07-securite-avancee.md)
8. [Le composant Workflow](08-workflow.md)
9. [Symfony UX : Stimulus et Turbo](09-symfony-ux-stimulus-turbo.md)
10. [Twig Components, Live Components et temps réel avec Mercure](10-twig-live-components-mercure.md)
11. [Tests avancés et qualité de code](11-tests-avances-qualite.md)
12. [Industrialisation et déploiement avancé](12-industrialisation-deploiement.md)
13. [Conclusion : architecture et au-delà](13-conclusion.md)

## Projet fil rouge

On fait grandir le **blog de la partie 1** jusqu'à une **plateforme de publication multi-auteurs**.
Au fil des chapitres :

- on **réorganise** le code autour de DTO et de services métier pour qu'il reste lisible ;
- on **optimise** les requêtes (liste d'articles, page d'un article) et on supprime les N+1 ;
- on déporte en **arrière-plan** l'envoi des e-mails et les tâches lentes (statistiques, nettoyage)
  avec Messenger et le Scheduler ;
- on expose une **API** des articles et commentaires, **sécurisée par JWT**, prête pour une appli
  mobile ou un front séparé ;
- on modélise le **workflow éditorial** d'un article (brouillon → en relecture → publié → archivé) ;
- on rend l'interface **dynamique** : commentaires postés sans rechargement, compteur de « j'aime »
  en direct, mises à jour temps réel via Mercure ;
- on couvre le tout de **tests** et d'une **CI** qui vérifie qualité et non-régression ;
- on **conteneurise** l'application avec Docker, on met en place le **cache** et on prépare un
  déploiement propre.

À la fin, tu disposes d'une application proche de ce qu'on trouve en production réelle — et surtout
de la méthode pour porter n'importe quel projet Symfony à ce niveau.

---

Commencer par le [chapitre 1 →](01-introduction.md).
