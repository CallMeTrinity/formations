# Synchronisation du contenu (markdown → BDD)

Le contenu pédagogique n'est **jamais saisi en base à la main**. Il vit en markdown dans les dossiers
de formation du dépôt parent, et une commande l'importe en base par un **upsert idempotent par
slug**. Ce document décrit ce flux : la commande, le service, les parsers et le rendu HTML.

C'est le flux de données central de l'application. Le modèle qu'il alimente est décrit dans
[`modele-de-donnees.md`](modele-de-donnees.md).

## En une phrase

`app:formations:sync` lit chaque dossier de formation, en extrait titre / présentation / blocs et
chapitres, rend tout en HTML, puis crée ou met à jour les entités correspondantes **sans toucher aux
champs admin ni aux données de progression**.

## Déclencher une sync

Deux points d'entrée, le même service (`FormationSyncService`) derrière :

```bash
# En ligne de commande (depuis site/)
php bin/console app:formations:sync
```

Ou depuis l'espace admin, bouton **Resynchroniser** (route `app_admin_resync`, POST protégé par
CSRF). Les deux affichent un compte-rendu : nombre de formations créées, mises à jour, chapitres
synchronisés, plus d'éventuels avertissements.

## Ce qui compte comme une formation

`FormationSyncService::sync()` parcourt les dossiers de **premier niveau** du dépôt parent (chemin
injecté via `$formationsContentDir`, valant `%kernel.project_dir%/..` en prod, un dossier de
fixtures en test). Un dossier est une formation s'il :

1. n'est **pas** dans la liste d'exclusion : `consignes`, `templates`, `site`, `target` ;
2. contient un `README.md`.

Le **nom du dossier** sert de `slug` (clé d'upsert). Les fichiers chapitres sont reconnus au motif
`NN-slug.md` (deux chiffres, un tiret, le slug). Le préfixe `NN` donne la `position` du chapitre, et
le `README.md` est exclu de ce balayage.

## Invariant de sync

> **À respecter impérativement.** La sync ne réécrit que les champs **contenu**. Les champs **admin**
> et toutes les données de **progression** ne sont jamais touchés.

| Réécrit par la sync (contenu) | Jamais touché par la sync |
|-------------------------------|---------------------------|
| `Formation` : `title`, `description`, `prerequisites`, `objectives`, `project` | `Formation` : `visibility`, `status`, `difficulty`, `tags`, `estimatedMinutes` |
| `Chapter` : `title`, `position`, `slug` | `Enrollment` et `ChapterProgress` (tout le suivi utilisateur) |
| `Section` : `type`, `title`, `content`, `position` | |

Conséquences concrètes :

- **Idempotence** : relancer la sync ne duplique rien (upsert par slug) et préserve tous les réglages
  admin et la progression de chacun.
- **Pas de suppression de chapitres** : un `ChapterProgress` est rattaché à un `Chapter`. La sync ne
  fait donc jamais de `DELETE` sur les chapitres, seulement des créations / mises à jour.
- **Sections recréées** : aucune donnée utilisateur n'est rattachée à une `Section`. La sync vide les
  sections d'un chapitre et les recrée (l'`orphanRemoval` sur `Chapter::$sections` nettoie les
  anciennes lignes).

Toute évolution du modèle ou de la commande **doit préserver cette séparation contenu / admin /
progression**.

## La chaîne de traitement

```
Dossier de formation
├── README.md ──────────►  ReadmeParser   ──► ParsedReadme  ──► champs Formation
└── NN-slug.md ──────────►  ChapterParser  ──► ParsedChapter ──► Chapter + Section[]
                                  │
                                  └── les deux passent par MarkdownRenderer (markdown → HTML)
```

### `ReadmeParser` — le README de la formation

Découpe le `README.md` en blocs canoniques (cf. `consignes/structure-formation.md`) :

- **titre H1** → `Formation::title` (si absent, la formation est **ignorée** avec un avertissement) ;
- **présentation** (tout ce qui est entre le H1 et le premier `##`) → `description` ;
- bloc dont le titre contient `prerequis` → `prerequisites` ;
- bloc « Ce que tu sauras faire » (titre contenant `sauras faire` ou `ce que tu`) → `objectives` ;
- bloc dont le titre contient `projet` → `project`.

