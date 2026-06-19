# Les pointeurs

[← Chapitre précédent](05-tableaux-et-chaines.md) · [Sommaire](README.md) · [Chapitre suivant →](07-structures.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est une **adresse mémoire** et un **pointeur** ;
- utiliser `&` (adresse de) et `*` (déréférencement) sans les confondre ;
- écrire une fonction qui **modifie** une variable de l'appelant ;
- comprendre le lien profond entre **tableaux et pointeurs** en C.

C'est le chapitre qui fait peur, et celui qui change tout. Une fois les pointeurs compris, le reste du
C devient limpide. On y va doucement, avec un modèle mental clair.

## La mémoire, des cases numérotées

La mémoire de ton programme est une immense suite d'**octets**, chacun avec un **numéro** : son
**adresse**. Quand tu déclares une variable, le programme lui réserve une ou plusieurs cases et retient
leur adresse.

```
adresse :  ...  1000  1001  1002  1003  1004  ...
contenu :  ...   ??    42    ??    ??    ??   ...
                       ^
                       int age = 42;  (stocké à l'adresse 1000)
```

Jusqu'ici tu manipulais les variables par leur **nom** (`age`) et leur **valeur** (`42`). Un
**pointeur** te donne accès à la troisième dimension : leur **adresse** (`1000`).

> **À retenir** — Toute variable a une **adresse** (où elle est) et une **valeur** (ce qu'elle
> contient). Un pointeur est une variable dont la valeur **est une adresse**.

## `&` : l'adresse de

L'opérateur `&` (déjà croisé avec `scanf`) donne l'**adresse** d'une variable. On peut la ranger dans
un **pointeur**, déclaré avec une `*` :

```c
int age = 42;
int *p = &age;          // p est un pointeur vers un int ; il contient l'adresse de age
```

Lis `int *p` comme « `p` est un pointeur vers un `int` ». La `*` dans la **déclaration** annonce un
pointeur. `p` contient maintenant l'adresse de `age` (par exemple 1000).

```c
printf("valeur de age : %d\n", age);    // 42
printf("adresse de age : %p\n", (void *) &age);   // par ex. 0x7ffd... (%p affiche une adresse)
printf("valeur de p   : %p\n", (void *) p);       // la même adresse
```

`%p` est le spécificateur pour afficher une adresse. Le `(void *)` est un cast d'usage pour `%p` ;
ne t'en préoccupe pas pour l'instant.

## `*` : déréférencer

La même étoile `*`, mais cette fois **devant un pointeur existant** (hors déclaration), signifie « la
valeur **à** cette adresse ». On appelle ça **déréférencer**. C'est l'opération inverse de `&`.

```c
int age = 42;
int *p = &age;

printf("%d\n", *p);     // 42 : la valeur à l'adresse contenue dans p
*p = 100;               // écrit 100 à cette adresse... donc modifie age !
printf("%d\n", age);    // 100
```

`*p` donne accès à la case pointée, **en lecture comme en écriture**. Modifier `*p`, c'est modifier
`age`, parce que `p` contient l'adresse de `age`. C'est la clé de tout.

> **Attention** — La `*` a **deux sens** selon le contexte. Dans une **déclaration** (`int *p`), elle
> dit « pointeur ». Dans une **expression** (`*p = 100`), elle dit « déréférence ». Même symbole, rôles
> opposés. C'est déroutant au début ; ça devient naturel.

Récapitulons les deux opérateurs miroirs :

| Opérateur | Nom | Effet | Exemple |
| --- | --- | --- | --- |
| `&x` | adresse de | donne l'adresse de `x` | `p = &age` |
| `*p` | déréférencement | donne la valeur pointée par `p` | `*p = 100` |

## Enfin : modifier une variable depuis une fonction

Souviens-toi de la frustration du [chapitre 4](04-fonctions.md) : une fonction reçoit une **copie**,
elle ne peut pas modifier l'original. Les pointeurs lèvent ce blocage. On ne passe plus la **valeur**,
on passe l'**adresse**, et la fonction écrit **à** cette adresse.

```c
#include <stdio.h>

void doubler(int *x)        // reçoit une adresse
{
    *x = *x * 2;            // modifie la valeur À cette adresse
}

int main(void)
{
    int n = 5;
    doubler(&n);            // on passe l'ADRESSE de n
    printf("%d\n", n);      // 10 : n a vraiment changé !
    return 0;
}
```

Compare avec la version « par valeur » du chapitre 4 qui ne marchait pas. La différence tient à trois
`*`/`&` : le paramètre est `int *x`, on déréférence avec `*x`, on appelle avec `&n`. **C'est
exactement ce que fait `scanf`** : tu lui passes `&age` pour qu'il puisse écrire dans `age`. Tu
comprends maintenant ce `&` que tu écrivais machinalement.

> **À retenir** — Pour qu'une fonction **modifie** une variable de l'appelant, passe son **adresse**
> (`&var`), reçois un **pointeur** (`type *p`), et travaille via `*p`. C'est le « passage par
> référence » du C.

Cas classique : une fonction qui doit renvoyer **deux** résultats. `return` n'en renvoie qu'un ; on
passe les autres par pointeur.

```c
// Range le min dans *pmin et le max dans *pmax.
void min_max(int a, int b, int *pmin, int *pmax)
{
    if (a < b) { *pmin = a; *pmax = b; }
    else       { *pmin = b; *pmax = a; }
}

int main(void)
{
    int mini, maxi;
    min_max(8, 3, &mini, &maxi);
    printf("min=%d max=%d\n", mini, maxi);   // min=3 max=8
    return 0;
}
```

## Tableaux et pointeurs : la grande révélation

Voici pourquoi, au chapitre 5, une fonction pouvait modifier un tableau sans qu'on parle d'adresses.
En C, **le nom d'un tableau s'utilise comme l'adresse de son premier élément**. On dit qu'il « se
dégrade » (en anglais *decays*) en pointeur dès qu'on le passe à une fonction.

```c
int notes[3] = {10, 20, 30};
// notes  vaut  &notes[0]  (l'adresse du premier élément)
// *notes vaut  notes[0]   (soit 10)
```

Conséquence directe : quand tu passes un tableau à une fonction, tu ne passes **pas** une copie de
tout le tableau, mais **l'adresse** de son début. La fonction travaille donc sur le **vrai** tableau,
et peut le modifier.

```c
#include <stdio.h>

void mettre_a_zero(int tab[], int taille)   // tab[] est en réalité un pointeur
{
    for (int i = 0; i < taille; i++)
        tab[i] = 0;             // modifie le vrai tableau de l'appelant
}

int main(void)
{
    int scores[3] = {5, 9, 2};
    mettre_a_zero(scores, 3);
    printf("%d %d %d\n", scores[0], scores[1], scores[2]);   // 0 0 0
    return 0;
}
```

Deux conséquences pratiques à retenir :

- `int tab[]` et `int *tab` en **paramètre** sont **identiques** : les deux reçoivent une adresse. Une
  fonction ne peut donc pas connaître la taille d'un tableau qu'on lui passe — d'où l'argument
  `taille` qu'on ajoute toujours.
- `tab[i]` est en fait un raccourci pour `*(tab + i)` : « la valeur à l'adresse `tab` décalée de `i`
  éléments ». Tableaux et pointeurs sont les deux faces d'une même pièce.

> **Attention** — `sizeof` ne fonctionne **pas** pour connaître la taille d'un tableau **dans une
> fonction** : il y mesure la taille d'un pointeur (8 octets), pas du tableau. `sizeof(tab)` ne donne
> la vraie taille que là où le tableau a été **déclaré**. Dans une fonction, passe toujours la taille
> en paramètre.

## Le pointeur nul et les pointeurs fous

Un pointeur peut ne pointer sur rien : on lui donne la valeur spéciale `NULL` (définie dans plusieurs
en-têtes, dont `<stdio.h>`).

```c
int *p = NULL;          // ne pointe sur rien pour l'instant
```

Déréférencer un pointeur `NULL` (ou un pointeur non initialisé, qui pointe n'importe où) fait planter
le programme : c'est le fameux ***segmentation fault*** (« segfault »). C'est le bug le plus courant en
C. La parade : **toujours initialiser** un pointeur, et **tester** s'il vaut `NULL` avant de le
déréférencer quand on n'est pas sûr.

```c
if (p != NULL)
    printf("%d\n", *p);     // on ne déréférence que si p pointe vraiment quelque part
```

> **À retenir** — Un *segfault* = tu as déréférencé un pointeur qui ne pointe pas sur une zone valide
> (`NULL`, non initialisé, ou libéré). Devant un segfault, suspecte d'abord tes pointeurs.

## Résumé

- Toute variable a une **adresse**. Un **pointeur** est une variable qui contient une adresse.
- `&x` = « adresse de `x` ». `*p` = « valeur à l'adresse `p` » (déréférencement). Ce sont des inverses.
- Dans une **déclaration**, `*` annonce un pointeur ; dans une **expression**, `*` déréférence.
- Pour qu'une fonction **modifie** une variable de l'appelant : passer `&var`, recevoir `type *p`,
  agir via `*p`. C'est ce que fait `scanf`.
- Le nom d'un **tableau** vaut l'adresse de son premier élément : passé à une fonction, c'est le vrai
  tableau qui est manipulé. `tab[i]` ⟺ `*(tab + i)`.
- `NULL` = pointe sur rien. Déréférencer un pointeur invalide = ***segfault***. Initialise et teste tes
  pointeurs.

## Exercices

### Exercice 1 — Échanger deux variables

Écris une fonction `void echanger(int *a, int *b)` qui échange les valeurs de deux entiers. Montre
qu'après l'appel, les variables de `main` sont bien échangées.

<details>
<summary>Voir le corrigé</summary>

Impossible sans pointeurs (à cause du passage par valeur). Avec les adresses, on travaille sur les
vraies variables. Une variable temporaire évite d'écraser une valeur avant de l'avoir copiée.

```c
#include <stdio.h>

void echanger(int *a, int *b)
{
    int temp = *a;      // on garde la valeur pointée par a
    *a = *b;            // a reçoit la valeur de b
    *b = temp;          // b reçoit l'ancienne valeur de a
}

int main(void)
{
    int x = 1, y = 2;
    echanger(&x, &y);
    printf("x=%d y=%d\n", x, y);   // x=2 y=1
    return 0;
}
```

C'est l'exemple canonique du passage par pointeur. Sans les `*` et les `&`, l'échange n'aurait aucun
effet visible dans `main`.

</details>

### Exercice 2 — Incrémenter via un pointeur

Écris `void incremente(int *p)` qui ajoute 1 à la valeur pointée. Appelle-la trois fois sur une même
variable et vérifie qu'elle vaut bien `+3`.

<details>
<summary>Voir le corrigé</summary>

```c
#include <stdio.h>

void incremente(int *p)
{
    *p = *p + 1;        // ou plus court : (*p)++;
}

int main(void)
{
    int n = 10;
    incremente(&n);
    incremente(&n);
    incremente(&n);
    printf("%d\n", n);  // 13
    return 0;
}
```

Compare avec l'exercice 2 du chapitre 4, qui donnait `10` : la seule différence est le pointeur, et il
change tout. Attention à `(*p)++` : les parenthèses sont nécessaires, car `*p++` incrémenterait le
**pointeur**, pas la valeur.

</details>

## Quiz

**1.** Que signifie `int *p = &x;` ?
- A. `p` reçoit la valeur de `x`.
- B. `p` est un pointeur qui reçoit l'adresse de `x`.
- C. `p` est multiplié par `x`.

**2.** Si `p` pointe vers `x` (qui vaut 7), que fait `*p = 20;` ?
- A. Donne 20 à `p`.
- B. Met `x` à 20.
- C. Ne compile pas.

**3.** Pourquoi une fonction peut-elle modifier un tableau qu'on lui passe, mais pas un `int` simple ?
- A. Les tableaux sont des variables globales.
- B. Le nom d'un tableau vaut l'adresse de son premier élément : la fonction reçoit l'adresse, pas une
  copie.
- C. Le C copie les `int` mais pas les tableaux pour gagner du temps.

**4.** Qu'est-ce qu'un *segmentation fault* ?
- A. Une erreur de compilation.
- B. Un plantage dû au déréférencement d'un pointeur invalide.
- C. Un tableau trop grand.

<details>
<summary>Voir les réponses</summary>

1. **B** — `int *p` déclare un pointeur ; `&x` est l'adresse de `x`.
2. **B** — `*p` déréférence : on écrit 20 à l'adresse pointée, donc dans `x`.
3. **B** — le tableau se « dégrade » en adresse de son premier élément ; la fonction agit sur l'original.
4. **B** — déréférencer un pointeur `NULL`, non initialisé ou libéré provoque un segfault.

</details>

## Projet fil rouge

On factorise la lecture des candidats dans une fonction qui **remplit** le tableau de noms passé en
paramètre. Comme c'est un tableau, la fonction modifie bien le vrai tableau de `main` (ce que tu
comprends maintenant). On en profite pour passer le nombre de candidats par valeur, et le tableau par
« référence ».

```c
#include <stdio.h>
#include <string.h>

#define MAX_CANDIDATS 9
#define LONGUEUR_NOM  30

// Remplit noms[0..nb-1]. tab de chaînes => la fonction modifie le vrai tableau.
void lire_candidats(char noms[][LONGUEUR_NOM], int nb)
{
    for (int c = 0; c < nb; c++)
    {
        printf("Nom du candidat %d : ", c);
        fgets(noms[c], LONGUEUR_NOM, stdin);
        noms[c][strcspn(noms[c], "\n")] = '\0';
    }
}

int main(void)
{
    char noms[MAX_CANDIDATS][LONGUEUR_NOM];
    int nb_candidats = 3;

    lire_candidats(noms, nb_candidats);

    printf("\nCandidats en lice :\n");
    for (int c = 0; c < nb_candidats; c++)
        printf("  [%d] %s\n", c, noms[c]);
    return 0;
}
```

Pour un tableau 2D en paramètre, le C exige qu'on précise **toutes les dimensions sauf la première** :
d'où `char noms[][LONGUEUR_NOM]`. La première dimension (le nombre de candidats) est libre, c'est `nb`
qui la borne. Au prochain chapitre, on remplace le couple « tableau de noms + données éparpillées » par
une vraie **structure** `candidate` qui regroupe tout ce qui concerne un candidat.

---

[← Chapitre précédent](05-tableaux-et-chaines.md) · [Sommaire](README.md) · [Chapitre suivant →](07-structures.md)
