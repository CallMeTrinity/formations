# Livraison fiable : retries, backoff et dead-letter

[← Chapitre précédent](07-emettre-webhooks.md) · [Sommaire](README.md) · [Chapitre suivant →](09-signer-ses-webhooks.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- déporter chaque livraison de webhook dans **Messenger** (une livraison = un message) ;
- mettre en place une politique de ***retry*** avec ***backoff* exponentiel** et expliquer pourquoi ;
- distinguer les erreurs **temporaires** (à réessayer) des erreurs **définitives** (à ne pas réessayer) ;
- diriger les échecs irrécupérables vers une file ***dead-letter*** ;
- adopter les bonnes pratiques d'un émetteur fiable (jitter, plafond de tentatives, désactivation d'un
  endpoint mort).

## Une livraison = un message

Le défaut du chapitre 7 : `publish()` envoyait à tous les abonnés **en synchrone**, dans une boucle. Un
abonné lent bloquait les autres, et un échec perdait l'événement. La solution est la même que côté
récepteur : **déporter dans Messenger**, mais cette fois **une livraison par message**.

```text
publish('payment.received')
        │
        ├──► message DeliverWebhook(abonné A, événement)  ─┐
        ├──► message DeliverWebhook(abonné B, événement)   ├─► file async
        └──► message DeliverWebhook(abonné C, événement)  ─┘
                                                            │
                                              worker : envoie chacun indépendamment,
                                                       réessaie celui qui échoue
```

Chaque abonné a **sa propre** livraison, traitée **indépendamment**. Un abonné en panne n'affecte plus les
autres, et son message pourra être **réessayé** sans rejouer les envois réussis.

### Le message de livraison

```php
<?php
// src/Message/DeliverWebhook.php
namespace App\Message;

// Intention : "livre cet événement à cet abonné".
final class DeliverWebhook
{
    public function __construct(
        public readonly int $subscriptionId,   // l'abonné cible (on rechargera l'entité)
        public readonly array $eventPayload,   // l'événement déjà construit (id stable !)
    ) {}
}
```

