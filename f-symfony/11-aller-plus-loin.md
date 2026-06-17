# Aller plus loin : commandes, événements, mails, API

[← Chapitre précédent](10-tests.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer une **commande** console personnalisée (les *invokable commands* de Symfony 8) ;
- réagir à un **événement** du framework avec un *subscriber* ;
- envoyer un **e-mail** avec le composant Mailer ;
- exposer une petite **API JSON** depuis un contrôleur.

Ce chapitre est un tour d'horizon : chaque section ouvre une porte que tu approfondiras au besoin.
L'objectif est que tu saches que ces outils existent et comment démarrer.

## Les commandes console

Tu as beaucoup utilisé `bin/console` (commandes fournies par Symfony). Tu peux écrire **les tiennes**,
pour les tâches hors navigateur : import de données, nettoyage, statistiques, traitements planifiés.

Symfony 8 introduit les **commandes invocables** (*invokable commands*) : une commande est une classe
avec une unique méthode `__invoke()`. Génère-en une :

```bash
$ php bin/console make:command app:articles:stats
```

Adapte `src/Command/ArticlesStatsCommand.php` :

```php
<?php
// src/Command/ArticlesStatsCommand.php
namespace App\Command;

use App\Repository\ArticleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:articles:stats',
    description: 'Affiche le nombre d\'articles publiés.',
)]
class ArticlesStatsCommand
{
    public function __construct(private ArticleRepository $articleRepository) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $publies = $this->articleRepository->findBy(['published' => true]);

        $io->success(sprintf('%d article(s) publié(s).', count($publies)));

        return Command::SUCCESS;
    }
}
```

Décortiquons :

- **`#[AsCommand(name: ..., description: ...)]`** déclare la commande et son nom d'appel.
- Le **constructeur** injecte les services dont tu as besoin (ici le repository) — l'autowiring du
  chapitre 8 fonctionne aussi pour les commandes.
- **`__invoke`** contient le travail. **`SymfonyStyle`** (`$io`) offre un affichage soigné :
  `$io->success()`, `$io->error()`, `$io->table()`, `$io->ask()`…
- On renvoie **`Command::SUCCESS`** (0) ou `Command::FAILURE` (1) : le **code de retour**, lu par les
  scripts et l'automatisation.

Lance ta commande :

```bash
$ php bin/console app:articles:stats
```

Pour accepter un **argument**, ajoute un paramètre typé à `__invoke` avec l'attribut `#[Argument]` :

```php
use Symfony\Component\Console\Attribute\Argument;

public function __invoke(SymfonyStyle $io, #[Argument] string $categorie = ''): int
{
    // $categorie reçoit la valeur passée : bin/console app:articles:stats symfony
    // ...
}
```

> **À retenir** — Une commande, c'est un point d'entrée **en ligne de commande** au lieu d'une URL.
> Même framework, mêmes services injectés ; seul change le « déclencheur ». Parfait pour les tâches
> planifiées (via `cron`, vu dans la formation Linux & Bash) ou les traitements de fond.

## Réagir à un événement

Symfony émet des **événements** à des moments clés (une requête arrive, une réponse part, une
exception survient…). Tu peux **t'abonner** à un événement pour exécuter du code à ce moment-là, sans
modifier le cœur du framework. C'est le mécanisme des *event subscribers*.

```bash
$ php bin/console make:subscriber ArticleViewSubscriber
```

Un *subscriber* déclare les événements qui l'intéressent et la méthode à exécuter pour chacun :

```php
public static function getSubscribedEvents(): array
{
    return [
        KernelEvents::EXCEPTION => 'onKernelException',
    ];
}

public function onKernelException(ExceptionEvent $event): void
{
    // par exemple : journaliser l'erreur, envoyer une alerte...
}
```

Les événements servent à **brancher du comportement** de façon découplée : compter les vues d'un
article, journaliser, notifier. Ton code reste séparé du contrôleur, donc réutilisable et testable.

> **Astuce** — Pour exécuter une seule méthode sur un seul événement, l'alternative moderne est un
> *listener* déclaré par l'attribut `#[AsEventListener]` directement sur la méthode. Subscriber et
> listener font le même travail ; choisis le plus lisible selon le cas.

## Envoyer un e-mail

Le composant **Mailer** envoie des e-mails. Installe-le si besoin :

```bash
$ composer require symfony/mailer
```

Configure l'envoi via `MAILER_DSN` dans `.env` (l'adresse du serveur d'envoi ; en développement, on
peut tout capturer dans le profiler sans envoyer pour de vrai). Puis, dans un contrôleur ou un service,
injecte `MailerInterface` et compose le message :

```php
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

public function notifier(MailerInterface $mailer): void
{
    $email = (new Email())
        ->from('blog@exemple.test')
        ->to('auteur@exemple.test')
        ->subject('Nouveau commentaire sur ton article')
        ->text('Quelqu\'un vient de commenter ton article.');

    $mailer->send($email);
}
```

Pour un e-mail mis en forme, `TemplatedEmail` permet d'utiliser un **template Twig** comme corps du
message. En développement, ouvre l'onglet **E-mails** du profiler : Symfony y affiche les messages
« envoyés » sans rien expédier réellement.

> **Attention** — Envoyer un e-mail peut être lent : ne fais jamais patienter l'utilisateur pendant
> l'envoi. En production, on **diffère** l'envoi avec le composant **Messenger** (une file de
> messages traités en arrière-plan). Retiens le nom : c'est l'outil des traitements asynchrones.

## Exposer une API JSON

