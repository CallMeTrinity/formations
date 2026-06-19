# Les webhooks : recevoir et émettre des événements

Quand un paiement réussit chez Stripe, quand quelqu'un pousse du code sur GitHub, quand un colis change
d'état chez un transporteur, comment ton application l'apprend-elle ? La mauvaise réponse : redemander
sans cesse au service « du nouveau ? » (*polling*). La bonne réponse, dans l'immense majorité des cas :
le service **t'appelle** dès que l'événement se produit. C'est ça, un **webhook**.

Un *webhook* (« crochet web ») est un mécanisme tout simple : tu donnes une **URL** à un service, et ce
service y envoie une **requête HTTP** chaque fois qu'un événement t'intéresse. Pas de connexion
permanente, pas de bibliothèque exotique : juste du HTTP, dans le sens « serveur vers serveur ». C'est la
colle qui relie les services modernes entre eux — paiements, messageries, CI/CD, CRM, e-commerce : tous
exposent des webhooks.

Cette formation t'apprend les webhooks **des deux côtés du miroir**. Côté **récepteur**, tu sauras
recevoir un webhook, le sécuriser et le traiter sans jamais perdre ni rejouer un événement par erreur.
Côté **émetteur**, tu sauras concevoir et exposer **tes propres** webhooks : abonnements, signature,
livraison fiable avec *retries*, observabilité. On code tout en **PHP avec Symfony 8.1**.

## Prérequis

Cette formation se concentre à 100 % sur les webhooks. Elle suppose que tu es déjà à l'aise avec :

- les **bases de PHP** et la **programmation orientée objet** (classes, objets, interfaces) ;
- les **fondamentaux de Symfony** : créer un contrôleur et une route, un service, lancer
  `php bin/console`. Si ces mots sont flous, fais d'abord la [formation Symfony](../f-symfony/) ;
- des notions de **HTTP** : requête, réponse, méthode (`GET`/`POST`), en-têtes (*headers*), code de
  statut (200, 404…), et ce qu'est du **JSON** ;
- l'usage d'un **terminal**.

Tu n'as **pas** besoin de connaître la cryptographie, Stripe, ni les files de messages : on part de zéro
sur ces points. Côté machine, il te faut **PHP 8.4+**, **Composer**, l'outil en ligne de commande
**Symfony**, et de quoi exposer ton serveur local sur Internet (on installera ça au chapitre 3).

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire sur les webhooks : capable d'intégrer un
fournisseur tiers proprement, et de concevoir ta propre API de webhooks. Concrètement, tu sauras :

- expliquer **ce qu'est** un webhook, **pourquoi** il existe et **quand** le préférer au *polling* ou à
  une API classique ;
- décrire l'**anatomie** d'une requête webhook : `POST`, *payload* JSON, en-têtes, URL d'*endpoint* ;
- **recevoir** un webhook avec Symfony et le **tester en local** en exposant ton serveur sur Internet ;
- **répondre correctement** (codes HTTP, accusé de réception rapide) et **traiter en asynchrone** pour ne
  pas faire patienter l'émetteur ;
- **sécuriser** la réception : vérification de **signature HMAC**, secret partagé, protection
  **anti-rejeu**, HTTPS ;
- rendre un récepteur **fiable** : **idempotence**, **déduplication** par identifiant d'événement,
  gestion de l'ordre d'arrivée ;
- **émettre tes propres webhooks** : modèle d'**abonnement** (*endpoints*), déclenchement sur événement ;
- assurer une **livraison fiable** côté émetteur : **retries** avec *backoff* exponentiel, file d'attente,
  *dead-letter* ;
- **signer** tes webhooks pour que tes consommateurs puissent les vérifier, et **faire tourner** les
  secrets ;
- mettre en place l'**observabilité** : journal des livraisons, **rejeu** manuel d'un webhook, monitoring ;
- **concevoir une bonne API de webhooks** : versionnage des *payloads*, catalogue de **types
  d'événements**, documentation ;
- choisir entre webhooks, **SSE**, **WebSocket** et *polling*, et connaître l'écosystème (CloudEvents…).

## Plan de la formation

1. [Introduction : qu'est-ce qu'un webhook et pourquoi](01-introduction.md)
2. [Anatomie d'une requête webhook](02-anatomie-requete.md)
3. [Recevoir son premier webhook](03-recevoir-premier-webhook.md)
4. [Bien répondre : accusé de réception et traitement asynchrone](04-bien-repondre-async.md)
5. [Sécuriser la réception : signature et anti-rejeu](05-securiser-reception.md)
6. [Fiabilité côté récepteur : idempotence et déduplication](06-fiabilite-recepteur.md)
7. [Émettre ses propres webhooks](07-emettre-webhooks.md)
8. [Livraison fiable : retries, backoff et dead-letter](08-livraison-fiable.md)
9. [Signer ses webhooks](09-signer-ses-webhooks.md)
10. [Observabilité : journal, rejeu et monitoring](10-observabilite.md)
11. [Concevoir une bonne API de webhooks](11-concevoir-une-api.md)
12. [Conclusion : alternatives, écosystème et au-delà](12-conclusion.md)

## Projet fil rouge

Tout au long de la formation, tu construis **PayHub**, un petit *hub* de notifications de paiement. Il
joue **les deux rôles** d'un système à webhooks :

- **Récepteur** : PayHub reçoit les webhooks d'un prestataire de paiement (**Stripe**). Quand un paiement
  réussit ou échoue, PayHub est notifié, **vérifie la signature**, **déduplique** l'événement et le traite
  en arrière-plan.
- **Émetteur** : à son tour, PayHub notifie **ses propres clients** (des boutiques qui se sont abonnées) en
  leur envoyant des webhooks **signés**, **réessayés** en cas d'échec, avec un **journal de livraison** et
  la possibilité de **rejouer** un envoi.

Chapitre après chapitre :

- on reçoit un premier webhook Stripe et on le **teste en local** (Stripe CLI / smee) ;
- on répond **vite** et on déporte le traitement avec **Messenger** ;
- on **vérifie la signature** Stripe et on bloque les requêtes forgées ou rejouées ;
- on rend le récepteur **idempotent** : un même événement reçu deux fois n'a d'effet qu'une seule fois ;
- on bascule côté émetteur : modèle d'**abonnement**, envoi sur événement métier ;
- on fiabilise l'envoi avec **retries**, *backoff* et **dead-letter** ;
- on **signe** nos webhooks et on documente la vérification pour nos consommateurs ;
- on ajoute un **journal des livraisons** consultable et un bouton **« rejouer »** ;
- on soigne la **conception** de l'API (types d'événements, versionnage, doc).

À la fin, tu disposes d'un système à webhooks complet, des deux côtés — et surtout de la méthode pour
intégrer n'importe quel fournisseur ou exposer tes propres événements proprement.

---

Commencer par le [chapitre 1 →](01-introduction.md).
