# Types, variables et entrées/sorties

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-conditions-et-boucles.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- déclarer des variables avec les **types de base** du C et comprendre pourquoi le type est
  obligatoire ;
- afficher des valeurs avec `printf` et ses **spécificateurs de format** (`%d`, `%f`, `%c`…) ;
- lire des valeurs tapées par l'utilisateur avec `scanf` ;
- éviter les pièges des **conversions de types** et des **constantes**.

## Pourquoi des types ?

En Python, tu écris `x = 5` et l'interpréteur devine que `x` est un entier. En C, c'est impossible :
le compilateur doit savoir **à l'avance** combien d'octets réserver pour `x` et comment interpréter
ces octets. Tu dois donc **déclarer le type** de chaque variable.

```c
int age = 24;        // un entier
double taille = 1.78; // un nombre à virgule
char initiale = 'A';  // un seul caractère
```

Une déclaration, c'est un **type**, un **nom**, et souvent une **valeur initiale**. Le type est fixé
une fois pour toutes : `age` sera un `int` jusqu'à la fin de sa vie.

> **Attention** — Une variable déclarée sans valeur (`int age;`) n'est **pas** à zéro. Elle contient
> ce qui traînait en mémoire à cet endroit : une valeur imprévisible (on dit *indéterminée*). En C,
> rien ne t'avertit. Prends l'habitude d'**initialiser tes variables** dès leur déclaration.

## Les types de base

Voici les types que tu utiliseras 95 % du temps :

| Type | Contenu | Exemple | Format `printf` |
| --- | --- | --- | --- |
| `int` | entier signé | `42`, `-7` | `%d` |
| `double` | nombre à virgule (double précision) | `3.14`, `-0.5` | `%f` |
| `char` | un caractère (en réalité un petit entier) | `'A'`, `'7'` | `%c` |
| `unsigned int` | entier positif ou nul | `0`, `3000000000` | `%u` |
| `long` | entier de plus grande capacité | `9000000000L` | `%ld` |

Quelques points qui surprennent quand on vient d'un langage haut niveau :

- Un `int` n'est pas infini. Sur les machines courantes il tient sur **4 octets**, soit des valeurs
  d'environ −2,1 à +2,1 milliards. Au-delà, il **déborde** (on y reviendra).
- `char` est un **caractère** mais aussi un **entier** sur 1 octet. `'A'` vaut en réalité 65 (son code
  ASCII). On peut donc faire de l'arithmétique dessus : `'A' + 1` vaut `'B'`.
- Il n'y a **pas de type booléen** d'origine. Le C utilise des entiers : **0 = faux**, **tout le reste
  = vrai**. Depuis C99, tu peux inclure `<stdbool.h>` pour écrire `bool`, `true`, `false`, qui sont
  juste des noms plus lisibles. On les utilisera.

```c
#include <stdbool.h>

bool majeur = true;   // équivaut à : int majeur = 1;
```

> **À retenir** — `char` est un entier sur 1 octet ; un « booléen » est un `int` où 0 = faux.
> `<stdbool.h>` ne fait qu'ajouter des noms lisibles par-dessus.

## Afficher avec `printf`

`printf` (*print formatted*) affiche du texte dans lequel tu insères des valeurs, repérées par des
**spécificateurs de format** commençant par `%`. Chaque `%` est remplacé, dans l'ordre, par les
arguments suivants.

```c
#include <stdio.h>

int main(void)
{
    int age = 24;
    double taille = 1.78;
    char initiale = 'A';

    printf("J'ai %d ans, je mesure %.2f m, initiale %c.\n", age, taille, initiale);
    return 0;
}
```

```
# Sortie :
J'ai 24 ans, je mesure 1.78 m, initiale A.
```

Le `.2` dans `%.2f` demande **2 chiffres après la virgule**. Sans lui, `%f` affiche 6 décimales par
défaut (`1.780000`). Tu peux aussi imposer une largeur : `%5d` affiche l'entier sur au moins 5
caractères, aligné à droite (pratique pour des colonnes).

> **Attention** — Le spécificateur doit **correspondre au type** de l'argument. Afficher un `double`
> avec `%d`, ou un `int` avec `%f`, produit n'importe quoi (pas forcément un plantage : juste un
> résultat faux). C'est l'erreur la plus courante du débutant. Compile avec `-Wall` : gcc te prévient.

