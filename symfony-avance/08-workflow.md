# Le composant Workflow

[← Chapitre précédent](07-securite-avancee.md) · [Sommaire](README.md) · [Chapitre suivant →](09-symfony-ux-stimulus-turbo.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- reconnaître un **cycle de vie** métier et pourquoi le modéliser explicitement ;
- distinguer un **state machine** d'un **workflow** ;
- déclarer un workflow (états, transitions) dans Symfony ;
- **appliquer** une transition et **vérifier** si elle est possible ;
- bloquer une transition avec un **guard** et réagir avec les **événements** de workflow.

## Le problème : les états éparpillés

Un article de blog n'est pas juste « publié » ou « pas publié ». Dans une vraie rédaction, il suit un
**cycle** : un auteur écrit un **brouillon**, le soumet **en relecture**, un relecteur le **publie**
(ou le renvoie en brouillon), et plus tard on l'**archive**. Chaque étape autorise certaines actions
et en interdit d'autres : on ne publie pas un brouillon directement, on n'archive pas un article jamais
publié.

Sans outil, cette logique finit dispersée en `if` un peu partout :

```php
// La logique d'état éparpillée : fragile et difficile à suivre
if ($article->getStatus() === 'review' && $user->isReviewer()) {
    $article->setStatus('published');
    $article->setPublishedAt(new \DateTimeImmutable());
}
// ... et ailleurs, d'autres if qui changent le statut, avec des règles qui se contredisent
```

Le risque : des transitions incohérentes (passer de « brouillon » à « archivé » sans publication), des
règles dupliquées, et aucune vue d'ensemble du cycle. Le composant **Workflow** existe précisément pour
**rassembler** ce cycle en un seul endroit, déclaratif et vérifiable.

> **À retenir** — Dès qu'une entité a un **statut** qui évolue selon des règles, tu as un cycle de
> vie. Le modéliser explicitement avec Workflow rend les règles claires, centralisées et impossibles
> à contourner par accident.

## State machine ou workflow ?

Symfony propose deux variantes du même composant :

- Une **state machine** (machine à états) : l'objet est dans **un seul état à la fois**. C'est le cas
  d'un article : brouillon, OU en relecture, OU publié, OU archivé. Les transitions vont d'un état
  unique vers un autre.
- Un **workflow** : l'objet peut être dans **plusieurs états en même temps** (utile pour des processus
  parallèles, ex. une commande à la fois « payée » et « en préparation »).

Pour notre article, c'est une **state machine**. Bonne nouvelle : la configuration est quasi
identique, on change juste le `type`.

## Déclarer le workflow

On installe le composant si besoin, puis on décrit la machine à états en configuration.

```bash
$ composer require symfony/workflow
```

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        article_publishing:
            type: state_machine            # un seul état à la fois
            marking_store:
                type: method               # l'état est lu/écrit via getStatus()/setStatus()
                property: status
            supports:
                - App\Entity\Article       # ce workflow s'applique aux articles
            initial_marking: draft         # état de départ d'un nouvel article
            places:                        # les états possibles
                - draft
                - review
                - published
                - archived
            transitions:
                to_review:                 # nom de la transition
                    from: draft
                    to: review
                publish:
                    from: review
                    to: published
                back_to_draft:
                    from: review
                    to: draft
                archive:
                    from: published
                    to: archived
```

Quelques mots de vocabulaire :

- les **places** sont les **états** possibles (`draft`, `review`, `published`, `archived`) ;
- les **transitions** sont les **passages autorisés** d'un état à un autre, chacun avec un **nom**
  (`to_review`, `publish`…) ;
- le **marking** est l'état courant de l'objet, stocké ici dans sa propriété `status` (Symfony
  appelle `getStatus()` / `setStatus()`).

Côté entité, il faut donc une propriété `status` (une simple `string`) avec son getter/setter, et une
migration pour la colonne. L'`initial_marking: draft` fait qu'un nouvel article naît en brouillon.

> **Astuce** — Visualise ton workflow ! La commande suivante génère un schéma :
> ```bash
> $ php bin/console workflow:dump article_publishing | dot -Tpng -o workflow.png
> ```
> (nécessite Graphviz). Un schéma vaut mille `if` : tu vois d'un coup tous les états et passages.

## Appliquer une transition

On manipule le workflow via le service `WorkflowInterface`. On l'injecte en précisant **quel**
workflow par le nom de l'argument (`$articlePublishingStateMachine` ou via l'attribut `#[Target]`).

```php
<?php
// src/Service/ArticleWorkflow.php
namespace App\Service;

use App\Entity\Article;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\Attribute\Target;

class ArticleWorkflow
{
    public function __construct(
        #[Target('article_publishing')]                 // on cible notre workflow par son nom
        private WorkflowInterface $workflow,
    ) {}

    public function submitForReview(Article $article): void
    {
        // Avant d'appliquer, on vérifie que la transition est possible depuis l'état courant.
        if ($this->workflow->can($article, 'to_review')) {
            $this->workflow->apply($article, 'to_review');   // change l'état : draft → review
        }
    }
}
```

