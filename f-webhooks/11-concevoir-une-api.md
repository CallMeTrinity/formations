# Concevoir une bonne API de webhooks

[← Chapitre précédent](10-observabilite.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- nommer et organiser un **catalogue de types d'événements** cohérent ;
- choisir ce qu'on met (ou non) dans le ***payload*** : « thin » vs « fat » events ;
- faire **évoluer** tes webhooks sans casser tes consommateurs (**versionnage**) ;
- offrir une expérience d'intégration soignée : **gestion des endpoints**, événements de **test**, doc ;
- reconnaître les décisions de conception qui distinguent une API de webhooks **professionnelle**.

## Pourquoi la conception compte

Les chapitres précédents ont rendu PayHub **techniquement** solide. Mais une API de webhooks est aussi un
**contrat** avec des développeurs tiers : ils écrivent du code **contre** tes événements et ne pourront
pas le changer à chaque humeur de ta part. Une API mal pensée se paie en **support**, en **intégrations
cassées** et en **réputation**. Bien concevoir, c'est anticiper ces coûts.

On passe ici de « ça marche » à « c'est **agréable et durable** à intégrer ». C'est ce qui sépare le 50 %
inférieur du reste.

## Nommer les types d'événements

Le `type` est ce que tes consommateurs lisent en premier pour aiguiller leur code. Règles d'or :

- **Format `ressource.action` au passé** : `payment.received`, `payment.refunded`,
  `subscription.canceled`. Le passé indique que **le fait est accompli** (un webhook annonce **ce qui
  s'est produit**, pas un ordre).
- **Cohérence absolue** : choisis une convention (point ou underscore, singulier ou pluriel) et **ne
  dévie jamais**. `payment.received` et `Order_Created` dans la même API, c'est le signe d'une conception
  bâclée.
- **Granularité utile** : ni trop vague (`payment.updated` oblige le consommateur à deviner *ce qui* a
  changé), ni trop fin (cent types quasi identiques). Vise des types **actionnables**.
- **Espaces de noms** par ressource pour pouvoir **s'abonner par préfixe** un jour (`payment.*`).

```text
BIEN                          À ÉVITER
payment.received              paymentOk
payment.refunded              payment-refund-done
subscription.canceled         SubCancel
invoice.payment_failed        thing_happened
```

> **À retenir** — Un bon `type` est `ressource.action`, **au passé**, **cohérent** dans tout le catalogue,
> et **actionnable**. Tes consommateurs aiguillent leur code dessus : c'est une promesse que tu ne pourras
> plus changer sans casser leur intégration.

## Thin events vs fat events : que mettre dans le payload ?

Deux philosophies sur le contenu du `data` :

- **Fat event** (« événement gras ») : le payload contient **toutes** les données de l'objet (le paiement
  complet). Avantage : le consommateur a tout, sans rappeler ton API. Inconvénient : payload volumineux,
  données potentiellement **périmées** au moment où il les lit, et exposition de données sensibles dans un
  message signé mais transitant.
- **Thin event** (« événement maigre ») : le payload contient le **strict minimum** — souvent juste un
  **identifiant** et le type. Le consommateur **rappelle ton API** pour obtenir l'état **à jour**.
  Avantage : petit, toujours frais, moins de fuite. Inconvénient : un appel d'API supplémentaire côté
  consommateur.

```json
// Fat event : tout est là
{ "type": "payment.received",
  "data": { "payment_id": "pay_553", "amount": 4900, "currency": "eur",
            "customer": { "id": "cus_12", "email": "..." }, "card_last4": "4242" } }

// Thin event : juste de quoi recharger
{ "type": "payment.received",
  "data": { "payment_id": "pay_553" } }
```

Beaucoup de fournisseurs (dont Stripe) adoptent un **entre-deux** : un payload riche **mais** en
recommandant de **recharger** l'objet via l'API avant toute action critique, **précisément à cause du
désordre d'arrivée** vu au chapitre 6 (le payload reçu peut être plus ancien que l'état réel).

> **Astuce** — En cas de doute, penche vers le **thin event** pour les données **sensibles** ou qui
> **changent vite** (statut, solde), et accepte des champs « gras » pour les données **stables** et utiles
> au tri (type, identifiants, montant). Et **documente** que l'état faisant foi est celui de ton **API**,
> pas celui du webhook.

## Versionner ses webhooks

Tôt ou tard, tu voudras **changer** un payload : renommer un champ, en ajouter, modifier une structure.
Si tu le fais brutalement, **toutes** les intégrations existantes cassent du jour au lendemain. D'où le
**versionnage**.

D'abord, la distinction cruciale :

- un changement **rétrocompatible** (*non-breaking*) — **ajouter** un champ optionnel — ne nécessite
  **pas** de nouvelle version. Les consommateurs ignorent ce qu'ils ne connaissent pas.