## Lire avec `scanf`

`scanf` (*scan formatted*) est le miroir de `printf` : il lit ce que l'utilisateur tape et le range
dans tes variables. Même logique de spécificateurs, avec **une différence cruciale** : tu dois passer
l'**adresse** de la variable, avec l'opérateur `&` (« adresse de »).

```c
#include <stdio.h>

int main(void)
{
    int annee_naissance;

    printf("Ton annee de naissance ? ");
    scanf("%d", &annee_naissance);          // &  ->  adresse de la variable

    printf("Tu auras %d ans en 2026.\n", 2026 - annee_naissance);
    return 0;
}
```

```
# Exemple d'exécution :
Ton annee de naissance ? 2002
Tu auras 24 ans en 2026.
```

Pourquoi `&` ? `scanf` doit **modifier** ta variable, donc il a besoin de savoir *où elle se trouve*
en mémoire, pas seulement de sa valeur. `&annee_naissance` veut dire « l'adresse de
`annee_naissance` ». On reviendra longuement là-dessus au [chapitre 6](06-pointeurs.md) : pour
l'instant, retiens la règle.

> **Attention** — Oublier le `&` dans un `scanf` est l'erreur classique. Le programme compile (souvent
> avec un *warning*) mais **plante à l'exécution** (*segmentation fault*), parce que `scanf` écrit à
> une adresse absurde. Devant un `scanf` qui plante, vérifie d'abord tes `&`.

## Conversions de types : le piège de la division

Quand tu mélanges des types, le C **convertit** automatiquement, et le résultat n'est pas toujours
celui qu'on attend. Le cas le plus piégeux : la division entière.

```c
int total = 7;
int parts = 2;

double moyenne = total / parts;     // PIÈGE
printf("%f\n", moyenne);            // affiche 3.000000, pas 3.5 !
```

Pourquoi ? `total / parts` est une division entre **deux entiers**, donc le C fait une **division
entière** : 7 / 2 = 3, le reste est jeté. Le résultat (3) n'est converti en `double` qu'**ensuite**,
trop tard. Pour obtenir 3.5, il faut qu'au moins un des deux opérandes soit un `double` au moment de
la division. On force avec un **cast** (conversion explicite) :

```c
double moyenne = (double) total / parts;   // (double) convertit total avant la division
printf("%f\n", moyenne);                    // affiche 3.500000
```

`(double) total` dit « traite `total` comme un `double` ». La division devient alors une division
réelle. Ce piège reviendra dans le projet quand on calculera des pourcentages de voix.

> **À retenir** — `int / int` donne un `int` (reste jeté). Pour une division réelle, **caste** un des
> opérandes en `double`.

## Constantes

Pour une valeur qui ne doit jamais changer, deux options :

```c
const int JOURS_PAR_SEMAINE = 7;   // une vraie variable, mais en lecture seule
#define MAX_CANDIDATS 9            // une macro : remplacée par le préprocesseur
```

`const` crée une variable normale que le compilateur **interdit de modifier**. `#define` est traité
par le préprocesseur, qui remplace bêtement chaque `MAX_CANDIDATS` par `9` **avant** la compilation.
Les deux sont courants ; on utilisera surtout `#define` pour les tailles de tableaux (on verra
pourquoi au [chapitre 5](05-tableaux-et-chaines.md)). Par convention, on écrit ces constantes en
**MAJUSCULES**.

## Résumé

- En C, **chaque variable a un type déclaré** : le compilateur doit connaître sa taille. Initialise
  toujours tes variables, sinon elles valent n'importe quoi.
- Types de base : `int`, `double`, `char` (un entier sur 1 octet), `unsigned`, `long`. Pas de booléen
  d'origine : 0 = faux, le reste = vrai ; `<stdbool.h>` ajoute `bool`/`true`/`false`.
- `printf` affiche avec des spécificateurs (`%d`, `%f`, `%c`…) ; `%.2f` contrôle les décimales. Le
  spécificateur doit **correspondre au type**.
- `scanf` lit dans tes variables ; il faut passer l'**adresse** avec `&`. Oublier le `&` fait planter.
- `int / int` est une **division entière** : caste en `(double)` pour une division réelle.
- Constantes via `const` ou `#define` (en MAJUSCULES).

## Exercices

### Exercice 1 — Convertisseur de température

