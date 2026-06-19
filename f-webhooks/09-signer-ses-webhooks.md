# Signer ses webhooks

[← Chapitre précédent](08-livraison-fiable.md) · [Sommaire](README.md) · [Chapitre suivant →](10-observabilite.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- **signer** les webhooks que tu émets, côté émetteur, avec HMAC ;
- inclure un **horodatage** dans la signature pour protéger tes abonnés du rejeu ;
- **documenter** clairement la vérification pour que tes consommateurs puissent l'implémenter ;
- gérer la **rotation des secrets** sans interrompre la livraison ;
- comprendre la symétrie totale entre signer (émetteur) et vérifier (récepteur, chapitre 5).

## L'autre côté de la signature

Au chapitre 5, PayHub **vérifiait** la signature de Stripe. Maintenant, PayHub **signe** : il doit prouver
à ses abonnés que le webhook vient **bien** de lui. C'est exactement la même mécanique HMAC, vue de
l'**émetteur** cette fois.

```text
CHAPITRE 5 (récepteur)              CHAPITRE 9 (émetteur)

Stripe signe ──► PayHub vérifie     PayHub signe ──► Boutique vérifie
                                     (toi, maintenant)   (ton abonné)
```

Rappel du principe (chapitre 5) : avec un **secret partagé**, l'émetteur calcule
`signature = HMAC(secret, horodatage + corps)` et la joint dans un en-tête ; le récepteur recalcule la
même chose et compare. Tu as déjà le secret : c'est le champ `secret` généré à la création de
`WebhookSubscription` (chapitre 7), **propre à chaque abonné**.

> **À retenir** — Chaque abonnement a **son propre secret**. On signe avec **ce** secret-là, pas un secret
> global. Ainsi, compromettre le secret d'un abonné n'expose pas les autres, et tu peux le faire tourner
> abonné par abonné.

## Signer un envoi

On ajoute la signature dans `WebhookSender`. La recette, calquée sur Stripe : signer la concaténation
**`horodatage.corps`** en HMAC-SHA256, et transmettre l'horodatage **et** la signature dans des en-têtes.

```php
<?php
// src/Webhook/WebhookSender.php (extrait, avec signature)
public function send(WebhookSubscription $abonnement, array $eventPayload): void
{
    $corps = json_encode($eventPayload, JSON_THROW_ON_ERROR);
    $horodatage = time();

    // On signe "horodatage.corps" avec le secret PROPRE à cet abonné.
    $aSigner = $horodatage . '.' . $corps;
    $signature = hash_hmac('sha256', $aSigner, $abonnement->getSecret());

    $reponse = $this->http->request('POST', $abonnement->getUrl(), [
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'PayHub-Webhooks/1.0',
            'X-PayHub-Event' => $eventPayload['type'],
            // L'en-tête de signature : horodatage + empreinte, comme Stripe-Signature.
            'X-PayHub-Signature' => "t=$horodatage,v1=$signature",
        ],
        'body' => $corps,
        'timeout' => 10,
    ]);

    // ... (gestion du code de statut et des retries, chapitre 8)
}
```

Trois décisions de conception importantes :

- **On signe `horodatage.corps`**, pas seulement le corps : l'horodatage signé permet à l'abonné de
  **rejeter le rejeu** (une signature ne couvre que ce qu'elle inclut).
- **On envoie le corps exactement tel qu'on l'a signé** (`$corps`). L'abonné devra le vérifier sur **son**
  corps brut reçu — d'où l'insistance, des deux côtés, sur le « brut ».
- **Le format `t=...,v1=...`** imite Stripe volontairement : tes consommateurs reconnaîtront un schéma
  familier, et tu pourras un jour ajouter `v2=` pour faire évoluer l'algorithme sans casser l'existant.

## Documenter la vérification pour tes consommateurs

Signer ne sert à rien si tes abonnés ne savent pas **vérifier**. Un bon émetteur fournit une **doc claire**
et, idéalement, un **exemple de code**. Voici ce que PayHub publierait à l'attention de ses intégrateurs.

> **Vérifier une signature PayHub.** Chaque requête porte un en-tête `X-PayHub-Signature` de la forme
> `t=<horodatage>,v1=<signature>`. Pour la vérifier :
> 1. lis le **corps brut** de la requête (avant tout décodage JSON) ;
> 2. extrais `t` et `v1` de l'en-tête ;
> 3. recalcule `HMAC-SHA256(ton_secret, "<t>.<corps_brut>")` ;
> 4. compare en **temps constant** à `v1` ; rejette si différent ;
> 5. rejette si `t` date de plus de **5 minutes** (protection anti-rejeu).

Et l'exemple de code (qu'on donne au consommateur, en PHP ici — on fournirait aussi d'autres langages) :

```php
<?php
// Côté CONSOMMATEUR de PayHub : vérifier la signature d'un webhook reçu.
$corpsBrut = $request->getContent();
$entete = $request->headers->get('X-PayHub-Signature', '');

// Extraire t et v1 de "t=...,v1=..."
parse_str(str_replace(',', '&', $entete), $parties);
$horodatage = (int) ($parties['t'] ?? 0);
$signatureRecue = (string) ($parties['v1'] ?? '');

// Anti-rejeu : refuser si trop ancien (tolérance 5 min).
if (abs(time() - $horodatage) > 300) {
    http_response_code(400);
    exit('horodatage trop ancien');
}

// Recalculer et comparer en temps constant.
$attendue = hash_hmac('sha256', $horodatage . '.' . $corpsBrut, $monSecretPayHub);
if (!hash_equals($attendue, $signatureRecue)) {
    http_response_code(401);
    exit('signature invalide');
}

// À partir d'ici, le webhook est authentique : on peut le traiter.
```

C'est exactement le code que **toi** as écrit au chapitre 5 pour vérifier Stripe. La boucle est bouclée :
tu sais désormais **les deux moitiés** du contrat.

> **Attention** — Une signature non documentée est inutile : tes consommateurs la **ignoreront**.
> Fournis la **forme de l'en-tête**, l'**algorithme** exact (HMAC-SHA256), ce qui est signé
> (`t.corps`), la **tolérance** d'horodatage, et un **exemple de code**. Sans ça, ton effort de sécurité
> est vain côté abonné.

## La rotation des secrets

Un secret peut **fuir** (log mal protégé, employé qui part, dépôt public par erreur). Il faut pouvoir le
**changer** — c'est la **rotation**. Le défi : changer le secret **sans interrompre** la livraison, car
le consommateur ne peut pas basculer au même instant que toi.

La technique standard est la **période de chevauchement** avec **deux secrets actifs** :

1. Tu génères un **nouveau** secret, en gardant l'**ancien** valide quelque temps.
2. Pendant la transition, tu **signes avec le nouveau ET l'ancien** (deux signatures dans l'en-tête,
   `v1=...,v1=...`), ou tu fournis les deux versions.
3. Le consommateur, qui accepte **l'une OU l'autre**, met à jour son secret à son rythme.
4. Passé le délai, tu **supprimes** l'ancien et ne signes plus qu'avec le nouveau.

```php
// Pendant la rotation : on joint DEUX signatures, l'abonné en valide au moins une.
$sigNouveau = hash_hmac('sha256', $aSigner, $abonnement->getSecret());
$sigAncien  = hash_hmac('sha256', $aSigner, $abonnement->getPreviousSecret());
$entete = "t=$horodatage,v1=$sigNouveau,v1=$sigAncien";
```

C'est précisément ce que fait Stripe quand tu « fais tourner » un *signing secret* dans son tableau de
bord : il maintient l'ancien actif 24 h.

> **À retenir** — Pour faire tourner un secret sans coupure : **chevauchement**. On signe avec
> **l'ancien et le nouveau** pendant une fenêtre de transition, l'abonné accepte **l'un ou l'autre**, puis
> on retire l'ancien. Jamais de bascule sèche qui casserait toutes les vérifications d'un coup.

## Bonnes pratiques de signature

- **Ne mets jamais le secret dans l'URL ou le payload.** Le secret reste **partagé hors bande** (affiché
  une fois à la création, stocké chiffré côté abonné). Seules les **signatures** circulent.