> **À retenir** — On transporte l'**`id` de l'abonné** (et non l'entité), comme appris en formation
> Symfony : un message peut être traité **plus tard**, dans un autre processus. On transporte en revanche
> le **payload complet** de l'événement, **avec son `id` déjà fixé**, pour qu'un *retry* renvoie
> exactement le même événement (essentiel pour la déduplication côté abonné).

### Le publisher dépose des messages

```php
<?php
// src/Webhook/WebhookPublisher.php (révisé)
namespace App\Webhook;

use App\Message\DeliverWebhook;
use App\Repository\WebhookSubscriptionRepository;
use Symfony\Component\Messenger\MessageBusInterface;

final class WebhookPublisher
{
    public function __construct(
        private WebhookSubscriptionRepository $abonnements,
        private MessageBusInterface $bus,
    ) {}

    public function publish(string $type, array $data): void
    {
        $evenement = new OutgoingEvent($type, $data);

        foreach ($this->abonnements->findActive() as $abonnement) {
            if ($abonnement->wantsEvent($type)) {
                // Une livraison = un message, traité indépendamment.
                $this->bus->dispatch(new DeliverWebhook(
                    subscriptionId: $abonnement->getId(),
                    eventPayload: $evenement->toArray(),
                ));
            }
        }
    }
}
```

### Le handler envoie réellement

```php
<?php
// src/MessageHandler/DeliverWebhookHandler.php
namespace App\MessageHandler;

use App\Message\DeliverWebhook;
use App\Repository\WebhookSubscriptionRepository;
use App\Webhook\WebhookSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeliverWebhookHandler
{
    public function __construct(
        private WebhookSubscriptionRepository $abonnements,
        private WebhookSender $sender,
    ) {}

    public function __invoke(DeliverWebhook $message): void
    {
        $abonnement = $this->abonnements->find($message->subscriptionId);
        if ($abonnement === null || !$abonnement->isActive()) {
            return; // abonné supprimé ou désactivé entre-temps : rien à faire.
        }

        // Si send() lève une exception, Messenger déclenchera un retry.
        $this->sender->send($abonnement, $message->eventPayload);
    }
}
```

L'astuce centrale : si `send()` **échoue** (lève une exception), Messenger **réessaiera** le message selon
la politique qu'on configure maintenant. C'est Messenger qui orchestre les *retries*, pas du code à la
main.

## Le retry avec backoff exponentiel

Réessayer **tout de suite**, en boucle, est contre-productif : si l'abonné est momentanément surchargé, le
marteler aggrave la situation. La bonne stratégie est le ***backoff* exponentiel** : on **espace** les
tentatives de plus en plus.

```text
Tentative 1 : maintenant            (échec)
Tentative 2 : +1 s                  (échec)
Tentative 3 : +2 s                  (échec)
Tentative 4 : +4 s                  (échec)
Tentative 5 : +8 s                  (échec) → abandon → dead-letter
```

Le délai **double** à chaque fois (1, 2, 4, 8 s… en réalité on étale souvent sur plusieurs minutes/heures
en production). Cela laisse à l'abonné le temps de se rétablir, sans l'inonder.

Configure un transport dédié à la livraison sortante, avec sa politique de retry :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            webhook_delivery:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 5            # 5 tentatives avant abandon
                    delay: 1000               # 1er délai : 1000 ms
                    multiplier: 2             # x2 à chaque tentative (backoff exponentiel)
                    max_delay: 3600000        # plafond : 1 h entre deux tentatives
                failure_transport: webhook_failed   # où vont les échecs définitifs
            webhook_failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            App\Message\DeliverWebhook: webhook_delivery
```

Messenger applique automatiquement : 1 s, 2 s, 4 s, 8 s, 16 s (plafonné à `max_delay`), puis, au-delà de
`max_retries`, envoie le message dans le **`failure_transport`** — la file *dead-letter*.

> **À retenir** — Le ***backoff* exponentiel** espace les tentatives (×2 à chaque échec) pour laisser
> l'abonné se rétablir au lieu de le marteler. On plafonne le délai (`max_delay`) et le nombre de
> tentatives (`max_retries`).

### Le jitter : éviter l'effet « troupeau »

Si **mille** livraisons échouent en même temps (l'abonné est tombé), elles réessaieront **toutes au même
instant** (+1 s, puis +2 s…), créant des pics qui peuvent **re-tuer** l'abonné dès qu'il se relève. Le
***jitter*** ajoute un **aléa** au délai (par ex. ±20 %) pour **étaler** les *retries*. Messenger ajoute
par défaut un peu de jitter ; en file maison, pense à l'ajouter toi-même.

## Erreurs temporaires vs définitives

Toutes les erreurs ne se valent pas. Réessayer une erreur **définitive** est une perte de temps (et de
ressources). Il faut **distinguer** :

| Situation | Type | Réessayer ? |
| --- | --- | --- |
| Timeout, connexion refusée, `502`, `503`, `504` | **temporaire** | oui |
| `500` ponctuel | temporaire (probable) | oui |
| `400` (payload refusé), `422` | **définitive** | non |
| `401` / `403` (secret de l'abonné changé) | définitive | non (alerter l'abonné) |
| `404` / `410` (URL supprimée) | définitive | non (désactiver l'abonnement) |

Pour empêcher Messenger de réessayer une erreur définitive, on lève une exception marquée
**`UnrecoverableMessageHandlingException`** : elle saute directement en *dead-letter*, sans *retry*.

```php
<?php
// Dans WebhookSender::send(), selon le code de réponse de l'abonné :
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

$code = $reponse->getStatusCode();

if (\in_array($code, [400, 401, 403, 404, 410, 422], strict: true)) {
    // Inutile de réessayer : le problème ne se résoudra pas tout seul.
    throw new UnrecoverableMessageHandlingException("Abonné a répondu $code (définitif)");
}

if ($code < 200 || $code >= 300) {
    // Erreur temporaire : on laisse Messenger réessayer (exception normale).
    throw new \RuntimeException("Abonné a répondu $code (temporaire)");
}
```

> **Attention** — Ne réessaie **jamais** indéfiniment une erreur **définitive** (`400`, `404`…). Tu
> gaspilles des ressources et tu retardes les autres livraisons. Lève une
> `UnrecoverableMessageHandlingException` pour court-circuiter les *retries*.

## La file dead-letter : que faire des échecs définitifs

Quand un message a épuisé ses tentatives (ou levé une exception irrécupérable), il atterrit dans la file
d'échec (***dead-letter queue***). Ce n'est **pas** une poubelle : c'est une **salle d'attente** pour
inspection et action.

```bash
# Lister les messages en échec :
$ php bin/console messenger:failed:show

# Inspecter un message précis (voir l'erreur, le contenu) :
$ php bin/console messenger:failed:show 42 -vv

# Rejouer un message après correction (ex. l'abonné est revenu) :
$ php bin/console messenger:failed:retry 42

# Supprimer un message définitivement perdu :
$ php bin/console messenger:failed:remove 42
```

Que faire d'un endpoint qui échoue **systématiquement** depuis longtemps ? Le bon réflexe d'un émetteur
mature :

- **désactiver l'abonnement** automatiquement après N échecs consécutifs (mettre `active = false`) ;
- **prévenir l'abonné** (e-mail : « ton endpoint webhook est en panne depuis X, voici comment le
  réactiver ») ;
- garder les événements manqués consultables, pour un **rejeu** manuel (chapitre 10).

> **À retenir** — La ***dead-letter queue*** recueille les livraisons définitivement échouées. On les
> **inspecte**, on les **rejoue** après correction, ou on les **supprime**. Un endpoint mort depuis
> longtemps doit être **désactivé** et son propriétaire **alerté** — pas martelé éternellement.

## Le profil d'un émetteur fiable

En résumé, un émetteur sérieux applique :

- **une livraison = un message**, traitée indépendamment et en asynchrone ;
- **retry avec backoff exponentiel** + **jitter**, plafonné en délai et en nombre ;
- **distinction temporaire / définitif** (pas de retry inutile) ;
- ***dead-letter*** pour les échecs, avec inspection et rejeu ;
- **désactivation + alerte** pour les endpoints durablement morts ;
- (au chapitre suivant) **signature** de chaque envoi.

Stripe, GitHub et les autres font exactement cela. Tu construis le même niveau de robustesse.

## Résumé

- On déporte chaque livraison dans **Messenger** : **une livraison = un message**, traité
  indépendamment — un abonné défaillant n'affecte plus les autres.
- Les ***retries*** sont orchestrés par Messenger (`retry_strategy`) avec un ***backoff* exponentiel**
  (délai ×`multiplier`), plafonné par `max_delay` et `max_retries`.
- Le ***jitter*** ajoute un aléa au délai pour éviter que toutes les livraisons réessaient au même instant.
- On distingue erreurs **temporaires** (timeout, 5xx → réessayer) et **définitives** (400, 404… →
  `UnrecoverableMessageHandlingException`, pas de retry).
- Les échecs définitifs vont en ***dead-letter*** : `messenger:failed:show / retry / remove`. On
  **désactive** et on **alerte** pour un endpoint durablement mort.

## Exercices

### Exercice 1 — Calculer les délais de retry

Avec `delay: 2000` (2 s), `multiplier: 3` et `max_delay: 60000` (60 s), quels sont les délais avant
chacune des 5 tentatives de retry ? Que se passe-t-il après la dernière ?

<details>
<summary>Voir le corrigé</summary>

Le délai part de 2 s et est multiplié par 3 à chaque fois, plafonné à 60 s :

- Retry 1 : 2 s
- Retry 2 : 2 × 3 = **6 s**
- Retry 3 : 6 × 3 = **18 s**
- Retry 4 : 18 × 3 = 54 s
- Retry 5 : 54 × 3 = 162 s → **plafonné à 60 s** (`max_delay`)

Après les `max_retries` tentatives échouées, le message part dans le **`failure_transport`** (la
*dead-letter queue*), où on pourra l'inspecter, le rejouer ou le supprimer.

(En pratique, un léger *jitter* fait varier ces valeurs de quelques pourcents.)

</details>

### Exercice 2 — Temporaire ou définitif ?

Pour chaque réponse d'un abonné, dis s'il faut réessayer ou non, et comment le signaler à Messenger :
(a) `503 Service Unavailable` ; (b) `404 Not Found` (l'URL n'existe plus) ; (c) un timeout de connexion ;
(d) `401 Unauthorized` (l'abonné a changé son secret).

<details>
<summary>Voir le corrigé</summary>

- **(a) `503`** : **temporaire** (service indisponible). On **réessaie** → lever une exception normale
  (`RuntimeException`), Messenger gère le retry.
- **(b) `404`** : **définitif** (l'URL n'existe plus). **Pas** de retry → lever
  `UnrecoverableMessageHandlingException`. Idéalement, **désactiver** l'abonnement et **alerter** le
  client.
- **(c) Timeout** : **temporaire** (l'abonné est peut-être juste lent ou momentanément down). On
  **réessaie** → exception normale.
- **(d) `401`** : **définitif** côté livraison (le secret ne correspond plus). **Pas** de retry utile →
  `UnrecoverableMessageHandlingException`, et **prévenir** l'abonné pour qu'il corrige son secret.

Règle : timeout / 5xx → réessayer ; 4xx « client » (400, 401, 403, 404, 410, 422) → définitif.

</details>

## Quiz

**1.** Pourquoi déporter chaque livraison dans son propre message Messenger ?
- A. Pour chiffrer les envois
- B. Pour que chaque abonné soit traité indépendamment et qu'un échec n'affecte pas les autres
- C. Pour réduire la taille du payload

**2.** Qu'est-ce que le backoff exponentiel ?
- A. Réessayer immédiatement et en boucle
- B. Espacer les tentatives de plus en plus (délai multiplié à chaque échec)
- C. Abandonner après le premier échec

**3.** Que faire d'une réponse `404` d'un abonné ?
- A. Réessayer indéfiniment
- B. La traiter comme définitive (pas de retry), désactiver et alerter
- C. Renvoyer un 200

**4.** À quoi sert la dead-letter queue ?
- A. À supprimer automatiquement les messages
- B. À recueillir les livraisons définitivement échouées pour inspection, rejeu ou suppression
- C. À stocker les abonnés inactifs

<details>
<summary>Voir les réponses</summary>

1. **B** — Une livraison = un message ; isolation totale entre abonnés.
2. **B** — Le délai croît (×multiplier) pour laisser l'abonné se rétablir.
3. **B** — `404` est définitif : pas de retry, on désactive et on alerte.
4. **B** — C'est la salle d'attente des échecs, pour agir dessus, pas une poubelle automatique.

</details>

## Projet fil rouge

PayHub émet maintenant de façon **fiable**. Tu as :

- transformé chaque livraison en **message Messenger** (`DeliverWebhook`) traité indépendamment ;
- configuré un transport `webhook_delivery` avec **retry**, ***backoff* exponentiel** et un
  **`failure_transport`** ;
- distingué erreurs **temporaires** (retry) et **définitives** (`UnrecoverableMessageHandlingException`) ;
- découvert la **dead-letter queue** et ses commandes (`messenger:failed:*`).

Il manque la pièce de sécurité côté émetteur : **signer** nos envois pour que les abonnés puissent
vérifier qu'ils viennent bien de PayHub. C'est l'objet du chapitre suivant — le pendant exact du chapitre
5, vu de l'autre côté.

---

[← Chapitre précédent](07-emettre-webhooks.md) · [Sommaire](README.md) · [Chapitre suivant →](09-signer-ses-webhooks.md)
