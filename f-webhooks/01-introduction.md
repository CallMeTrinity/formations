# Introduction : qu'est-ce qu'un webhook et pourquoi

[Sommaire](README.md) · [Chapitre suivant →](02-anatomie-requete.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **webhook** avec tes propres mots ;
- comprendre pourquoi le *polling* (interroger en boucle) est un mauvais réflexe et ce que le webhook
  apporte à la place ;
- situer le webhook par rapport à une **API** classique et aux autres techniques temps réel ;
- reconnaître les cas où un webhook est le bon outil… et ceux où il ne l'est pas ;
- créer le squelette du projet fil rouge **PayHub**.

## Le problème : comment apprendre qu'un événement s'est produit ?

Imagine que tu vends des formations en ligne. Le paiement est géré par un prestataire externe (Stripe,
PayPal…). Quand un client paie, **leur** serveur le sait. Mais **toi**, comment l'apprends-tu pour donner
accès au cours ?

La première idée qui vient, c'est de **redemander régulièrement** : « ce paiement est-il réglé ? »,
« et maintenant ? », « et là ? ». Cette technique s'appelle le *polling* (« interroger en boucle »).

```php
<?php
// Polling : on interroge l'API de paiement toutes les 10 secondes.
while (true) {
    $statut = $api->getPaiement($paiementId)->statut;   // appel HTTP
    if ($statut === 'paye') {
        donnerAcces();
        break;
    }
    sleep(10);   // on attend, puis on redemande
}
```

Ça marche, mais c'est mauvais sur tous les plans :

- **Lent** : le client peut attendre jusqu'à 10 secondes après avoir payé.
- **Gaspilleur** : tu envoies des centaines de requêtes qui répondent « toujours pas payé » avant la
  bonne. Multiplie ça par tous tes clients en attente, 24h/24.
- **Fragile et limité** : la plupart des API imposent un nombre maximal d'appels (*rate limiting*). À
  force d'interroger, tu te fais bloquer.

Le problème de fond : c'est **toi** qui cours après l'information, alors que c'est **l'autre** qui la
détient et qui sait, à la milliseconde près, quand elle change.

## La solution : qu'on t'appelle quand ça arrive

Inverse le sens. Plutôt que de redemander sans cesse, tu donnes au prestataire une **URL** à toi, et tu
lui dis : « dès qu'un paiement réussit, **envoie-moi** un message à cette adresse ». Le prestataire
t'enverra alors une simple **requête HTTP** au moment exact où l'événement se produit.

C'est ça, un **webhook** : une URL de ton application que **le service tiers appelle** quand un événement
l'intéressant survient.

```text
POLLING (toi qui demandes, en boucle)        WEBHOOK (lui qui t'appelle, une fois)

Toi  ── "payé ?" ──►  Stripe                 Toi  ── "voici mon URL" ──►  Stripe
Toi  ◄── "non" ────   Stripe                          (un paiement réussit)
Toi  ── "payé ?" ──►  Stripe                 Toi  ◄── POST /webhook ────  Stripe
Toi  ◄── "non" ────   Stripe                       (une seule requête,
Toi  ── "payé ?" ──►  Stripe                        au bon moment)
Toi  ◄── "OUI" ────   Stripe
```

Le mot « webhook » se décompose ainsi : *hook* (« crochet ») désigne un point où l'on accroche du
comportement ; *web* parce que ce crochet est déclenché par une requête HTTP. On parle aussi parfois de
*HTTP callback* (« rappel HTTP ») ou de *reverse API* : c'est le service tiers qui appelle **ton** API,
et non l'inverse.

> **À retenir** — Avec une API classique, **c'est toi qui appelles** le service quand tu veux savoir. Avec
> un webhook, **c'est le service qui t'appelle** quand il a quelque chose à te dire. Le webhook supprime
> l'attente et le gaspillage du *polling* en inversant le sens de l'appel.

## Webhook vs API : deux faces complémentaires

Webhook et API ne s'opposent pas, ils se complètent. La règle est simple :

- **API classique** (tu appelles le service) : pour les actions que **tu** déclenches et dont tu veux le
  résultat tout de suite. Exemple : « crée un paiement de 49 € » → tu reçois la réponse immédiatement.
- **Webhook** (le service t'appelle) : pour les événements qui surviennent **plus tard** ou **côté
  serveur**, sans que tu sois là pour les attendre. Exemple : « le paiement a finalement été validé par la
  banque, trois secondes après », ou « le client a contesté le paiement, deux semaines plus tard ».

Beaucoup d'événements n'ont **aucun** moment où tu pourrais les attendre par un appel : un remboursement
décidé par le prestataire, un abonnement qui se renouvelle tout seul le mois suivant, un litige ouvert par
la banque. Pour ceux-là, le webhook n'est pas seulement plus pratique : c'est **le seul moyen** d'être au
courant sans interroger en permanence.

## Webhook vs WebSocket vs SSE : ne pas confondre

Trois mécanismes permettent à un serveur de « pousser » de l'information. Ils visent des usages
différents :

| Mécanisme | Sens | Connexion | Usage typique |
| --- | --- | --- | --- |
| **Webhook** | serveur → serveur | aucune (une requête HTTP par événement) | intégrations entre back-ends : paiement, CI, e-commerce |
| **WebSocket** | bidirectionnel | permanente | temps réel **navigateur ↔ serveur** : chat, jeu |
| **SSE** | serveur → navigateur | permanente | flux **vers un navigateur** : notifications, tableau de bord |

La distinction clé : un webhook relie **deux serveurs** (le tien et celui d'un fournisseur), sans
connexion maintenue ; **WebSocket** et **SSE** relient un **navigateur** à un serveur via une connexion
ouverte en continu. Si tu veux mettre à jour une **page web** en direct, c'est WebSocket ou SSE. Si tu
veux que **ton back-end** réagisse à un événement d'un **autre back-end**, c'est un webhook. On
reviendra sur ce choix au [chapitre 12](12-conclusion.md).

> **Attention** — Un webhook **n'arrive pas dans le navigateur**. C'est une requête HTTP qu'un serveur
> tiers envoie à **ton** serveur, sur une URL publique. Ton code JavaScript côté navigateur ne « reçoit »
> jamais un webhook directement.

## Quand utiliser un webhook (et quand non)

Un webhook est le bon outil quand :

- un **service tiers** doit te prévenir d'un événement (paiement, livraison, signature de document…) ;
- l'événement survient **de façon imprévisible** ou **différée**, sans moment naturel pour l'attendre ;
- tu veux **éviter le polling** et sa charge inutile.

Il est mal adapté quand :

- tu as besoin de la réponse **immédiatement, dans le même échange** : utilise un appel d'API classique ;
- la cible est un **navigateur** : utilise SSE ou WebSocket ;
- tu n'as **pas d'URL publique** stable où être appelé (on verra comment contourner ça **en
  développement** au chapitre 3, mais en production il te faut une vraie URL accessible).

## Mise en place du projet fil rouge : PayHub

On va construire **PayHub**, un *hub* de notifications de paiement, avec **Symfony**. Au fil de la
formation, PayHub jouera les deux rôles : **recevoir** les webhooks de Stripe, puis **émettre** ses
propres webhooks vers des boutiques clientes.

### Vérifier l'environnement

```bash
$ php --version       # PHP 8.4 ou plus récent attendu
$ composer --version
$ symfony version     # l'outil en ligne de commande Symfony
```

Si l'un manque, installe **PHP 8.4+**, **Composer** (getcomposer.org) et l'outil **Symfony**
(symfony.com/download). On installera de quoi exposer le serveur sur Internet au chapitre 3.

### Créer le projet

On crée une application web Symfony et on entre dans le dossier :

```bash
$ symfony new payhub --webapp --version=7.2   # squelette web complet
$ cd payhub
```

> **Astuce** — `--webapp` installe d'un coup les paquets utiles à une vraie application (Twig, Doctrine,
> formulaires, sécurité, Maker…). On ajoutera les briques spécifiques aux webhooks au fur et à mesure.

Lance le serveur de développement pour vérifier que tout tourne :

```bash
$ symfony server:start -d        # démarre en arrière-plan
$ symfony open:local             # ouvre l'appli dans le navigateur
```

Tu dois voir la page d'accueil par défaut de Symfony. PayHub est prêt à accueillir son premier webhook —
mais avant d'écrire le moindre récepteur, il faut comprendre **à quoi ressemble** une requête webhook.
C'est l'objet du chapitre 2.

## Résumé

- Un **webhook** est une URL de ton application qu'un **service tiers appelle** (via une requête HTTP)
  quand un événement survient.
- Il remplace avantageusement le ***polling*** (interroger en boucle), qui est lent, gaspilleur et
  souvent bloqué par les limites d'appels.
- **API vs webhook** : tu appelles l'API quand **tu** veux un résultat ; le service t'appelle par webhook
  quand **lui** a quelque chose à signaler, souvent plus tard.
- Webhook = **serveur à serveur**, sans connexion permanente. **WebSocket/SSE** = vers un **navigateur**,
  connexion ouverte. Ne pas confondre.
- Le webhook brille pour les événements **tiers, imprévisibles ou différés** ; il ne convient pas quand
  tu veux une réponse immédiate ou que la cible est un navigateur.
- Notre projet **PayHub** sera construit avec **Symfony** et jouera tour à tour les rôles de **récepteur**
  et d'**émetteur** de webhooks.

## Exercices

### Exercice 1 — Webhook ou pas webhook ?

Pour chacun de ces besoins, dis si un webhook est pertinent, ou s'il vaut mieux un appel d'API classique,
ou SSE/WebSocket — et pourquoi : (a) afficher le solde d'un compte sur demande ; (b) être prévenu qu'un
abonnement mensuel s'est renouvelé ; (c) afficher en direct, dans le navigateur, les messages d'un chat ;
(d) savoir qu'un transporteur a livré un colis.

<details>
<summary>Voir le corrigé</summary>

La question centrale : **qui détient l'information, et quand survient-elle ?**

- **(a) Solde sur demande** : appel d'**API classique**. C'est toi qui veux la donnée, à un instant
  précis, et tu veux la réponse tout de suite.
- **(b) Renouvellement d'abonnement** : **webhook**. L'événement survient côté prestataire, de façon
  différée (un mois plus tard), sans moment où tu l'attendrais. Le polling serait absurde.
- **(c) Chat dans le navigateur** : **WebSocket** (ou SSE si un seul sens suffit). La cible est un
  **navigateur** et il faut une connexion ouverte. Un webhook n'arrive pas dans le navigateur.
- **(d) Colis livré** : **webhook**. Le transporteur sait quand il livre ; il te prévient via une requête
  vers ton serveur.

</details>

### Exercice 2 — Le coût du polling

Tu gères 500 paiements « en attente » à un instant donné, et ton code interroge l'API de paiement toutes
les 5 secondes pour chacun, jusqu'à confirmation. Combien d'appels d'API fais-tu par minute au pic ? Si
un webhook remplaçait ce polling, combien d'appels **entrants** recevrais-tu pour ces mêmes 500
paiements (en supposant un seul événement « payé » par paiement) ?

<details>
<summary>Voir le corrigé</summary>

**Démarche** : compter les appels du polling, puis les comparer au modèle webhook.

- Polling : 1 appel toutes les 5 s = 12 appels/minute **par paiement**. Pour 500 paiements en attente :
  12 × 500 = **6 000 appels/minute**, dont l'écrasante majorité répondent « toujours pas payé ».
- Webhook : 1 requête entrante **par paiement confirmé**, soit **500 requêtes au total** (pas par minute,
  une seule fois chacune), reçues pile au bon moment.

Le polling fait des milliers d'appels par minute pour rien et risque le blocage par *rate limiting* ; le
webhook fait exactement le nombre d'échanges utiles, au bon moment. C'est tout l'intérêt d'inverser le
sens de l'appel.

</details>

## Quiz

**1.** Qu'est-ce qu'un webhook ?
- A. Une connexion permanente entre un navigateur et un serveur
- B. Une URL de ton application qu'un service tiers appelle quand un événement survient
- C. Une API que tu interroges en boucle pour savoir s'il y a du nouveau

**2.** Pourquoi le *polling* est-il un mauvais réflexe pour suivre des paiements ?
- A. Il ne fonctionne pas en HTTPS
- B. Il est lent, gaspilleur et se heurte aux limites d'appels de l'API
- C. Il ne sait pas transporter du JSON

**3.** Quelle différence essentielle entre un webhook et WebSocket ?
- A. Le webhook relie deux serveurs sans connexion permanente ; WebSocket relie un navigateur et un serveur via une connexion ouverte
- B. Aucune, ce sont deux noms pour la même chose
- C. WebSocket ne marche qu'en local, le webhook seulement en production

**4.** Dans quel cas un webhook est-il **inadapté** ?
- A. Être prévenu qu'un remboursement a eu lieu
- B. Obtenir immédiatement le résultat d'une action que tu viens de lancer
- C. Apprendre qu'un colis a été livré

<details>
<summary>Voir les réponses</summary>

1. **B** — Un webhook est une URL appelée **par** un tiers ; ce n'est ni une connexion permanente ni du
   polling.
2. **B** — Beaucoup d'appels inutiles, de la latence, et le risque de *rate limiting*.
3. **A** — Webhook = serveur à serveur, sans connexion maintenue ; WebSocket = navigateur ↔ serveur,
   connexion ouverte.
4. **B** — Pour une réponse immédiate dans le même échange, c'est un appel d'API classique qu'il faut.

</details>

## Projet fil rouge

Premier jalon : l'environnement est prêt. Tu as :

- vérifié **PHP 8.4+**, **Composer** et l'outil **Symfony** ;
- créé l'application **PayHub** (`symfony new payhub --webapp`) ;
- démarré le serveur de développement et vu la page d'accueil par défaut.

Au chapitre suivant, on dissèque une **vraie requête webhook** (celle de Stripe) pour comprendre, octet
par octet, ce que PayHub va devoir lire et vérifier — avant d'écrire le récepteur au chapitre 3.

---

[Sommaire](README.md) · [Chapitre suivant →](02-anatomie-requete.md)
