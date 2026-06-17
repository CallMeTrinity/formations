# Services et injection de dépendances

[← Chapitre précédent](07-formulaires.md) · [Sommaire](README.md) · [Chapitre suivant →](09-securite.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **service** et le **conteneur de services** ;
- comprendre l'**injection de dépendances** et l'**autowiring** ;
- écrire ton propre service et l'**injecter** dans un contrôleur ou un autre service ;
- inspecter les services disponibles avec `debug:autowiring` ;
- utiliser un service fourni par Symfony (le *slugger*) dans un cas concret.

## Qu'est-ce qu'un service

Tu as déjà utilisé des services sans le savoir. Quand tu écris
`public function index(ArticleRepository $repo, EntityManagerInterface $em)`, Symfony te **fournit**
deux objets : un repository et un gestionnaire d'entités. Ces objets sont des **services**.

Un **service** est simplement un objet qui **rend un service**, c'est-à-dire qui accomplit une tâche
réutilisable : accéder à la base, envoyer un e-mail, générer un slug, calculer quelque chose. Par
opposition, une **entité** (`Article`) représente une **donnée**, pas un travail.

> **À retenir** — Une **entité** = une donnée (un article, un commentaire). Un **service** = un
> travail réutilisable (envoyer un mail, parler à la base). Tu écriras tes propres services dès que tu
> auras une logique métier à isoler.

## Le conteneur de services

Symfony range tous les services dans un grand **conteneur de services** (*service container*) : un
annuaire d'objets prêts à l'emploi, chacun créé une seule fois et partagé. Tu ne fais jamais
`new ArticleRepository(...)` toi-même : tu **demandes** le service au conteneur, qui te le donne tout
construit.

Pour voir l'ampleur du catalogue :

```bash
$ php bin/console debug:container
```

La liste est longue : Symfony et ses paquets enregistrent des centaines de services. Tu n'as pas à les
connaître par cœur ; tu apprendras à demander ceux dont tu as besoin.

## L'injection de dépendances

Le mécanisme qui te fournit ces objets s'appelle l'**injection de dépendances** (*dependency
injection*). Le principe : un objet ne **crée pas** lui-même les objets dont il dépend (ses
*dépendances*) ; on les lui **fournit de l'extérieur**.

Comparons. Sans injection, une classe fabrique elle-même ses dépendances — rigide et intestable :

```php
// À ÉVITER : la classe crée elle-même sa dépendance
class ArticleManager
{
    public function __construct()
    {
        $this->em = new EntityManager(/* ... config compliquée ... */);
    }
}
```

Avec injection, on **reçoit** la dépendance par le constructeur — souple et testable :

```php
// BIEN : la dépendance est injectée
class ArticleManager
{
    public function __construct(private EntityManagerInterface $em) {}
}
```

C'est exactement ce que fait Symfony quand il remplit les arguments de tes contrôleurs.

## L'autowiring

Comment Symfony sait-il **quel** service fournir pour `EntityManagerInterface $em` ? Grâce à
l'**autowiring** (« câblage automatique ») : il lit le **type** de l'argument et y branche le service
correspondant. Le type sert d'identifiant.

C'est pour cela que **typer** tes arguments est essentiel : `EntityManagerInterface $em`,
`ArticleRepository $repo`. Sans type, Symfony ne saurait pas quoi injecter.

Pour découvrir ce que tu peux demander par autowiring :

```bash
$ php bin/console debug:autowiring
# ... ou filtré :
$ php bin/console debug:autowiring slug
```

Cette commande liste les types injectables. Le filtre `slug` te montre par exemple
`Symfony\Component\String\Slugger\SluggerInterface` : un service tout prêt qu'on va utiliser.

## Écrire ton propre service

Créons un service qui calcule le **temps de lecture** d'un article (utile pour afficher « 3 min de
lecture »). Crée le fichier `src/Service/ReadingTime.php` :

