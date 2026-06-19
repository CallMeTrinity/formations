# Émettre ses propres webhooks

[← Chapitre précédent](06-fiabilite-recepteur.md) · [Sommaire](README.md) · [Chapitre suivant →](08-livraison-fiable.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- modéliser un système d'**abonnement** : qui veut recevoir quels événements, et où ;
- concevoir le **format** des événements que tu émets (enveloppe `id`/`type`/`data`) ;
- **déclencher** l'envoi d'un webhook depuis un événement métier de ton application ;
- envoyer une requête `POST` HTTP vers l'endpoint d'un abonné avec le **HttpClient** de Symfony ;
- distinguer clairement le rôle d'**émetteur** de celui de **récepteur** vu jusqu'ici.

## Changer de casquette : de récepteur à émetteur

Jusqu'ici, PayHub **recevait** les webhooks de Stripe. Maintenant, PayHub **émet** : il veut prévenir
**ses propres clients** (des boutiques) quand quelque chose les concerne — par exemple « un paiement a été
reçu pour ta boutique ». Les rôles s'inversent :

```text
RÉCEPTEUR (chapitres 3-6)            ÉMETTEUR (chapitres 7-10)

Stripe ──POST──► PayHub              PayHub ──POST──► Boutique A (abonnée)
       (tu vérifies, tu traites)            ──POST──► Boutique B (abonnée)
                                     (tu signes, tu réessaies, tu logues)
```

Tout ce que tu as appris côté récepteur devient ta **responsabilité** côté émetteur : c'est **toi** qui
dois maintenant répondre vite à tes abonnés, signer tes envois, réessayer en cas d'échec. La symétrie est
totale — et c'est ce qui fait qu'apprendre les deux côtés se renforce.

## Modéliser l'abonnement

Pour émettre, il faut savoir **à qui** envoyer **quoi**. C'est le rôle d'un **abonnement** (*endpoint*
abonné) : un client enregistre une **URL** à lui, et choisit **quels types d'événements** il veut recevoir.

```php
<?php
// src/Entity/WebhookSubscription.php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WebhookSubscription
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    // L'URL de l'abonné, où PayHub enverra les webhooks.
    #[ORM\Column(length: 500)]
    private string $url;

    // Les types d'événements souhaités, ex. ['payment.received', 'payment.refunded'].
    #[ORM\Column(type: Types::JSON)]
    private array $eventTypes = [];

    // Le secret partagé pour signer les envois (chapitre 9). Généré à la création.
    #[ORM\Column(length: 64)]
    private string $secret;

    // Un abonnement peut être désactivé (échecs répétés, demande du client...).
    #[ORM\Column]
    private bool $active = true;

    public function __construct(string $url, array $eventTypes)
    {
        $this->url = $url;
        $this->eventTypes = $eventTypes;
        // Secret aléatoire et imprévisible (32 octets → 64 caractères hexadécimaux).
        $this->secret = bin2hex(random_bytes(32));
    }

    public function wantsEvent(string $type): bool
    {
        return $this->active && \in_array($type, $this->eventTypes, strict: true);
    }

    public function getUrl(): string { return $this->url; }
    public function getSecret(): string { return $this->secret; }
}
```

Points de conception :

- **`eventTypes`** : un abonné ne veut pas forcément **tout**. On stocke la liste des types qui
  l'intéressent et on ne lui enverra **que ceux-là**. Évite d'inonder un abonné d'événements inutiles.
- **`secret`** : généré **aléatoirement** à la création (`random_bytes`), propre à chaque abonnement. Il
  servira à **signer** les envois (chapitre 9). On le communique **une seule fois** à l'abonné.
- **`active`** : on pourra **désactiver** un abonnement (échecs répétés, fermeture du compte) sans le
  supprimer.

Génère la migration :

```bash
$ php bin/console make:migration && php bin/console doctrine:migrations:migrate
```

> **À retenir** — Un abonnement, c'est **une URL + une liste de types d'événements + un secret**. Le
> secret est généré **côté émetteur** à la création et transmis **une fois** à l'abonné, qui s'en servira
> pour vérifier la signature.

## Définir le format de tes événements

En tant qu'émetteur, **tu** choisis le format de tes événements. Reprends l'ossature éprouvée du chapitre
2 — c'est ce que tes consommateurs attendront, par habitude des autres fournisseurs :

```json
{
  "id": "evt_2c9a1f8e",
  "type": "payment.received",
  "created": 1718800000,
  "data": {
    "payment_id": "pay_553",
    "amount": 4900,
    "currency": "eur",
    "shop_id": "shop_12"
  }
}
```

- **`id`** : unique et stable, pour que **tes** abonnés puissent dédupliquer (tu leur imposes la même
  discipline que celle apprise au chapitre 6 — c'est un cercle vertueux).
- **`type`** : `ressource.action`, cohérent dans tout ton catalogue (on soignera ce catalogue au
  [chapitre 11](11-concevoir-une-api.md)).
- **`created`** + **`data`** : horodatage et charge métier.

Modélise cet événement comme un petit objet, pour ne pas manipuler des tableaux partout :

```php
<?php
// src/Webhook/OutgoingEvent.php
namespace App\Webhook;

// Un événement que PayHub émet vers ses abonnés.
final class OutgoingEvent
{
    public readonly string $id;
    public readonly int $created;

    public function __construct(
        public readonly string $type,   // ex. payment.received
        public readonly array $data,    // la charge métier
    ) {
        $this->id = 'evt_' . bin2hex(random_bytes(8));
        $this->created = time();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'created' => $this->created,
            'data' => $this->data,
        ];
    }
}
```

## Envoyer une requête `POST` à un abonné

Émettre un webhook, c'est envoyer une requête `POST` HTTP vers l'URL de l'abonné. Symfony fournit le
**HttpClient** pour ça.

```bash
$ composer require symfony/http-client
```

Un premier service d'envoi, volontairement simple (on le fiabilisera au chapitre suivant) :

```php
<?php
// src/Webhook/WebhookSender.php
namespace App\Webhook;

use App\Entity\WebhookSubscription;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WebhookSender
{
    public function __construct(
        private HttpClientInterface $http,
        private LoggerInterface $logger,
    ) {}

    public function send(WebhookSubscription $abonnement, OutgoingEvent $evenement): void
    {
        $corps = json_encode($evenement->toArray(), JSON_THROW_ON_ERROR);

        $reponse = $this->http->request('POST', $abonnement->getUrl(), [
            'headers' => [
                'Content-Type' => 'application/json',
                // On identifiera notre service et on signera au chapitre 9.
                'User-Agent' => 'PayHub-Webhooks/1.0',
                'X-PayHub-Event' => $evenement->type,
            ],
            'body' => $corps,
            'timeout' => 10,   // on n'attend pas un abonné lent indéfiniment
        ]);

        $code = $reponse->getStatusCode();
        $this->logger->info('Webhook émis', [
            'url' => $abonnement->getUrl(),
            'type' => $evenement->type,
            'statut' => $code,
        ]);

        // Un abonné qui ne répond pas 2xx est considéré en échec (retries au chapitre 8).
        if ($code < 200 || $code >= 300) {
            throw new \RuntimeException("Abonné a répondu $code");
        }
    }
}
```

Remarque la symétrie : on applique à nos abonnés exactement ce que Stripe nous appliquait. On pose un
**timeout** (on ne se laisse pas bloquer par un abonné lent), on lit le **code de statut**, et un non-2xx
est un **échec** à réessayer.

> **Attention** — Ne te laisse **jamais** bloquer par un abonné lent ou injoignable. Sans **timeout**, un
> seul abonné défaillant pourrait figer ton émission pour tous les autres. Le `timeout` est une protection
> de **ta** robustesse, pas une politesse.

## Déclencher l'envoi depuis un événement métier

Reste à **brancher** l'émission sur la vie de ton application. Quand un fait métier se produit (un
paiement reçu, par exemple via le webhook Stripe traité au chapitre 6), on veut **notifier les abonnés
intéressés**.

On crée un service « point d'entrée » qui, pour un événement donné, retrouve les abonnés concernés et
déclenche l'envoi.

```php
<?php
// src/Webhook/WebhookPublisher.php
namespace App\Webhook;

use App\Entity\WebhookSubscription;
use App\Repository\WebhookSubscriptionRepository;

final class WebhookPublisher
{
    public function __construct(
        private WebhookSubscriptionRepository $abonnements,
        private WebhookSender $sender,
    ) {}

    // Publie un événement vers tous les abonnés qui le veulent.
    public function publish(string $type, array $data): void
    {
        $evenement = new OutgoingEvent($type, $data);

        foreach ($this->abonnements->findActive() as $abonnement) {
            if ($abonnement->wantsEvent($type)) {
                $this->sender->send($abonnement, $evenement);
            }
        }
    }
}
```

Et on l'appelle depuis le handler du chapitre 6, une fois le paiement Stripe traité :

```php
// Dans StripeWebhookReceivedHandler::onPaiementReussi(), après mise à jour métier :
$this->publisher->publish('payment.received', [
    'payment_id' => $payload['data']['object']['id'],
    'amount' => $payload['data']['object']['amount'],
    'currency' => $payload['data']['object']['currency'],
]);
```

PayHub boucle ainsi la boucle : il **reçoit** un webhook de Stripe, le traite, puis **émet** son propre
webhook vers les boutiques abonnées. Le hub joue pleinement son rôle de relais.

> **À retenir** — Émettre, c'est : (1) un fait métier survient, (2) on construit un **événement**
> (`type` + `data`), (3) on retrouve les **abonnés** qui veulent ce type, (4) on leur envoie un `POST`
> signé et réessayé. Ce chapitre pose (1) à (3) et l'envoi de base ; le chapitre 8 fiabilise (4).

## Un défaut à corriger dès le prochain chapitre

Notre `publish()` envoie **en synchrone**, dans la foulée du traitement. C'est exactement l'erreur qu'on a
dénoncée côté récepteur (chapitre 4) ! Si un abonné est lent ou injoignable, on bloque, et un échec fait
tout planter. De plus, en cas d'échec, l'événement est **perdu** : aucun *retry*.

On corrige tout ça au chapitre suivant en déportant chaque envoi dans **Messenger** (une livraison = un
message), avec **retries** et ***backoff***. Pour l'instant, l'essentiel est acquis : **PayHub sait
émettre.**

## Résumé

- **Émettre**, c'est l'inverse de recevoir : c'est **toi** qui envoies un `POST` à l'URL d'un **abonné**.
- Un **abonnement** = **URL** + **liste de types d'événements** voulus + **secret** (généré à la création).
- Tu définis le **format** de tes événements ; reprends l'ossature standard `id`/`type`/`created`/`data`
  pour rester familier à tes consommateurs.
- L'envoi se fait avec le **HttpClient** de Symfony, **avec un timeout** ; un non-2xx est un **échec** à
  réessayer.
- On **déclenche** l'émission depuis un **fait métier**, en ne notifiant que les abonnés qui veulent ce
  **type**.
- Envoyer **en synchrone** est un défaut (blocage, perte en cas d'échec) : on déportera l'envoi dans
  Messenger au chapitre 8.

## Exercices

### Exercice 1 — Filtrer les abonnés

Trois abonnés sont enregistrés : A veut `['payment.received']`, B veut
`['payment.received', 'payment.refunded']`, C veut `['payment.refunded']` mais est **inactif**. Pour un
événement `payment.received`, lesquels reçoivent le webhook ? Et pour `payment.refunded` ?

<details>
<summary>Voir le corrigé</summary>

On applique `wantsEvent($type)` : actif **et** type dans la liste.

- **`payment.received`** : A (oui), B (oui), C (non : il ne veut pas ce type, et il est inactif). →
  **A et B**.
- **`payment.refunded`** : A (non), B (oui), C (veut ce type mais **inactif** → non). → **B seulement**.

La leçon : on respecte à la fois le **filtre par type** et l'**état actif**. Un abonné inactif ne reçoit
**rien**, même pour un type qu'il avait demandé.

</details>

### Exercice 2 — Repérer le risque du synchrone

Dix boutiques sont abonnées à `payment.received`. L'une d'elles a un serveur en panne qui ne répond
jamais (le `timeout` de 10 s se déclenche). Avec notre `publish()` synchrone actuel, que se passe-t-il
pour les autres abonnés et pour le traitement du webhook Stripe ?

<details>
<summary>Voir le corrigé</summary>

La boucle `foreach` envoie aux abonnés **l'un après l'autre**. L'abonné en panne fait attendre **10
secondes** (le timeout) puis lève une exception. Conséquences :

- les abonnés **placés après** lui dans la boucle ne sont **pas servis** (l'exception interrompt la
  boucle) ;
- ces 10 secondes s'ajoutent au **traitement du webhook Stripe** : on rallonge le temps de travail du
  worker, et un seul abonné défaillant **pénalise tout le monde** ;
- l'événement vers cet abonné est **perdu** : aucun *retry* prévu.

C'est exactement le défaut annoncé. La solution (chapitre 8) : **une livraison = un message Messenger**,
traité indépendamment, avec **retries**. Ainsi un abonné lent ou en panne n'affecte ni les autres ni le
traitement principal.

</details>

## Quiz

**1.** Qu'est-ce qu'un abonnement dans un système d'émission de webhooks ?
- A. Un paiement mensuel
- B. Une URL d'abonné, une liste de types d'événements voulus, et un secret
- C. Une connexion WebSocket permanente

**2.** Quel format adopter pour les événements qu'on émet ?
- A. Un format propriétaire totalement unique
- B. L'ossature standard `id`/`type`/`created`/`data`, familière aux consommateurs
- C. Du XML obligatoirement

**3.** Pourquoi poser un `timeout` sur l'envoi vers un abonné ?
- A. Pour respecter une norme HTTP
- B. Pour ne pas se laisser bloquer par un abonné lent ou injoignable
- C. Pour chiffrer la requête

**4.** Pourquoi l'envoi synchrone dans une boucle est-il un mauvais choix ?
- A. Un abonné lent ou en panne bloque les autres et fait perdre l'événement
- B. Le HttpClient ne fonctionne pas en synchrone
- C. C'est interdit par Symfony

<details>
<summary>Voir les réponses</summary>

1. **B** — URL + types voulus + secret : le trio de base d'un abonnement.
2. **B** — Reprendre l'ossature standard facilite l'intégration de tes consommateurs.
3. **B** — Le timeout protège ta robustesse face à un abonné défaillant.
4. **A** — Blocage en chaîne et perte d'événement : d'où le passage à Messenger au chapitre 8.

</details>

## Projet fil rouge

PayHub sait maintenant **émettre**. Tu as :

- créé l'entité `WebhookSubscription` (URL + types voulus + **secret** généré aléatoirement + état actif) ;
- défini le **format** de tes événements et l'objet `OutgoingEvent` ;
- écrit `WebhookSender` (envoi `POST` via **HttpClient**, avec **timeout**) et `WebhookPublisher`
  (filtrage des abonnés par type) ;
- **branché** l'émission sur le traitement du paiement Stripe : recevoir → traiter → **émettre**.

Mais l'envoi est encore **fragile et synchrone**. Au chapitre suivant, on le rend **fiable** : chaque
livraison devient un message Messenger, avec **retries**, ***backoff* exponentiel** et **dead-letter**
pour les échecs définitifs.

---

[← Chapitre précédent](06-fiabilite-recepteur.md) · [Sommaire](README.md) · [Chapitre suivant →](08-livraison-fiable.md)
