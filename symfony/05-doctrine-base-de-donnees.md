# La base de données avec Doctrine

[← Chapitre précédent](04-twig.md) · [Sommaire](README.md) · [Chapitre suivant →](06-doctrine-relations.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre ce qu'est **Doctrine** et le principe d'un **ORM** ;
- **configurer** la connexion à une base de données et la créer ;
- définir une **entité** (une classe qui représente une table) avec `make:entity` ;
- comprendre les **attributs de mapping** (`#[ORM\Entity]`, `#[ORM\Column]`…) ;
- créer et exécuter des **migrations** pour construire et faire évoluer le schéma de la base.

## Doctrine et le principe d'un ORM

Jusqu'ici, tes articles étaient codés en dur dans le contrôleur : ils disparaissent à chaque
redémarrage et tu ne peux pas en ajouter sans toucher au code. Il te faut une **base de données** : un
système qui stocke durablement tes données dans des **tables** (des grilles de lignes et de colonnes).

Le langage des bases de données relationnelles est le **SQL**. Tu pourrais écrire toi-même des
requêtes SQL (`SELECT * FROM article WHERE ...`), mais c'est verbeux et risqué. Symfony s'appuie sur
**Doctrine**, un **ORM** (*Object-Relational Mapping*, « correspondance objet-relationnel »).

Le principe de l'ORM : tu manipules des **objets PHP**, et Doctrine se charge de les traduire en
**lignes de table** (et inversement). Une **classe** correspond à une **table**, un **objet** à une
**ligne**, une **propriété** à une **colonne**.

```text
Classe Article    ◄──►   table "article"
objet $article    ◄──►   une ligne de la table
$article->titre   ◄──►   colonne "titre"
```

> **À retenir** — Avec Doctrine, tu écris du PHP orienté objet ; tu n'écris presque jamais de SQL à la
> main. L'ORM fait la traduction dans les deux sens.

Une classe qui représente une table s'appelle une **entité** (*entity*).

## Configurer la base de données

L'adresse de la base est dans la variable `DATABASE_URL` du fichier `.env`. Pour cette formation, on
utilise **SQLite** : une base de données rangée dans un simple fichier, sans aucun serveur à
installer. C'est parfait pour apprendre et reproductible partout.

Ouvre `.env` et règle `DATABASE_URL` ainsi :

```bash
# .env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
```

`%kernel.project_dir%` est un raccourci Symfony vers la racine du projet : la base sera donc le fichier
`var/data.db`.

> **Astuce** — En production, on utilise plutôt **PostgreSQL** ou **MySQL/MariaDB**. Le changement se
> fait en une ligne : il suffit de remplacer `DATABASE_URL` par l'adresse du serveur (par exemple
> `postgresql://user:pass@127.0.0.1:5432/blog`). Tout le reste de ton code Doctrine reste
> **identique** : c'est l'un des grands avantages de l'ORM.

Crée maintenant la base de données :

```bash
$ php bin/console doctrine:database:create
```

Avec SQLite, cette commande crée le fichier `var/data.db`. La base est vide pour l'instant : aucune
table. On va définir nos tables sous forme d'entités.

## Créer une entité avec `make:entity`

L'entité centrale du blog est l'**article**. Génère-la avec le MakerBundle, qui te pose des questions
en mode interactif :

```bash
$ php bin/console make:entity
```

La commande demande le **nom de l'entité** (`Article`), puis te fait ajouter les **champs** un par
un : pour chacun, son nom, son type, sa longueur, s'il peut être vide (*nullable*). Réponds ainsi
pour notre article :

```text
Class name of the entity to create or update:
> Article

New property name (press <return> to stop adding fields):
> title
Field type [string]:
> string
Field length [255]:
> 255
Can this field be null in the database (nullable)? (yes/no) [no]:
> no

New property name:
> slug
Field type [string]:
> string
...

New property name:
> content
Field type [string]:
> text          (un texte long, sans limite de 255 caractères)
...

New property name:
> createdAt
Field type [string]:
> datetime_immutable
...

New property name:
> (Entrée pour terminer)
```

