# Le bus de messages : Messenger

[← Chapitre précédent](03-doctrine-avance-performance.md) · [Sommaire](README.md) · [Chapitre suivant →](05-messenger-production-scheduler.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer **pourquoi** on déporte certains traitements en arrière-plan ;
- distinguer un **message** et son **handler** (gestionnaire) ;
- envoyer un message sur le **bus** depuis un service ou un contrôleur ;
- comprendre la différence entre traitement **synchrone** et **asynchrone** ;
- écrire ton premier couple message + handler dans le blog.

## Pourquoi l'asynchrone ?

Reprends l'envoi d'e-mail de la partie 1 : quand un lecteur poste un commentaire, on prévient
l'auteur par mail. Le problème, c'est que l'envoi d'un e-mail peut prendre **plusieurs secondes** (le
serveur de mail répond lentement, ou est momentanément indisponible). Or, tout ce temps, **le lecteur
attend** que sa page se recharge. Pire : si l'envoi échoue, il voit une erreur alors que son
commentaire, lui, est bien enregistré.

L'idée de l'**asynchrone** est simple : séparer **« ce que l'utilisateur doit attendre »** de
**« ce qui peut se faire plus tard »**. Enregistrer le commentaire doit être immédiat. Envoyer le
mail peut attendre une fraction de seconde et se faire **en arrière-plan**, pendant que l'utilisateur
a déjà sa réponse.

C'est exactement le rôle du composant **Messenger** : tu déposes un **message** (« il faut notifier
l'auteur ») dans une file, tu rends immédiatement la main à l'utilisateur, et un **processus séparé**
traite le message tranquillement de son côté.

> **À retenir** — On déporte en arrière-plan tout ce qui est **lent**, **faillible** ou **non
> essentiel à la réponse immédiate** : e-mails, génération de fichiers, appels à des services
> externes, traitements lourds.

## Installer Messenger

```bash
$ composer require symfony/messenger
```

Le paquet ajoute un fichier de configuration `config/packages/messenger.yaml` et un dossier conseillé
`src/Message/` (les messages) et `src/MessageHandler/` (les gestionnaires). On va remplir ces deux
dossiers.

## Le vocabulaire : message, handler, bus

Trois mots à fixer une fois pour toutes.

- Un **message** est un objet **simple** qui décrit **une intention** : « envoie la notification de
  commentaire numéro 42 ». C'est une classe sans logique, comme un DTO (chapitre 2) : juste les
  données nécessaires au traitement.
- Un **handler** (gestionnaire) est la classe qui **fait le travail** quand ce message arrive. Un
  message, un handler.
- Le **bus** (*message bus*) est le **tapis roulant** : tu y déposes un message, il le route vers le
  bon handler.

Le découplage est total : le code qui **émet** le message ne sait pas **qui** le traite, ni
**quand**. C'est ce qui rend le système souple.

```text
   Contrôleur / service
          │  dispatch(message)
          ▼
      ┌────────┐     route vers      ┌─────────┐
      │  BUS   │ ──────────────────► │ HANDLER │  fait le travail
      └────────┘                     └─────────┘
```

## Écrire un message et son handler

Créons le message qui dit « notifie l'auteur d'un nouveau commentaire ». Il transporte juste
l'**identifiant** du commentaire (on récupérera l'objet complet côté handler).

```php
<?php
// src/Message/CommentPostedNotification.php
namespace App\Message;

// Un message : une intention + les données minimales pour la réaliser.
final class CommentPostedNotification
{
    public function __construct(
        public readonly int $commentId,
    ) {}
}
```

> **Attention** — Transporte des **identifiants**, pas des entités Doctrine. Un message peut être
> sérialisé (transformé en texte) puis traité **plus tard**, dans un autre processus : l'entité
> d'origine n'y existerait plus telle quelle. On passe l'`id` et on **recharge** l'objet dans le
> handler.

Maintenant le handler. L'attribut `#[AsMessageHandler]` indique à Symfony quelle classe traite ce
message ; la méthode `__invoke` reçoit le message en argument typé.