- un changement **cassant** (*breaking*) — **renommer/supprimer** un champ, changer un type — **exige** une
  nouvelle version.

> **À retenir** — **Ajouter** un champ est sûr ; **renommer, supprimer ou retyper** est cassant. Conçois
> tes consommateurs pour **ignorer les champs inconnus** (toi y compris quand tu reçois), et réserve le
> versionnage aux changements **cassants**.

Trois stratégies de versionnage, par ordre de finesse :

1. **Version dans le payload** : un champ `api_version` ou un `type` versionné (`payment.received.v2`). Le
   consommateur lit la version et adapte. Simple, explicite.
2. **Version par abonnement** : chaque endpoint est **figé** à la version active au moment de sa création
   (approche de Stripe). Les anciens abonnés continuent de recevoir l'ancien format ; les nouveaux ont le
   récent. Tu fais évoluer chacun **à son rythme**.
3. **En-tête de version** : `X-PayHub-Version: 2026-06-01`. Souvent une **date** plutôt qu'un numéro, ce
   qui rend l'historique limpide.

```text
Abonné créé en 2025 → reste en version 2025-01-01 (format figé)
Abonné créé en 2026 → version 2026-06-01 (nouveau format)
            ↑ chacun évolue quand IL le décide, pas quand TU changes
```

Quelle que soit la stratégie : **annonce** les changements (changelog), **préviens** à l'avance, et
maintiens les anciennes versions **un temps raisonnable** avant de les retirer (politique de
*deprecation*).

## Soigner l'expérience d'intégration

Au-delà du format, ce qui fait une API de webhooks **agréable** :

- **Gestion des endpoints en libre-service** : une interface (ou une API) où le consommateur **ajoute**,
  **modifie**, **désactive** ses URL et **choisit ses types** d'événements — sans t'écrire un e-mail.
- **Événement de test** : un bouton « envoyer un événement de test » qui pousse un faux webhook vers
  l'endpoint, pour valider l'intégration **avant** la production (le `stripe trigger` du chapitre 3, côté
  émetteur).
