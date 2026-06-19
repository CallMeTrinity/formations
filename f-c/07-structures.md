# Structures, enums et typedef

[← Chapitre précédent](06-pointeurs.md) · [Sommaire](README.md) · [Chapitre suivant →](08-memoire-dynamique.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- regrouper plusieurs valeurs liées dans une **structure** (`struct`) ;
- accéder à ses champs avec `.` et, via un pointeur, avec `->` ;
- créer des **alias de type** lisibles avec `typedef` ;
- définir un ensemble de constantes nommées avec un **enum**.

## Le besoin : regrouper des données liées

Jusqu'ici, un candidat du projet était éparpillé : son nom dans un tableau, son nombre de voix dans un
autre, son statut « éliminé » dans un troisième. C'est fragile : rien ne garantit que les indices
restent alignés. Une **structure** (`struct`) résout ça : elle regroupe sous un seul nom plusieurs
**champs** de types éventuellement différents.

```c
struct candidate
{
    char nom[30];       // son nom
    int  votes;         // ses voix au tour courant
    bool elimine;       // est-il éliminé ?
};
```

`struct candidate` est désormais un **type**, au même titre que `int`. On peut déclarer des variables
de ce type :

```c
struct candidate alice;
```

## Accéder aux champs : l'opérateur `.`

On lit et écrit chaque champ avec un **point** :

```c
#include <string.h>

struct candidate alice;
strcpy(alice.nom, "Alice");     // un champ chaîne se copie avec strcpy (rappel ch.5)
alice.votes = 0;
alice.elimine = false;

printf("%s a %d voix\n", alice.nom, alice.votes);   // Alice a 0 voix
```

On peut aussi tout initialiser à la déclaration, dans l'ordre des champs ou en les nommant :

```c
struct candidate bob = {"Bob", 0, false};
struct candidate charlie = {.nom = "Charlie", .votes = 0, .elimine = false}; // initialisation nommée
```

> **À retenir** — Une `struct` regroupe des champs hétérogènes sous un seul type. On accède à un champ
> avec `variable.champ`. Le nom d'un champ chaîne reste un tableau : il se copie avec `strcpy`, pas
> avec `=`.

## Tableau de structures

Le vrai gain pour le projet : un **tableau de structures**. Une seule ligne décrit tout un scrutin.

```c
struct candidate candidats[9];

strcpy(candidats[0].nom, "Alice");
candidats[0].votes = 0;
candidats[0].elimine = false;
```

`candidats[0].nom` se lit « le champ `nom` du candidat d'indice 0 ». On parcourt comme n'importe quel
tableau :

```c
for (int c = 0; c < nb; c++)
{
    printf("[%d] %s : %d voix%s\n",
           c, candidats[c].nom, candidats[c].votes,
           candidats[c].elimine ? " (elimine)" : "");
}
```

## Structures et pointeurs : l'opérateur `->`

Quand tu as un **pointeur** vers une structure, écrire `(*p).champ` est lourd. Le C offre un raccourci,
la **flèche** `->` :

```c
struct candidate alice = {"Alice", 0, false};
struct candidate *p = &alice;

p->votes = 5;           // équivaut à (*p).votes = 5;
printf("%s\n", p->nom); // équivaut à (*p).nom
```

`p->champ` veut dire « déréférence `p`, puis prends le champ ». C'est la notation que tu verras
partout. Elle est essentielle pour les fonctions qui **modifient** une structure : on leur passe un
**pointeur** vers la structure (sinon, passage par valeur, elles n'auraient qu'une copie).

```c
// Ajoute une voix au candidat pointé. Modifie le vrai candidat de l'appelant.
void ajouter_voix(struct candidate *c)
{
    c->votes++;
}

int main(void)
{
    struct candidate alice = {"Alice", 0, false};
    ajouter_voix(&alice);       // on passe l'adresse
    printf("%d\n", alice.votes); // 1
    return 0;
}
```

> **À retenir** — Avec un pointeur de structure, utilise `p->champ` (pas `p.champ`). Pour qu'une
> fonction **modifie** une structure, passe-lui un **pointeur** (`&ma_struct`) et travaille en `->`.

> **Astuce** — Passer une grosse structure par valeur la **copie** entièrement (coûteux). Même quand
> la fonction ne la modifie pas, on passe souvent un pointeur pour éviter la copie, en le protégeant
> avec `const` : `void afficher(const struct candidate *c)`.

## `typedef` : des noms de types plus courts

Répéter `struct candidate` partout est verbeux. `typedef` crée un **alias** de type :

```c
typedef struct candidate Candidate;     // "Candidate" devient un synonyme de "struct candidate"

Candidate alice;                        // plus besoin du mot-clé struct
```

On combine souvent la définition de la structure et le `typedef` en un seul bloc, idiome très répandu :

```c
typedef struct
{
    char nom[30];
    int  votes;
    bool elimine;
} Candidate;            // le type s'appelle simplement Candidate

Candidate candidats[9];
```

C'est cette forme qu'on utilisera dans le projet : plus lisible, plus courte.

## Les enums : des constantes nommées

Un **enum** (énumération) crée un ensemble de constantes entières lisibles. Au lieu de coder un statut
par des nombres magiques (0, 1, 2…), on leur donne des noms :

```c
typedef enum
{
    TOUR_EN_COURS,      // vaut 0
    GAGNANT_TROUVE,     // vaut 1
    EGALITE_TOTALE      // vaut 2
} EtatScrutin;

EtatScrutin etat = TOUR_EN_COURS;

if (etat == GAGNANT_TROUVE)
    printf("Un gagnant !\n");
```

Par défaut, le premier vaut 0 et chaque suivant s'incrémente. L'intérêt n'est pas la valeur (qu'on
ignore souvent) mais la **lisibilité** : `if (etat == GAGNANT_TROUVE)` se comprend d'un coup d'œil, là
où `if (etat == 1)` exige de se souvenir de ce que signifie 1. On s'en servira pour l'état de
l'algorithme runoff.

