# Recevoir son premier webhook

[← Chapitre précédent](02-anatomie-requete.md) · [Sommaire](README.md) · [Chapitre suivant →](04-bien-repondre-async.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer un **contrôleur Symfony** qui reçoit une requête webhook en `POST` ;
- lire le **corps brut** et le **décoder** en JSON proprement ;
- renvoyer une **réponse** correcte à l'émetteur ;
- **tester** ton endpoint en local avec `curl` ;
- **exposer** ton serveur de développement sur Internet pour recevoir de **vrais** webhooks (Stripe CLI,
  smee.io, ngrok).

## Le contrôleur de réception

Un endpoint de webhook, dans Symfony, c'est un **contrôleur** comme un autre, restreint à la méthode
`POST`. Crée-le avec le Maker :

```bash
$ php bin/console make:controller WebhookController
```

Remplace le contenu généré par un premier récepteur Stripe :

```php
<?php
// src/Controller/WebhookController.php
namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    #[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function stripe(Request $request, LoggerInterface $logger): Response
    {
        // 1. Récupérer le corps BRUT (la chaîne reçue telle quelle).
        $payloadBrut = $request->getContent();

        // 2. Décoder le JSON. associative = true → on obtient un tableau PHP.
        $evenement = json_decode($payloadBrut, associative: true);

        // 3. Si le JSON est invalide, on le signale (400 = mauvaise requête).
        if (!is_array($evenement) || !isset($evenement['type'], $evenement['id'])) {
            $logger->warning('Webhook Stripe : payload illisible.');

            return new JsonResponse(['error' => 'payload invalide'], Response::HTTP_BAD_REQUEST);
        }

        // 4. On trace ce qu'on a reçu (utile pour debugger).
        $logger->info('Webhook Stripe reçu', [
            'id' => $evenement['id'],
            'type' => $evenement['type'],
        ]);

        // 5. On accuse réception. 200 = "bien reçu" pour l'émetteur.
        return new JsonResponse(['received' => true]);
    }
}
```

Quelques points méritent qu'on s'y arrête.

- **`$request->getContent()`** renvoie le **corps brut**. C'est lui qu'on lit, et **pas**
  `$request->request->all()` (qui ne fonctionne que pour les formulaires `x-www-form-urlencoded`, pas pour
  du JSON). On garde aussi `$payloadBrut` sous le coude : il servira à vérifier la signature au chapitre 5.
- **`json_decode(..., associative: true)`** transforme le JSON en tableau PHP. On vérifie ensuite qu'on a
  bien un tableau **et** les champs attendus (`id`, `type`).
- **Le code de statut** : `400` si le payload est inexploitable, `200` sinon. On a vu au chapitre 2
  pourquoi c'est le code qui compte pour l'émetteur.
- **Le log** : indispensable. Un webhook arrive « tout seul », sans utilisateur derrière son écran ; sans
  trace, tu es aveugle.

> **Attention** — N'utilise **jamais** `$request->request->get(...)` pour lire un webhook JSON : cette
> méthode lit les champs de formulaire et te renverra du vide. Pour du JSON, c'est `getContent()` puis
> `json_decode()`.

### Pourquoi ne pas tout traiter ici tout de suite ?

Tu pourrais être tenté d'ajouter, juste avant le `return`, tout le traitement métier (mettre à jour la
commande, envoyer un e-mail de confirmation…). **Résiste.** On a vu au chapitre 2 que l'émetteur impose un
**délai d'attente** : si ton traitement est lent, il croit à un échec et **rejoue** l'événement. Pour
l'instant, on se contente de **recevoir** et **accuser réception**. Le traitement asynchrone propre est le
sujet du [chapitre 4](04-bien-repondre-async.md).

## Tester en local avec curl

Avant même de brancher Stripe, tu peux vérifier que ton endpoint répond. Démarre le serveur :

```bash
$ symfony server:start -d
```

Puis simule un webhook avec `curl`, en envoyant un corps JSON en `POST` :

```bash
$ curl -i -X POST https://127.0.0.1:8000/webhook/stripe \
    -H "Content-Type: application/json" \
    -d '{"id":"evt_test_1","type":"payment_intent.succeeded","data":{"object":{"amount":4900}}}'
```

Décortiquons les options : `-i` affiche les en-têtes de la réponse, `-X POST` force la méthode,
`-H` ajoute un en-tête, `-d` fournit le corps. La réponse attendue :

```text
HTTP/2 200
content-type: application/json

{"received":true}
```

Et dans les logs du serveur (`var/log/dev.log` ou la sortie de `symfony server:log`), tu dois voir ta
ligne `Webhook Stripe reçu` avec l'`id` et le `type`. Essaie aussi un corps invalide pour vérifier le
`400` :

```bash
$ curl -i -X POST https://127.0.0.1:8000/webhook/stripe \
    -H "Content-Type: application/json" -d 'ceci-nest-pas-du-json'
# Sortie attendue : HTTP/2 400 ... {"error":"payload invalide"}
```