Deux méthodes clés :

- **`can($article, 'transition')`** : la transition est-elle possible **depuis l'état actuel** ? Renvoie
  `true`/`false` sans rien modifier.
- **`apply($article, 'transition')`** : exécute la transition (met à jour `status`). Si elle est
  impossible, elle lève une exception — d'où l'intérêt de tester avec `can` d'abord, ou d'attraper
  l'exception.

Tu peux aussi lister les transitions **disponibles** maintenant, par exemple pour n'afficher que les
boutons pertinents :

```php
$transitions = $this->workflow->getEnabledTransitions($article);
// Pour un article en 'review' : ['publish', 'back_to_draft']
```

> **À retenir** — `can` interroge, `apply` exécute, `getEnabledTransitions` liste. Avec ces trois
> méthodes, l'interface ne propose **que** des actions valides : impossible d'arriver dans un état
> incohérent.

## Bloquer une transition : les guards

Parfois, une transition est structurellement possible mais soumise à une **condition métier** : on ne
peut publier un article que s'il a un titre **et** un contenu non vides, ou seulement si l'utilisateur
a le rôle relecteur. C'est le rôle d'un **guard** (« garde ») : du code qui peut **bloquer** une
transition au dernier moment.

Le composant émet un événement `workflow.<nom>.guard.<transition>` ; on y réagit pour interdire le
passage.

```php
<?php
// src/Workflow/PublishGuardListener.php
namespace App\Workflow;

use App\Entity\Article;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsEventListener(event: 'workflow.article_publishing.guard.publish')]
class PublishGuardListener
{
    public function __invoke(GuardEvent $event): void
    {
        /** @var Article $article */
        $article = $event->getSubject();

        // On interdit la publication d'un article sans contenu.
        if (trim($article->getContent()) === '') {
            $event->setBlocked(true, 'Impossible de publier un article sans contenu.');
        }
    }
}
```

Désormais, `can($article, 'publish')` renvoie `false` tant que le contenu est vide, et `apply`
échouerait avec le message du guard. La règle métier est **rattachée à la transition**, pas perdue
dans un contrôleur.

> **Attention** — Distingue bien **transition** (structurellement permise par le graphe) et **guard**
> (condition métier qui peut la bloquer). Le graphe dit « de `review` on peut aller vers `published` » ;
> le guard ajoute « …à condition que l'article ait du contenu ».

## Réagir aux transitions : les événements

Le workflow émet aussi des événements **autour** d'une transition réussie. Les plus utiles :

- `workflow.<nom>.transition.<transition>` : pendant la transition ;
- `workflow.<nom>.entered.<place>` : quand l'objet **entre** dans un état.

C'est l'endroit idéal pour brancher un **effet de bord** : renseigner `publishedAt` à la publication,
ou — en réutilisant le chapitre 5 — **dispatcher un message** pour notifier en arrière-plan.

```php
<?php
// src/Workflow/ArticlePublishedListener.php
namespace App\Workflow;

use App\Entity\Article;
use App\Message\ArticlePublished;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Workflow\Event\EnteredEvent;

#[AsEventListener(event: 'workflow.article_publishing.entered.published')]
class ArticlePublishedListener
{
    public function __construct(private MessageBusInterface $bus) {}

    public function __invoke(EnteredEvent $event): void
    {
        /** @var Article $article */
        $article = $event->getSubject();

        $article->setPublishedAt(new \DateTimeImmutable());

        // On réutilise le message du chapitre 4-5 : notification asynchrone.
        $this->bus->dispatch(new ArticlePublished($article->getId()));
    }
}
```

Tu vois la synergie : le **workflow** décide *quand* publier, les **événements** branchent les effets,
et **Messenger** les exécute en arrière-plan. Chaque composant garde son rôle.

> **À retenir** — Le workflow ne fait pas que changer un statut : ses **événements** sont des points
> d'accroche propres pour déclencher tout ce qui doit accompagner un changement d'état, sans polluer
> le code métier principal.

## Afficher les actions dans Twig

API Platform et tes contrôleurs profitent du workflow ; Twig aussi. La fonction `workflow_can` n'affiche
un bouton que si la transition est permise :

```twig
{# templates/admin/article/show.html.twig #}
{% if workflow_can(article, 'publish') %}
    <form method="post" action="{{ path('admin_article_publish', {id: article.id}) }}">
        <button>Publier</button>
    </form>
{% endif %}

<p>État actuel : {{ article.status }}</p>
```

L'interface reflète exactement les règles du workflow : pas de bouton « Publier » sur un brouillon qui
doit d'abord passer en relecture, ni sur un article au contenu vide (le guard).

