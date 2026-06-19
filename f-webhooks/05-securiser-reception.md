# Sécuriser la réception : signature et anti-rejeu

[← Chapitre précédent](04-bien-repondre-async.md) · [Sommaire](README.md) · [Chapitre suivant →](06-fiabilite-recepteur.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer pourquoi un endpoint de webhook **public** est une cible et ce qu'un attaquant peut tenter ;
- comprendre la **signature HMAC** : ce qu'elle prouve, comment elle est calculée ;
- **vérifier** la signature d'un webhook Stripe dans Symfony, sur le **corps brut** ;
- te protéger contre les **attaques par rejeu** (*replay*) grâce à l'horodatage ;
- comparer la signature aux autres méthodes (token secret dans l'URL, *mTLS*) et connaître les pièges.

## Le problème : ton endpoint est ouvert à tous

Ton endpoint `/webhook/stripe` est une **URL publique**. N'importe qui sur Internet peut lui envoyer une
requête `POST` — pas seulement Stripe. Un attaquant qui devine (ou découvre) ton URL peut forger une
fausse requête :

```bash
# Un attaquant forge un faux "paiement réussi" :
$ curl -X POST https://payhub.example.com/webhook/stripe \
    -H "Content-Type: application/json" \
    -d '{"id":"evt_faux","type":"payment_intent.succeeded",
         "data":{"object":{"amount":999999}}}'
```

Si PayHub traite cette requête sans vérification, l'attaquant peut **se faire livrer une commande sans
payer**, fausser tes statistiques, déclencher des e-mails… Le `User-Agent: Stripe/1.0` ne prouve rien : un
en-tête se falsifie en une ligne. Il faut une preuve **cryptographique** que la requête vient bien du
vrai émetteur.

> **Attention** — Un endpoint de webhook **non vérifié** est une faille de sécurité béante. **Tout** ce
> qui arrive sur cette URL est, par défaut, **non fiable**. La vérification de signature n'est pas une
> option : c'est la première chose à mettre en place avant de traiter quoi que ce soit en production.

## La signature HMAC : prouver l'authenticité

La technique standard est la **signature HMAC** (*Hash-based Message Authentication Code*, « code
d'authentification de message basé sur le hachage »). L'idée :

1. Toi et l'émetteur partagez un **secret** (une longue chaîne aléatoire) — connu de vous deux **seuls**.
   C'est le `whsec_...` de Stripe.
2. Avant d'envoyer le webhook, l'émetteur calcule une **empreinte** du corps **combinée au secret** :
   `signature = HMAC(secret, corps)`. Il joint cette signature dans un en-tête.
3. À la réception, tu **recalcules** la même empreinte avec **ton** secret et le corps reçu. Si ta
   signature **est identique** à celle reçue, alors :
   - le message vient bien de quelqu'un qui **connaît le secret** (donc l'émetteur) ;
   - le message **n'a pas été modifié** en route (sinon l'empreinte différerait).

```text
Émetteur                                  Toi (récepteur)
─────────                                 ───────────────
corps + secret ──HMAC──► signature        corps reçu + secret ──HMAC──► signature recalculée
        │                                                                    │
        └──── envoie corps + signature ────────────────────────────────────►│
                                                       compare : identiques ? ✓ authentique
                                                                  différentes ? ✗ on rejette
```

Le secret **ne circule jamais** : seules les **signatures** transitent. Un attaquant qui intercepte la
requête voit la signature, mais ne peut pas en forger une nouvelle pour un corps modifié, faute de
connaître le secret.

> **À retenir** — La signature HMAC prouve **deux choses** d'un coup : l'**authenticité** (ça vient bien
> de l'émetteur, qui seul connaît le secret) et l'**intégrité** (le corps n'a pas été altéré). Elle se
> calcule **sur le corps brut**.

## Vérifier la signature Stripe dans Symfony

Stripe fournit une bibliothèque officielle qui fait le calcul HMAC **et** la vérification anti-rejeu pour
toi. C'est la méthode recommandée : on n'écrit pas sa propre crypto quand le fournisseur fournit l'outil.

```bash
$ composer require stripe/stripe-php
```

On adapte le contrôleur du chapitre 4. La vérification se fait **avant** tout traitement, sur le **corps
brut** et l'en-tête `Stripe-Signature`, avec le secret de `.env.local`.

```php
<?php
// src/Controller/WebhookController.php (extrait)
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

#[Route('/webhook/stripe', name: 'webhook_stripe', methods: ['POST'])]
public function stripe(
    Request $request,
    MessageBusInterface $bus,
    LoggerInterface $logger,
    #[Autowire('%env(STRIPE_WEBHOOK_SECRET)%')] string $secret,
): Response {
    $payloadBrut = $request->getContent();
    $signature = $request->headers->get('Stripe-Signature', '');

    try {
        // Vérifie la signature ET l'horodatage (anti-rejeu). Lève une exception sinon.
        $evenement = Webhook::constructEvent($payloadBrut, $signature, $secret);
    } catch (SignatureVerificationException $e) {
        $logger->warning('Webhook Stripe : signature invalide', ['erreur' => $e->getMessage()]);

        // 401 : la requête n'est pas authentifiée. On NE traite PAS.
        return new JsonResponse(['error' => 'signature invalide'], Response::HTTP_UNAUTHORIZED);
    } catch (\UnexpectedValueException $e) {
        // Payload illisible.
        return new JsonResponse(['error' => 'payload invalide'], Response::HTTP_BAD_REQUEST);
    }

    // À partir d'ici, l'événement est AUTHENTIQUE. On peut le mettre en file.
    $bus->dispatch(new StripeWebhookReceived(
        eventId: $evenement->id,
        eventType: $evenement->type,
        payload: json_decode($payloadBrut, associative: true),
    ));

    return new JsonResponse(['received' => true], Response::HTTP_ACCEPTED);
}
```

Trois points essentiels :

- **On vérifie sur `$payloadBrut`**, le corps exact reçu. C'est pourquoi on ne devait surtout pas le
  re-sérialiser (rappel du chapitre 2).
- **`constructEvent` lève une exception** si la signature ne colle pas : on renvoie alors **401** et on
  **n'exécute rien**. La sécurité passe **avant** la mise en file.
- Le secret est **injecté** depuis l'environnement (`#[Autowire('%env(...)%')]`), jamais écrit en dur.

### Comprendre l'en-tête `Stripe-Signature`

L'en-tête de Stripe ressemble à `t=1718800000,v1=abcd...`. Il contient :

- **`t`** : l'horodatage (*timestamp*) du moment où Stripe a signé ;
- **`v1`** : la signature HMAC-SHA256 de la chaîne `t.corps`, avec ton secret.

`constructEvent` recalcule `HMAC-SHA256(secret, "{t}.{corps}")` et le compare à `v1`. Le `t` sert aussi à
l'anti-rejeu, qu'on voit maintenant.

## L'attaque par rejeu et la défense par horodatage

La signature prouve qu'un message est authentique… mais **un message authentique reste valide pour
toujours**. Un attaquant qui **capture** une vraie requête signée (par exemple via un log mal protégé)
peut la **renvoyer** telle quelle, des heures plus tard : la signature est correcte, donc elle passe.
C'est l'**attaque par rejeu** (*replay attack*).

La parade : signer aussi un **horodatage** et **refuser** les requêtes trop vieilles. C'est précisément le
rôle du `t` dans l'en-tête Stripe. La bibliothèque vérifie par défaut que `t` est récent (tolérance de 5
minutes) ; au-delà, `constructEvent` lève l'exception. Tu peux ajuster la tolérance :

```php
// Tolérance personnalisée (en secondes) : ici 300 s = 5 minutes.
$evenement = Webhook::constructEvent($payloadBrut, $signature, $secret, tolerance: 300);
```

Si tu implémentes une vérification **à la main** (fournisseur sans bibliothèque), tu dois faire les deux :
recalculer le HMAC **et** comparer le timestamp signé à l'heure courante, en rejetant l'écart trop grand.

> **À retenir** — La signature seule n'empêche pas le **rejeu** d'une requête authentique capturée. Il
> faut **signer un horodatage** et **rejeter** les requêtes trop anciennes (quelques minutes de
> tolérance). Signature **+** fraîcheur = protection complète.

## Vérifier une signature « à la main » (autre fournisseur)

Tous les fournisseurs ne livrent pas de bibliothèque PHP. Le schéma reste le même. Exemple typique d'un
en-tête `X-Signature: sha256=...` calculé en HMAC-SHA256 :

```php
<?php
// Vérification manuelle d'une signature HMAC-SHA256.
$payloadBrut = $request->getContent();
$signatureRecue = $request->headers->get('X-Signature', '');

// On recalcule l'empreinte attendue avec NOTRE secret.
$attendue = 'sha256=' . hash_hmac('sha256', $payloadBrut, $secret);

// Comparaison en temps constant : indispensable contre les attaques temporelles.
if (!hash_equals($attendue, $signatureRecue)) {
    return new JsonResponse(['error' => 'signature invalide'], Response::HTTP_UNAUTHORIZED);
}
```

Deux pièges classiques, à ne jamais commettre :

- **Ne compare pas avec `===` ou `==`.** Utilise **`hash_equals`**, qui compare en **temps constant**.
  Une comparaison naïve s'arrête au premier caractère différent, ce qui laisse mesurer le temps de réponse
  et **deviner la signature octet par octet** (attaque temporelle). `hash_equals` ferme cette porte.
- **Calcule le HMAC sur le corps brut**, pas sur du JSON ré-encodé.

## Autres garde-fous (en complément, pas à la place)

La signature est la défense principale. D'autres mesures la **complètent** :

- **HTTPS obligatoire.** Un webhook doit arriver en `https://`. En clair (`http://`), la signature et le
  corps transitent en clair et peuvent être lus. Tous les fournisseurs sérieux exigent HTTPS.
- **Token secret dans l'URL** (ex. `/webhook/stripe/9f3a...`) : un chemin imprévisible évite que des bots
  trouvent l'endpoint. C'est un **complément** faible (l'URL fuit dans les logs, l'historique…), **jamais
  un remplacement** de la signature.
- **Liste blanche d'adresses IP** : certains fournisseurs publient leurs plages d'IP ; tu peux n'accepter
  que celles-là. Utile en défense en profondeur, mais les IP changent : ne t'y fie pas seul.
- **mTLS** (TLS mutuel) : le client (l'émetteur) présente lui aussi un certificat. Robuste mais lourd,
  réservé aux intégrations sensibles.

> **Attention** — Aucune de ces mesures ne remplace la **signature**. L'URL secrète, la liste d'IP et le
> reste sont des couches **supplémentaires**. Le pilier reste : **vérifier la signature sur le corps
> brut, avant tout traitement.**

## Résumé

- Un endpoint public reçoit du trafic de **n'importe qui** : par défaut, tout y est **non fiable**.
- La **signature HMAC** prouve l'**authenticité** (vient de l'émetteur, qui seul connaît le **secret**) et
  l'**intégrité** (corps non modifié). On la calcule **sur le corps brut**.
- Avec Stripe, **`Webhook::constructEvent($brut, $signature, $secret)`** vérifie tout ; en cas d'échec, on
  renvoie **401** et on **ne traite pas**.
- La signature seule n'empêche pas le **rejeu** : il faut **signer un horodatage** et **refuser** les
  requêtes trop vieilles (Stripe le fait avec le `t`, tolérance ~5 min).
- En vérification manuelle : `hash_hmac` pour recalculer, **`hash_equals`** (temps constant) pour comparer
  — jamais `===`.
- HTTPS, URL secrète, liste d'IP, mTLS sont des **compléments**, pas des substituts à la signature.

## Exercices

### Exercice 1 — Corriger une comparaison dangereuse

Un développeur vérifie une signature ainsi. Qu'est-ce qui est dangereux, et comment corriger ?

```php
$attendue = hash_hmac('sha256', $request->getContent(), $secret);
if ($attendue !== $request->headers->get('X-Signature')) {
    throw new AccessDeniedHttpException();
}
```

<details>
<summary>Voir le corrigé</summary>

Le problème est la comparaison **`!==`**. Elle s'effectue caractère par caractère et **s'arrête au
premier qui diffère** : le temps de réponse varie selon le nombre de caractères corrects en tête, ce qui
permet à un attaquant de **reconstituer la signature** par mesures successives (attaque temporelle).

Correction : comparer en **temps constant** avec `hash_equals`.

```php
$attendue = hash_hmac('sha256', $request->getContent(), $secret);
$recue = (string) $request->headers->get('X-Signature', '');
if (!hash_equals($attendue, $recue)) {
    throw new AccessDeniedHttpException();
}
```

(Selon le format du fournisseur, il faudra peut-être préfixer `sha256=` ou décoder du base64 ; mais le
point crucial est `hash_equals`.)

</details>

### Exercice 2 — Pourquoi l'horodatage ?

Ton endpoint vérifie parfaitement la signature HMAC, mais ignore tout horodatage. Un attaquant a réussi à
récupérer, dans un vieux fichier de log, une requête `payment_intent.succeeded` **authentique** (signature
valide) datant d'hier. Que peut-il faire, et quelle protection manquait ?

<details>
<summary>Voir le corrigé</summary>

L'attaquant peut **renvoyer telle quelle** cette requête authentique : la signature étant valide (le corps
n'a pas changé), ton endpoint l'accepte et **rejoue** le paiement. C'est une **attaque par rejeu**.

La protection manquante : la **vérification de fraîcheur**. Le fournisseur signe un **horodatage** (le `t`
chez Stripe) ; tu dois vérifier qu'il est **récent** et rejeter au-delà de quelques minutes. Avec
`Webhook::constructEvent`, c'est fait par défaut. À la main, il faut comparer le timestamp signé à
l'heure courante.

À noter : l'**idempotence** (chapitre 6) limiterait aussi les dégâts, puisqu'un événement déjà traité ne
serait pas rejoué côté métier. Mais la défense propre contre le rejeu reste la **fraîcheur de
l'horodatage**.

</details>

## Quiz

**1.** Que prouve une signature HMAC valide ?
- A. Que le message est chiffré
- B. Qu'il vient de l'émetteur (qui connaît le secret) et n'a pas été modifié
- C. Que l'émetteur a payé pour le service

**2.** Sur quoi calcule-t-on la signature ?
- A. Sur le JSON re-sérialisé proprement
- B. Sur le corps brut, exactement tel que reçu
- C. Sur l'URL de l'endpoint

**3.** Pourquoi utiliser `hash_equals` plutôt que `===` ?
- A. C'est plus rapide
- B. Pour comparer en temps constant et éviter les attaques temporelles
- C. Parce que `===` ne marche pas sur les chaînes

**4.** Comment se protéger d'une attaque par rejeu ?
- A. En vérifiant uniquement la signature
- B. En signant un horodatage et en rejetant les requêtes trop anciennes
- C. En répondant toujours 200

<details>
<summary>Voir les réponses</summary>

1. **B** — Authenticité + intégrité. (Une signature ne chiffre pas le contenu.)
2. **B** — Toujours sur le corps brut ; le re-sérialiser invaliderait la vérification.
3. **B** — La comparaison en temps constant empêche de deviner la signature par mesure du temps.
4. **B** — Signature **et** fraîcheur de l'horodatage ; la signature seule laisse rejouer une requête
   capturée.

</details>

## Projet fil rouge

PayHub n'accepte plus n'importe quoi. Tu as :

- installé `stripe/stripe-php` et vérifié la **signature** avec `Webhook::constructEvent` sur le **corps
  brut**, avant toute mise en file ;
- renvoyé **401** et refusé le traitement en cas de signature invalide ;
- compris la protection **anti-rejeu** par horodatage (le `t` de `Stripe-Signature`) ;
- vu comment vérifier **à la main** (`hash_hmac` + `hash_equals`) pour un fournisseur sans bibliothèque.

Reste un dernier risque, inhérent aux webhooks : **les doublons**. Stripe (ou ton propre worker) peut
livrer **deux fois** le même événement. Au chapitre suivant, on rend PayHub **idempotent** pour qu'un
même événement n'ait **jamais** d'effet deux fois.

---

[← Chapitre précédent](04-bien-repondre-async.md) · [Sommaire](README.md) · [Chapitre suivant →](06-fiabilite-recepteur.md)
