# La mémoire : pile, tas, malloc et free

[← Chapitre précédent](07-structures.md) · [Sommaire](README.md) · [Chapitre suivant →](09-organiser-un-programme.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- distinguer la **pile** (*stack*) et le **tas** (*heap*), et savoir où vivent tes variables ;
- allouer de la mémoire à l'exécution avec `malloc` et la libérer avec `free` ;
- redimensionner un tableau dynamique avec `realloc` ;
- reconnaître et éviter les **fuites mémoire** et les **pointeurs pendants**.

## Deux zones de mémoire

Jusqu'ici, toutes tes variables avaient une taille **connue à la compilation** : `int n`,
`Candidate candidats[9]`. Le compilateur les place sur la **pile** (en anglais *stack*), une zone gérée
automatiquement. Une variable locale y naît à l'entrée d'une fonction et **meurt** à sa sortie : c'est
ce qu'on a vu au chapitre 4.

Mais comment faire un tableau dont la taille n'est connue qu'**à l'exécution** (le nombre de votants,
tapé par l'utilisateur) ? La pile ne convient pas. Il faut l'autre zone : le **tas** (en anglais
*heap*), une réserve de mémoire que **tu** gères à la main, qui ne dépend pas des fonctions.

| | Pile (*stack*) | Tas (*heap*) |
| --- | --- | --- |
| Géré par | le compilateur, automatiquement | toi, manuellement |
| Durée de vie | celle de la fonction | jusqu'à ce que tu libères |
| Taille | connue à la compilation | choisie à l'exécution |
| Exemple | `int n;`, `int t[10];` | `malloc(n * sizeof(int))` |

> **À retenir** — Variables locales = **pile**, automatiques mais figées en taille et éphémères.
> Mémoire demandée à l'exécution = **tas**, flexible mais à gérer (et libérer) toi-même.

## `malloc` : demander de la mémoire

`malloc` (*memory allocation*, dans `<stdlib.h>`) réserve un bloc de mémoire sur le tas et renvoie son
**adresse** (un pointeur). Tu lui donnes le **nombre d'octets** voulu, calculé avec `sizeof`.

```c
#include <stdlib.h>

int n = 5;
int *tableau = malloc(n * sizeof(int));     // réserve de quoi stocker 5 int
```

`n * sizeof(int)` = 5 × 4 = 20 octets. `malloc` renvoie l'adresse du bloc, qu'on range dans un
pointeur. Ensuite, **on utilise ce pointeur comme un tableau normal** :

```c
for (int i = 0; i < n; i++)
    tableau[i] = i * 10;        // tableau[i] fonctionne exactement comme un tableau classique
```

Souviens-toi du chapitre 6 : `tableau[i]` n'est qu'un raccourci pour `*(tableau + i)`. Un pointeur sur
un bloc alloué se manipule donc comme un tableau, parce que c'en est un.

> **Attention** — `malloc` peut **échouer** (mémoire insuffisante) et renvoyer `NULL`. Le code robuste
> teste toujours le retour avant de l'utiliser :
>
> ```c
> int *tableau = malloc(n * sizeof(int));
> if (tableau == NULL)
> {
>     fprintf(stderr, "Memoire insuffisante\n");
>     return 1;
> }
> ```
>
> `fprintf(stderr, ...)` écrit sur la **sortie d'erreur**, le canal réservé aux messages d'erreur.

## `free` : rendre la mémoire

La mémoire du tas n'est **pas** libérée automatiquement. Tant que ton programme tourne, elle reste
réservée jusqu'à ce que **tu** la rendes avec `free`. Oublier de le faire, c'est une **fuite mémoire**
(en anglais *memory leak*) : le programme consomme de plus en plus, jusqu'à éventuellement épuiser la
machine.

```c
free(tableau);          // rend le bloc au système
tableau = NULL;         // bonne habitude : éviter de réutiliser un pointeur libéré
```

La règle d'or : **à chaque `malloc` correspond un `free`**. Un bloc alloué doit être libéré une fois,
exactement une fois, quand on n'en a plus besoin.

> **Attention** — Deux erreurs graves autour de `free` :
> - **Double *free*** : appeler `free` deux fois sur le même pointeur corrompt le tas (plantage).
> - **Pointeur pendant** (*dangling pointer*) : utiliser un pointeur **après** l'avoir libéré, car il
>   pointe vers une zone rendue. Mettre le pointeur à `NULL` après `free` t'évite ces deux pièges :
>   un déréférencement de `NULL` plante immédiatement, donc le bug se voit tout de suite.

## Le cycle complet

Voici le schéma type, à mémoriser : allouer → tester → utiliser → libérer.

```c
#include <stdio.h>
#include <stdlib.h>

int main(void)
{
    int n;
    printf("Combien de valeurs ? ");
    scanf("%d", &n);

    int *valeurs = malloc(n * sizeof(int));     // 1. allouer
    if (valeurs == NULL)                        // 2. tester
        return 1;

    for (int i = 0; i < n; i++)                 // 3. utiliser
        valeurs[i] = i * i;
    for (int i = 0; i < n; i++)
        printf("%d ", valeurs[i]);
    printf("\n");

    free(valeurs);                              // 4. libérer
    valeurs = NULL;
    return 0;
}
```

Ce programme alloue **exactement** la taille demandée à l'exécution, ce qu'un tableau classique ne sait
pas faire.

## `realloc` : redimensionner

Quand tu ne connais pas la taille à l'avance et qu'elle grandit (on lit des votants un par un sans
savoir combien il y en aura), `realloc` agrandit un bloc déjà alloué en préservant son contenu :

```c
// On double la capacité quand le tableau est plein.
capacite *= 2;
valeurs = realloc(valeurs, capacite * sizeof(int));
if (valeurs == NULL) { /* gérer l'échec */ }
```

`realloc` peut déplacer le bloc ailleurs (et renvoyer une nouvelle adresse), c'est pourquoi on
réaffecte `valeurs`. On l'utilisera peu dans le projet (on lira le nombre de votants au début), mais
c'est l'outil des tableaux qui grossissent.

## Détecter les fuites avec valgrind

Comment savoir si tu as bien libéré toute ta mémoire ? L'œil humain n'est pas fiable. **valgrind** (déjà
installé dans ton conteneur) exécute ton programme en surveillant chaque allocation. On lance :

```bash
$ gcc -g -Wall -Wextra prog.c -o prog
$ valgrind ./prog
```

L'option `-g` ajoute les informations de débogage (numéros de ligne). À la fin, valgrind affiche un
bilan. Si tout est libéré :

```
All heap blocks were freed -- no leaks are possible
```

Si tu as oublié un `free`, il pointe précisément où le bloc fuit :

```
40 bytes in 1 blocks are definitely lost in loss record 1 of 1
   at 0x...: malloc
   by 0x...: main (prog.c:9)
```

> **Astuce** — Lance `valgrind` régulièrement pendant que tu développes du code qui alloue, pas
> seulement à la fin. Une fuite repérée tôt se corrige en dix secondes ; une fuite noyée dans 2000
> lignes te coûte une soirée. On y reviendra en détail au [chapitre 12](12-deboguer.md).

## Résumé

- La **pile** (*stack*) héberge les variables locales : automatique, mais taille figée et durée de vie
  limitée à la fonction. Le **tas** (*heap*) est géré à la main et permet une taille choisie à
  l'exécution.
- `malloc(n * sizeof(type))` réserve un bloc et renvoie un pointeur ; **teste s'il vaut `NULL`**.
- Un pointeur sur un bloc alloué s'utilise comme un **tableau**.
- À chaque `malloc` son `free`. Oublier = **fuite mémoire**. Mets le pointeur à `NULL` après `free`
  pour éviter double *free* et pointeur pendant.
- `realloc` redimensionne un bloc (et peut le déplacer : réaffecte le pointeur).
- **valgrind** détecte les fuites et les erreurs mémoire : `valgrind ./prog`.

## Exercices

### Exercice 1 — Tableau dynamique de carrés

Demande un nombre `n`, alloue un tableau de `n` entiers, remplis-le avec les carrés `0, 1, 4, 9, …`,
affiche-le, puis libère. Vérifie avec valgrind qu'il n'y a aucune fuite.

<details>
<summary>Voir le corrigé</summary>

```c
#include <stdio.h>
#include <stdlib.h>

int main(void)
{
    int n;
    printf("n ? ");
    scanf("%d", &n);

    int *carres = malloc(n * sizeof(int));
    if (carres == NULL)
        return 1;

    for (int i = 0; i < n; i++)
        carres[i] = i * i;
    for (int i = 0; i < n; i++)
        printf("%d ", carres[i]);
    printf("\n");

    free(carres);
    return 0;
}
```

```bash
$ gcc -g -Wall -Wextra carres.c -o carres
$ valgrind ./carres
...
All heap blocks were freed -- no leaks are possible
```

Si tu commentes la ligne `free(carres);`, valgrind signalera `definitely lost` : c'est exactement ce
qu'on veut apprendre à repérer.

</details>

### Exercice 2 — Repérer la fuite

Ce programme a un bug. Lequel ? Corrige-le.

```c
#include <stdlib.h>

int main(void)
{
    for (int i = 0; i < 1000; i++)
    {
        int *buffer = malloc(256 * sizeof(int));
        buffer[0] = i;
    }
    return 0;
}
```

<details>
<summary>Voir le corrigé</summary>

À chaque tour de boucle, on alloue 256 entiers et on **perd** l'adresse au tour suivant (le pointeur
`buffer` est réécrit) sans avoir libéré. C'est une fuite : 1000 blocs alloués, 0 libéré. valgrind
dirait `definitely lost`. Il faut libérer dans la boucle :

