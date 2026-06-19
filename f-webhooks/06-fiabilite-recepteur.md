# Fiabilité côté récepteur : idempotence et déduplication

[← Chapitre précédent](05-securiser-reception.md) · [Sommaire](README.md) · [Chapitre suivant →](07-emettre-webhooks.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer pourquoi un même webhook peut arriver **plusieurs fois** (« au moins une fois ») ;
- définir l'**idempotence** et pourquoi elle est indispensable côté récepteur ;
- **dédupliquer** un événement par son `id` avec une table dédiée et une contrainte d'unicité ;
- gérer les problèmes d'**ordre d'arrivée** (un événement plus récent reçu avant un plus ancien) ;
- raisonner sur les cas limites (traitement interrompu, doublon concurrent).

## « Au moins une fois » : le doublon est la norme, pas l'exception

Les systèmes de webhooks garantissent presque toujours une livraison **« au moins une fois »**
(*at-least-once*), **jamais** « exactement une fois ». Autrement dit : tu es sûr de recevoir chaque
événement, mais tu peux le recevoir **plusieurs fois**. Les raisons sont structurelles :

- l'émetteur t'envoie l'événement, tu le traites, mais ta réponse `200` **se perd** sur le réseau :
  l'émetteur croit à un échec et **rejoue** ;
- ton endpoint répond juste **après le délai d'attente** : l'émetteur a déjà décidé de **rejouer** ;
- l'émetteur lui-même renvoie un événement par sécurité ;
- côté toi, **Messenger** rejoue un message dont le handler a échoué (chapitre 4).

```text
Toi reçois evt_123 ──► traites ──► réponds 200 ──╳ (réponse perdue)
Émetteur n'a rien reçu ──► REJOUE evt_123 ──► tu le reçois une 2e fois !
```

Conclusion : **tu ne peux pas empêcher les doublons**. Tu dois donc **les rendre inoffensifs**. C'est tout
l'enjeu de l'idempotence.

> **À retenir** — Les webhooks sont livrés **au moins une fois**. Recevoir deux fois (ou plus) le même
> événement est **normal**. Ton traitement doit être conçu pour qu'un doublon **n'ait aucun effet
> supplémentaire**.

## L'idempotence : traiter deux fois = traiter une fois

Une opération est **idempotente** si l'exécuter **plusieurs fois** produit le **même résultat** que de
l'exécuter **une seule** fois. Quelques exemples pour ancrer l'idée :

- « **mettre** le statut de la commande à `payée` » est idempotent : le refaire ne change rien.
- « **créditer** le compte de 49 € » **n'est pas** idempotent : le refaire ajoute 49 € en trop. Un
  doublon ici, c'est de l'argent perdu.
- « envoyer un e-mail de confirmation » n'est pas idempotent non plus : le client reçoit deux mails.

Deux stratégies, complémentaires :

1. **Écrire des traitements naturellement idempotents** quand c'est possible (préférer « fixer une valeur »
   à « incrémenter », « créer si absent » à « créer »).
2. **Dédupliquer en amont** : reconnaître qu'un événement a **déjà été traité** grâce à son `id` unique, et
   l'ignorer dans ce cas. C'est la méthode générale, qui marche même quand le traitement n'est pas
   idempotent par nature.

## Dédupliquer par l'`id` d'événement

Chaque événement a un identifiant **unique et stable** (chapitre 2) : `evt_1P9xY2abc123`. Deux livraisons
du **même** événement portent le **même** `id`. L'idée : tenir un **registre des événements déjà traités**
et refuser d'en traiter un deux fois.

### Une entité pour le registre

On crée une entité qui mémorise les événements vus, avec une **contrainte d'unicité** sur l'identifiant
fourni par le fournisseur.

```php
<?php
// src/Entity/ProcessedEvent.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
// Contrainte d'unicité : impossible d'insérer deux fois le même externalId.
#[ORM\UniqueConstraint(name: 'uniq_external_id', columns: ['external_id'])]
class ProcessedEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    // L'id de l'événement tel que fourni par Stripe (ex. evt_1P9xY2abc123).
    #[ORM\Column(length: 255)]
    private string $externalId;

    #[ORM\Column]
    private \DateTimeImmutable $processedAt;

    public function __construct(string $externalId)
    {
        $this->externalId = $externalId;
        $this->processedAt = new \DateTimeImmutable();
    }
}
```