> **À retenir** — Un `enum` remplace des nombres magiques par des noms parlants. `typedef enum {...}
> Nom;` crée un type d'énumération utilisable directement.

## Résumé

- Une `struct` regroupe des **champs** hétérogènes sous un seul type. Accès par `variable.champ`.
- Un **tableau de structures** modélise une collection d'objets (les candidats du scrutin).
- Avec un **pointeur** de structure, on utilise `p->champ`. Pour modifier une structure dans une
  fonction, passe un pointeur ; ajoute `const` quand tu ne modifies pas, pour éviter la copie.
- `typedef` crée un **alias** de type ; `typedef struct {…} Nom;` évite de répéter `struct`.
- Un `enum` nomme un ensemble de constantes entières et rend le code lisible.

## Exercices

### Exercice 1 — Une structure `Point`

Définis un type `Point` (deux champs `int x` et `int y`) avec `typedef`. Écris une fonction
`double distance(Point a, Point b)` qui renvoie la distance entre deux points. Teste avec (0,0) et
(3,4) : tu dois obtenir 5.

<details>
<summary>Voir le corrigé</summary>

La distance euclidienne utilise `sqrt` de `<math.h>`. Avec gcc, il faut parfois lier la bibliothèque
math avec l'option `-lm`.

```c
#include <stdio.h>
#include <math.h>

typedef struct
{
    int x;
    int y;
} Point;

double distance(Point a, Point b)
{
    int dx = a.x - b.x;
    int dy = a.y - b.y;
    return sqrt(dx * dx + dy * dy);
}

int main(void)
{
    Point origine = {0, 0};
    Point cible   = {3, 4};
    printf("%.1f\n", distance(origine, cible));   // 5.0
    return 0;
}
```

```bash
$ gcc -Wall -Wextra distance.c -o distance -lm
```

Ici on passe les `Point` **par valeur** : la fonction ne les modifie pas et ils sont petits, donc la
copie est sans conséquence.

</details>

