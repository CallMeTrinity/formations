# Les formulaires

[← Chapitre précédent](06-doctrine-relations.md) · [Sommaire](README.md) · [Chapitre suivant →](08-services-injection.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer un **formulaire** Symfony lié à une entité avec un **FormType** ;
- **afficher** ce formulaire en Twig et le **traiter** dans le contrôleur ;
- ajouter des **règles de validation** sur une entité avec des attributs `#[Assert\...]` ;
- générer un **CRUD** complet (créer, lire, modifier, supprimer) avec `make:crud` ;
- comprendre la protection **CSRF** offerte gratuitement par les formulaires Symfony.

## Pourquoi les formulaires Symfony

Saisir un article en dur dans le code n'est pas tenable. Il te faut un **formulaire** HTML. Tu
pourrais l'écrire à la main, mais il faudrait alors : générer les champs, récupérer les données
envoyées, les valider, les remettre dans l'objet, réafficher les erreurs, et te protéger contre les
attaques CSRF. Beaucoup de plomberie, encore.

Le composant **Form** de Symfony fait tout cela. Tu décris ton formulaire une fois (un **FormType**),
et Symfony s'occupe de :

- **générer** le HTML des champs ;
- **lier** les données saisies à ton entité (le *data binding*) ;
- **valider** selon des règles déclarées ;
- afficher les **messages d'erreur** au bon endroit ;
- protéger contre les attaques **CSRF** automatiquement.

## Créer un FormType

Un **FormType** est une classe qui décrit les champs d'un formulaire. Génère celui de l'article :

```bash
$ php bin/console make:form ArticleType
# The name of Entity or fully qualified model class name that the new form will be bound to:
# > Article
```

Le maker crée `src/Form/ArticleType.php`. Allégé, il ressemble à ceci :

```php
<?php
// src/Form/ArticleType.php
namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('slug')
            ->add('content')
            ->add('published')
            ->add('category')   // une liste déroulante des catégories, automatiquement
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
```

Deux méthodes structurent un FormType :

- **`buildForm`** liste les **champs** avec `->add('nomDePropriété')`. Symfony devine le bon type de
  champ d'après l'entité (texte, case à cocher pour un booléen, liste déroulante pour une relation).
- **`configureOptions`** fixe les options ; la plus importante est **`data_class`**, qui relie le
  formulaire à l'entité `Article`. C'est ce lien qui permet le remplissage automatique de l'objet.

> **Astuce** — Tu peux préciser le type d'un champ et ses options :
> `->add('title', TextType::class, ['label' => 'Titre de l\'article'])`. Symfony propose un type par
> besoin : `TextareaType`, `CheckboxType`, `ChoiceType`, `DateType`, `EmailType`, etc.

## Traiter le formulaire dans le contrôleur

Le contrôleur crée le formulaire, le confronte à la requête, et agit s'il est valide :

```php
use App\Entity\Article;
use App\Form\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

#[Route('/admin/article/nouveau', name: 'admin_article_new')]
public function new(Request $request, EntityManagerInterface $em): Response
{
    $article = new Article();
    $form = $this->createForm(ArticleType::class, $article);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $article->setCreatedAt(new \DateTimeImmutable());
        $em->persist($article);
        $em->flush();

        $this->addFlash('success', 'Article créé.');

        return $this->redirectToRoute('blog_index');
    }

    return $this->render('admin/article_new.html.twig', [
        'form' => $form,
    ]);
}
```

Déroulons la logique, car ce schéma est **toujours le même** :

1. On crée l'objet (`new Article()`) et le formulaire lié (`createForm`).
2. **`handleRequest($request)`** : si la requête contient des données soumises, Symfony les injecte
   dans l'objet. Sinon, il ne fait rien (premier affichage).
3. **`isSubmitted() && isValid()`** : vrai uniquement si le formulaire a été envoyé **et** que toutes
   les règles de validation passent.
4. Si valide : on complète, on `persist`/`flush`, on ajoute un **message flash** (un message affiché
   une fois à l'écran suivant), et on **redirige**.
5. Sinon (premier affichage ou erreurs) : on rend la vue, qui réaffiche le formulaire — avec les
   erreurs s'il y en a.

> **À retenir** — Une seule action gère **l'affichage** et **le traitement** du formulaire. Le couple
> `isSubmitted() && isValid()` est le point de décision. Après un succès, on **redirige** toujours
> (pour éviter qu'un rafraîchissement renvoie le formulaire).

## Afficher le formulaire en Twig

Dans le template, la fonction `form()` génère tout le HTML du formulaire :

```twig
{# templates/admin/article_new.html.twig #}
{% extends 'base.html.twig' %}

{% block title %}Nouvel article{% endblock %}

{% block body %}
    <h1>Nouvel article</h1>
    {{ form(form) }}
{% endblock %}
```

`{{ form(form) }}` produit la balise `<form>`, tous les champs avec leurs `<label>`, les messages
d'erreur et le **jeton CSRF** caché. Pour maîtriser la mise en page, tu peux aussi rendre les champs un
par un :

```twig
{{ form_start(form) }}
    {{ form_row(form.title) }}
    {{ form_row(form.content) }}
    {{ form_row(form.category) }}
    <button type="submit">Enregistrer</button>
{{ form_end(form) }}
```

`form_start`/`form_end` encadrent le formulaire, `form_row` rend un champ complet (label + champ +
erreurs).

> **À retenir** — Le **jeton CSRF** (*Cross-Site Request Forgery*) est un champ caché qui prouve que
> la soumission vient bien de ton site. Symfony l'ajoute et le vérifie **automatiquement** : tu es
> protégé sans rien faire.

## La validation

Un formulaire valide est un formulaire dont les données respectent tes **règles**. On déclare ces
règles directement sur l'**entité**, avec des attributs `#[Assert\...]` du composant Validator :

```php
<?php
// src/Entity/Article.php
use Symfony\Component\Validator\Constraints as Assert;

class Article
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Le titre doit faire au moins {{ limit }} caractères.')]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $content = null;

    // ...
}
```

Quelques contraintes courantes :

| Contrainte | Vérifie que… |
| --- | --- |
| `#[Assert\NotBlank]` | la valeur n'est pas vide |
| `#[Assert\Length(min:, max:)]` | la longueur est dans l'intervalle |
| `#[Assert\Email]` | la valeur est un e-mail valide |
| `#[Assert\Positive]` | le nombre est strictement positif |
| `#[Assert\Choice([...])]` | la valeur fait partie d'une liste |

Quand `isValid()` est appelé, Symfony vérifie toutes ces contraintes. Si l'une échoue, le formulaire
est invalide et le message s'affiche **automatiquement** sous le champ concerné.

> **Attention** — Ne confonds pas les deux niveaux. **`#[ORM\Column]`** décrit la base de données (le
> stockage). **`#[Assert\...]`** décrit la validation des données saisies. Une colonne `length: 255`
> limite le stockage ; un `Assert\Length(max: 255)` affiche un message propre **avant** d'atteindre
> cette limite. Les deux sont complémentaires.

## Générer un CRUD complet

CRUD signifie *Create, Read, Update, Delete* : les quatre opérations de base sur une entité. Plutôt
que d'écrire chaque action à la main, `make:crud` génère le contrôleur, le FormType et tous les
templates d'un coup :

```bash
$ php bin/console make:crud Article
```

Le maker crée un `ArticleCrudController` (ou nom proche) avec les actions `index`, `new`, `show`,
`edit`, `delete`, le `ArticleType` si besoin, et les templates correspondants. C'est un **point de
départ** idéal : tu obtiens un CRUD fonctionnel que tu personnalises ensuite. Lis le code généré : tu
y reconnaîtras exactement le schéma `handleRequest` / `isSubmitted` / `isValid` vu plus haut.

> **Astuce** — Symfony 8 introduit les **formulaires multi-étapes** (*multi-step forms*) : un
> formulaire long découpé en étapes guidées, avec validation par étape. Utile pour une inscription
> complexe ou un tunnel de commande. Garde-le en tête pour plus tard ; le formulaire simple suffit
> largement ici.

## Résumé

- Le composant **Form** génère le HTML, lie les données à l'entité, valide, affiche les erreurs et
  protège du **CSRF** automatiquement.
- Un **FormType** (`make:form`) décrit les champs dans `buildForm` et se lie à l'entité via
  `data_class` dans `configureOptions`.
- Le contrôleur suit toujours : `createForm` → `handleRequest` → `isSubmitted() && isValid()` →
  `persist`/`flush` → **redirection**.
- En Twig, `{{ form(form) }}` rend tout, ou `form_start`/`form_row`/`form_end` pour personnaliser.
- La **validation** se déclare sur l'entité avec `#[Assert\...]` ; à distinguer de `#[ORM\Column]` qui
  décrit le stockage.
- **`make:crud`** génère un CRUD complet prêt à personnaliser.

## Exercices

### Exercice 1 — Valider la catégorie

Ajoute une règle de validation pour que le champ `name` d'une `Category` soit obligatoire et fasse
entre 2 et 100 caractères. Teste en soumettant un nom vide dans un formulaire de catégorie.

<details>
<summary>Voir le corrigé</summary>

La démarche : des attributs `#[Assert\...]` sur la propriété de l'entité `Category`.

```php
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Column(length: 100)]
#[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire.')]
#[Assert\Length(min: 2, max: 100)]
private ?string $name = null;
```

Avec un nom vide, `isValid()` renvoie `false` et le message « Le nom de la catégorie est obligatoire. »
s'affiche sous le champ. Aucune ligne n'est enregistrée.

</details>

### Exercice 2 — Formulaire d'édition

À partir de ton action `new`, crée une action `edit` sur `/admin/article/{id}/modifier` qui récupère
l'article existant et réaffiche le **même** `ArticleType` pré-rempli pour le modifier.

<details>
<summary>Voir le corrigé</summary>

La démarche : on récupère l'entité existante au lieu d'en créer une neuve ; tout le reste est
identique. Comme l'objet a déjà un `id`, Doctrine fera un `UPDATE` au lieu d'un `INSERT`.

```php
#[Route('/admin/article/{id}/modifier', name: 'admin_article_edit')]
public function edit(Article $article, Request $request, EntityManagerInterface $em): Response
{
    $form = $this->createForm(ArticleType::class, $article);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $em->flush();   // pas de persist : l'objet est déjà suivi par Doctrine
        $this->addFlash('success', 'Article modifié.');

        return $this->redirectToRoute('blog_show', ['slug' => $article->getSlug()]);
    }

    return $this->render('admin/article_edit.html.twig', ['form' => $form]);
}
```

Note : en édition, pas besoin de `persist` (l'objet vient de la base, Doctrine le suit déjà) ; un
simple `flush` suffit.

</details>

## Quiz

**1.** Quel couple de méthodes décide si on traite le formulaire dans le contrôleur ?
- A. `isSent() && isOk()`
- B. `isSubmitted() && isValid()`
- C. `handleRequest() && flush()`

**2.** Où déclare-t-on les règles de validation d'un article ?
- A. Dans le template Twig
- B. Sur l'entité, avec des attributs `#[Assert\...]`
- C. Dans le fichier `.env`

**3.** Que fait `{{ form(form) }}` en Twig ?
- A. Il valide le formulaire
- B. Il génère tout le HTML du formulaire (champs, erreurs, jeton CSRF)
- C. Il enregistre les données en base

**4.** Pourquoi redirige-t-on après un enregistrement réussi ?
- A. Pour vider la base
- B. Pour éviter qu'un rafraîchissement renvoie le formulaire une seconde fois
- C. C'est obligatoire pour que Doctrine fonctionne

<details>
<summary>Voir les réponses</summary>

1. **B** — `isSubmitted()` (a-t-il été envoyé ?) et `isValid()` (respecte-t-il les règles ?).
2. **B** — Les contraintes `#[Assert\...]` se placent sur les propriétés de l'entité.
3. **B** — `form()` rend l'ensemble du formulaire, jeton CSRF compris.
4. **B** — La redirection après succès évite la double soumission au rafraîchissement.

</details>

## Projet fil rouge

Tu peux maintenant écrire des articles depuis le navigateur.

1. Crée le `ArticleType` avec `make:form` (champs `title`, `slug`, `content`, `published`,
   `category`).
2. Ajoute des contraintes `#[Assert\...]` sur `Article` (titre obligatoire et d'au moins 5 caractères,
   contenu obligatoire).
3. Crée une action `new` sur `/admin/article/nouveau` qui affiche et traite le formulaire, puis une
   action `edit` pour modifier un article existant.
4. Crée un `CommentType` (champs `author`, `content`) et un formulaire de commentaire sur la page d'un
   article, rattaché à l'article courant à l'enregistrement.

Ton espace d'administration est ouvert à tous pour l'instant : n'importe qui peut créer un article. Au
chapitre suivant, on apprend l'**injection de dépendances** pour écrire nos propres **services** —
puis on s'attaquera à la sécurité pour protéger l'admin.

---

[← Chapitre précédent](06-doctrine-relations.md) · [Sommaire](README.md) · [Chapitre suivant →](08-services-injection.md)