> **Astuce** — `curl` est ton meilleur ami pour tester un endpoint de webhook : tu reproduis n'importe
> quelle requête à volonté, sans dépendre du fournisseur. Garde quelques commandes `curl` types sous la
> main pendant tout le développement.

## Le problème du local : ton serveur n'est pas sur Internet

Ton serveur tourne sur `127.0.0.1:8000`, une adresse **locale**, invisible depuis Internet. Or Stripe,
lui, vit sur Internet : il ne peut pas atteindre `127.0.0.1`. Comment recevoir de **vrais** webhooks
pendant le développement ?

Trois approches, de la plus simple à la plus générale.

### Option A — Stripe CLI (le plus simple pour Stripe)

Stripe fournit un outil en ligne de commande qui **écoute** les événements de ton compte et les
**réinjecte** dans ton serveur local, sans que tu aies besoin d'une URL publique.

```bash
# Installe la Stripe CLI (voir stripe.com/docs/stripe-cli), puis connecte-toi :
$ stripe login

# Redirige les événements Stripe vers ton endpoint local :
$ stripe listen --forward-to https://127.0.0.1:8000/webhook/stripe
```

La commande affiche un **secret de signature** (`whsec_...`) : note-le, il servira au chapitre 5. Garde ce
terminal ouvert : il faut faire « le pont » tant que tu développes. Dans un autre terminal, déclenche un
faux événement de test :

```bash
$ stripe trigger payment_intent.succeeded
```

Stripe génère un vrai événement de test, la CLI le transmet à ton serveur local, et tu vois ton log
s'afficher. C'est le circuit complet, en vrai.

### Option B — smee.io (gratuit, sans compte, agnostique)

