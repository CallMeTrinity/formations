# Bien répondre : accusé de réception et traitement asynchrone

[← Chapitre précédent](03-recevoir-premier-webhook.md) · [Sommaire](README.md) · [Chapitre suivant →](05-securiser-reception.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer la **règle d'or** du récepteur : accuser réception **vite**, traiter **ensuite** ;
- choisir le bon **code de statut** de réponse (200, 202, 4xx, 5xx) et ce qu'il déclenche chez l'émetteur ;
- installer **Messenger** et déporter le traitement d'un webhook en **arrière-plan** ;
- comprendre le mécanisme de **retry** de l'émetteur et comment t'en servir intelligemment.

## La règle d'or : accuser réception vite, traiter ensuite

On l'a effleuré aux chapitres 2 et 3 : l'émetteur impose un **délai d'attente** (*timeout*), souvent de
quelques secondes seulement (Stripe coupe autour de 20 s, GitHub à 10 s, beaucoup d'autres à 5 s). Si ta
réponse n'arrive pas à temps, l'émetteur **abandonne la connexion**, considère la livraison comme
**échouée**, et **renvoie** le même événement plus tard.

Or, le travail déclenché par un webhook est souvent **lent** : mettre à jour une base, appeler une autre
API, envoyer un e-mail, générer un PDF… Si tu fais tout ça **avant** de répondre, tu prends deux risques :

1. dépasser le délai → l'émetteur croit à un échec et **rejoue** l'événement (tu le traites deux fois) ;
2. faire échouer la requête entière si **une** de ces étapes plante, alors que l'événement était
   parfaitement valide.

La solution tient en une phrase :

> **À retenir** — **Réponds d'abord, traite ensuite.** Le rôle de l'endpoint est de **valider** et
> d'**enregistrer** l'événement le plus vite possible, puis de renvoyer un **2xx**. Le vrai travail se
> fait **en arrière-plan**, hors du temps de réponse.

```text
MAUVAIS                                  BON

reçoit  ──► traite tout (lent) ──► 200   reçoit ──► met en file ──► 200 (rapide)
        (risque de timeout)                              │
                                                         ▼
                                              traitement en arrière-plan
```

## Les codes de statut et ce qu'ils déclenchent

Avant de coder l'asynchrone, fixons quel code renvoyer, car chacun a une **conséquence** côté émetteur.

| Code | Sens | Réaction de l'émetteur |
| --- | --- | --- |
| **200 / 202 / 204** | reçu | livraison réussie, il passe à la suite |
| **400** | requête malformée | échec **définitif** souvent : il ne rejoue pas (le payload est invalide) |
| **401 / 403** | non authentifié / interdit | échec ; signature invalide, il peut alerter |
| **404 / 410** | endpoint introuvable / supprimé | il peut **désactiver** l'endpoint |
| **5xx ou timeout** | erreur de ton côté | il **rejoue** plus tard (c'est temporaire) |

Deux nuances importantes :

- **`202 Accepted`** est sémantiquement le code idéal pour un webhook traité en asynchrone : il dit
  littéralement « accepté, je traiterai plus tard ». `200` convient aussi et reste le plus courant.
- **Distingue « payload invalide » (4xx) de « mon serveur a planté » (5xx).** Un `400` dit « inutile de
  rejouer, c'est mal formé » ; un `500` dit « réessaie, c'est passager ». Renvoyer `200` sur une erreur de
  ton côté serait une faute : l'émetteur croirait que tout va bien et **ne rejouerait pas** un événement
  que tu as pourtant perdu.

> **Attention** — Ne renvoie **jamais** `200` quand ton traitement a réellement échoué pour une raison
> temporaire. Tu perdrais l'événement : l'émetteur, croyant à un succès, ne le renverra pas. En cas de
> doute sur un problème **passager**, renvoie une **5xx** pour provoquer un *retry*.

## Installer Messenger pour traiter en arrière-plan

Symfony fournit **Messenger**, un composant qui permet de déposer un **message** dans une **file** et de
le faire traiter par un **processus séparé**. C'est exactement l'outil pour « répondre vite, traiter
ensuite ».

```bash
$ composer require symfony/messenger
```

Le vocabulaire (vu dans la formation Symfony avancé, on le rappelle) :

- un **message** est un objet simple décrivant une intention (« traite l'événement Stripe X ») ;
- un **handler** (gestionnaire) est la classe qui **fait le travail** quand le message arrive ;
- le **bus** est le tapis roulant : tu y déposes le message, il le route vers le bon handler.

### Le message : transporter l'événement

On crée un message qui transporte **les données minimales** nécessaires au traitement. Pour un webhook,
le plus sûr est de transporter le **payload brut** et le **type** : le handler aura tout ce qu'il faut,
même traité plus tard.

```php
<?php
// src/Message/StripeWebhookReceived.php
namespace App\Message;

// Une intention : "un webhook Stripe est arrivé, voici son contenu".
final class StripeWebhookReceived
{
    public function __construct(
        public readonly string $eventId,     // l'id de l'événement (déduplication, chapitre 6)
        public readonly string $eventType,   // ex. payment_intent.succeeded
        public readonly array $payload,      // le contenu décodé de l'événement
    ) {}
}
```

### Le handler : faire le vrai travail

```php
<?php
// src/MessageHandler/StripeWebhookReceivedHandler.php
namespace App\MessageHandler;

use App\Message\StripeWebhookReceived;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class StripeWebhookReceivedHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(StripeWebhookReceived $message): void
    {
        // Ici, le VRAI traitement, qui peut être lent — on n'est plus dans
        // le temps de réponse du webhook.
        $this->logger->info('Traitement asynchrone du webhook', [
            'id' => $message->eventId,
            'type' => $message->eventType,
        ]);

        // On aiguille selon le type d'événement.
        match ($message->eventType) {
            'payment_intent.succeeded' => $this->onPaiementReussi($message->payload),
            'payment_intent.payment_failed' => $this->onPaiementEchoue($message->payload),
            default => $this->logger->info('Type ignoré', ['type' => $message->eventType]),
        };
    }

    private function onPaiementReussi(array $payload): void
    {
        // ... mettre à jour la commande, donner l'accès, envoyer un mail, etc.
    }

    private function onPaiementEchoue(array $payload): void
    {
        // ... prévenir le client, marquer la commande, etc.
    }
}
```

L'attribut `#[AsMessageHandler]` suffit à Symfony pour relier ce handler au message : pas de
configuration à écrire.

### Le contrôleur : déposer et répondre

On allège le contrôleur du chapitre 3 : il **valide**, **dépose** le message, et **répond** aussitôt.

```php
<?php
// src/Controller/WebhookController.php (extrait)
use App\Message\StripeWebhookReceived;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
public function stripe(Request $request, MessageBusInterface $bus, LoggerInterface $logger): Response
{
    $payloadBrut = $request->getContent();
    $evenement = json_decode($payloadBrut, associative: true);

    if (!is_array($evenement) || !isset($evenement['type'], $evenement['id'])) {
        return new JsonResponse(['error' => 'payload invalide'], Response::HTTP_BAD_REQUEST);
    }

    // On dépose le travail dans la file, sans l'exécuter ici.
    $bus->dispatch(new StripeWebhookReceived(
        eventId: $evenement['id'],
        eventType: $evenement['type'],
        payload: $evenement,
    ));

    // Réponse immédiate : 202 "accepté, je traite plus tard".
    return new JsonResponse(['received' => true], Response::HTTP_ACCEPTED);
}
```

## Synchrone vs asynchrone : router le message vers une vraie file

Par défaut, Messenger traite le message **synchrone** : `dispatch()` appelle le handler **tout de suite**,
dans la même requête. On n'a alors rien gagné ! Pour traiter **vraiment en arrière-plan**, il faut router
ce message vers un **transport** asynchrone (une file).

Configure un transport dans `config/packages/messenger.yaml` :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            # 'async' : une file persistée (ici via Doctrine, table dédiée).
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            # Notre message part dans la file asynchrone.
            App\Message\StripeWebhookReceived: async
```

Et le DSN dans `.env` (le transport Doctrine stocke les messages dans une table, simple pour débuter) :

```bash
# .env
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=1
```

Désormais, `dispatch()` **enregistre** le message dans la file et rend la main **immédiatement**. Un
processus séparé, le **worker**, consomme la file et exécute les handlers :

```bash
$ php bin/console messenger:consume async -vv
# -vv affiche chaque message traité, pratique en développement.
```

Refais ton test (`stripe trigger payment_intent.succeeded` ou `curl`) : le contrôleur répond en quelques
millisecondes, et c'est le **worker** qui logue le traitement, à son rythme.

> **À retenir** — En **synchrone**, le handler tourne pendant la requête (rien gagné). En **asynchrone**,
> le message est **mis en file** et exécuté par un **worker** séparé. C'est ce découplage qui permet
> d'accuser réception instantanément.

## Tirer parti des retries de l'émetteur

Le mécanisme de *retry* de l'émetteur (qui rejoue en cas de 5xx ou de timeout) est une **sécurité
gratuite**, à condition de jouer le jeu :

- si tu **n'as pas pu enregistrer** l'événement (base indisponible, file injoignable), renvoie une
  **5xx** : l'émetteur rejouera, et tu ne perds rien.
- si tu **as enregistré** l'événement (déposé en file), renvoie un **2xx** même si le traitement
  ultérieur peut encore échouer : la responsabilité du traitement t'appartient désormais, et c'est
  Messenger (pas l'émetteur) qui gérera les *retries* du handler.

Autrement dit, il y a **deux niveaux de retry** :

1. celui de **l'émetteur**, tant que tu n'as pas accusé réception ;
2. celui de **Messenger**, une fois le message dans ta file (on le configurera au chapitre 8 côté
   émetteur, mais le principe vaut aussi ici).

Conséquence directe, qui motive tout le chapitre 6 : comme un même événement peut être **rejoué** (par
l'émetteur **ou** par Messenger), ton traitement doit pouvoir être exécuté **plusieurs fois sans dégât**.
C'est l'**idempotence**, qu'on attaque bientôt.

## Résumé

- **Règle d'or** : accuse réception **vite** (2xx), fais le **vrai travail en arrière-plan**. Une réponse
  lente provoque un *timeout* et un rejeu.
- **`202 Accepted`** est le code idéal pour un webhook traité en asynchrone ; `200` convient aussi.
- Distingue **4xx** (payload invalide, ne pas rejouer) de **5xx** (erreur passagère, rejoue). Ne renvoie
  **jamais** `200` sur un échec temporaire réel : tu perdrais l'événement.
- **Messenger** permet de **déposer** un message et de le faire traiter par un **worker** séparé
  (`messenger:consume`).
- Router le message vers un **transport asynchrone** (Doctrine, Redis, AMQP…) est ce qui rend le
  traitement **vraiment** différé.
- Le **retry** de l'émetteur est une sécurité : renvoie une **5xx** tant que tu n'as rien pu enregistrer.
  Mais comme un événement peut être **rejoué**, le traitement doit devenir **idempotent** (chapitre 6).

## Exercices

### Exercice 1 — Choisir le bon code

Pour chaque situation, dis quel code de statut renvoyer et pourquoi : (a) le payload n'est pas du JSON ;
(b) la signature est invalide ; (c) ta base de données est momentanément injoignable, tu n'as pas pu
mettre le message en file ; (d) tout s'est bien passé, le message est en file.

<details>
<summary>Voir le corrigé</summary>

- **(a) JSON invalide** : **400**. La requête est malformée, inutile de la rejouer.
- **(b) Signature invalide** : **401** (ou 403). La requête n'est pas authentifiée ; on la rejette (on
  verra ça au chapitre 5).
- **(c) Base injoignable** : **500** (5xx). C'est **passager** et de **ton** côté : tu veux que l'émetteur
  **rejoue** pour ne pas perdre l'événement.
- **(d) Message en file** : **202** (ou 200). Tu as bien pris en charge l'événement ; le traitement
  se fera en arrière-plan.

</details>

### Exercice 2 — Repérer la faute

Un développeur écrit ce contrôleur. Qu'est-ce qui cloche, et quelle conséquence concrète ?

```php
public function stripe(Request $request): Response
{
    $evenement = json_decode($request->getContent(), true);
    try {
        $this->traiterPaiement($evenement);   // appels API + mail, ~8 secondes
    } catch (\Throwable $e) {
        // on avale l'erreur
    }
    return new JsonResponse(['received' => true]); // toujours 200
}
```

<details>
<summary>Voir le corrigé</summary>

Deux fautes graves :

1. **Traitement synchrone et lent** (`~8 s`) dans le temps de réponse : risque de **timeout** côté
   émetteur, donc rejeu inutile et traitement en double.
2. **`200` quoi qu'il arrive** : l'erreur est **avalée** (`catch` vide), puis on renvoie `200`. Si le
   traitement échoue, l'émetteur **croit que c'est bon** et **ne rejoue pas** → l'événement est **perdu**
   silencieusement.

Correction : valider puis **déposer en file** (`$bus->dispatch(...)`), renvoyer **202** ; faire le
traitement dans un **handler**, et si l'enregistrement en file échoue, laisser remonter l'erreur pour
renvoyer une **5xx** et déclencher le rejeu de l'émetteur.

</details>

## Quiz

**1.** Quelle est la règle d'or du récepteur de webhooks ?
- A. Toujours traiter tout le travail avant de répondre
- B. Accuser réception vite, puis traiter en arrière-plan
- C. Répondre 200 dans tous les cas

**2.** Que fait l'émetteur si ton endpoint répond `500` ?
- A. Il désactive définitivement l'endpoint
- B. Il considère un échec passager et rejoue plus tard
- C. Il ignore l'événement pour toujours

**3.** Pourquoi router le message Messenger vers un transport asynchrone ?
- A. Sinon le handler s'exécute pendant la requête, et on n'a rien gagné
- B. Pour chiffrer le message
- C. Parce que `dispatch()` ne fonctionne pas autrement

**4.** Pourquoi est-il dangereux de renvoyer `200` après un échec de traitement réel ?
- A. Ça ralentit le serveur
- B. L'émetteur croit à un succès et ne rejouera pas : l'événement est perdu
- C. Ça déclenche un trop grand nombre de retries

<details>
<summary>Voir les réponses</summary>

1. **B** — On valide et on enregistre vite, le travail lourd part en arrière-plan.
2. **B** — Une 5xx signale une erreur temporaire ; l'émetteur rejoue.
3. **A** — En synchrone, le handler tourne dans la requête : aucun gain. L'async le déporte sur un worker.
4. **B** — Le `200` ment à l'émetteur, qui n'a alors aucune raison de renvoyer l'événement perdu.

</details>

## Projet fil rouge

PayHub répond maintenant **en quelques millisecondes** et traite **en arrière-plan**. Tu as :

- installé **Messenger** et créé le message `StripeWebhookReceived` + son **handler** ;
- allégé `WebhookController` : il **valide**, **dépose** le message sur le bus, renvoie **`202`** ;
- routé le message vers un **transport asynchrone** (Doctrine) et lancé un **worker**
  (`messenger:consume`) ;
- compris les **codes de statut** à renvoyer et le **double niveau de retry** (émetteur + Messenger).

PayHub reçoit, répond vite et traite proprement… mais il fait confiance à **n'importe qui**. Au chapitre
suivant, on **sécurise** la réception : vérifier que le webhook vient **vraiment** de Stripe, via la
**signature**, et bloquer les requêtes forgées ou **rejouées** par un attaquant.

---

[← Chapitre précédent](03-recevoir-premier-webhook.md) · [Sommaire](README.md) · [Chapitre suivant →](05-securiser-reception.md)