Toutes les réponses ne sont pas du HTML. Une **API** renvoie des données brutes, souvent en **JSON**,
pour être consommées par une application mobile ou du JavaScript. Le raccourci `$this->json()`
construit une réponse JSON :

```php
use App\Repository\ArticleRepository;

#[Route('/api/articles', name: 'api_articles', methods: ['GET'])]
public function apiList(ArticleRepository $articleRepository): Response
{
    $articles = $articleRepository->findBy(['published' => true]);

    return $this->json($articles);
}
```

`$this->json()` **sérialise** automatiquement tes objets (les transforme en JSON) grâce au composant
Serializer. Visite `/api/articles` : tu obtiens un tableau JSON de tes articles.

Pour contrôler **quels champs** sont exposés (et éviter d'exposer un mot de passe, par exemple), on
utilise les **groupes de sérialisation** : on annote les propriétés à inclure et on précise le groupe
à l'appel. Pour une API complète et robuste (pagination, filtres, documentation), l'écosystème propose
**API Platform**, un projet bâti sur Symfony.

> **À retenir** — `$this->json($data)` suffit pour exposer rapidement des données. Dès que l'API
> devient sérieuse, regarde les **groupes de sérialisation** puis **API Platform**.

## Résumé

- Une **commande** console (Symfony 8 : classe avec `__invoke`, attribut `#[AsCommand]`) exécute des
  tâches hors navigateur ; elle profite de l'injection de dépendances et renvoie un **code de retour**.
- Un **event subscriber** (ou listener) branche du code sur les **événements** du framework, de façon
  découplée.
- Le composant **Mailer** (`MailerInterface` + `Email`) envoie des e-mails ; on diffère les envois
  lents avec **Messenger**.
- `$this->json($data)` expose des données en **JSON** (API) en les sérialisant automatiquement ;
  groupes de sérialisation et **API Platform** pour aller plus loin.

## Exercices

### Exercice 1 — Une commande de comptage

Crée une commande `app:comments:count` qui affiche le nombre total de commentaires en base, avec un
joli message de succès.

<details>
<summary>Voir le corrigé</summary>

La démarche : une commande invocable qui injecte le `CommentRepository`.

```php
<?php
// src/Command/CommentsCountCommand.php
namespace App\Command;

use App\Repository\CommentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:comments:count', description: 'Compte les commentaires.')]
class CommentsCountCommand
{
    public function __construct(private CommentRepository $commentRepository) {}

    public function __invoke(SymfonyStyle $io): int
    {
        $total = count($this->commentRepository->findAll());
        $io->success(sprintf('%d commentaire(s) en base.', $total));

        return Command::SUCCESS;
    }
}
```

Lance : `php bin/console app:comments:count`.

</details>

### Exercice 2 — API d'un article

Ajoute un point d'API `GET /api/articles/{slug}` qui renvoie un seul article au format JSON, ou une
réponse 404 s'il n'existe pas.

<details>
<summary>Voir le corrigé</summary>

La démarche : on récupère l'article (récupération automatique du chapitre 6) et on renvoie du JSON.

```php
use App\Entity\Article;

#[Route('/api/articles/{slug}', name: 'api_article_show', methods: ['GET'])]
public function apiShow(Article $article): Response
{
    return $this->json($article);
}
```

Si le slug n'existe pas, la récupération automatique renvoie déjà une **404** : rien de plus à écrire.
Visite `/api/articles/un-slug-valide` pour voir le JSON.

</details>

## Quiz

**1.** Dans une commande invocable de Symfony 8, quelle méthode contient le travail ?
- A. `execute()`
- B. `__invoke()`
- C. `run()`

**2.** À quoi sert un *event subscriber* ?
- A. À exécuter du code en réaction à un événement du framework
- B. À créer des routes
- C. À hacher les mots de passe

**3.** Pourquoi diffère-t-on l'envoi des e-mails lents en production ?
- A. Pour économiser de la mémoire
- B. Pour ne pas faire attendre l'utilisateur (traitement en arrière-plan via Messenger)
- C. Parce que Mailer ne fonctionne pas en synchrone

**4.** Que fait `$this->json($articles)` dans un contrôleur ?
- A. Il enregistre les articles en base
- B. Il renvoie une réponse JSON en sérialisant les objets
- C. Il rend un template Twig

<details>
<summary>Voir les réponses</summary>

1. **B** — Les commandes invocables de Symfony 8 reposent sur `__invoke()`.
2. **A** — Un subscriber réagit aux événements du framework, de façon découplée.
3. **B** — On évite de bloquer l'utilisateur ; Messenger traite l'envoi en arrière-plan.
4. **B** — `json()` sérialise les objets et renvoie une réponse JSON.

</details>

## Projet fil rouge

Tu ajoutes des finitions professionnelles à ton blog.

1. Crée la commande `app:articles:stats` qui affiche le nombre d'articles publiés.
2. Envoie un **e-mail** (capturé par le profiler en dev) à l'auteur quand un commentaire est posté sur
   son article.
3. Expose une petite **API JSON** : `GET /api/articles` (liste des articles publiés) et
   `GET /api/articles/{slug}` (un article).
4. (Optionnel) Branche un *subscriber* qui journalise chaque consultation d'article.

Ton application est complète : pages, base de données, formulaires, sécurité, tests et finitions. Au
dernier chapitre, on la **déploie**, on récapitule les bonnes pratiques et on trace ta route vers le
niveau avancé.

---

[← Chapitre précédent](10-tests.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)