## Résumé

- Un **cycle de vie** (statut qui évolue selon des règles) gagne à être modélisé explicitement plutôt
  qu'éparpillé en `if`.
- Une **state machine** = un seul état à la fois (notre article) ; un **workflow** = plusieurs états
  simultanés.
- On déclare **places** (états) et **transitions** (passages nommés) en configuration ; le **marking**
  est stocké dans une propriété de l'entité.
- `can` interroge, `apply` exécute, `getEnabledTransitions` liste les actions valides.
- Un **guard** bloque une transition selon une condition métier ; les **événements**
  (`transition`, `entered`) branchent les effets de bord (dates, notifications via Messenger).
- En Twig, `workflow_can` n'affiche que les actions réellement permises.

## Exercices

### Exercice 1 — Ajouter une transition « unpublish »

Ajoute une transition `unpublish` qui ramène un article de `published` à `draft`. Vérifie avec
`workflow:dump` (ou mentalement) que le graphe reste cohérent, puis expose un bouton conditionnel dans
Twig.

<details>
<summary>Voir le corrigé</summary>

La démarche : une transition supplémentaire dans la configuration, puis l'affichage conditionnel.

```yaml
transitions:
    # ... transitions existantes
    unpublish:
        from: published
        to: draft
```

Dans Twig :

```twig
{% if workflow_can(article, 'unpublish') %}
    <button>Repasser en brouillon</button>
{% endif %}
```

Le bouton n'apparaît que sur un article publié. Pense à `php bin/console cache:clear` après un
changement de configuration de workflow.

</details>

### Exercice 2 — Un guard sur la relecture

Empêche la transition `to_review` si l'article a un titre de moins de 5 caractères (un brouillon
vraiment trop vide ne part pas en relecture).

<details>
<summary>Voir le corrigé</summary>

La démarche : un listener sur l'événement guard de la transition `to_review`.

```php
<?php
// src/Workflow/ToReviewGuardListener.php
namespace App\Workflow;

use App\Entity\Article;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

#[AsEventListener(event: 'workflow.article_publishing.guard.to_review')]
class ToReviewGuardListener
{
    public function __invoke(GuardEvent $event): void
    {
        /** @var Article $article */
        $article = $event->getSubject();

        if (mb_strlen(trim($article->getTitle())) < 5) {
            $event->setBlocked(true, 'Le titre est trop court pour partir en relecture.');
        }
    }
}
```

`workflow_can(article, 'to_review')` renverra `false` tant que le titre est trop court.

</details>

## Quiz

**1.** Quand modéliser un cycle de vie avec Workflow ?
- A. Pour toute entité
- B. Dès qu'une entité a un statut qui évolue selon des règles
- C. Uniquement pour les utilisateurs

**2.** Quelle est la différence entre une state machine et un workflow ?
- A. Aucune
- B. La state machine n'autorise qu'un état à la fois ; le workflow plusieurs simultanément
- C. Le workflow est plus rapide

**3.** Que fait `$workflow->can($article, 'publish')` ?
- A. Applique la transition
- B. Indique si la transition est possible depuis l'état courant, sans rien modifier
- C. Supprime l'article

**4.** À quoi sert un guard ?
- A. À ajouter une transition
- B. À bloquer une transition selon une condition métier
- C. À paginer une liste

**5.** Où brancher l'envoi d'une notification à la publication ?
- A. Dans un guard
- B. Dans un listener sur l'événement `entered.published`, qui dispatche un message Messenger
- C. Dans la configuration YAML

<details>
<summary>Voir les réponses</summary>

1. **B** — Un statut qui évolue selon des règles = un cycle de vie.
2. **B** — État unique (state machine) vs états multiples (workflow).
3. **B** — `can` interroge sans modifier.
4. **B** — Le guard impose une condition métier sur une transition permise.
5. **B** — L'événement `entered` est le point d'accroche ; Messenger exécute en arrière-plan.

</details>

## Projet fil rouge

1. Ajoute une propriété `status` à `Article` (migration incluse) et déclare la state machine
   `article_publishing` (`draft → review → published → archived`).
2. Crée un service `ArticleWorkflow` et des actions d'admin pour appliquer `to_review`, `publish`,
   `archive`, en n'affichant que les boutons permis (`workflow_can`).
3. Ajoute un **guard** qui interdit de publier un article sans contenu.
4. Branche un **listener** sur `entered.published` qui renseigne `publishedAt` et dispatche
   `ArticlePublished` (réutilise le message des chapitres 4-5).

Le cycle éditorial du blog est désormais explicite et inviolable. Au prochain chapitre, on rend
l'interface dynamique avec Symfony UX : Stimulus et Turbo.

---

[← Chapitre précédent](07-securite-avancee.md) · [Sommaire](README.md) · [Chapitre suivant →](09-symfony-ux-stimulus-turbo.md)
