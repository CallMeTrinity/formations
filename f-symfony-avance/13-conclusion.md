# Conclusion : architecture et au-delà

[← Chapitre précédent](12-industrialisation-deploiement.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- prendre du recul sur l'**architecture** d'une application qui grandit ;
- ce que recouvrent **DDD** et l'**architecture hexagonale**, et quand t'en soucier ;
- quand envisager de **découper** une application (et pourquoi le plus souvent l'éviter) ;
- où aller pour continuer à progresser vers l'**expertise** ;
- mesurer le chemin parcouru depuis la partie 1.

## Reprendre de la hauteur

Tu as enchaîné beaucoup de composants : Messenger, API Platform, sécurité fine, Workflow, Symfony UX,
tests, Docker, cache. Chacun résout un problème précis. Mais le vrai marqueur du niveau avancé n'est
pas de tous les connaître : c'est de savoir **lequel sortir, quand, et à quel coût**.

Le fil conducteur de toute cette partie tient en une phrase : **séparer ce qui doit l'être**.
Séparer la logique métier de la plomberie (chapitre 2), le traitement immédiat du différé (Messenger),
le contrat d'API du modèle interne (DTO), l'autorisation du reste (voters), les états et leurs règles
(Workflow), le rendu de l'interactivité (Symfony UX). L'architecture avancée, c'est essentiellement
**poser les bonnes frontières**.

> **À retenir** — La compétence avancée n'est pas la quantité d'outils connus, mais le **jugement** :
> choisir la bonne frontière, le bon outil, et savoir quand **ne pas** ajouter de complexité.

## DDD et architecture hexagonale, sans le jargon

Deux termes reviennent dès qu'on parle d'architecture « sérieuse ». Démystifions-les.

Le **DDD** (*Domain-Driven Design*, « conception pilotée par le domaine ») est une façon de concevoir
le logiciel en partant du **métier** plutôt que de la technique. Tu modélises avec les mots du domaine
(un « article », une « publication », un « relecteur »), tu te mets d'accord sur un **vocabulaire
commun** avec les experts métier, et tu organises le code autour de ces concepts. Tu en as déjà fait
sans le nommer : tes **value objects** (chapitre 2) et ton **Workflow** (chapitre 8) sont des idées
issues du DDD.

L'**architecture hexagonale** (aussi dite *ports & adapters*) pousse plus loin l'idée de frontière :
on met la **logique métier au centre**, indépendante de toute technique (base, framework, API), et on
la relie au monde extérieur par des **adaptateurs**. L'avantage : tu peux changer la base de données
ou exposer ton métier via le web **ou** une API **ou** une commande, sans toucher au cœur. Tes
**interfaces** (chapitre 2, dépendre d'un contrat) en sont la brique de base.

> **Attention** — Ces approches ont un **coût** : plus de classes, plus d'indirection. Elles brillent
> sur des domaines **complexes** et des projets **durables**, en équipe. Sur un petit projet, elles
> ajoutent de la cérémonie sans bénéfice. **N'adopte pas l'hexagonal par mode** : adopte-le quand la
> complexité du domaine le justifie.

## Faut-il découper l'application ?

À un moment, on entend parler de **microservices** : découper l'application en plusieurs petits
services indépendants, communiquant par le réseau (souvent via des messages — tu as les bases avec
Messenger). C'est séduisant, mais c'est un **choix lourd**.

Une application Symfony bien structurée — un **monolithe modulaire**, avec des frontières internes
claires comme celles de cette formation — couvre l'immense majorité des besoins, avec **bien moins**
de complexité opérationnelle qu'un essaim de services. Les microservices répondent à des problèmes
**d'organisation et d'échelle** précis (grandes équipes indépendantes, parties au cycle de vie très
différent), pas à un besoin de « faire moderne ».

> **À retenir** — Commence **monolithe, mais propre** (frontières internes nettes). Tu ne découpes que
> si un problème réel et mesuré l'exige. Un monolithe bien rangé est plus facile à découper plus tard
> qu'un plat de spaghettis distribué à débrouiller.

## Pour continuer vers l'expertise

Tu as les fondations avancées. Pour aller plus loin, pars toujours d'un **besoin réel** de ton projet,
puis explore :

- **Performance approfondie** : profiling avec **Blackfire**, optimisation Doctrine fine (cache de
  second niveau, requêtes en lecture seule), tuning d'OPcache et du *preloading* PHP.
- **Messenger avancé** : *middlewares* personnalisés, sagas, gestion de la cohérence, transports
  AMQP/Kafka pour de gros volumes.
- **API Platform avancé** : state providers/processors, sous-ressources, versionnement d'API,
  GraphQL.
- **Sécurité approfondie** : OAuth2 / OpenID Connect, gestion fine des *access tokens*, audit.
- **Frontend** : Symfony UX en profondeur (composants complexes, Turbo Streams temps réel), ou
  intégration d'un front découplé consommant ton API.
- **Qualité** : montée en niveau de PHPStan (jusqu'à `max`), tests de mutation (Infection),
  architecture testée (Deptrac pour faire respecter les frontières).
- **Architecture** : DDD tactique et stratégique, CQRS, *event sourcing* — sur les domaines qui le
  justifient.

Les ressources de référence restent les mêmes qu'en partie 1, et elles vont loin : la **documentation
officielle** (`symfony.com/doc`), **SymfonyCasts** (cours vidéo du débutant à l'expert), le **blog
Symfony** et les conférences **SymfonyCon**. Lis aussi du **code open source** Symfony de qualité :
c'est l'un des meilleurs moyens de progresser.

> **Astuce** — La méthode que tu appliques depuis la partie 1 ne change pas : **besoin → composant →
> doc → essai → test**. Elle marche pour chaque sujet de cette liste. L'expertise, c'est cette boucle
> répétée mille fois sur des problèmes réels.

## Le chemin parcouru

Pars d'où tu étais à la fin de la partie 1 — capable de construire une application Symfony complète —
et regarde ce que tu sais faire maintenant :

- **structurer** une application maintenable (DTO, value objects, services, interfaces) ;
- écrire des requêtes **Doctrine performantes** et traquer le **N+1** ;
- déporter des traitements avec **Messenger** et planifier avec le **Scheduler** ;
- exposer une **API** propre avec **API Platform** ;
- la **sécuriser** (JWT, authenticators, voters, rate limiting) ;
- modéliser un cycle de vie avec **Workflow** ;
- rendre l'interface **dynamique et temps réel** avec **Symfony UX** et **Mercure** ;
- écrire des **tests avancés** et une **CI** ;
- **conteneuriser**, **mettre en cache** et **superviser** en production.

C'est très exactement le bagage qui te place **bien au-delà de la moyenne** des développeurs Symfony :
non seulement tu fais marcher une application, mais tu la rends robuste, rapide, sûre et durable. Tu
n'es plus quelqu'un qui « connaît Symfony » : tu es quelqu'un sur qui une équipe peut s'appuyer.

## Récapitulatif de la formation

En treize chapitres, tu es passé d'un niveau intermédiaire à un niveau avancé : tu poses des
**frontières** claires, tu choisis le **bon outil au bon moment**, tu **mesures** avant d'optimiser, et
tu **automatises** tests, qualité et déploiement. Surtout, tu as une **méthode** reproductible sur
n'importe quel projet et n'importe quel composant.

## Résumé

- L'architecture avancée, c'est **poser les bonnes frontières** ; la compétence clé est le **jugement**,
  pas le nombre d'outils.
- **DDD** (partir du métier) et **hexagonal** (métier au centre, adaptateurs autour) sont précieux sur
  les **domaines complexes** ; inutilement coûteux sur les petits projets.
- Préfère un **monolithe propre** aux microservices tant qu'un besoin réel ne justifie pas le découpage.
- Continue par **besoin réel → doc → essai → test** ; ressources : doc officielle, SymfonyCasts,
  code open source.
- Tu as le bagage **avancé** complet : structurer, optimiser, asynchrone, API, sécurité, workflow,
  front moderne, tests/CI, déploiement.

## Exercices

### Exercice 1 — Cartographier les frontières de ton blog

Sans regarder le code, dessine (sur papier ou en ASCII) les **frontières** de ton blog : où vit la
logique métier, où sont les services, l'API, l'asynchrone, l'interface. Identifie un endroit où une
frontière est encore floue.

<details>
<summary>Voir le corrigé</summary>

Il n'y a pas de réponse unique : l'objectif est la **prise de recul**. Un schéma sain fait apparaître :

- les **contrôleurs / API** en bordure (entrée web) ;
- les **services métier** au centre (la vraie logique) ;
- **Doctrine** comme adaptateur de persistance ;
- **Messenger** pour le différé ;
- **Symfony UX** côté affichage.

Un signe de frontière floue : une logique métier encore présente dans un contrôleur, ou un template qui
contient une décision métier. Note-le : c'est ta prochaine amélioration, à faire avec la méthode du
chapitre 2.

</details>

### Exercice 2 — Choisir (ou écarter) une évolution avancée

Choisis **une** piste de la section « Pour continuer vers l'expertise » qui répond à un besoin réel de
ton blog, et écris en quelques lignes : le besoin, le composant, la page de doc de départ, et comment
tu le testerais. Justifie aussi une piste que tu **écartes** pour l'instant, et pourquoi.

<details>
<summary>Voir le corrigé</summary>

L'objectif est d'appliquer le **jugement** avancé : adopter par besoin, écarter par sobriété. Exemple :

- **J'adopte** le profiling Blackfire : la page d'accueil est lente sous charge (besoin mesuré). Doc :
  la documentation Blackfire ; test : comparer le temps avant/après optimisation sur un scénario
  reproductible.
- **J'écarte** les microservices : mon blog est un mono-domaine tenu par une petite équipe ; découper
  ajouterait une énorme complexité réseau pour aucun gain. Je garde un monolithe propre.

C'est exactement le raisonnement coût/bénéfice attendu d'un développeur avancé.

</details>

## Quiz

**1.** Quel est le vrai marqueur du niveau avancé ?
- A. Connaître tous les composants par cœur
- B. Le jugement : choisir le bon outil au bon moment, et savoir ne pas sur-complexifier
- C. Écrire le plus de code possible

**2.** Que propose l'architecture hexagonale ?
- A. Mettre la base de données au centre
- B. Mettre la logique métier au centre, reliée au monde par des adaptateurs
- C. Découper en microservices

**3.** Quand envisager des microservices ?
- A. Dès le début de tout projet
- B. Quand un problème réel d'organisation/échelle le justifie ; sinon, un monolithe propre suffit
- C. Pour faire moderne

**4.** Quelle méthode pour continuer à progresser ?
- A. Tout apprendre d'un coup
- B. Partir d'un besoin réel → doc → essai → test
- C. Copier du code sans le comprendre

<details>
<summary>Voir les réponses</summary>

1. **B** — Le jugement prime sur l'accumulation d'outils.
2. **B** — Métier au centre, adaptateurs autour.
3. **B** — Sur besoin réel ; sinon, monolithe propre.
4. **B** — La boucle besoin → doc → essai → test.

</details>

## Projet fil rouge

C'est l'aboutissement de la partie 2.

1. Relis ton blog à la lumière des **frontières** (exercice 1) et corrige un endroit encore flou.
2. Vérifie que la **CI** passe au vert (tests, PHPStan, style) et que la pile **Docker** démarre.
3. Rédige dans `NOTES.md` un court **bilan** : ce que le blog sait faire désormais, et la prochaine
   évolution que tu aimerais lui apporter, avec la méthode pour t'y prendre.
4. Si tu publies ton code (rappel : c'est la base de cette formation pour les autres apprenants),
   soigne le `README` du projet : installation, lancement Docker, et lancement des tests.

Félicitations : tu as porté une application Symfony de « fonctionnelle » à « solide, performante et
maintenable ». Le plus important n'est pas le blog, mais la **démarche** d'ingénierie que tu sais
maintenant appliquer à n'importe quel projet.

---

[← Chapitre précédent](12-industrialisation-deploiement.md) · [Sommaire](README.md)