```c
for (int i = 0; i < 1000; i++)
{
    int *buffer = malloc(256 * sizeof(int));
    if (buffer == NULL)
        return 1;
    buffer[0] = i;
    free(buffer);           // on libère avant de perdre l'adresse
}
```

Règle générale : si tu alloues **dans** une boucle, libère **dans** la même boucle (ou conserve les
adresses pour les libérer plus tard).

</details>

## Quiz

**1.** Où vit une variable locale `int x;` déclarée dans une fonction ?
- A. Sur le tas.
- B. Sur la pile.
- C. Dans un registre uniquement.

**2.** Que renvoie `malloc` en cas d'échec ?
- A. 0 dans tous les cas.
- B. `NULL`.
- C. Une adresse aléatoire.

**3.** Qu'est-ce qu'une fuite mémoire ?
- A. Un tableau lu hors de ses bornes.
- B. De la mémoire allouée jamais libérée.
- C. Un pointeur qui vaut `NULL`.

**4.** Pourquoi mettre un pointeur à `NULL` après `free` ?
- A. Pour libérer plus de mémoire.
- B. Pour éviter de le réutiliser par erreur (pointeur pendant, double *free*).
- C. C'est obligatoire, sinon le programme ne compile pas.

<details>
<summary>Voir les réponses</summary>