```php
<?php
// src/MessageHandler/CommentPostedNotificationHandler.php
namespace App\MessageHandler;

use App\Message\CommentPostedNotification;
use App\Notifier\CommentNotifierInterface;
use App\Repository\CommentRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CommentPostedNotificationHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private CommentNotifierInterface $notifier,   // l'interface du chapitre 2
    ) {}

    public function __invoke(CommentPostedNotification $message): void
    {
        // On recharge l'entité à partir de l'identifiant transporté.
        $comment = $this->commentRepository->find($message->commentId);

        if ($comment === null) {
            return;   // le commentaire a pu être supprimé entre-temps : on ne fait rien
        }

        $this->notifier->notifyNewComment($comment);
    }
}
```

Le handler profite de l'**autowiring** : il reçoit le repository et le notifier comme n'importe quel
service. Symfony relie automatiquement le message `CommentPostedNotification` à ce handler grâce au
**type de l'argument** de `__invoke`.

## Émettre le message

Reste à **déposer** le message sur le bus. Tu injectes `MessageBusInterface` et tu appelles
`dispatch`. Reprenons le service `CommentPublisher` du chapitre 2 :

```php
<?php
// src/Service/CommentPublisher.php
namespace App\Service;

use App\Entity\Article;
use App\Entity\Comment;
use App\Message\CommentPostedNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CommentPublisher
{
    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $bus,
    ) {}

    public function publish(Comment $comment, Article $article): Comment
    {
        $comment->setArticle($article);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $this->em->persist($comment);
        $this->em->flush();   // le commentaire a maintenant un id

        // On dépose l'intention « notifier » sur le bus. On ne bloque pas l'utilisateur.
        $this->bus->dispatch(new CommentPostedNotification($comment->getId()));

        return $comment;
    }
}
```

C'est tout. Le contrôleur appelle `publish()`, l'utilisateur reçoit immédiatement sa réponse, et la
notification part par le bus.

> **À retenir** — Émettre un message tient en une ligne : `$bus->dispatch(new MonMessage(...))`. Le
> code émetteur ne sait rien du handler. Ce découplage est la grande force de Messenger.

## Synchrone par défaut, asynchrone sur décision