Le bloc « Plan de la formation » est **volontairement ignoré** : il est déjà reconstruit en base via
les entités `Chapter` et réaffiché par le lecteur. La barre de navigation de pied (« Commencer par le
chapitre 1 → ») et le séparateur orphelin qu'elle laisse sont retirés avant rendu.

### `ChapterParser` — un fichier chapitre

Découpe un fichier `NN-slug.md` en :

- **titre H1** → `Chapter::title` ;
- une `Section` par titre `##`, dans l'ordre, avec un `position` à partir de 1.

Le **type** de section est déduit du titre `##` par recherche de sous-chaîne sur le titre normalisé
(sans accent, en minuscules). Le mapping exact figure dans la table `SectionType` de
[`modele-de-donnees.md`](modele-de-donnees.md#sectiontype-8). Un titre non reconnu tombe sur
`CONTENT`.

Deux précautions de parsing partagées avec le `ReadmeParser` :

- **Les blocs de code clôturés (```` ``` ````, `~~~`) sont préservés** : un `##` ou un `#` à
  l'intérieur d'un bloc de code n'ouvre pas de section et n'est pas pris pour un titre.
- **Les barres de navigation du markdown sont retirées** : une ligne contenant
  `[Sommaire](README.md)` (doublon de la navigation du lecteur, et dont les liens pointent vers des
  `.md`) est supprimée, ainsi que le séparateur `---` orphelin laissé en bas de chapitre.

### `MarkdownRenderer` — markdown → HTML

Commun aux chapitres et aux README. Convertit en **GitHub Flavored Markdown** (League CommonMark)
puis **réécrit les liens relatifs inter-chapitres** vers les routes du lecteur :

| Lien dans le markdown | Réécrit en |
|-----------------------|------------|
| `03-les-fonctions.md` | `/formations/<slug>/les-fonctions` |
| `README.md`           | `/formations/<slug>` |
| `02-bases.md#section` | `/formations/<slug>/bases#section` |

Les liens externes (`http`, `mailto:`), absolus (`/…`) et les ancres pures (`#…`) sont laissés
intacts. Le préfixe `NN-` est retiré du slug de chapitre cible (le lecteur n'utilise pas le numéro
dans l'URL).

> Note d'implémentation : la réécriture se fait par expression régulière sur le HTML rendu. Les
> chemins cibles sont générés via `UrlGeneratorInterface` (routes `app_formation_show` et
> `app_formation_chapter`), pas écrits en dur.

## DTOs intermédiaires

Le parsing ne manipule pas directement les entités : il produit des objets de transfert immuables,
que `FormationSyncService` mappe ensuite sur les entités.

| DTO | Produit par | Contenu |
|-----|-------------|---------|
| `ParsedReadme` | `ReadmeParser` | `title`, `description`, `prerequisites`, `objectives`, `project` (HTML) |
| `ParsedChapter` | `ChapterParser` | `title`, liste de `ParsedSection` |
| `ParsedSection` | `ChapterParser` | `type` (`SectionType`), `title`, `html`, `position` |
| `SyncReport` | `FormationSyncService` | `created`, `updated`, `chaptersCount`, `warnings` |

## Tester la sync

Les tests utilisent un dossier de contenu dédié (`tests/fixtures/content/`, branché via
`$formationsContentDir` dans la config de test) plutôt que les vraies formations :

```bash
php bin/phpunit tests/Command/FormationsSyncCommandTest.php
php bin/phpunit tests/Service/ChapterParserTest.php
php bin/phpunit tests/Service/ReadmeParserTest.php
```

> Les fixtures de démo (`AppFixtures`) sont un sujet séparé : elles créent des formations **sans
> chapitres**, dont les slugs ne correspondent à aucun dossier markdown. La sync ne les touche donc
> jamais. Elles servent uniquement à exercer les filtres du catalogue et le score de recommandation.
