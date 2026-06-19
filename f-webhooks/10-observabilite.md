# Observabilité : journal, rejeu et monitoring

[← Chapitre précédent](09-signer-ses-webhooks.md) · [Sommaire](README.md) · [Chapitre suivant →](11-concevoir-une-api.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer pourquoi un système de webhooks est **invisible** sans observabilité et ce qu'il faut tracer ;
- enregistrer un **journal des livraisons** (statut, code, tentatives, réponse) ;
- offrir un **rejeu manuel** d'une livraison, côté émetteur comme récepteur ;
- mettre en place des **logs structurés** et un **endpoint de santé** pour le monitoring ;
- définir les **alertes** et **métriques** qui comptent (taux d'échec, latence, file qui s'allonge).

## Pourquoi l'observabilité est vitale ici

Un webhook arrive (ou part) **tout seul**, sans utilisateur derrière son écran pour signaler un souci.
Quand un abonné dit « je n'ai jamais reçu le webhook de paiement d'hier », tu dois pouvoir **répondre** :
l'as-tu envoyé ? Quand ? Qu'a-t-il répondu ? Combien de tentatives ? Sans traces, tu es **aveugle**, et le
débogage devient un cauchemar (« peut-être que… »).

Les fournisseurs sérieux l'ont compris : Stripe, GitHub, Shopify offrent tous un **tableau des
livraisons** où l'on voit chaque tentative, sa réponse, et un bouton **« redeliver »**. On va construire
l'équivalent pour PayHub.

> **À retenir** — Dans un système de webhooks, l'observabilité n'est pas un luxe : c'est la **seule
> fenêtre** sur des échanges qui se font sans témoin. « Si ce n'est pas tracé, ça n'a jamais eu lieu » —
> pour toi comme pour ton support client.

## Le journal des livraisons

L'outil central est un **journal** : une ligne par **livraison** (rappel du vocabulaire du chapitre 2 :
un événement peut donner lieu à plusieurs livraisons). On modélise ça côté **émetteur**.

```php
<?php
// src/Entity/WebhookDelivery.php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WebhookDelivery
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $eventId;          // l'id de l'événement émis

    #[ORM\Column(length: 64)]
    private string $eventType;        // ex. payment.received

    #[ORM\ManyToOne]
    private WebhookSubscription $subscription;  // l'abonné cible

    #[ORM\Column(length: 20)]
    private string $status = 'pending';  // pending | success | failed

    #[ORM\Column(nullable: true)]
    private ?int $responseCode = null;   // code HTTP renvoyé par l'abonné

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseBody = null; // début de la réponse (tronquée)

    #[ORM\Column]
    private int $attempts = 0;           // nombre de tentatives effectuées

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    // ... constructeur + getters/setters
}
```

On enregistre une ligne au moment de l'envoi, puis on la **met à jour** à chaque tentative dans
`WebhookSender` :

```php
// Dans WebhookSender::send(), après l'appel HTTP :
$delivery->setAttempts($delivery->getAttempts() + 1);
$delivery->setResponseCode($code);
// On tronque la réponse : pas la peine de stocker des mégaoctets.
$delivery->setResponseBody(mb_substr($reponse->getContent(throw: false), 0, 2000));

if ($code >= 200 && $code < 300) {
    $delivery->setStatus('success');
    $delivery->setDeliveredAt(new \DateTimeImmutable());
} else {
    $delivery->setStatus('failed');
}
$this->em->flush();
```

Ce qu'on enregistre, et **pourquoi** :

- **`status` / `responseCode`** : pour répondre « envoyé ? réussi ? » en un coup d'œil.
- **`attempts`** : pour voir si on a réessayé (lien avec le chapitre 8).
- **`responseBody` (tronquée)** : souvent, l'abonné explique son refus dans le corps (« signature
  invalide », « champ X manquant ») — précieux pour le support.
- **`eventId`** : pour retrouver **toutes les livraisons** d'un même événement.