```php
<?php
// src/Service/ReadingTime.php
namespace App\Service;

class ReadingTime
{
    private const MOTS_PAR_MINUTE = 200;

    /**
     * Renvoie le temps de lecture estimé, en minutes (au moins 1).
     */
    public function minutesPour(string $contenu): int
    {
        $nombreDeMots = str_word_count(strip_tags($contenu));

        return max(1, (int) ceil($nombreDeMots / self::MOTS_PAR_MINUTE));
    }
}
```

C'est une simple classe PHP. **Tu n'as rien à configurer** : grâce à un réglage par défaut
(*autoconfiguration*), toute classe de `src/` est automatiquement enregistrée comme service et
injectable. Tu peux donc la demander immédiatement dans un contrôleur :

```php
use App\Service\ReadingTime;

#[Route('/blog/{slug}', name: 'blog_show')]
public function show(Article $article, ReadingTime $readingTime): Response
{
    return $this->render('blog/show.html.twig', [
        'article' => $article,
        'tempsLecture' => $readingTime->minutesPour($article->getContent()),
    ]);
}
```

Symfony voit le type `ReadingTime`, trouve ton service dans le conteneur, l'instancie une fois et te
l'injecte. Dans Twig : `{{ tempsLecture }} min de lecture`.

> **À retenir** — Toute classe placée dans `src/` est un service injectable par défaut, identifiable
> par son **type**. Crée la classe, type-la là où tu en as besoin : c'est tout.

## Injecter un service dans un autre service

Un service peut dépendre d'autres services : on les déclare dans son **constructeur**, et l'autowiring
fait le reste. Exemple : un service qui génère et enregistre un article complet, en s'appuyant sur le
*slugger* et l'EntityManager.

```php
<?php
// src/Service/ArticlePublisher.php
namespace App\Service;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class ArticlePublisher
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
    ) {}

    public function publier(string $titre, string $contenu): Article
    {
        $article = new Article();
        $article->setTitle($titre);
        $article->setSlug(strtolower($this->slugger->slug($titre)));  // "Mon Titre" -> "mon-titre"
        $article->setContent($contenu);
        $article->setCreatedAt(new \DateTimeImmutable());
        $article->setPublished(true);

        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }
}
```

Le **`SluggerInterface`** est un service fourni par Symfony : il transforme un texte en *slug* propre
pour les URL (« Démarrer avec Symfony ! » devient `demarrer-avec-symfony`). On l'a repéré tout à
l'heure avec `debug:autowiring slug`. On l'injecte sans effort, à côté de l'EntityManager.

> **Astuce** — L'**injection par constructeur** est la norme : déclare tes dépendances en
> `private` dans le constructeur (syntaxe concise de PHP 8). C'est la forme la plus claire et la plus
> testable, car on pourra fournir de fausses dépendances dans les tests (chapitre 10).

## Quand la configuration est nécessaire

L'autowiring couvre la grande majorité des cas. Parfois, un service a besoin d'une **valeur** (pas un
objet) : une clé d'API, un nombre. On la passe via un **paramètre**, configuré dans
`config/services.yaml` :

```yaml
# config/services.yaml
parameters:
    app.mots_par_minute: 200

services:
    App\Service\ReadingTime:
        arguments:
            $motsParMinute: '%app.mots_par_minute%'
```

Ici, on injecte la valeur du paramètre `app.mots_par_minute` dans un argument `$motsParMinute` du
service. C'est l'exception : tant que tu n'injectes que des objets, l'autowiring suffit et tu n'as
**rien** à écrire dans `services.yaml`.

## Résumé

- Un **service** est un objet qui accomplit un **travail réutilisable** (par opposition à une
  **entité**, qui porte une **donnée**).