Écris un programme qui demande une température en degrés Celsius (un `double`) et affiche son
équivalent en Fahrenheit, avec 1 décimale. Formule : `F = C * 9 / 5 + 32`.

<details>
<summary>Voir le corrigé</summary>

Le piège : `9 / 5` est une division entière qui vaut 1 ! Il faut écrire `9.0 / 5.0`, ou multiplier par
le `double` `celsius` d'abord.

```c
#include <stdio.h>

int main(void)
{
    double celsius;
    printf("Temperature en Celsius ? ");
    scanf("%lf", &celsius);                 // %lf : lit un double

    double fahrenheit = celsius * 9.0 / 5.0 + 32;
    printf("%.1f C = %.1f F\n", celsius, fahrenheit);
    return 0;
}
```

Note `%lf` pour **lire** un `double` avec `scanf` (le `l` veut dire *long*). Pour l'**afficher** avec
`printf`, `%f` suffit. C'est une incohérence historique du C qu'il faut juste connaître.

```
Temperature en Celsius ? 20
20.0 C = 68.0 F
```

</details>

### Exercice 2 — Moyenne de trois notes

Demande trois notes entières et affiche leur moyenne avec 2 décimales. Vérifie que tu obtiens bien
`13.67` pour 12, 14, 15 (et pas un nombre entier).

<details>
<summary>Voir le corrigé</summary>

La somme de trois `int` est un `int`. Pour que la division par 3 soit réelle, on caste la somme (ou le
3) en `double`.

```c
#include <stdio.h>

int main(void)
{
    int n1, n2, n3;
    printf("Trois notes ? ");
    scanf("%d %d %d", &n1, &n2, &n3);       // scanf lit trois entiers separes par des espaces

    double moyenne = (double) (n1 + n2 + n3) / 3;
    printf("Moyenne : %.2f\n", moyenne);
    return 0;
}
```

```
Trois notes ? 12 14 15
Moyenne : 13.67
```

Sans le `(double)`, tu obtiendrais `13.00`.

</details>

## Quiz

**1.** Que vaut `7 / 2` en C, si les deux opérandes sont des `int` ?
- A. `3.5`
- B. `3`
- C. `4`

**2.** Pourquoi écrit-on `scanf("%d", &age)` et pas `scanf("%d", age)` ?
- A. Le `&` accélère la lecture.
- B. `scanf` doit modifier la variable, il lui faut son **adresse**.
- C. C'est une convention sans effet réel.

**3.** Quel est le résultat de `'A' + 1` en C ?
- A. Une erreur : on n'additionne pas des lettres.
- B. La valeur `'B'` (66), car un `char` est un entier.
- C. La chaîne `"A1"`.

**4.** Comment afficher un `double` avec exactement 2 décimales ?
- A. `%2f`
- B. `%.2f`
- C. `%d2`

<details>
<summary>Voir les réponses</summary>

1. **B** — division entière entre deux `int` : le reste est jeté.
2. **B** — `scanf` écrit dans la variable, donc il a besoin de son adresse (`&`).
3. **B** — `char` est un entier sur 1 octet ; `'A'` vaut 65, donc `'A' + 1` vaut 66 = `'B'`.
4. **B** — `%.2f` ; `%2f` impose une largeur minimale, pas un nombre de décimales.

</details>

## Projet fil rouge

On rend `runoff` interactif : il demande **combien de candidats** et **combien de votants** participent
au scrutin, et affiche un récapitulatif. Ces deux nombres pilotent tout le reste du programme.

```c
#include <stdio.h>

int main(void)
{
    printf("=== Scrutin a vote alternatif (runoff) ===\n");

    int nb_candidats;
    int nb_votants;

    printf("Nombre de candidats ? ");
    scanf("%d", &nb_candidats);

    printf("Nombre de votants ? ");
    scanf("%d", &nb_votants);

    printf("\nScrutin avec %d candidats et %d votants.\n", nb_candidats, nb_votants);
    return 0;
}
```

```
=== Scrutin a vote alternatif (runoff) ===
Nombre de candidats ? 3
Nombre de votants ? 5

Scrutin avec 3 candidats et 5 votants.
```

Au prochain chapitre, on calculera le **seuil de majorité** (la moitié des votants) à l'aide de
conditions, et on commencera à valider les entrées.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-conditions-et-boucles.md)