[smee.io](https://smee.io) crée une **URL publique** qui transfère tout ce qu'elle reçoit vers ton serveur
local. Pratique pour **n'importe quel** fournisseur (pas seulement Stripe).

```bash
# Va sur smee.io, clique "Start a new channel" → tu obtiens une URL du type
#   https://smee.io/AbC123xyz
# Installe le client, puis fais le pont vers ton endpoint local :
$ npx smee-client --url https://smee.io/AbC123xyz \
    --target https://127.0.0.1:8000/webhook/stripe
```

Tu déclares ensuite **`https://smee.io/AbC123xyz`** comme URL d'endpoint dans le tableau de bord du
fournisseur. Chaque webhook qu'il enverra sera relayé jusqu'à ta machine.

### Option C — ngrok (un tunnel public vers ton port local)

[ngrok](https://ngrok.com) ouvre un **tunnel** : il te donne une URL publique (`https://xxxx.ngrok.app`)
qui pointe directement vers ton port local.

```bash
$ ngrok http 8000
# Affiche une URL publique, ex. https://a1b2c3.ngrok.app -> http://localhost:8000
```

Tu déclares alors `https://a1b2c3.ngrok.app/webhook/stripe` chez le fournisseur. C'est la solution la plus
générale (elle expose tout ton serveur), au prix d'une URL qui change à chaque redémarrage (sauf compte
payant).

| Outil | Pour qui | Avantage | Limite |
| --- | --- | --- | --- |
| **Stripe CLI** | Stripe uniquement | aucun réglage d'URL, secret fourni | spécifique à Stripe |
| **smee.io** | tout fournisseur | gratuit, sans compte | un intermédiaire de plus |
| **ngrok** | tout fournisseur | tunnel direct, vrai HTTPS public | URL changeante en gratuit |

> **À retenir** — En développement, ton `localhost` n'est pas joignable par un fournisseur. Tu fais
> « le pont » avec un outil de tunnel (**Stripe CLI**, **smee.io**, **ngrok**) qui relaie les vrais
> webhooks jusqu'à ta machine. En production, tu remplaceras ce pont par ta **vraie URL publique**.

## Bien démarrer : config du secret

On aura besoin, dès le chapitre 5, du secret de signature affiché par `stripe listen`. Prends l'habitude
de le mettre dans `.env.local` (jamais en clair dans le code, jamais commité) :

```bash
# .env.local  (ce fichier n'est pas versionné)
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxx
```

On l'exploitera bientôt. Pour l'instant, l'essentiel est acquis : **PayHub reçoit un webhook et répond**.

## Résumé

- Un endpoint de webhook est un **contrôleur Symfony** en **`POST`** (`#[Route(..., methods: ['POST'])]`).
- On lit le **corps brut** avec **`$request->getContent()`**, puis on **décode** avec `json_decode` — pas
  `$request->request->get()`, qui ne marche pas pour du JSON.
- On **valide** le payload et on renvoie **`400`** s'il est illisible, **`200`** si tout va bien.
- On **logue** chaque réception : un webhook arrive sans personne derrière, les logs sont ta seule
  visibilité.
- En local, on teste d'abord au **`curl`**, puis on reçoit de **vrais** webhooks via un pont :
  **Stripe CLI**, **smee.io** ou **ngrok**.
- Le **secret de signature** se range dès maintenant dans `.env.local`.

## Exercices

### Exercice 1 — Renforcer la validation

Notre contrôleur accepte le payload dès que `id` et `type` existent. Modifie-le pour **rejeter** aussi le
cas où l'en-tête `Content-Type` n'est pas `application/json`, en renvoyant un `415 Unsupported Media
Type`. Teste les deux cas au `curl`.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on lit le `Content-Type` via `$request->headers` et on coupe court avant même de décoder.

```php
// Au début de l'action, avant getContent() :
if (!str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
    return new JsonResponse(['error' => 'Content-Type attendu : application/json'],
        Response::HTTP_UNSUPPORTED_MEDIA_TYPE);   // 415
}
```

Test du cas refusé :

```bash
$ curl -i -X POST https://127.0.0.1:8000/webhook/stripe \
    -H "Content-Type: text/plain" -d '{"id":"x","type":"y"}'
# Sortie attendue : HTTP/2 415
```

On utilise `str_contains` car le `Content-Type` peut être suivi d'un paramètre, comme
`application/json; charset=utf-8`.

</details>

### Exercice 2 — Recevoir un vrai événement de test

Avec la **Stripe CLI** (ou smee.io si tu n'utilises pas Stripe), mets en place le pont vers ton endpoint
local, déclenche un événement `payment_intent.succeeded`, et vérifie dans tes logs que PayHub l'a bien
reçu. Note le secret `whsec_...` dans `.env.local`.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : deux terminaux, un pour le pont, un pour déclencher.

```bash
# Terminal 1 — le pont (laisse-le ouvert) :
$ stripe listen --forward-to https://127.0.0.1:8000/webhook/stripe
# Affiche : > Ready! ... webhook signing secret is whsec_abc123...

# Terminal 2 — déclencher un événement :
$ stripe trigger payment_intent.succeeded
```

Dans `var/log/dev.log` (ou `symfony server:log`), tu dois voir :

```text
[info] Webhook Stripe reçu {"id":"evt_...","type":"payment_intent.succeeded"}
```

Et tu copies le secret affiché par `stripe listen` dans `.env.local` :

```bash
STRIPE_WEBHOOK_SECRET=whsec_abc123...
```

Si rien n'arrive : vérifie que le serveur Symfony tourne, que l'URL du `--forward-to` est exacte (port,
chemin `/webhook/stripe`), et que le terminal du pont reste ouvert.

</details>

## Quiz

**1.** Comment lit-on le corps JSON d'un webhook dans un contrôleur Symfony ?
- A. `$request->request->get('body')`
- B. `$request->getContent()` puis `json_decode()`
- C. `$_POST['payload']`

**2.** Pourquoi ne pas faire tout le traitement métier dans l'action avant de répondre ?
- A. Parce que Symfony l'interdit
- B. Parce qu'une réponse lente peut dépasser le délai de l'émetteur, qui rejouera l'événement
- C. Parce que le JSON serait corrompu

**3.** À quoi sert un outil comme la Stripe CLI, smee.io ou ngrok en développement ?
- A. À chiffrer les webhooks
- B. À relayer de vrais webhooks vers ton serveur local, qui n'est pas joignable depuis Internet
- C. À remplacer le serveur Symfony

**4.** Quel code renvoyer si le payload reçu n'est pas du JSON valide ?
- A. `200`
- B. `400`
- C. `500`

<details>
<summary>Voir les réponses</summary>

1. **B** — Pour du JSON, on lit le corps brut puis on décode ; `$request->request` ne sert qu'aux
   formulaires.
2. **B** — Au-delà du délai d'attente, l'émetteur considère un échec et rejoue. On répond vite, on traite
   après (chapitre 4).
3. **B** — Ces outils font le pont entre Internet et ton `localhost` injoignable.
4. **B** — `400 Bad Request` : la requête est mal formée. (`500` serait à réserver à une erreur de **ton**
   code.)

</details>

## Projet fil rouge

PayHub reçoit désormais ses premiers webhooks. Tu as :

- créé `WebhookController` avec l'endpoint **`POST /webhook/stripe`** ;
- lu le **corps brut**, **décodé** le JSON, **validé** les champs `id` et `type`, et **logué** la
  réception ;
- testé au **`curl`**, puis reçu un **vrai** événement de test via la **Stripe CLI** ;
- rangé le **secret de signature** dans `.env.local`.

Mais PayHub fait encore tout son (futur) travail dans l'action. Au chapitre suivant, on apprend à
**accuser réception en quelques millisecondes** et à **déporter le traitement en arrière-plan** avec
Messenger — la règle d'or du récepteur de webhooks.

---

[← Chapitre précédent](02-anatomie-requete.md) · [Sommaire](README.md) · [Chapitre suivant →](04-bien-repondre-async.md)