1. **B** — les variables locales vivent sur la pile, gérées automatiquement.
2. **B** — `malloc` renvoie `NULL` s'il ne peut pas allouer ; toujours le tester.
3. **B** — de la mémoire allouée et jamais rendue avec `free`.
4. **B** — éviter le pointeur pendant et le double *free* ; un déréférencement de `NULL` plante tout de
   suite, donc le bug se voit.

</details>

## Projet fil rouge

On supprime les limites `MAX_CANDIDATS` et `MAX_VOTANTS` codées en dur. Le tableau de candidats et la
grille des préférences sont désormais **alloués dynamiquement** selon les nombres saisis. Le scrutin
s'adapte à n'importe quelle taille.

La grille des préférences est un tableau 2D dynamique. La technique la plus simple et la plus sûre : un
**tableau de pointeurs**, une ligne par votant.

```c
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <stdbool.h>

#define LONGUEUR_NOM 30

typedef struct
{
    char nom[LONGUEUR_NOM];
    int  votes;
    bool elimine;
} Candidate;

int main(void)
{
    int nb_candidats = 3;
    int nb_votants   = 5;

    // Tableau de candidats, taille choisie à l'exécution.
    Candidate *candidats = malloc(nb_candidats * sizeof(Candidate));
    if (candidats == NULL)
        return 1;

    // Grille des préférences : un tableau de nb_votants lignes,
    // chaque ligne ayant nb_candidats colonnes (les rangs).
    int **preferences = malloc(nb_votants * sizeof(int *));
    if (preferences == NULL)
        return 1;
    for (int v = 0; v < nb_votants; v++)
    {
        preferences[v] = malloc(nb_candidats * sizeof(int));
        if (preferences[v] == NULL)
            return 1;
    }

    printf("Memoire allouee pour %d candidats et %d votants.\n", nb_candidats, nb_votants);

    // ... (saisie et dépouillement viendront aux chapitres suivants)

    // Libération : symétrique de l'allocation, dans l'ordre inverse.
    for (int v = 0; v < nb_votants; v++)
        free(preferences[v]);
    free(preferences);
    free(candidats);
    return 0;
}
```

`int **preferences` est un **pointeur de pointeurs** : `preferences[v]` est la ligne du votant `v`, et
`preferences[v][rang]` une case. Note la **symétrie** allocation/libération : chaque ligne allouée est
libérée, puis le tableau de lignes, puis les candidats. Lance `valgrind ./runoff` pour confirmer
« no leaks ».

Au prochain chapitre, on **organise** ce programme grandissant en plusieurs fichiers avec un Makefile,
et on lit les bulletins depuis un **fichier** plutôt qu'au clavier.

---

[← Chapitre précédent](07-structures.md) · [Sommaire](README.md) · [Chapitre suivant →](09-organiser-un-programme.md)
