# WebSocket : le temps réel sur le web

Sur le web classique, c'est toujours le navigateur qui demande et le serveur qui répond. Le serveur ne
peut rien envoyer de lui-même : pour savoir s'il y a du nouveau, le client doit redemander, encore et
encore. Ça suffit pour afficher une page, mais pas pour un chat, une notification instantanée, un cours
de bourse qui bouge ou la position d'un livreur sur une carte.

**WebSocket** est le protocole qui règle ce problème : il ouvre une **connexion permanente et
bidirectionnelle** entre le navigateur et le serveur. Une fois la connexion établie, les deux côtés
peuvent s'envoyer des messages à tout moment, dans les deux sens, sans rouvrir de connexion. C'est la
brique de base du temps réel sur le web, et elle est supportée nativement par tous les navigateurs et
par Node.js.

Cette formation t'apprend WebSocket de zéro, en construisant une vraie application : un **chat en
direct**. Tu pars d'un simple « echo » et tu arrives à un chat multi-salons, avec présence, historique,
reconnexion automatique, sécurité et déploiement.

## Prérequis

Cette formation se concentre à 100 % sur WebSocket. Elle suppose que tu es déjà à l'aise avec :

- les **bases de JavaScript** : variables, fonctions, objets, tableaux, `JSON`, et l'asynchrone
  (`async`/`await`, promesses, *callbacks*) ;
- l'usage d'un **terminal** et de **npm** (installer un paquet, lancer un script) ;
- les notions web de base : ce qu'est une requête **HTTP**, une **URL**, le **navigateur**.

Côté machine, il te faut **Node.js 20+** (qui fournit `npm`) et un éditeur de code. On installe le
reste (les quelques bibliothèques) au fil de la formation. Aucune connaissance préalable de WebSocket
ni du temps réel n'est requise.

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire sur WebSocket. Concrètement, tu sauras :

- expliquer **pourquoi** WebSocket existe et **quand** le préférer au polling, au long-polling ou aux
  *Server-Sent Events* ;
- décrire le **protocole** : le *handshake* `Upgrade`, `ws://` vs `wss://`, les *frames*, le mécanisme
  `ping`/`pong` ;
- écrire un **serveur WebSocket** en Node.js avec la bibliothèque `ws` et un **client** avec l'API
  native du navigateur ;
- diffuser des messages à plusieurs clients (*broadcast*) et concevoir un **protocole de messages**
  applicatif propre (JSON typé) ;
- gérer des **salons**, la **présence** (qui est en ligne) et l'indicateur « est en train d'écrire » ;
- rendre une connexion **robuste** : détection de coupure, *heartbeat*, **reconnexion automatique** et
  file d'attente des messages ;
- **persister** l'historique des messages et le restituer à la connexion ;
- **sécuriser** une application temps réel : `wss`/TLS, authentification, validation des entrées,
  vérification de l'`Origin`, *rate limiting* ;
- **déployer** derrière un reverse proxy et comprendre la **montée en charge** multi-instances avec
  Redis pub/sub.

## Plan de la formation

1. [Introduction : le temps réel sur le web](01-introduction.md)
2. [Le protocole WebSocket sous le capot](02-protocole-websocket.md)
3. [Premier aller-retour : serveur et client](03-premier-aller-retour.md)
4. [Le chat de base : diffuser à tous](04-chat-de-base-broadcast.md)
5. [Un protocole de messages applicatif](05-protocole-messages-json.md)
6. [Les salons](06-salons.md)
7. [Présence et indicateur de saisie](07-presence-saisie.md)
8. [Robustesse côté client : reconnexion et heartbeat](08-robustesse-reconnexion.md)
9. [Historique et persistance](09-historique-persistance.md)
10. [Sécurité d'une application temps réel](10-securite.md)
11. [Déploiement et montée en charge](11-deploiement-montee-en-charge.md)
12. [Conclusion : bonnes pratiques et au-delà](12-conclusion.md)

## Projet fil rouge

Tout au long de la formation, on construit **un chat en direct**, du squelette à une application prête
pour une mise en production légère. Chapitre après chapitre :

- on établit un premier **aller-retour** entre un serveur Node et un client navigateur ;
- on diffuse les messages à **tous les participants** connectés ;
- on définit un **protocole de messages** propre (JSON typé) avec pseudos ;
- on ajoute des **salons** pour discuter par sujet ;
- on affiche la **présence** (qui est en ligne) et l'indicateur **« est en train d'écrire »** ;
- on rend le client **robuste** : il se reconnaît tout seul après une coupure ;
- on **persiste l'historique** pour qu'un nouvel arrivant voie les derniers messages ;
- on **sécurise** le tout (TLS, authentification, validation, *rate limiting*) ;
- on **déploie** derrière nginx et on prépare la **montée en charge** avec Redis.

À la fin, tu disposes d'un chat complet et fonctionnel — et surtout de la méthode pour bâtir
n'importe quelle fonctionnalité temps réel.

---

Commencer par le [chapitre 1 →](01-introduction.md).