`make:entity` crée deux fichiers : `src/Entity/Article.php` (l'entité) et
`src/Repository/ArticleRepository.php` (on verra le repository au chapitre suivant).

## Anatomie d'une entité

Ouvre `src/Entity/Article.php`. Voici sa structure, allégée des commentaires :

```php
<?php
// src/Entity/Article.php
namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
class Article
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    // ... suivis des getters et setters générés pour chaque propriété
}
```

Les **attributs** `#[ORM\...]` sont le **mapping** : ils disent à Doctrine comment traduire la classe
en table.

- **`#[ORM\Entity(...)]`** sur la classe : « ceci est une entité ». Doctrine lui associera une table
  (`article` par défaut).
- **`#[ORM\Column]`** sur une propriété : « cette propriété est une colonne ». Les options précisent le
  type et la taille (`length: 255`, `type: Types::TEXT`).
- **`#[ORM\Id]`** désigne la **clé primaire** : l'identifiant unique de chaque ligne.
- **`#[ORM\GeneratedValue]`** : la valeur de l'`id` est **générée automatiquement** par la base (1, 2,
  3…). Tu ne la fixes jamais toi-même.

Les propriétés sont **`private`** : on y accède par des **getters** et **setters** (`getTitle()`,
`setTitle()`) générés par le maker. C'est de l'encapsulation classique en POO.

> **Attention** — Ne crée pas tes tables à la main dans la base. La **source de vérité**, c'est
> l'entité. La base se construit **à partir** des entités, via les migrations qu'on voit maintenant.

## Les migrations