- Tous les services vivent dans le **conteneur** ; tu les **demandes** au lieu de faire `new`.
- L'**injection de dépendances** fournit à un objet ses dépendances depuis l'extérieur (par le
  constructeur ou les arguments d'action).
- L'**autowiring** branche le bon service d'après le **type** de l'argument : type bien tes arguments.
- Toute classe de **`src/`** est un service injectable **sans configuration** ; on l'injecte par son
  type.
- `debug:autowiring` liste ce qu'on peut injecter ; `services.yaml` ne sert que pour les cas
  particuliers (injecter une **valeur**).

## Exercices

### Exercice 1 — Un service de comptage de mots

Crée un service `WordCounter` avec une méthode `compter(string $texte): int` qui renvoie le nombre de
mots. Injecte-le dans un contrôleur et affiche le résultat pour un article.

<details>
<summary>Voir le corrigé</summary>

La démarche : une classe dans `src/Service/`, injectée par son type.

```php
<?php
// src/Service/WordCounter.php
namespace App\Service;

class WordCounter
{
    public function compter(string $texte): int
    {
        return str_word_count(strip_tags($texte));
    }
}
```

Dans le contrôleur :

```php
use App\Service\WordCounter;

public function show(Article $article, WordCounter $counter): Response
{
    $nbMots = $counter->compter($article->getContent());
    // ... passer $nbMots à la vue
}
```

Aucune configuration : la classe est dans `src/`, donc injectable par son type.

</details>

### Exercice 2 — Slug automatique

Modifie ton action de création d'article pour que le **slug** soit généré automatiquement à partir du
titre avec le `SluggerInterface`, au lieu d'être saisi à la main.

<details>
<summary>Voir le corrigé</summary>

La démarche : injecter `SluggerInterface` et l'appeler avant de persister.

```php
use Symfony\Component\String\Slugger\SluggerInterface;

public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
{
    $article = new Article();
    $form = $this->createForm(ArticleType::class, $article);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $article->setSlug(strtolower($slugger->slug($article->getTitle())));
        $article->setCreatedAt(new \DateTimeImmutable());
        $em->persist($article);
        $em->flush();

        return $this->redirectToRoute('blog_index');
    }

    return $this->render('admin/article_new.html.twig', ['form' => $form]);
}
```

Tu peux alors retirer le champ `slug` du `ArticleType` : il n'a plus à être saisi.

</details>

## Quiz

**1.** Quelle est la différence entre une entité et un service ?
- A. Aucune
- B. Une entité porte une donnée ; un service accomplit un travail réutilisable
- C. Un service est toujours dans `templates/`

**2.** Sur quoi se base l'autowiring pour injecter le bon service ?
- A. Sur le nom de la variable
- B. Sur le **type** déclaré de l'argument
- C. Sur l'ordre des arguments

**3.** Que faut-il configurer pour rendre une classe de `src/Service/` injectable ?
- A. Rien : elle l'est automatiquement
- B. L'ajouter à `services.yaml` une par une
- C. L'enregistrer dans `.env`

**4.** Comment un service reçoit-il un autre service dont il dépend ?
- A. En faisant `new` dans une méthode
- B. Par injection dans son **constructeur**
- C. Via une variable globale

<details>
<summary>Voir les réponses</summary>

1. **B** — Entité = donnée, service = travail réutilisable.
2. **B** — L'autowiring identifie le service par le type de l'argument.
3. **A** — Toute classe de `src/` est un service injectable par défaut.
4. **B** — On déclare ses dépendances dans le constructeur ; l'autowiring les fournit.

</details>

## Projet fil rouge

Tu enrichis le blog avec de la vraie logique métier isolée.

1. Crée le service `ReadingTime` qui calcule le temps de lecture d'un article et affiche
   « X min de lecture » sur la page d'un article.
2. Fais générer le **slug** automatiquement à partir du titre avec `SluggerInterface`, dans ton action
   de création (et d'édition).
3. (Optionnel) Regroupe la création d'un article dans un service `ArticlePublisher` injecté dans le
   contrôleur.

Ton administration fonctionne mais reste accessible à tout le monde. Au chapitre suivant, on installe
la **sécurité** : inscription, connexion, rôles et autorisations, pour réserver l'admin aux personnes
habilitées.

---

[← Chapitre précédent](07-formulaires.md) · [Sommaire](README.md) · [Chapitre suivant →](09-securite.md)
