# Messenger en production : transports, workers et Scheduler

[← Chapitre précédent](04-messenger.md) · [Sommaire](README.md) · [Chapitre suivant →](06-serialisation-api-platform.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- configurer un **transport** asynchrone (file d'attente) et y router tes messages ;
- lancer et comprendre un **worker** qui consomme les messages ;
- gérer les **échecs** : *retries* (nouvelles tentatives) et *failure transport* ;
- garder un worker en vie en production (Supervisor, le principe) ;
- planifier des tâches récurrentes avec le **Scheduler** de Symfony.

## Du synchrone à l'asynchrone : choisir un transport

Au chapitre 4, ton message partait sur le bus mais était traité **immédiatement**. Pour le traiter
**en arrière-plan**, il faut un **transport** : un endroit où le message attend d'être consommé. Le
transport le plus simple à mettre en place utilise **ta base de données** comme file d'attente : pas
de logiciel supplémentaire à installer.

Le transport est décrit par un **DSN** (*Data Source Name*, une URL de connexion). Pour le transport
Doctrine :

```bash
# .env
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=true
```

`doctrine://default` veut dire « utilise la connexion Doctrine par défaut » ; `auto_setup=true`
crée automatiquement la table de file d'attente (`messenger_messages`) la première fois.

On déclare le transport et on **route** nos messages dessus :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        failure_transport: failed   # on y reviendra (les échecs)

        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3        # on retente 3 fois avant d'abandonner
                    delay: 1000           # 1re attente : 1 s
                    multiplier: 2         # puis 2 s, puis 4 s (back-off exponentiel)
            failed: 'doctrine://default?queue_name=failed'   # file des messages en échec

        routing:
            App\Message\CommentPostedNotification: async
            App\Message\ArticlePublished: async
```

Désormais, quand tu postes un commentaire, le message `CommentPostedNotification` n'est **plus traité
tout de suite** : il est **stocké** dans la file et la main est rendue immédiatement à l'utilisateur.
Ton code émetteur n'a pas changé d'une ligne — seule la configuration a évolué.

> **Astuce** — En développement, transport `sync` (immédiat) ou `in-memory` est souvent plus pratique
> pour déboguer. On réserve le transport asynchrone aux environnements où un worker tourne. Tu peux
> router différemment selon l'environnement avec des fichiers `messenger.yaml` par environnement.

## Le worker : consommer la file

Le message attend dans la file. Qui le traite ? Un **worker** : un processus qui tourne en boucle,
prend les messages un par un et appelle leur handler. Tu le lances avec :

```bash
$ php bin/console messenger:consume async -vv
```

- `async` est le nom du transport à consommer.
- `-vv` (verbeux) affiche chaque message traité : pratique pour comprendre ce qui se passe.

Laisse cette commande tournée dans un terminal, poste un commentaire dans un autre, et tu verras le
worker **réveiller** ton handler et envoyer la notification. L'utilisateur, lui, n'a rien attendu.

Quelques commandes utiles autour du worker :

```bash
$ php bin/console messenger:stats          # combien de messages en attente dans chaque transport
$ php bin/console messenger:consume async --limit=10   # traite 10 messages puis s'arrête
$ php bin/console messenger:consume async --time-limit=3600   # tourne 1 h puis s'arrête
```

> **À retenir** — Le **transport** stocke les messages, le **worker** les consomme. Sans worker en
> marche, les messages s'accumulent dans la file sans être traités. C'est un changement de modèle
> mental : le traitement ne se fait plus « pendant la requête ».

## Gérer les échecs : retries et failure transport

En arrière-plan, les erreurs sont normales : le serveur de mail est momentanément indisponible, une
API externe répond mal. Messenger gère ça proprement.

**Les retries.** Si un handler lève une exception, Messenger ne jette pas le message : il le
**remet dans la file** et **réessaie** plus tard, selon la `retry_strategy` configurée (ici jusqu'à 3
fois, avec un délai qui double à chaque fois). Beaucoup de pannes sont passagères : souvent, la 2ᵉ
tentative réussit.

**Le failure transport.** Si toutes les tentatives échouent, le message ne disparaît pas
silencieusement : il est déplacé dans une file spéciale, le **failure transport** (`failed` dans notre
config). Tu peux ensuite **inspecter** et **rejouer** ces messages :

```bash
$ php bin/console messenger:failed:show          # liste les messages en échec
$ php bin/console messenger:failed:show 42 -vv   # détail du message 42 (et l'erreur)
$ php bin/console messenger:failed:retry         # rejoue les messages en échec (interactif)
$ php bin/console messenger:failed:remove 42     # supprime un message définitivement
```

C'est une sécurité majeure : **aucun message n'est perdu**. Une notification qui a échoué peut être
rejouée une fois le problème corrigé.

> **Attention** — Rends tes handlers **idempotents** quand c'est possible : comme un message peut être
> rejoué, traiter deux fois le même message ne doit pas causer de dégât (par exemple, ne pas
> compter deux fois, ne pas envoyer deux mails identiques). En pratique : vérifie un état avant
> d'agir, ou marque le travail comme fait.

## Garder un worker vivant en production

En développement, tu lances `messenger:consume` à la main. En production, le worker doit tourner
**en permanence** et **redémarrer** s'il s'arrête. C'est le rôle d'un **gestionnaire de processus**
comme **Supervisor** (sous Linux). Le principe : tu décris ton worker dans un fichier de
configuration, et Supervisor le maintient en vie.

```ini
; /etc/supervisor/conf.d/messenger-worker.conf (exemple de principe)
[program:messenger-consume]
command=php /var/www/blog/bin/console messenger:consume async --time-limit=3600
numprocs=2                  ; deux workers en parallèle
autostart=true
autorestart=true            ; redémarre s'il s'arrête
user=www-data
```

Le `--time-limit=3600` fait redémarrer chaque worker toutes les heures : c'est volontaire, ça évite
les fuites de mémoire sur un processus PHP qui vit trop longtemps. Supervisor le relance aussitôt.

> **À retenir** — Quand tu **déploies** une nouvelle version, les workers en cours tournent encore
> l'ancien code. Lance `php bin/console messenger:stop-workers` après un déploiement : les workers
> finissent leur message courant puis s'arrêtent proprement, et le gestionnaire les relance avec le
> nouveau code. On reverra le déploiement au chapitre 12.

## Planifier des tâches : le Scheduler

Certaines tâches ne sont pas déclenchées par un utilisateur mais par le **temps** : envoyer un
récapitulatif chaque matin, nettoyer les commentaires de spam chaque nuit, recalculer des statistiques
chaque heure. Historiquement, on utilisait `cron` (vu dans la formation Linux & Bash). Symfony propose
le **Scheduler**, intégré à Messenger, qui décrit ces tâches **en PHP**, au plus près du code.

```bash
$ composer require symfony/scheduler
```

L'idée : un **schedule** (planning) liste des messages à envoyer à intervalle régulier. À chaque
échéance, le Scheduler **dispatche le message** : il rejoint le bus et son handler comme n'importe
quel autre message. Tu réutilises donc tout ce que tu sais déjà.

Réutilisons le message `ArticlePublished`… non : créons une tâche dédiée, un récapitulatif quotidien.

```php
<?php
// src/Message/DailyStatsReport.php
namespace App\Message;

final class DailyStatsReport
{
    // Pas de données nécessaires : c'est une tâche périodique simple.
}
```

```php
<?php
// src/MessageHandler/DailyStatsReportHandler.php
namespace App\MessageHandler;

use App\Message\DailyStatsReport;
use App\Repository\ArticleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DailyStatsReportHandler
{
    public function __construct(
        private ArticleRepository $articles,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(DailyStatsReport $message): void
    {
        $count = count($this->articles->findBy(['published' => true]));
        $this->logger->info('Récapitulatif quotidien', ['articles_publies' => $count]);
        // En vrai : on enverrait un e-mail aux admins.
    }
}
```

On décrit le planning avec un **schedule provider**, marqué par l'attribut `#[AsSchedule]` :

```php
<?php
// src/Scheduler/MainSchedule.php
namespace App\Scheduler;

use App\Message\DailyStatsReport;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('main')]   // 'main' est le nom de ce planning
final class MainSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            // Tous les jours à 8 h : on envoie le message DailyStatsReport.
            RecurringMessage::cron('0 8 * * *', new DailyStatsReport()),
            // On peut aussi exprimer un intervalle : toutes les heures, par exemple :
            RecurringMessage::every('1 hour', new \App\Message\HourlyCleanup()),
        );
    }
}
```

`RecurringMessage::cron('0 8 * * *', ...)` utilise la **syntaxe cron** classique (minute, heure, jour
du mois, mois, jour de la semaine). `RecurringMessage::every('1 hour', ...)` exprime un intervalle en
langage naturel. Pour que le planning s'exécute, on **consomme le transport `scheduler_main`** avec un
worker (même mécanisme que Messenger) :

```bash
$ php bin/console messenger:consume scheduler_main -vv
```

À chaque échéance, le Scheduler dépose le message sur le bus, et ton handler s'exécute. En production,
ce worker tourne en permanence via Supervisor, exactement comme le worker `async`.

> **Astuce** — Le Scheduler vit dans **ton application** : un seul endroit pour voir toutes les tâches
> planifiées, versionné avec ton code, et qui réutilise tes handlers. C'est plus lisible et plus
> portable qu'une liste de lignes `cron` éparpillées sur un serveur.

## Résumé

- Un **transport** (DSN, ex. `doctrine://default`) stocke les messages ; on y **route** chaque message
  dans `messenger.yaml`. Le code émetteur ne change pas.
- Un **worker** (`messenger:consume`) consomme la file et appelle les handlers. Sans worker, les
  messages s'accumulent.
- Les **retries** (back-off exponentiel) rejouent automatiquement les échecs passagers ; le **failure
  transport** garde les messages définitivement échoués pour inspection et rejeu.
- Rends les handlers **idempotents** : un message peut être rejoué.
- En production, un **gestionnaire de processus** (Supervisor) garde les workers vivants ;
  `messenger:stop-workers` les recharge après un déploiement.
- Le **Scheduler** planifie des tâches récurrentes en PHP (`#[AsSchedule]`, `RecurringMessage`), qui
  réutilisent le mécanisme de Messenger.

## Exercices

### Exercice 1 — Basculer en asynchrone et observer

Configure le transport `doctrine`, route `CommentPostedNotification` dessus, puis poste un
commentaire **sans** lancer de worker. Vérifie avec `messenger:stats` que le message attend. Lance
ensuite le worker et observe le traitement.

<details>
<summary>Voir le corrigé</summary>

La démarche : on prouve que le message attend tant qu'aucun worker ne tourne.

```bash
# Après avoir posté un commentaire, sans worker :
$ php bin/console messenger:stats
# Sortie attendue (extrait) :
# async         1        ← un message en attente

# On lance le worker :
$ php bin/console messenger:consume async -vv
# Le worker affiche le traitement du CommentPostedNotification, puis :
$ php bin/console messenger:stats
# async         0        ← la file est vide
```

Tu as vu concrètement le découplage : émission (immédiate) et traitement (différé, par le worker).

</details>

### Exercice 2 — Une tâche planifiée

Crée un message `WeeklyDigest` et planifie-le tous les lundis à 9 h via le Scheduler. Le handler se
contente de journaliser « digest hebdo envoyé ».

<details>
<summary>Voir le corrigé</summary>

La démarche : un message vide, un handler qui logue, et une entrée dans le schedule.

```php
// src/Message/WeeklyDigest.php
namespace App\Message;
final class WeeklyDigest {}
```

```php
// src/MessageHandler/WeeklyDigestHandler.php
#[AsMessageHandler]
final class WeeklyDigestHandler
{
    public function __construct(private \Psr\Log\LoggerInterface $logger) {}
    public function __invoke(WeeklyDigest $message): void
    {
        $this->logger->info('Digest hebdo envoyé');
    }
}
```

Dans `MainSchedule::getSchedule()`, ajoute :

```php
RecurringMessage::cron('0 9 * * 1', new WeeklyDigest()),   // lundi (1) à 9 h
```

Lance `php bin/console messenger:consume scheduler_main -vv` pour activer le planning.

</details>

## Quiz

**1.** À quoi sert un transport Messenger ?
- A. À afficher une page
- B. À stocker les messages en attendant qu'un worker les consomme
- C. À valider un formulaire

**2.** Que se passe-t-il si aucun worker ne tourne et que des messages sont routés en asynchrone ?
- A. Ils sont traités quand même
- B. Ils s'accumulent dans la file sans être traités
- C. Ils sont supprimés

**3.** Que devient un message dont toutes les tentatives échouent ?
- A. Il est perdu
- B. Il est déplacé dans le failure transport pour inspection et rejeu
- C. Il bloque le worker pour toujours

**4.** Pourquoi rendre un handler idempotent ?
- A. Pour aller plus vite
- B. Parce qu'un message peut être rejoué : le traiter deux fois ne doit pas causer de dégât
- C. C'est obligatoire pour le Scheduler

**5.** Qu'apporte le Scheduler par rapport à un `cron` système ?
- A. Il décrit les tâches en PHP, dans l'application, et réutilise les handlers Messenger
- B. Il remplace la base de données
- C. Il rend les workers inutiles

<details>
<summary>Voir les réponses</summary>

1. **B** — Le transport est la file d'attente des messages.
2. **B** — Sans worker, rien n'est consommé ; les messages attendent.
3. **B** — Le failure transport conserve les échecs définitifs.
4. **B** — Un message peut être rejoué ; l'idempotence évite les effets en double.
5. **A** — Tâches en PHP, versionnées, réutilisant le mécanisme Messenger.

</details>

## Projet fil rouge

1. Configure le transport `doctrine` et route `CommentPostedNotification` et `ArticlePublished` en
   asynchrone. Vérifie que poster un commentaire est **instantané** côté utilisateur.
2. Lance un worker et observe le traitement ; provoque une erreur volontaire dans un handler pour voir
   les **retries** puis le passage au **failure transport**.
3. Installe le Scheduler et planifie un **récapitulatif quotidien** (`DailyStatsReport`) à 8 h qui
   journalise le nombre d'articles publiés.
4. Note dans `NOTES.md` la commande worker à lancer en production et le rôle de
   `messenger:stop-workers` au déploiement.

Ton blog traite désormais ses tâches lentes en arrière-plan et sait planifier des traitements
récurrents. Au prochain chapitre, on ouvre l'application au monde extérieur avec une API propre.

---

[← Chapitre précédent](04-messenger.md) · [Sommaire](README.md) · [Chapitre suivant →](06-serialisation-api-platform.md)