### Exercice 2 — Modifier une structure via pointeur

Écris `void deplacer(Point *p, int dx, int dy)` qui déplace un point de `dx`, `dy`. Vérifie qu'après
l'appel, le point de `main` a bien bougé.

<details>
<summary>Voir le corrigé</summary>

Comme la fonction doit **modifier** le point, on passe un pointeur et on travaille en `->`.

```c
#include <stdio.h>

typedef struct { int x; int y; } Point;

void deplacer(Point *p, int dx, int dy)
{
    p->x += dx;         // (*p).x += dx
    p->y += dy;
}

int main(void)
{
    Point pos = {2, 3};
    deplacer(&pos, 5, -1);
    printf("(%d, %d)\n", pos.x, pos.y);   // (7, 2)
    return 0;
}
```

Sans le pointeur, le déplacement n'affecterait qu'une copie locale (rappel du chapitre 4).

</details>

## Quiz

**1.** Comment accède-t-on au champ `votes` d'une variable `Candidate c` ?
- A. `c->votes`
- B. `c.votes`
- C. `votes(c)`

**2.** Si `p` est un `Candidate *`, comment lit-on son champ `nom` ?
- A. `p.nom`
- B. `p->nom`
- C. `*p.nom`

**3.** À quoi sert `typedef struct {…} Candidate;` ?
- A. À créer une variable nommée Candidate.
- B. À définir un type utilisable sans répéter le mot-clé `struct`.
- C. À copier une structure.

**4.** Quel est l'intérêt principal d'un `enum` ?
- A. Accélérer le programme.
- B. Remplacer des nombres magiques par des constantes nommées lisibles.
- C. Économiser de la mémoire.

<details>
<summary>Voir les réponses</summary>

1. **B** — accès par `.` sur une variable structure.
2. **B** — sur un **pointeur** de structure, on utilise la flèche `->`.
3. **B** — `typedef` crée un alias de type, ici `Candidate` pour la structure anonyme.
4. **B** — un `enum` nomme des constantes et rend les comparaisons explicites.

</details>

## Projet fil rouge

On remplace les tableaux séparés (noms, votes, statut) par **un seul tableau de structures
`Candidate`**. C'est un tournant : toutes les données d'un candidat vivent désormais ensemble. On
ajoute aussi l'`enum` qui décrira l'état de l'algorithme.

```c
#include <stdio.h>
#include <string.h>
#include <stdbool.h>

#define MAX_CANDIDATS 9
#define LONGUEUR_NOM  30

typedef struct
{
    char nom[LONGUEUR_NOM];
    int  votes;         // voix au tour courant
    bool elimine;       // hors course ?
} Candidate;

void lire_candidats(Candidate candidats[], int nb)
{
    for (int c = 0; c < nb; c++)
    {
        printf("Nom du candidat %d : ", c);
        fgets(candidats[c].nom, LONGUEUR_NOM, stdin);
        candidats[c].nom[strcspn(candidats[c].nom, "\n")] = '\0';
        candidats[c].votes = 0;
        candidats[c].elimine = false;
    }
}

int main(void)
{
    Candidate candidats[MAX_CANDIDATS];
    int nb_candidats = 3;

    lire_candidats(candidats, nb_candidats);

    printf("\nCandidats en lice :\n");
    for (int c = 0; c < nb_candidats; c++)
        printf("  [%d] %s : %d voix\n", c, candidats[c].nom, candidats[c].votes);
    return 0;
}
```

Chaque candidat est maintenant un objet cohérent : nom, voix et statut ne peuvent plus se
désynchroniser. Mais on est toujours limité à `MAX_CANDIDATS` candidats et `MAX_VOTANTS` votants codés
en dur. Au prochain chapitre, l'**allocation dynamique** va lever cette limite : le programme
s'adaptera à n'importe quelle taille de scrutin saisie à l'exécution.

---

[← Chapitre précédent](06-pointeurs.md) · [Sommaire](README.md) · [Chapitre suivant →](08-memoire-dynamique.md)
