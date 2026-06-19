# Anatomie d'une requête webhook

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-recevoir-premier-webhook.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- décomposer une requête webhook : **méthode**, **URL**, **en-têtes**, **corps** ;
- lire un *payload* JSON d'événement et reconnaître ses champs récurrents (`id`, `type`, `data`) ;
- comprendre le rôle des en-têtes spéciaux (`Content-Type`, signature, identifiant d'événement) ;
- savoir ce que l'émetteur attend en **réponse** ;
- distinguer l'**endpoint** (ton URL) de l'**événement** (ce qui s'est produit).

## Un webhook, c'est « juste » une requête HTTP POST

Pas de magie : un webhook est une **requête HTTP**, exactement comme celles que ton navigateur envoie,
mais émise par le serveur d'un tiers vers le tien. Voici à quoi ressemble, en vrai, un webhook envoyé par
Stripe quand un paiement réussit :

```text
POST /webhook/stripe HTTP/1.1
Host: payhub.example.com
Content-Type: application/json
Stripe-Signature: t=1718800000,v1=5257a869e7 d... (tronqué)
User-Agent: Stripe/1.0 (+https://stripe.com/docs/webhooks)
Content-Length: 412

{
  "id": "evt_1P9xY2abc123",
  "object": "event",
  "api_version": "2024-06-20",
  "created": 1718800000,
  "type": "payment_intent.succeeded",
  "data": {
    "object": {
      "id": "pi_3P9xY1xyz",
      "object": "payment_intent",
      "amount": 4900,
      "currency": "eur",
      "status": "succeeded"
    }
  }
}
```

Décortiquons cette requête morceau par morceau : c'est **tout** ce que ton récepteur aura à lire.

## La ligne de requête : méthode et URL

```text
POST /webhook/stripe HTTP/1.1
```

- **La méthode est presque toujours `POST`.** Un webhook **transporte des données** (l'événement) vers
  ton serveur ; `POST` est la méthode HTTP faite pour envoyer un corps de requête. Tu ne verras quasiment
  jamais un webhook en `GET`.
- **L'URL (`/webhook/stripe`) est l'*endpoint*** : le « point de réception » que **tu** as choisi et
  communiqué au fournisseur. C'est une URL de **ton** application. Le chemin est libre — `/webhook/stripe`,
  `/callbacks/payment`, peu importe — tant que tu donnes la bonne au fournisseur.

> **À retenir** — L'***endpoint*** (« point d'arrivée ») est l'URL que ton application expose pour
> recevoir les webhooks. Tu la crées, puis tu la déclares chez le fournisseur. Le fournisseur, lui, ne
> connaît que cette URL.

## Les en-têtes : les métadonnées de la requête

Les *headers* (« en-têtes ») accompagnent la requête avec des informations **sur** elle. Pour un webhook,
quelques-uns sont cruciaux.

### `Content-Type`

```text
Content-Type: application/json
```

Il décrit le **format du corps**. La grande majorité des webhooks envoient du **JSON**
(`application/json`). Certains fournisseurs plus anciens (ou certaines intégrations type GitHub) peuvent
utiliser `application/x-www-form-urlencoded` (le format d'un formulaire HTML). Tu dois **lire le
`Content-Type`** pour savoir comment décoder le corps, et ne jamais le supposer aveuglément.

### L'en-tête de signature

```text
Stripe-Signature: t=1718800000,v1=5257a869e7d...
```

C'est l'en-tête le plus important pour la **sécurité**. Il contient une **signature cryptographique** qui
prouve que la requête vient **bien** du fournisseur et n'a **pas été modifiée** en route. Chaque
fournisseur le nomme à sa façon : `Stripe-Signature`, `X-Hub-Signature-256` (GitHub),
`X-Webhook-Signature`… On apprendra à la vérifier au [chapitre 5](05-securiser-reception.md). Pour
l'instant, retiens qu'il **existe** et qu'il faudra le contrôler.

### L'identifiant d'événement

Beaucoup de fournisseurs ajoutent un identifiant unique de l'événement, soit dans un en-tête
(`X-Event-Id`, `Webhook-Id`…), soit dans le corps (le champ `id` du JSON ci-dessus : `evt_1P9xY2abc123`).
Cet identifiant est la **clé de la déduplication** : si le même webhook arrive deux fois, son `id` est
identique. On s'en servira au [chapitre 6](06-fiabilite-recepteur.md).

### Autres en-têtes utiles

- `User-Agent` : identifie l'émetteur (ex. `Stripe/1.0`). Pratique pour les logs, **insuffisant** pour la
  sécurité (il se falsifie trivialement).
- `Content-Length` : la taille du corps en octets.

## Le corps : le *payload* de l'événement

Le **corps** (*body*) de la requête contient le ***payload*** (« charge utile ») : les données de
l'événement, généralement en JSON. Au-delà des spécificités de chaque fournisseur, on retrouve presque
toujours la même ossature :

```json
{
  "id": "evt_1P9xY2abc123",
  "type": "payment_intent.succeeded",
  "created": 1718800000,
  "data": { "object": { "...": "les détails de l'objet concerné" } }
}
```

- **`id`** : l'identifiant **unique** de cet événement. Deux livraisons du même événement portent le même
  `id`. C'est ta clé anti-doublon.
- **`type`** : la **nature** de l'événement, presque toujours sous la forme `ressource.action`
  (`payment_intent.succeeded`, `invoice.payment_failed`, `charge.refunded`…). C'est lui qui te dit **quoi
  faire**. Un même endpoint reçoit souvent **plusieurs types** d'événements ; tu aiguilles selon ce champ.
- **`created`** : l'horodatage (*timestamp*) de l'événement, souvent en secondes Unix. Utile pour
  l'ordonnancement et la protection anti-rejeu (chapitre 5).
- **`data`** : la **charge métier** — l'objet concerné (le paiement, la facture, le client…) avec ses
  détails.

> **Attention** — Le format **varie d'un fournisseur à l'autre**. Stripe imbrique l'objet dans
> `data.object` ; GitHub met tout à la racine et indique le type d'événement dans l'en-tête
> `X-GitHub-Event` ; un autre nommera ses champs autrement. **Lis toujours la documentation du
> fournisseur** : il n'existe pas (encore) de format universel imposé. On parlera de la tentative de
> standardisation (CloudEvents) au [chapitre 12](12-conclusion.md).

### Lire le corps brut compte

Un point qui semble anodin mais qui sera **capital** au chapitre 5 : pour vérifier la signature, il te
faudra le corps **exactement tel qu'il a été envoyé** — le ***raw body*** (« corps brut »), octet pour
octet. Si tu décodes le JSON puis le ré-encodes, l'ordre des clés ou les espaces peuvent changer, et la
signature ne correspondra plus. Garde en tête cette distinction :

- le **corps brut** : la chaîne de caractères reçue telle quelle ;
- le **corps décodé** : l'objet PHP obtenu après `json_decode`.

On vérifie la signature sur le **brut**, on lit les données sur le **décodé**.

## La réponse : ce que l'émetteur attend de toi

Une requête HTTP appelle une **réponse**. Côté webhook, l'émetteur attend une réponse **rapide** avec un
**code de statut de succès** (généralement **2xx**, typiquement `200 OK`).

```text
HTTP/1.1 200 OK
Content-Type: text/plain

ok
```

Ce que tu renvoies dans le **corps** de la réponse importe peu (souvent ignoré) ; c'est le **code de
statut** qui parle :

- **2xx** (200, 202, 204) : « bien reçu ». L'émetteur considère la livraison **réussie** et passe à la
  suite.
- **4xx / 5xx ou pas de réponse** : « échec ». L'émetteur considère que **tu n'as pas reçu** et va
  **réessayer** plus tard (on étudiera les *retries* aux chapitres 4 et 8).

Cette mécanique a une conséquence majeure qu'on creusera au chapitre 4 : si tu réponds **lentement** (le
temps de tout traiter), tu risques le **délai d'attente** (*timeout*) de l'émetteur, qui croira à un échec
et **renverra** le même événement. D'où la règle d'or à venir : **répondre vite, traiter ensuite**.

> **À retenir** — L'émetteur lit surtout le **code de statut** de ta réponse. **2xx = reçu**, le reste =
> à réessayer. Une réponse lente est interprétée comme un échec.

## Endpoint, événement, livraison : le vocabulaire

Fixons trois mots qu'on emploiera tout le long de la formation :

- **Endpoint** : l'URL de ton application qui reçoit les webhooks (côté récepteur), ou l'URL d'un abonné
  vers qui tu envoies (côté émetteur, à partir du chapitre 7).
- **Événement** (*event*) : la chose qui s'est produite (un paiement réussi). Il a un `id` et un `type`.
- **Livraison** (*delivery*) : **une tentative d'envoi** d'un événement vers un endpoint. Un même
  événement peut donner lieu à **plusieurs livraisons** (si la première échoue et qu'on réessaie). C'est
  une distinction qu'on retrouvera au cœur des chapitres sur la fiabilité.

```text
Événement evt_123  ──► Livraison #1 (échec, timeout)
                   ──► Livraison #2 (échec, 500)
                   ──► Livraison #3 (succès, 200)   ← même événement, 3 livraisons
```

## Résumé

- Un webhook est une **requête HTTP `POST`** vers ton ***endpoint*** (une URL de ton application).
- Le **`Content-Type`** indique le format du corps (le plus souvent `application/json`) : lis-le, ne le
  suppose pas.
- Un en-tête de **signature** (nom variable selon le fournisseur) permettra de vérifier l'authenticité
  (chapitre 5).
- Le ***payload*** JSON suit souvent l'ossature `id` / `type` / `created` / `data`. Le **`type`** dit quoi
  faire ; l'**`id`** sert à dédupliquer.
- Pour vérifier la signature, il faudra le **corps brut** (octet pour octet), distinct du corps décodé.
- L'émetteur attend une réponse **2xx rapide** ; sinon il considère un **échec** et **réessaie**.
- Vocabulaire clé : **endpoint** (l'URL), **événement** (ce qui s'est produit), **livraison** (une
  tentative d'envoi).

## Exercices

### Exercice 1 — Lire un payload inconnu

On te donne ce webhook reçu d'un service de signature électronique :

```json
{
  "id": "ev_88af20",
  "event": "document.signed",
  "occurred_at": "2026-06-19T10:32:00Z",
  "payload": { "document_id": "doc_42", "signer": "marie@exemple.fr" }
}
```

Repère : (a) l'identifiant d'événement à utiliser pour la déduplication ; (b) le champ qui dit **quoi
faire** ; (c) où se trouvent les données métier. Note les différences avec l'ossature « Stripe » vue
dans le chapitre.

<details>
<summary>Voir le corrigé</summary>

- **(a) Déduplication** : `id` = `ev_88af20`. C'est l'identifiant unique de l'événement.
- **(b) Quoi faire** : le champ `event` (`document.signed`). Ici il s'appelle `event` et non `type` —
  d'où l'importance de lire la doc du fournisseur.
- **(c) Données métier** : sous `payload` (et non `data.object` comme chez Stripe). On y trouve
  `document_id` et `signer`.

Différences notables : le champ « type » s'appelle `event`, l'horodatage est une **date ISO 8601**
(texte) et non un *timestamp* Unix, et les données sont sous `payload`. Le **concept** est le même, les
**noms** changent : toujours vérifier la doc.

</details>

### Exercice 2 — Diagnostiquer une réponse

Un fournisseur t'indique dans son tableau de bord que tes webhooks sont « en échec » et qu'il les
réessaie. Tes logs montrent que ton endpoint répond bien, mais après **45 secondes**, avec un code `200`.
Quel est probablement le problème, et de quel côté se situe-t-il ?

<details>
<summary>Voir le corrigé</summary>

Le problème est **chez toi** : ta réponse `200` arrive **trop tard**. Les fournisseurs imposent un
**délai d'attente** court (souvent 5 à 30 secondes). Au-delà, ils coupent la connexion et considèrent la
livraison comme **échouée**, même si ton traitement finit par réussir — d'où les *retries* et le statut
« en échec » affiché.

La cause typique : tu fais **tout le traitement** (appels externes, calculs, e-mails) **avant** de
répondre. La solution, vue au chapitre 4 : **accuser réception immédiatement** (`200`/`202`) puis traiter
**en arrière-plan**.

</details>

## Quiz

**1.** Quelle méthode HTTP utilise presque toujours un webhook ?
- A. `GET`
- B. `POST`
- C. `DELETE`

**2.** Dans un payload de webhook, à quoi sert le champ `type` (ou `event`) ?
- A. À indiquer la taille du corps
- B. À dire quelle action déclencher de ton côté
- C. À chiffrer le message

**3.** Que regarde principalement l'émetteur dans ta réponse ?
- A. Le corps de la réponse, mot pour mot
- B. Le code de statut HTTP (2xx = reçu)
- C. L'en-tête `User-Agent`

**4.** Pourquoi conserver le **corps brut** de la requête ?
- A. Pour l'afficher plus joliment
- B. Parce que la vérification de signature se fait sur le corps exact reçu, octet pour octet
- C. Parce que `json_decode` ne fonctionne pas sur les webhooks

<details>
<summary>Voir les réponses</summary>

1. **B** — Un webhook transporte des données : c'est `POST`.
2. **B** — Le `type` indique la nature de l'événement et donc le traitement à appliquer.
3. **B** — C'est le **code de statut** qui dit « reçu » (2xx) ou « à réessayer ».
4. **B** — La signature est calculée sur le corps exact ; le re-sérialiser invaliderait la vérification.

</details>

## Projet fil rouge

Pas de code cette fois, mais une décision de conception : PayHub recevra les webhooks Stripe sur
l'endpoint **`/webhook/stripe`** (méthode `POST`, corps JSON). On sait désormais ce qu'il devra lire :

- le **corps brut** (pour la future vérification de signature) ;
- l'en-tête **`Stripe-Signature`** (chapitre 5) ;
- dans le JSON décodé, les champs **`id`** (déduplication), **`type`** (aiguillage) et **`data`**
  (données du paiement).

Au chapitre suivant, on écrit enfin le contrôleur qui reçoit ce `POST`, et on le **teste en local** en
exposant PayHub sur Internet pour recevoir de vrais événements Stripe.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-recevoir-premier-webhook.md)
