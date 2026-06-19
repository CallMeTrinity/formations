# Tableaux et chaînes de caractères

[← Chapitre précédent](04-fonctions.md) · [Sommaire](README.md) · [Chapitre suivant →](06-pointeurs.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- déclarer et parcourir un **tableau** à taille fixe, et indicer correctement ;
- comprendre qu'une **chaîne de caractères** en C est un tableau de `char` terminé par `\0` ;
- lire et afficher des chaînes (`%s`, `fgets`) et utiliser `<string.h>` ;
- manipuler un **tableau à deux dimensions** (la structure des bulletins du projet).

## Les tableaux

Un **tableau** (en anglais *array*) stocke plusieurs valeurs **du même type**, contiguës en mémoire,
accessibles par un **indice**. On déclare un tableau en précisant son type, son nom et sa **taille**
entre crochets :

```c
int notes[5];                       // 5 entiers, indices 0 à 4 (non initialisés)
int scores[3] = {12, 8, 15};        // déclaré et initialisé
```

L'accès se fait par l'indice, **à partir de 0** :

```c
scores[0] = 20;                     // modifie le premier élément
printf("%d\n", scores[2]);          // affiche 15 (le troisième)
```

Pour parcourir un tableau, la boucle `for` est faite pour ça. On va de `0` à `taille - 1` :

```c
int total = 0;
for (int i = 0; i < 3; i++)
{
    total += scores[i];
}
printf("Somme : %d\n", total);      // 35
```

> **Attention** — Le C **ne vérifie pas** les indices. Écrire `scores[3]` ou `scores[10]` sur un
> tableau de taille 3 ne provoque aucune erreur à la compilation : le programme lit ou écrit **en
> dehors** du tableau, dans une mémoire qui ne lui appartient pas. Résultat : valeur fausse, plantage,
> ou bug qui n'apparaît que plus tard. **C'est à toi de rester dans les bornes `0` à `taille - 1`.**

La taille d'un tableau doit être connue à la déclaration. On utilise donc souvent une constante
`#define`, qui sert à la fois à dimensionner et à borner les boucles :

```c
#define NB_JOUEURS 4

int scores[NB_JOUEURS];
for (int i = 0; i < NB_JOUEURS; i++)
    scores[i] = 0;                  // tout à zéro
```

Si demain l'équipe passe à 6 joueurs, tu changes **une seule ligne**. C'est pour ça qu'on définit les
tailles par constante plutôt qu'en dur.

## Les chaînes de caractères

Le C n'a **pas de type `string`**. Une chaîne, c'est un **tableau de `char`** qui se termine par un
caractère spécial : le **caractère nul** `'\0'` (valeur 0). Ce `\0` marque la fin : c'est lui qui
permet à `printf` de savoir où la chaîne s'arrête.

```c
char ville[] = "Lyon";
```

Cette ligne crée un tableau de **5** `char` (pas 4 !) : `'L'`, `'y'`, `'o'`, `'n'`, `'\0'`. Le `\0`
est ajouté automatiquement par le compilateur.

```
indice :   0    1    2    3    4
valeur :  'L'  'y'  'o'  'n'  '\0'
```

On affiche une chaîne avec `%s`, qui lit les caractères **jusqu'au `\0`** :

```c
printf("Ville : %s\n", ville);      // Ville : Lyon
```

> **À retenir** — Une chaîne C = tableau de `char` + un `'\0'` final. Toujours prévoir **une case de
> plus** pour ce `\0`. Un tableau `char nom[10]` ne peut contenir qu'une chaîne de **9 caractères**
> utiles.

### Lire une chaîne au clavier

On pourrait utiliser `scanf("%s", ...)`, mais il a un défaut grave : il ne vérifie pas la taille du
tableau et **déborde** si l'utilisateur tape trop long. Préfère `fgets`, qui prend la taille maximale
en argument :

```c
char nom[20];
printf("Ton nom ? ");
fgets(nom, sizeof(nom), stdin);     // lit au plus 19 caractères depuis l'entrée standard
printf("Bonjour %s", nom);
```

`sizeof(nom)` donne la taille du tableau en octets (ici 20). `stdin` désigne l'entrée clavier. Petit
défaut de `fgets` : il **garde le retour à la ligne** `\n` que tu tapes. On le retire souvent ainsi :

```c
nom[strcspn(nom, "\n")] = '\0';     // coupe la chaîne au premier \n
```

`strcspn` vient de `<string.h>` et renvoie la position du `\n` ; on y place un `\0` pour terminer la
chaîne juste avant.

### La bibliothèque `<string.h>`

Comme une chaîne est un tableau, tu **ne peux pas** la copier avec `=` ni la comparer avec `==`. Ces
opérations agiraient sur des adresses, pas sur le contenu. On utilise les fonctions de `<string.h>` :

| Fonction | Rôle | Exemple |
| --- | --- | --- |
| `strlen(s)` | longueur (hors `\0`) | `strlen("Lyon")` vaut 4 |
| `strcmp(a, b)` | compare ; **0 si égales** | `strcmp(x, "oui") == 0` |
| `strcpy(dst, src)` | copie `src` dans `dst` | `strcpy(nom, "Marie")` |
| `strncpy(dst, src, n)` | copie au plus `n` caractères | plus sûr que `strcpy` |

```c
#include <string.h>

char reponse[10];
fgets(reponse, sizeof(reponse), stdin);
reponse[strcspn(reponse, "\n")] = '\0';

if (strcmp(reponse, "oui") == 0)    // ATTENTION : == 0 signifie "égales"
    printf("Tu as dit oui\n");
```

> **Attention** — `strcmp` renvoie **0 quand les chaînes sont égales** (et une valeur non nulle
> sinon). C'est contre-intuitif : on écrit `if (strcmp(a, b) == 0)` pour « si a égale b ». Oublier le
> `== 0` inverse la logique.

## Tableaux à deux dimensions

Un tableau peut avoir plusieurs dimensions : c'est une grille. `grille[ligne][colonne]`. C'est
exactement ce qu'il faut pour les **bulletins** du projet : une ligne par votant, une colonne par
rang de préférence.

```c
int preferences[3][4];              // 3 votants, chacun classe jusqu'à 4 candidats

preferences[0][0] = 2;              // le votant 0 met le candidat 2 en premier
preferences[0][1] = 0;              // puis le candidat 0
preferences[0][2] = 1;              // puis le candidat 1
```

On parcourt une grille avec **deux boucles imbriquées** :

```c
for (int v = 0; v < 3; v++)             // pour chaque votant
{
    for (int rang = 0; rang < 4; rang++) // pour chaque rang de préférence
    {
        printf("%d ", preferences[v][rang]);
    }
    printf("\n");
}
```

> **À retenir** — `tableau[i][j]` : le premier indice choisit la **ligne**, le second la **colonne**.
> On parcourt avec deux `for` imbriqués, l'extérieur pour les lignes, l'intérieur pour les colonnes.

## Résumé

- Un **tableau** stocke `n` valeurs du même type, indicées de `0` à `n - 1`. Le C **ne vérifie pas**
  les bornes : sortir du tableau est un bug silencieux.
- Dimensionne les tableaux avec une constante `#define` pour pouvoir la changer en un endroit.
- Une **chaîne** est un tableau de `char` terminé par `'\0'`. Prévois toujours une case pour ce `\0`.
- Affiche avec `%s`, lis avec `fgets(tab, sizeof(tab), stdin)` (pas `scanf("%s")`, qui déborde).
- Copie/comparaison de chaînes via `<string.h>` : `strcpy`, `strcmp` (**0 = égales**), `strlen`.
- Un tableau 2D `t[ligne][colonne]` se parcourt avec deux `for` imbriqués.

## Exercices

### Exercice 1 — Maximum d'un tableau

Écris une fonction `int maximum(int tab[], int taille)` qui renvoie la plus grande valeur d'un tableau
d'entiers. Teste-la sur `{12, 45, 7, 33, 9}`.

<details>
<summary>Voir le corrigé</summary>

On initialise le maximum avec le **premier** élément, puis on compare chaque suivant. (Initialiser à 0
serait faux si toutes les valeurs étaient négatives.)

```c
#include <stdio.h>

int maximum(int tab[], int taille)
{
    int max = tab[0];
    for (int i = 1; i < taille; i++)
    {
        if (tab[i] > max)
            max = tab[i];
    }
    return max;
}

int main(void)
{
    int valeurs[5] = {12, 45, 7, 33, 9};
    printf("Max : %d\n", maximum(valeurs, 5));   // 45
    return 0;
}
```

Note `int tab[]` en paramètre : on passe un tableau **et** sa taille, car la fonction ne connaît pas
le nombre d'éléments toute seule. On comprendra au chapitre 6 pourquoi.

</details>

### Exercice 2 — Compter une lettre

Écris une fonction qui compte combien de fois une lettre apparaît dans une chaîne. Teste avec la
lettre `'a'` dans `"banana"` (réponse attendue : 3).

<details>
<summary>Voir le corrigé</summary>

On parcourt la chaîne jusqu'au `\0`. Soit on utilise `strlen`, soit on teste `s[i] != '\0'`
directement.

```c
#include <stdio.h>

int compter(char s[], char lettre)
{
    int n = 0;
    for (int i = 0; s[i] != '\0'; i++)   // on s'arrête au caractère nul
    {
        if (s[i] == lettre)
            n++;
    }
    return n;
}

int main(void)
{
    printf("%d\n", compter("banana", 'a'));   // 3
    return 0;
}
```

La condition `s[i] != '\0'` exploite directement le marqueur de fin de chaîne : élégant et idiomatique
en C.

</details>

## Quiz

**1.** Combien d'octets occupe `char mot[] = "chat"` ?
- A. 4
- B. 5
- C. 8

**2.** Que renvoie `strcmp("oui", "oui")` ?
- A. 1
- B. 0
- C. la longueur de la chaîne

**3.** Que se passe-t-il si tu écris `tab[5]` sur un tableau `int tab[5]` ?
- A. Erreur de compilation.
- B. Le C ramène l'indice à 4.
- C. Rien n'empêche l'accès : tu lis/écris hors du tableau, comportement indéfini.

**4.** Pourquoi préférer `fgets` à `scanf("%s", ...)` pour lire une chaîne ?
- A. `fgets` est plus rapide.
- B. `fgets` limite la taille lue et évite le débordement du tableau.
- C. `scanf` ne lit pas les chaînes.

<details>
<summary>Voir les réponses</summary>

1. **B** — `"chat"` = 4 lettres + le `'\0'` final = 5 octets.
2. **B** — `strcmp` renvoie 0 quand les chaînes sont égales.
3. **C** — pas de vérification des bornes : accès hors tableau, comportement indéfini.
4. **B** — `fgets` prend une taille maximale et ne déborde pas, contrairement à `scanf("%s")`.

</details>

## Projet fil rouge

On stocke enfin les **données du scrutin** : les **noms** des candidats (un tableau de chaînes) et les
**bulletins** (un tableau 2D où chaque ligne est le classement d'un votant). On fixe des tailles
maximales par `#define` ; on les rendra dynamiques au [chapitre 8](08-memoire-dynamique.md).

```c
#include <stdio.h>
#include <string.h>

#define MAX_CANDIDATS 9
#define MAX_VOTANTS   100
#define LONGUEUR_NOM  30

int main(void)
{
    char noms[MAX_CANDIDATS][LONGUEUR_NOM];   // un nom par candidat
    int  preferences[MAX_VOTANTS][MAX_CANDIDATS]; // preferences[v][rang] = indice du candidat

    int nb_candidats = 3;

    // Saisie des noms des candidats.
    for (int c = 0; c < nb_candidats; c++)
    {
        printf("Nom du candidat %d : ", c);
        fgets(noms[c], LONGUEUR_NOM, stdin);
        noms[c][strcspn(noms[c], "\n")] = '\0';   // retire le retour à la ligne
    }

    // Vérification : on réaffiche les candidats avec leur indice.
    printf("\nCandidats en lice :\n");
    for (int c = 0; c < nb_candidats; c++)
    {
        printf("  [%d] %s\n", c, noms[c]);
    }
    return 0;
}
```

```
Nom du candidat 0 : Alice
Nom du candidat 1 : Bob
Nom du candidat 2 : Charlie

Candidats en lice :
  [0] Alice
  [1] Bob
  [2] Charlie
```

Chaque candidat a un **indice** (0, 1, 2…) : c'est par cet indice qu'on le désignera dans les
bulletins, pas par son nom. Au prochain chapitre, les **pointeurs** vont éclairer pourquoi un tableau
passé à une fonction se comporte de façon particulière, et nous donner enfin le moyen de modifier des
données depuis une fonction.

---

[← Chapitre précédent](04-fonctions.md) · [Sommaire](README.md) · [Chapitre suivant →](06-pointeurs.md)