Tu as décrit ta table (l'entité), mais la base ne le sait pas encore : il faut lui appliquer ce
changement. Une **migration** est un script qui modifie le schéma de la base (créer une table, ajouter
une colonne…). Doctrine les **génère pour toi** en comparant tes entités à l'état actuel de la base.

Génère la migration :

```bash
$ php bin/console make:migration
```

Doctrine crée un fichier dans `migrations/` (nommé d'après l'horodatage). Ouvre-le : tu y vois la
requête SQL `CREATE TABLE article (...)` que Doctrine a déduite de ton entité. Tu n'as pas eu à
l'écrire.

Applique ensuite la migration à la base :

```bash
$ php bin/console doctrine:migrations:migrate
```

Confirme par `yes`. La table `article` est maintenant créée dans `var/data.db`. Tu peux le vérifier :

```bash
$ php bin/console dbal:run-sql "SELECT name FROM sqlite_master WHERE type='table'"
```

> **À retenir** — Le cycle est toujours le même : **(1)** tu modifies une entité, **(2)**
> `make:migration` génère le script de changement, **(3)** `doctrine:migrations:migrate` l'applique.
> Les fichiers de `migrations/` sont versionnés dans Git : ils décrivent l'histoire de ton schéma et
> permettent à tes coéquipiers (et au serveur de prod) de reconstruire la même base.

## Faire évoluer le schéma

Imagine que tu veuilles ajouter un champ `published` (booléen, pour savoir si un article est en ligne).
Tu peux relancer `make:entity` sur l'entité existante : le maker la **complète** sans rien écraser.

```bash
$ php bin/console make:entity Article
# > published, type boolean, nullable no
```

Puis tu refais le cycle migration :

```bash
$ php bin/console make:migration
$ php bin/console doctrine:migrations:migrate
```

Cette fois, la migration générée contient un `ALTER TABLE article ADD published ...`. Doctrine n'a
ajouté **que la différence**, sans toucher aux données existantes.

> **Attention** — Ne modifie jamais une migration **déjà appliquée** pour changer le schéma : crée
> une **nouvelle** migration. Modifier une migration passée désynchronise ta base de l'historique.

## Résumé

- Une **base de données** stocke durablement tes données dans des **tables** ; **Doctrine** est l'ORM
  qui traduit tes **objets PHP** en **lignes** et inversement, sans écrire de SQL.
- Une **entité** est une classe qui représente une table ; on la génère avec **`make:entity`**.
- Les **attributs** `#[ORM\Entity]`, `#[ORM\Column]`, `#[ORM\Id]`, `#[ORM\GeneratedValue]` constituent
  le **mapping** entre la classe et la table.
- La connexion est définie par **`DATABASE_URL`** dans `.env` ; on utilise **SQLite** pour la
  formation (`var/data.db`), changeable en une ligne pour PostgreSQL/MySQL.
- Le cycle d'évolution du schéma : **modifier l'entité → `make:migration` → `doctrine:migrations:migrate`**.
- L'entité est la **source de vérité** ; on ne crée et ne modifie jamais les tables à la main.

## Exercices

### Exercice 1 — Une entité Catégorie

Crée une entité `Category` avec deux champs : `name` (string, 100 caractères, non nul) et `slug`
(string, 255, non nul). Génère et applique la migration. Vérifie que la table `category` existe.

<details>
<summary>Voir le corrigé</summary>

La démarche : `make:entity`, puis le cycle de migration.

```bash
$ php bin/console make:entity
# Class name: Category
# name  -> string, length 100, nullable no
# slug  -> string, length 255, nullable no

$ php bin/console make:migration
$ php bin/console doctrine:migrations:migrate
```

Pour vérifier :

```bash
$ php bin/console dbal:run-sql "SELECT name FROM sqlite_master WHERE type='table'"
```

Tu dois voir `article`, `category` (et les tables internes de Doctrine pour les migrations).

</details>

### Exercice 2 — Ajouter un champ

Ajoute à l'entité `Article` un champ `published` de type `boolean` (non nul). Génère la migration,
ouvre-la pour repérer la ligne `ALTER TABLE`, puis applique-la.

<details>
<summary>Voir le corrigé</summary>

La démarche : relancer `make:entity` sur l'entité existante complète sans tout réécrire.

```bash
$ php bin/console make:entity Article
# published -> boolean, nullable no

$ php bin/console make:migration
```

Ouvre le nouveau fichier dans `migrations/` : sa méthode `up()` contient un `ALTER TABLE article ADD
published ...`. Applique :

```bash
$ php bin/console doctrine:migrations:migrate
```

</details>

## Quiz

**1.** Que fait un ORM comme Doctrine ?
- A. Il remplace la base de données
- B. Il traduit tes objets PHP en lignes de table et inversement
- C. Il génère le HTML de tes pages

**2.** Dans une entité, à quoi sert `#[ORM\Id]` combiné à `#[ORM\GeneratedValue]` ?
- A. À définir une clé primaire générée automatiquement par la base
- B. À rendre la propriété obligatoire
- C. À créer un index de recherche

**3.** Quel est le bon cycle pour ajouter une colonne ?
- A. Modifier la table à la main dans la base
- B. Modifier l'entité, puis `make:migration`, puis `doctrine:migrations:migrate`
- C. Supprimer la base et la recréer

**4.** Quelle est la **source de vérité** du schéma de ta base ?
- A. Le fichier `var/data.db`
- B. Les entités dans `src/Entity/`
- C. Le fichier `.env`

<details>
<summary>Voir les réponses</summary>

1. **B** — L'ORM fait la correspondance objets ↔ lignes, ce qui t'évite d'écrire du SQL.
2. **A** — `#[ORM\Id]` marque la clé primaire, `#[ORM\GeneratedValue]` la fait générer par la base.
3. **B** — On modifie l'entité, puis on génère et applique la migration.
4. **B** — Les entités décrivent le schéma ; la base et les migrations en découlent.

</details>

## Projet fil rouge

Ton blog a maintenant une vraie base de données.

1. Configure `DATABASE_URL` sur SQLite dans `.env` et crée la base avec
   `php bin/console doctrine:database:create`.
2. Crée l'entité `Article` avec au minimum : `title` (string), `slug` (string), `content` (text),
   `createdAt` (datetime_immutable) et `published` (boolean).
3. Crée aussi l'entité `Category` (`name`, `slug`).
4. Génère et applique les migrations : `make:migration` puis `doctrine:migrations:migrate`.

Tes tables existent, mais elles sont vides et ton contrôleur affiche toujours des articles en dur. Au
chapitre suivant, on apprend à **enregistrer**, **lire** et **relier** ces données : on remplira la
base et on branchera enfin la page `/blog` sur de vrais articles.

---

[← Chapitre précédent](04-twig.md) · [Sommaire](README.md) · [Chapitre suivant →](06-doctrine-relations.md)