- **HTTPS obligatoire** pour tes envois aussi : sans TLS, le corps et la signature transitent en clair.
- **Stocke tes secrets de façon protégée** (chiffrés en base ou dans un coffre/*secret manager*), pas en
  clair dans le code ni dans les logs.
- **Versionne ton schéma de signature** (`v1`, `v2`…) pour pouvoir changer d'algorithme un jour sans
  rupture.
- **Permets la régénération** d'un secret par l'abonné (bouton « régénérer »), avec chevauchement.

## Résumé

- Signer (émetteur) est le **miroir** de vérifier (récepteur, chapitre 5) : même HMAC, autre côté.
- On signe **`horodatage.corps`** avec le secret **propre à chaque abonné**, et on transmet
  `t` + signature dans un en-tête (`X-PayHub-Signature: t=...,v1=...`).
- L'**horodatage signé** permet à l'abonné de se protéger du **rejeu** (rejeter ce qui est trop vieux).
- Une signature **doit être documentée** : forme de l'en-tête, algorithme, ce qui est signé, tolérance,
  et un **exemple de code** — sinon les consommateurs l'ignorent.
- La **rotation** se fait par **chevauchement** : deux secrets actifs le temps que l'abonné bascule, puis
  on retire l'ancien.
- Le secret reste **hors bande**, jamais dans l'URL/payload/logs ; **HTTPS** obligatoire ; **versionne**
  ton schéma (`v1`, `v2`).

## Exercices

### Exercice 1 — Implémenter la signature

À partir du `WebhookSender` du chapitre 8, ajoute la signature `X-PayHub-Signature: t=...,v1=...` calculée
en HMAC-SHA256 sur `horodatage.corps`, avec le secret de l'abonnement. Quel piège dois-tu absolument
éviter sur le **corps** ?

<details>
<summary>Voir le corrigé</summary>

```php
$corps = json_encode($eventPayload, JSON_THROW_ON_ERROR);
$horodatage = time();
$signature = hash_hmac('sha256', $horodatage . '.' . $corps, $abonnement->getSecret());

// ... dans les headers :
'X-PayHub-Signature' => "t=$horodatage,v1=$signature",
// ... dans la requête :
'body' => $corps,
```

**Le piège** : il faut envoyer **exactement** la chaîne `$corps` qui a servi au calcul de la signature.
Si tu re-encodes le tableau une seconde fois pour le `body` (autre appel à `json_encode`), l'ordre des
clés ou l'échappement peuvent différer, et la signature de l'abonné **ne correspondra plus**. On
**calcule la chaîne une fois** et on **réutilise la même variable** pour la signature ET le corps. C'est
le même principe « corps brut » que côté récepteur (chapitre 5).

</details>

### Exercice 2 — Concevoir une rotation sans coupure

Un abonné t'écrit : « j'ai accidentellement publié mon secret PayHub sur un dépôt public ». Tu dois le
remplacer **sans** que ses webhooks tombent en erreur le temps qu'il mette à jour son code. Décris la
procédure.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : rotation par **chevauchement**, jamais de bascule sèche.

1. **Générer** un nouveau secret pour cet abonnement, en **conservant** l'ancien (`previousSecret`).
2. **Communiquer** le nouveau secret à l'abonné (canal sûr) et lui demander de le déployer.
3. Pendant la fenêtre de transition, **signer avec les deux** : `t=...,v1=<nouveau>,v1=<ancien>`. L'abonné
   valide **l'une OU l'autre** signature, donc :
   - tant qu'il a l'ancien secret → il valide la signature « ancien » ;
   - dès qu'il a déployé le nouveau → il valide la signature « nouveau ».
   Aucune coupure.
4. Après un délai raisonnable (ex. 24–48 h, ou confirmation de l'abonné), **supprimer** l'ancien secret et
   ne signer qu'avec le nouveau.

Le secret compromis est ainsi révoqué **sans** qu'aucun webhook ne soit rejeté pendant la transition.

</details>

## Quiz

**1.** Avec quel secret PayHub signe-t-il un webhook destiné à un abonné donné ?
- A. Un secret global unique pour tous les abonnés
- B. Le secret propre à cet abonnement
- C. Le secret de Stripe

**2.** Pourquoi signer `horodatage.corps` et pas seulement le corps ?
- A. Pour que la signature soit plus longue
- B. Pour permettre à l'abonné de rejeter les requêtes rejouées (trop anciennes)
- C. Pour compresser le payload

**3.** Que faut-il fournir pour qu'une signature soit utile à tes consommateurs ?
- A. Rien, ils devineront
- B. La doc : forme de l'en-tête, algorithme, ce qui est signé, tolérance, et un exemple de code
- C. Uniquement le secret

**4.** Comment faire tourner un secret sans interrompre la livraison ?
- A. Changer le secret d'un coup des deux côtés au même instant
- B. Par chevauchement : signer avec l'ancien et le nouveau pendant une fenêtre de transition
- C. En désactivant l'abonnement

<details>
<summary>Voir les réponses</summary>

1. **B** — Un secret par abonnement : isole les compromissions et permet une rotation ciblée.
2. **B** — L'horodatage signé est ce qui permet l'anti-rejeu côté abonné.
3. **B** — Sans documentation et exemple, la signature est ignorée et l'effort perdu.
4. **B** — Le chevauchement évite toute coupure ; une bascule sèche casserait toutes les vérifications.

</details>

## Projet fil rouge

PayHub signe désormais ses envois. Tu as :

- ajouté la **signature HMAC-SHA256** sur `horodatage.corps` dans `WebhookSender`, avec le secret **propre
  à chaque abonné**, dans l'en-tête `X-PayHub-Signature` ;
- rédigé la **documentation de vérification** et un **exemple de code** pour tes consommateurs ;
- conçu une **rotation des secrets** par chevauchement, sans coupure de livraison.

PayHub est maintenant un émetteur **fiable et sécurisé**. Mais en cas de problème (un abonné se plaint de
ne rien recevoir), comment **enquêter** ? Au chapitre suivant, on ajoute l'**observabilité** : un
**journal des livraisons** consultable, un bouton **« rejouer »**, et du **monitoring**.

---

[← Chapitre précédent](08-livraison-fiable.md) · [Sommaire](README.md) · [Chapitre suivant →](10-observabilite.md)