Voici un point qui surprend souvent : par défaut, **Messenger traite le message immédiatement**, dans
le même processus (mode **synchrone**). À ce stade, tu n'as donc encore rien gagné en rapidité — tu as
juste **mieux organisé** ton code (l'émission est découplée du traitement).

Le vrai gain vient quand tu **routes** le message vers un **transport asynchrone** : une file
d'attente d'où un processus séparé (le *worker*) viendra le chercher. La bascule se fait **en
configuration**, sans changer ton code :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'   # une file (Doctrine, Redis...) — chapitre 5
        routing:
            App\Message\CommentPostedNotification: async   # ce message part en asynchrone
```

C'est la beauté du système : tu écris ton message et ton handler **une fois**, et tu décides ensuite,
par configuration, ce qui reste synchrone et ce qui passe en file. On met en place ces transports au
chapitre 5.

> **Astuce** — Tu peux vérifier le routage de tes messages avec :
> ```bash
> $ php bin/console debug:messenger
> ```
> La commande liste les messages connus et leur handler. Pratique pour confirmer que ton handler est
> bien détecté.

## Voir Messenger dans le profiler

En développement, ouvre le profiler après avoir posté un commentaire : un onglet **Messages**
récapitule ce qui a transité par le bus. Tu y vois ton `CommentPostedNotification`, le handler appelé,
et le temps pris. C'est ton outil pour vérifier que l'émission fonctionne avant même de configurer un
transport asynchrone.

## Résumé

- L'**asynchrone** déporte en arrière-plan ce qui est lent, faillible ou non essentiel à la réponse
  immédiate (e-mails, fichiers, appels externes).
- Un **message** décrit une intention et transporte des **identifiants** (jamais d'entité Doctrine).
- Un **handler** (`#[AsMessageHandler]`, méthode `__invoke`) fait le travail ; Symfony relie message
  et handler par le **type** de l'argument.
- On émet avec `MessageBusInterface::dispatch(new MonMessage(...))` : le code émetteur ignore qui
  traite.
- Par défaut le traitement est **synchrone** ; on bascule en **asynchrone** par configuration
  (transport + routing), sans changer le code.

## Exercices

### Exercice 1 — Un message « article publié »

Crée un message `ArticlePublished` transportant l'`id` d'un article, et un handler qui, pour
l'instant, écrit simplement une ligne dans les logs (`LoggerInterface`). Émets-le depuis
`ArticlePublisher::create`.

<details>
<summary>Voir le corrigé</summary>

La démarche : un message minimal, un handler qui journalise, une émission après le `flush`.

```php
<?php
// src/Message/ArticlePublished.php
namespace App\Message;

final class ArticlePublished
{
    public function __construct(public readonly int $articleId) {}
}
```

```php
<?php
// src/MessageHandler/ArticlePublishedHandler.php
namespace App\MessageHandler;

use App\Message\ArticlePublished;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ArticlePublishedHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(ArticlePublished $message): void
    {
        $this->logger->info('Article publié', ['id' => $message->articleId]);
    }
}
```

Dans `ArticlePublisher::create`, après le `flush` :
`$this->bus->dispatch(new ArticlePublished($article->getId()));` (injecte `MessageBusInterface`).

</details>

### Exercice 2 — Vérifier le routage

Lance `php bin/console debug:messenger` et identifie tes messages et leurs handlers. Que se passe-t-il
si un message n'a aucun handler ?

<details>
<summary>Voir le corrigé</summary>

La commande liste chaque message géré et le handler associé. Si tu vois ton `ArticlePublished` relié à
`ArticlePublishedHandler`, tout est branché.

Un message **sans handler** déclenche une erreur `NoHandlerForMessageException` au moment du
`dispatch` (en mode synchrone) : Messenger ne sait pas quoi en faire. C'est souvent le signe d'un
oubli de l'attribut `#[AsMessageHandler]` ou d'un mauvais type dans `__invoke`.

</details>

## Quiz

**1.** Pourquoi déporter l'envoi d'un e-mail en arrière-plan ?
- A. Pour économiser de la mémoire
- B. Pour ne pas faire attendre l'utilisateur et isoler une opération lente/faillible
- C. Parce que Mailer ne fonctionne qu'en asynchrone

**2.** Que doit transporter un message Messenger ?
- A. L'entité Doctrine complète
- B. Des identifiants et données simples, rechargées côté handler
- C. La requête HTTP

**3.** Comment Symfony relie-t-il un message à son handler ?
- A. Par le nom du fichier
- B. Par le type de l'argument de `__invoke` et l'attribut `#[AsMessageHandler]`
- C. Par une configuration manuelle obligatoire

**4.** Par défaut, un message dispatché est traité…
- A. de façon asynchrone
- B. de façon synchrone, dans le même processus, jusqu'à ce qu'on configure un transport
- C. jamais, tant qu'aucun worker ne tourne

<details>
<summary>Voir les réponses</summary>

1. **B** — On évite de bloquer l'utilisateur sur une opération lente et faillible.
2. **B** — Des identifiants ; on recharge l'entité dans le handler.
3. **B** — Le type de l'argument de `__invoke`, avec `#[AsMessageHandler]`.
4. **B** — Synchrone par défaut ; l'asynchrone vient avec un transport (chapitre 5).

</details>

## Projet fil rouge

1. Installe Messenger (`composer require symfony/messenger`).
2. Crée le message `CommentPostedNotification` et son handler, qui réutilise ton
   `CommentNotifierInterface` du chapitre 2.
3. Émets ce message depuis `CommentPublisher::publish`, après le `flush`.
4. Poste un commentaire et vérifie dans le **profiler** (onglet Messages) que le message a bien
   transité et que le handler a été appelé.

L'envoi de notification est maintenant **découplé** du fait de poster un commentaire. Au prochain
chapitre, on le bascule réellement en arrière-plan avec un transport, un worker, et on gère les
échecs — puis on planifie des tâches récurrentes.

---

[← Chapitre précédent](03-doctrine-avance-performance.md) · [Sommaire](README.md) · [Chapitre suivant →](05-messenger-production-scheduler.md)