> **Attention** — Ne logue **jamais** de secret ni de donnée sensible en clair dans le journal. Tronque
> les corps, **masque** les éventuels jetons, et applique une **rétention** (purge des vieilles
> livraisons) : un journal de webhooks grossit vite et peut contenir des données personnelles soumises au
> RGPD.

## Le rejeu manuel

Le journal devient vraiment utile couplé à un **rejeu** : ré-émettre une livraison à la demande, sans
attendre un nouvel événement métier. C'est le bouton « redeliver » de Stripe.

```php
<?php
// src/Controller/Admin/DeliveryController.php (extrait)
#[Route('/admin/deliveries/{id}/replay', name: 'admin_delivery_replay', methods: ['POST'])]
public function replay(WebhookDelivery $delivery, MessageBusInterface $bus): Response
{
    // On redéclenche une livraison à partir des données enregistrées.
    $bus->dispatch(new DeliverWebhook(
        subscriptionId: $delivery->getSubscription()->getId(),
        eventPayload: $delivery->getPayloadSnapshot(),  // on a stocké le payload émis
    ));

    $this->addFlash('success', 'Livraison rejouée.');

    return $this->redirectToRoute('admin_deliveries');
}
```

Pour que le rejeu renvoie **le même événement** (même `id`, donc déduplicable par l'abonné), on stocke le
**payload émis** dans la livraison (`payloadSnapshot`). Le rejeu réutilise ce snapshot : l'abonné reçoit un
événement à l'`id` identique et peut le **dédupliquer** (chapitre 6) s'il l'avait déjà traité. La boucle
récepteur/émetteur se complète : ton rejeu est sûr **parce que** tes abonnés savent dédupliquer.

### Côté récepteur aussi

Le rejeu vaut aussi quand PayHub est **récepteur**. Stripe offre un rejeu depuis son tableau de bord, et
la **Stripe CLI** le permet en local :

```bash
# Rejouer un événement Stripe déjà reçu, vers ton endpoint local :
$ stripe events resend evt_1P9xY2abc123
```

Comme PayHub déduplique par `id` (chapitre 6), rejouer un événement déjà traité est **sans danger** : il
sera ignoré. L'idempotence n'est pas qu'une protection contre les bugs : c'est ce qui rend le **rejeu**
exploitable sans crainte.

> **À retenir** — Le **rejeu** (réémettre/retraiter une livraison à la demande) est l'outil de réparation
> n°1 : un abonné rate des webhooks pendant une panne, tu les **rejoues** une fois qu'il est revenu. Il
> n'est sûr **que** parce que la déduplication (chapitre 6) rend le retraitement inoffensif.

## Logs structurés

Des logs en texte libre sont durs à exploiter. Préfère des **logs structurés** (clé/valeur), qu'on peut
**filtrer** et **agréger**. Symfony (via Monolog) le permet directement en passant un **contexte** :

```php
// Au lieu de : $logger->info("Webhook evt_123 envoyé à shop_12 : 200")
$logger->info('webhook.delivered', [
    'event_id' => $evenement['id'],
    'event_type' => $evenement['type'],
    'subscription_id' => $abonnement->getId(),
    'status_code' => $code,
    'attempt' => $delivery->getAttempts(),
    'duration_ms' => $dureeMs,
]);
```

Avec ce format, tu peux ensuite **chercher** « toutes les livraisons vers `subscription_id=12` en échec »
ou « la latence moyenne des envois ». En production, ces logs partent vers un agrégateur (ELK, Grafana
Loki, Datadog…) où tu construis des tableaux de bord et des recherches.

> **Astuce** — Attache un **identifiant de corrélation** (le `event_id`, ou un `delivery_id`) à **tous**
> les logs liés à une même livraison. Tu pourras alors reconstituer **toute l'histoire** d'un webhook —
> réception, mise en file, tentatives, réponse — en filtrant sur cet identifiant.

## Monitoring : endpoint de santé, métriques et alertes

### Endpoint de santé

Un *health check* permet à un outil externe (ou à un orchestrateur) de vérifier que ton système tourne :

```php
#[Route('/health/webhooks', name: 'health_webhooks', methods: ['GET'])]
public function health(WebhookDeliveryRepository $repo): JsonResponse
{
    // Combien de livraisons en attente depuis trop longtemps ? (file qui bouchonne)
    $enRetard = $repo->countPendingOlderThan(new \DateTimeImmutable('-5 minutes'));

    return new JsonResponse([
        'status' => $enRetard === 0 ? 'ok' : 'degraded',
        'pending_overdue' => $enRetard,
    ], $enRetard === 0 ? 200 : 503);
}
```

### Les métriques qui comptent

Surveille en priorité :

- **Taux d'échec de livraison** (échecs / total). Une hausse soudaine = un abonné majeur tombé, ou un bug
  de ton côté.
- **Latence d'envoi** (temps de réponse des abonnés). Des abonnés lents allongent la file.
- **Profondeur de la file** (messages en attente dans `webhook_delivery`). Si elle **croît sans
  redescendre**, tes workers ne suivent pas → il faut en ajouter, ou un abonné bloque.
- **Âge du plus vieux message en attente** : indicateur direct d'un bouchon.
- **Taille de la *dead-letter*** : des échecs définitifs s'accumulent → un endpoint à désactiver/alerter.

### Les alertes

Une métrique sans alerte ne sert qu'**après** l'incident. Configure des alertes sur les seuils critiques :

- taux d'échec global > X % sur 5 min ;
- file qui dépasse N messages, ou plus vieux message > N minutes ;
- *dead-letter* qui grossit.

> **À retenir** — Trois piliers de monitoring : un **endpoint de santé** (« est-ce que ça tourne ? »), des
> **métriques** (taux d'échec, latence, profondeur de file), et des **alertes** sur les seuils critiques
> pour être prévenu **avant** que les clients ne le soient.

## Résumé

- Un système de webhooks est **invisible** sans observabilité : c'est ta seule fenêtre sur des échanges
  sans témoin.
- Le **journal des livraisons** (statut, code, tentatives, réponse tronquée, payload) répond à « envoyé ?
  quand ? qu'a-t-il répondu ? ».
- Le **rejeu** (réémettre/retraiter à la demande) est l'outil de réparation n°1 ; il est **sûr** grâce à
  la **déduplication** (chapitre 6).
- Les **logs structurés** (clé/valeur) + un **identifiant de corrélation** permettent de filtrer, agréger
  et reconstituer l'histoire d'un webhook.
- Le **monitoring** repose sur un **endpoint de santé**, des **métriques** (taux d'échec, latence,
  profondeur de file, dead-letter) et des **alertes**.
- Attention au **RGPD** et aux **secrets** : tronquer, masquer, et appliquer une **rétention**.

## Exercices

### Exercice 1 — Que tracer pour ce ticket de support ?

Un client te contacte : « le webhook du paiement de la commande #553 n'est jamais arrivé chez nous hier
vers 14 h ». Quelles informations de ton journal des livraisons consultes-tu, et dans quel ordre, pour
diagnostiquer ?

<details>
<summary>Voir le corrigé</summary>

**Démarche** : remonter la piste de la livraison.

1. Retrouver l'**événement** lié à la commande #553 (par `eventId` ou via les données métier) et ses
   **livraisons** vers cet abonné (`subscription_id` du client).
2. Regarder le **`status`** : `success`, `failed`, ou `pending` (jamais parti) ?
   - `success` avec `responseCode` 2xx → **c'est arrivé** ; le problème est chez le client (il l'a peut-être
     rejeté ou perdu en interne). Lui montrer le code/heure de la réponse **qu'il** a renvoyée.
   - `failed` → regarder `responseCode` et `responseBody` : son serveur a refusé (signature ? 500 ?). Voir
     le nombre d'`attempts` et proposer un **rejeu**.
   - `pending` ou absent → l'événement n'a peut-être pas été **publié** (vérifier en amont, côté réception
     Stripe) ou les **workers** étaient à l'arrêt.
3. Selon le diagnostic : **rejouer** la livraison, corriger un secret, ou remonter un incident interne.

La leçon : un bon journal transforme un « peut-être » en réponse **factuelle** et chiffrée.

</details>

### Exercice 2 — Choisir une alerte utile

Tes workers traitent normalement la file de livraison en quelques secondes. Tu veux être prévenu **tôt**
si quelque chose coince (workers arrêtés, abonné majeur qui fait timeout en masse). Quelle métrique
surveiller et quel seuil d'alerte choisir ? Pourquoi pas simplement « alerter à chaque échec » ?

<details>
<summary>Voir le corrigé</summary>

**Métrique** : l'**âge du plus vieux message en attente** dans la file (ou la **profondeur de file**). Si,
en temps normal, un message est traité en quelques secondes, alerter quand le **plus vieux message en
attente dépasse, par exemple, 2 minutes** capte aussi bien un arrêt des workers qu'un bouchon dû à un
abonné lent. C'est ce que fait l'endpoint de santé (`countPendingOlderThan`).

**Pourquoi pas « alerter à chaque échec »** : les échecs **isolés sont normaux** (un abonné redémarre, un
timeout ponctuel) — ils sont gérés par les *retries* (chapitre 8). Alerter sur **chacun** noierait le
signal sous le bruit (« fatigue d'alerte »), et on finirait par tout ignorer. On alerte sur une
**tendance anormale** (taux d'échec élevé sur une fenêtre, file qui ne se vide plus), pas sur un échec
unitaire que le système sait déjà rattraper.

</details>

## Quiz

**1.** Pourquoi l'observabilité est-elle particulièrement importante pour les webhooks ?
- A. Parce que les webhooks sont lents
- B. Parce qu'ils s'échangent sans utilisateur témoin : sans traces, le débogage est aveugle
- C. Parce que Symfony l'impose

**2.** Qu'enregistre-t-on dans un journal de livraisons ?
- A. Uniquement la date
- B. Statut, code de réponse, nombre de tentatives, réponse (tronquée), payload
- C. Le secret de signature en clair

**3.** Pourquoi le rejeu d'un webhook est-il sans danger pour l'abonné ?
- A. Parce qu'il efface l'ancien
- B. Parce que la déduplication par `id` rend le retraitement inoffensif
- C. Parce qu'on change l'`id` à chaque rejeu

**4.** Quelle métrique signale le mieux un bouchon de livraison ?
- A. Le nombre total d'événements depuis le lancement
- B. L'âge du plus vieux message en attente / la profondeur de la file
- C. La version de PHP

<details>
<summary>Voir les réponses</summary>

1. **B** — Sans témoin humain, les traces sont la seule visibilité.
2. **B** — Tout ce qui permet de diagnostiquer une livraison ; jamais de secret en clair.
3. **B** — Un même `id` rejoué est dédupliqué côté abonné : aucun double effet.
4. **B** — Une file qui s'allonge ou un message vieillissant révèle un blocage ; pas un compteur cumulatif.

</details>

## Projet fil rouge

PayHub est désormais **observable**. Tu as :

- créé l'entité `WebhookDelivery` (statut, code, tentatives, réponse tronquée, payload) et l'alimentes à
  chaque envoi ;
- ajouté un **rejeu manuel** (`/admin/deliveries/{id}/replay`), sûr grâce à la déduplication ;
- mis en place des **logs structurés** avec identifiant de corrélation ;
- exposé un **endpoint de santé** et identifié les **métriques/alertes** clés (taux d'échec, profondeur de
  file, dead-letter).

PayHub fonctionne, est fiable, sécurisé et observable. Reste à **bien concevoir** l'API de webhooks pour
qu'elle soit agréable et durable côté consommateurs. C'est l'objet du chapitre suivant : types
d'événements, **versionnage** et **documentation**.

---

[← Chapitre précédent](09-signer-ses-webhooks.md) · [Sommaire](README.md) · [Chapitre suivant →](11-concevoir-une-api.md)