- **Affichage du secret une seule fois** + bouton **régénérer** (avec chevauchement, chapitre 9).
- **Tableau des livraisons** côté consommateur (le journal du chapitre 10, exposé à l'abonné) avec
  **rejeu**.
- **Documentation** : la liste des **types**, le **schéma** de chaque payload (avec exemple), la procédure
  de **vérification de signature** (chapitre 9), la politique de **retry** (combien de tentatives, sur
  quelle durée), et les **codes** que tu attends en retour.

```text
Checklist "doc de webhooks" :
[ ] Liste des types d'événements + description
[ ] Exemple de payload pour chaque type
[ ] Format de l'en-tête de signature + algo + exemple de vérification
[ ] Politique de retry (tentatives, délais, dead-letter)
[ ] Codes de réponse attendus (2xx) et comportement sur 4xx/5xx
[ ] Politique de versionnage et changelog
[ ] Notes de sécurité (HTTPS, anti-rejeu, idempotence recommandée)
```

> **Attention** — Le piège le plus courant : soigner la **technique** et **négliger la doc**. Un
> consommateur qui ne trouve ni la liste des types, ni comment vérifier la signature, **abandonnera** ou
> remplira ton support de tickets. La doc **fait partie** de l'API, au même titre que le code.

## Quelques décisions qui trahissent une API mature

- **Garantir la déduplication** : fournir un `id` **stable** par événement et **documenter** que les
  consommateurs doivent dédupliquer (tu leur transmets la discipline du chapitre 6).
- **Recommander l'idempotence** et le **traitement asynchrone** côté consommateur, dans ta doc.
- **Limiter le débit** (*rate limit*) par endpoint pour ne pas écraser un petit abonné, et le documenter.
- **Ne jamais exiger** une réponse autre que `2xx` : ton consommateur ne devrait pas avoir à renvoyer un
  corps particulier.
- **Ordonner ou non** : si tu ne garantis pas l'ordre (cas général), **dis-le** explicitement, pour que
  le consommateur s'y prépare.

## Résumé

- Le **`type`** suit `ressource.action` **au passé**, reste **cohérent** et **actionnable** : c'est un
  contrat durable.
- **Thin vs fat event** : payload minimal (recharger via API, données fraîches/sûres) ou complet
  (pratique mais lourd et potentiellement périmé). Souvent un entre-deux + « l'API fait foi ».
- **Versionner** : ajouter un champ est sûr ; renommer/supprimer est **cassant**. Stratégies : version
  dans le payload, **par abonnement** (figée à la création), ou en-tête de version (souvent une date).
- Soigner l'**intégration** : endpoints en libre-service, événement de **test**, secret régénérable,
  **journal** côté abonné, et surtout une **documentation** complète.
- Une API mature **fournit un `id` stable** (déduplication), **recommande** idempotence et async,
  **documente** retry, ordre et sécurité.

## Exercices

### Exercice 1 — Auditer un catalogue d'événements

Une API expose ces types : `userCreated`, `user.updated`, `DELETE_USER`, `payment_done`,
`payment.refunded`. Relève les incohérences et propose un catalogue propre et cohérent.

<details>
<summary>Voir le corrigé</summary>

**Incohérences** :

- **Conventions de nommage mélangées** : camelCase (`userCreated`), point (`user.updated`), MAJUSCULES
  avec underscore (`DELETE_USER`), underscore (`payment_done`), point (`payment.refunded`). Cinq styles
  pour cinq types !
- **Temps mélangés** : `DELETE_USER` est un **impératif** (un ordre), pas un fait passé.
- **`payment_done`** est vague et hors convention par rapport à `payment.refunded`.

**Catalogue propre** (convention `ressource.action` au passé, point, snake_case pour l'action) :

```text
user.created
user.updated
user.deleted
payment.succeeded   (ou payment.received)
payment.refunded
```

Tout est désormais homogène, au passé, actionnable, et regroupé par ressource — on pourrait même proposer
un abonnement `user.*` ou `payment.*`.

</details>

### Exercice 2 — Cassant ou non ?

Pour chaque évolution d'un payload, dis si elle est **rétrocompatible** ou **cassante**, et ce que tu fais
en conséquence : (a) ajouter un champ `currency` ; (b) renommer `amount` en `amount_cents` ; (c) changer
`amount` de nombre à chaîne ; (d) ajouter un nouveau **type** d'événement `payment.disputed`.

<details>
<summary>Voir le corrigé</summary>

- **(a) Ajouter `currency`** : **rétrocompatible**. Les anciens consommateurs ignorent le champ inconnu.
  Pas de nouvelle version nécessaire (mais on l'annonce dans le changelog).
- **(b) Renommer `amount` → `amount_cents`** : **cassant**. Le code qui lit `amount` ne trouvera plus
  rien. Nécessite une **nouvelle version** (ou, en transition, **envoyer les deux** champs un temps).
- **(c) `amount` nombre → chaîne** : **cassant**. Changer le **type** d'un champ casse le parsing.
  Nouvelle version obligatoire.
- **(d) Nouveau type `payment.disputed`** : **rétrocompatible**. Les abonnés qui ne s'y sont pas abonnés
  ne le reçoivent pas ; les autres l'ajoutent quand ils veulent. Pas de version cassée, juste une **entrée
  au catalogue** + doc.

Règle : **ajout** (champ ou type) = sûr ; **renommage/suppression/retypage** = cassant → versionner.

</details>

## Quiz

**1.** Quel est le bon format pour un type d'événement ?
- A. `ressource.action` au passé, cohérent dans tout le catalogue
- B. N'importe quoi, du moment que c'est unique
- C. Toujours en MAJUSCULES

**2.** Qu'est-ce qu'un « thin event » ?
- A. Un événement chiffré
- B. Un payload minimal (souvent un id), le consommateur recharge l'état via l'API
- C. Un événement envoyé une seule fois

**3.** Quel changement de payload est **cassant** ?
- A. Ajouter un champ optionnel
- B. Renommer ou supprimer un champ existant
- C. Ajouter un nouveau type d'événement

**4.** Pourquoi figer la version d'un webhook **par abonnement** (à la création) ?
- A. Pour forcer tous les abonnés à migrer en même temps
- B. Pour que chaque abonné évolue à son rythme sans casser son intégration
- C. Pour réduire la taille du payload

<details>
<summary>Voir les réponses</summary>

1. **A** — `ressource.action` au passé, cohérent et actionnable : un contrat durable.
2. **B** — Payload minimal ; l'état faisant foi est rechargé via l'API (frais et sûr).
3. **B** — Renommer/supprimer casse le code existant ; ajouter (champ ou type) est sûr.
4. **B** — La version figée par abonnement laisse chacun migrer quand il le décide.

</details>

## Projet fil rouge

PayHub est maintenant une API de webhooks **bien conçue**. Tu as :

- défini un **catalogue de types** cohérent (`payment.received`, `payment.refunded`…), au passé ;
- choisi une politique **thin/fat** et documenté que l'**API fait foi** ;
- mis en place une stratégie de **versionnage** (par abonnement / en-tête de date) et la distinction
  cassant / non-cassant ;
- soigné l'**intégration** : endpoints en libre-service, événement de test, secret régénérable, journal
  côté abonné, et une **documentation** complète.

PayHub est complet, des deux côtés. Au dernier chapitre, on prend de la hauteur : **récapitulatif**,
**alternatives** aux webhooks (SSE, WebSocket, polling, files de messages), l'**écosystème** (CloudEvents,
passerelles) et les pistes pour aller plus loin.

---

[← Chapitre précédent](10-observabilite.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)