Génère la migration et applique-la :

```bash
$ php bin/console make:migration
$ php bin/console doctrine:migrations:migrate
```

> **À retenir** — La **contrainte d'unicité** en base est ta filet de sécurité ultime. Même si deux
> doublons arrivent **exactement en même temps**, la base **refusera** la deuxième insertion. Ne te repose
> pas seulement sur un `SELECT` préalable : entre le `SELECT` et l'`INSERT`, un autre processus peut
> insérer (c'est une *race condition*). La contrainte tranche.

### Dédupliquer dans le handler

On déplace la déduplication dans le **handler** (côté worker), juste avant le traitement métier. Si
l'événement est déjà connu, on s'arrête net.

```php
<?php
// src/MessageHandler/StripeWebhookReceivedHandler.php (extrait)
use App\Entity\ProcessedEvent;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

#[AsMessageHandler]
final class StripeWebhookReceivedHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(StripeWebhookReceived $message): void
    {
        // 1. A-t-on déjà traité cet événement ?
        $repo = $this->em->getRepository(ProcessedEvent::class);
        if ($repo->findOneBy(['externalId' => $message->eventId]) !== null) {
            $this->logger->info('Doublon ignoré', ['id' => $message->eventId]);

            return; // déjà traité : on ne refait rien.
        }

        // 2. On marque l'événement comme traité, en s'appuyant sur la contrainte d'unicité.
        try {
            $this->em->persist(new ProcessedEvent($message->eventId));
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Un autre worker l'a inséré entre-temps : c'est un doublon concurrent. On sort.
            $this->logger->info('Doublon concurrent ignoré', ['id' => $message->eventId]);

            return;
        }

        // 3. Traitement métier, exécuté UNE seule fois par événement.
        match ($message->eventType) {
            'payment_intent.succeeded' => $this->onPaiementReussi($message->payload),
            'payment_intent.payment_failed' => $this->onPaiementEchoue($message->payload),
            default => null,
        };
    }
}
```

On a **deux niveaux** : le `findOneBy` filtre la plupart des doublons (lecture rapide), et le `catch` sur
`UniqueConstraintViolationException` rattrape le cas rare de **deux doublons simultanés**. Ceinture **et**
bretelles.

> **Attention** — Le `findOneBy` seul ne suffit pas. Deux workers peuvent lire « pas encore traité » en
> même temps, puis traiter tous les deux. Seule la **contrainte d'unicité** en base garantit qu'un seul
> passera. C'est pour ça qu'on **attrape** l'exception d'unicité plutôt que de l'ignorer.

### Marquer avant ou après le traitement ?

Question subtile : doit-on enregistrer `ProcessedEvent` **avant** ou **après** le traitement métier ?

- **Avant** (notre choix ci-dessus) : si le traitement plante **après** l'insertion, l'événement est marqué
  « traité » alors qu'il ne l'est pas vraiment → risque de **ne jamais** le traiter. À éviter si le
  traitement est risqué.
- **Après** : si le traitement réussit mais que l'enregistrement du marqueur échoue, on risque de **re**
  traiter → il faut alors que le traitement soit idempotent.
- **Dans la même transaction** : le plus sûr. On marque **et** on traite dans **une transaction unique**,
  qui réussit ou échoue **en bloc**. Ainsi, « marqué » équivaut exactement à « traité ».

```php
// Variante robuste : marquage + traitement dans une seule transaction.
$this->em->wrapInTransaction(function () use ($message) {
    $this->em->persist(new ProcessedEvent($message->eventId)); // peut lever l'unicité
    $this->traiterMetier($message);                            // le vrai travail
}); // commit atomique : tout, ou rien
```

C'est l'approche à privilégier quand le traitement écrit en base. Si le traitement appelle un **service
externe** non transactionnel (envoyer un mail, appeler une API), vise plutôt à le rendre **idempotent**
côté métier, car aucune transaction de base ne peut annuler un mail déjà parti.

## Le problème de l'ordre d'arrivée

Deuxième piège de fiabilité : **rien ne garantit l'ordre**. Les webhooks peuvent arriver **dans le
désordre**. Exemple chez Stripe : `customer.subscription.updated` (statut `active`) puis, une seconde
après, `customer.subscription.updated` (statut `canceled`). À cause des *retries* et de la concurrence, tu
peux recevoir le **`canceled`** *avant* le **`active`**. Si tu appliques bêtement « le dernier reçu », tu
réactives un abonnement annulé.

Trois stratégies, selon le besoin :

1. **Se fier à l'horodatage de l'événement**, pas à l'ordre d'arrivée. Stocke le `created` (ou un numéro
   de version) de l'objet et **ignore** un événement plus **ancien** que ce que tu as déjà appliqué.
2. **Recharger l'état courant** auprès du fournisseur. Plutôt que de faire confiance au payload reçu,
   appelle l'API (« quel est le statut **actuel** de cet abonnement ? ») : tu obtiens la **vérité du
   moment**, indépendante de l'ordre des webhooks. Coûteux mais fiable.
3. **Concevoir des transitions tolérantes** : ne traite l'événement que s'il fait avancer un état de
   façon cohérente (une commande `payée` ne redevient pas `en attente`).

```php
// Exemple de stratégie 1 : ignorer un événement plus ancien que l'état déjà appliqué.
$horodatageEvenement = $message->payload['created'] ?? 0;
if ($horodatageEvenement < $commande->getDernierEvenementAppliqueAt()) {
    $this->logger->info('Événement obsolète ignoré (arrivé dans le désordre)');
    return;
}
```

> **À retenir** — Ne **suppose jamais** que les webhooks arrivent dans l'ordre. Fie-toi à
> l'**horodatage/version** porté par l'événement, ou **recharge** l'état courant auprès du fournisseur.
> L'ordre d'arrivée n'est pas fiable.

## Récapitulatif des défenses de fiabilité

Un récepteur robuste empile :

- **validation + signature** (chapitres 4–5) : rejeter ce qui est invalide ou non authentique ;
- **déduplication par `id`** + contrainte d'unicité : un doublon n'a aucun effet ;
- **traitement idempotent** ou **transaction atomique** : « marqué » = « traité » ;
- **gestion de l'ordre** : se fier à l'horodatage/version, ou recharger l'état.

Avec ça, peu importe que l'émetteur livre une fois, deux fois ou dans le désordre : l'état final est
**correct**.

## Résumé

- Les webhooks sont livrés **au moins une fois** : recevoir des **doublons** est normal et inévitable.
- L'**idempotence** garantit que traiter un événement plusieurs fois équivaut à le traiter une fois.
- On **déduplique** par l'**`id`** d'événement, via une entité dédiée et surtout une **contrainte
  d'unicité** en base (qui tranche les doublons concurrents).
- Combiner `findOneBy` (filtrage rapide) **et** capture de `UniqueConstraintViolationException` (cas
  concurrent).
- Marquer + traiter dans **une même transaction** rend « marqué » équivalent à « traité » ; pour les
  effets externes (mail, API), viser l'idempotence métier.
- L'**ordre d'arrivée n'est pas garanti** : se fier à l'**horodatage/version** de l'événement, ou
  **recharger** l'état courant auprès du fournisseur.

## Exercices

### Exercice 1 — Idempotent ou non ?

Pour chaque traitement déclenché par un webhook `payment_intent.succeeded`, dis s'il est idempotent, et
si non, comment le rendre sûr face aux doublons : (a) `commande.statut = 'payée'` ; (b)
`compteurVentes = compteurVentes + 1` ; (c) `envoyerMailConfirmation($client)` ; (d)
`creerFactureSiAbsente($commande)`.

<details>
<summary>Voir le corrigé</summary>

- **(a)** Idempotent : fixer une valeur donne le même résultat à chaque fois.
- **(b)** **Non** idempotent : un doublon incrémenterait deux fois. À sécuriser par **déduplication** (ne
  traiter qu'une fois par `id`), ou recalculer le compteur à partir des données plutôt que d'incrémenter.
- **(c)** **Non** idempotent : deux mails partiraient. La déduplication par `id` évite le second envoi ;
  sinon, mémoriser « mail déjà envoyé pour cette commande ».
- **(d)** Idempotent **par conception** : « créer **si absente** » ne crée pas de doublon. C'est le bon
  réflexe : préférer « créer si absent » à « créer ».

La leçon : soit le traitement est idempotent par nature (a, d), soit on s'appuie sur la **déduplication
par `id`** pour neutraliser les cas non idempotents (b, c).

</details>

### Exercice 2 — Le désordre qui casse tout

PayHub reçoit pour une commande : à 10:00:02 un `payment_intent.payment_failed`, puis à 10:00:01 (oui,
plus tôt !) un `payment_intent.succeeded` — l'ordre d'arrivée est inversé par rapport à l'ordre réel.
Avec un traitement « j'applique le dernier reçu », quel est le résultat ? Comment corriger ?

<details>
<summary>Voir le corrigé</summary>

**Résultat erroné** : le dernier **reçu** est le `succeeded` (arrivé en second), donc la commande finit
`payée`… alors que le paiement a en réalité **échoué** après coup. On livre une commande non payée.

Attends — relisons : à 10:00:02 l'événement réel est `failed`, à 10:00:01 c'est `succeeded`. L'ordre réel
est donc `succeeded` (01) **puis** `failed` (02) : la vérité finale est **échec**. Mais comme ils
arrivent dans le désordre, « le dernier reçu » peut être le `succeeded`, d'où une commande marquée payée à
tort.

**Correction** : se fier à l'**horodatage de l'événement** (`created`), pas à l'ordre d'arrivée. On
n'applique un événement que s'il est **plus récent** que le dernier appliqué. Ainsi le `failed` (02)
gagne, car il est postérieur, quel que soit l'ordre de réception. Alternative : **recharger** le statut
réel du paiement auprès de Stripe pour trancher.

</details>

## Quiz

**1.** Que signifie « livraison au moins une fois » ?
- A. Tu reçois chaque événement exactement une fois
- B. Tu reçois chaque événement, mais possiblement plusieurs fois
- C. Tu peux ne jamais recevoir certains événements

**2.** Qu'est-ce qu'une opération idempotente ?
- A. Une opération chiffrée
- B. Une opération qui, répétée, donne le même résultat qu'une seule exécution
- C. Une opération qui ne touche pas la base

**3.** Pourquoi une contrainte d'unicité en base, et pas juste un `SELECT` préalable ?
- A. Parce que le `SELECT` est trop lent
- B. Parce qu'entre le `SELECT` et l'`INSERT`, un autre processus peut insérer le même id (race condition)
- C. Parce que Doctrine l'exige

**4.** Comment gérer des webhooks qui arrivent dans le désordre ?
- A. Toujours appliquer le dernier reçu
- B. Se fier à l'horodatage/version de l'événement, ou recharger l'état courant
- C. Refuser tout événement reçu en retard

<details>
<summary>Voir les réponses</summary>

1. **B** — Garanti au moins une fois, donc potentiellement plusieurs : les doublons sont normaux.
2. **B** — Répéter l'opération ne change pas le résultat final.
3. **B** — Le `SELECT` seul laisse une fenêtre de concurrence ; la contrainte d'unicité tranche en base.
4. **B** — L'ordre d'arrivée n'est pas fiable ; on se base sur l'horodatage/version ou on recharge l'état.

</details>

## Projet fil rouge

PayHub est désormais un récepteur **robuste**. Tu as :

- créé l'entité `ProcessedEvent` avec **contrainte d'unicité** sur l'`id` Stripe ;
- ajouté la **déduplication** dans le handler (`findOneBy` + capture de l'exception d'unicité) ;
- compris comment rendre le traitement **idempotent** (transaction atomique, « créer si absent ») ;
- appris à gérer le **désordre** d'arrivée via l'horodatage de l'événement.

La moitié « récepteur » de PayHub est complète : il reçoit, répond vite, vérifie, déduplique et traite
proprement. Au chapitre suivant, on **change de casquette** : PayHub devient **émetteur** et envoie ses
**propres** webhooks à des boutiques abonnées.

---

[← Chapitre précédent](05-securiser-reception.md) · [Sommaire](README.md) · [Chapitre suivant →](07-emettre-webhooks.md)
