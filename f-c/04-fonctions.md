# Les fonctions

[← Chapitre précédent](03-conditions-et-boucles.md) · [Sommaire](README.md) · [Chapitre suivant →](05-tableaux-et-chaines.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- définir une fonction avec un **type de retour**, des **paramètres** et un corps ;
- comprendre pourquoi le C exige un **prototype** avant l'appel, et où le placer ;
- saisir que les arguments sont passés **par valeur** (copiés), et ce que ça implique ;
- distinguer **variables locales** et **globales**, et leur portée.

## Définir une fonction

Une fonction regroupe un bout de code sous un nom, pour le réutiliser et clarifier l'intention. En C,
sa déclaration suit toujours la forme : **type de retour**, **nom**, **paramètres typés**.

```c
// Renvoie le plus grand des deux entiers.
int maximum(int a, int b)
{
    if (a > b)
        return a;
    return b;
}
```

- `int` (devant le nom) est le **type de la valeur renvoyée**.
- `int a, int b` sont les **paramètres** : chacun a son type, comme une variable.
- `return` renvoie une valeur **et termine** la fonction immédiatement.

Une fonction qui ne renvoie rien a le type spécial `void` :

```c
void afficher_titre(void)        // ne renvoie rien, ne prend rien
{
    printf("=== Resultats ===\n");
}
```

Dans une fonction `void`, `return;` (sans valeur) sort de la fonction ; il est facultatif à la fin.

## L'ordre compte : les prototypes

Le compilateur C lit ton fichier **de haut en bas**, une seule fois. S'il rencontre un appel à une
fonction qu'il n'a pas encore vue, il proteste. Ce code échoue donc :

```c
#include <stdio.h>

int main(void)
{
    printf("%d\n", maximum(3, 7));   // ERREUR : maximum pas encore connu ici
    return 0;
}

int maximum(int a, int b) { return a > b ? a : b; }
```

Deux solutions. La première : **définir la fonction avant** de l'appeler (la mettre au-dessus de
`main`). La seconde, qui passe à l'échelle : déclarer un **prototype** en haut du fichier. Un prototype
est la **signature** de la fonction (sa première ligne suivie d'un `;`), sans le corps :

```c
#include <stdio.h>

int maximum(int a, int b);       // PROTOTYPE : "cette fonction existe, voici sa forme"

int main(void)
{
    printf("%d\n", maximum(3, 7));   // OK : le compilateur connaît la signature
    return 0;
}

int maximum(int a, int b)        // DÉFINITION : le vrai code
{
    return a > b ? a : b;
}
```

Le prototype suffit au compilateur pour vérifier que tu appelles `maximum` correctement (bon nombre
d'arguments, bons types). C'est cette séparation **déclaration / définition** qui permettra, au
[chapitre 9](09-organiser-un-programme.md), de ranger les prototypes dans des fichiers `.h` partagés.

> **À retenir** — Déclare un **prototype** en haut du fichier pour chaque fonction, et écris les
> **définitions** plus bas. `main` reste ainsi en haut, lisible, et l'ordre des définitions n'a plus
> d'importance.

> **Astuce** — `a > b ? a : b` est l'**opérateur ternaire** : `condition ? valeur_si_vrai :
> valeur_si_faux`. C'est un `if`/`else` condensé en une expression. Pratique pour les petits choix,
> mais ne l'imbrique pas : ça devient illisible.

## Passage par valeur : les arguments sont copiés

Point fondamental, et source de surprises : en C, **les arguments sont passés par valeur**. La
fonction reçoit une **copie** de ce que tu lui donnes. Modifier le paramètre à l'intérieur ne change
**rien** à l'extérieur.

```c
#include <stdio.h>

void essaie_doubler(int x)
{
    x = x * 2;               // modifie la COPIE locale, pas l'original
    printf("dans la fonction : %d\n", x);
}

int main(void)
{
    int n = 5;
    essaie_doubler(n);
    printf("dans main       : %d\n", n);   // n vaut toujours 5 !
    return 0;
}
```

```
# Sortie :
dans la fonction : 10
dans main       : 5
```

`essaie_doubler` reçoit une copie de `n` dans son paramètre `x`. Elle double `x`, mais `n` dans `main`
est intact. Pour qu'une fonction **modifie** une variable de l'appelant, il faut lui passer son
**adresse** — c'est exactement ce que fait `scanf` avec `&`, et tout le sujet du
[chapitre 6](06-pointeurs.md). Garde cette frustration en tête : elle motive les pointeurs.

> **À retenir** — Passer une variable à une fonction la **copie**. La fonction ne peut pas modifier
> l'original ainsi. Pour ça, il faudra passer une **adresse** (chapitre 6).

## Portée des variables

Une variable déclarée **dans** une fonction (ou un bloc) est **locale** : elle n'existe que là, et
disparaît à la sortie. Deux fonctions peuvent avoir chacune leur variable `i` sans interférer.

```c
void f(void)
{
    int compteur = 0;        // local à f, inconnu ailleurs
}
```

Une variable déclarée **en dehors** de toute fonction est **globale** : accessible partout, vivante
toute la durée du programme.

```c
#include <stdio.h>

int total = 0;               // globale

void ajouter(int x) { total += x; }   // += : total = total + x

int main(void)
{
    ajouter(3);
    ajouter(4);
    printf("%d\n", total);   // 7
    return 0;
}
```

Les globales sont pratiques mais **dangereuses** : n'importe quelle fonction peut les modifier, ce qui
rend les bugs difficiles à traquer. **Préfère les variables locales et le passage d'arguments.**
N'utilise une globale que pour une vraie donnée partagée et stable (une constante de configuration,
par exemple).

> **Attention** — Une variable locale est **détruite** dès que la fonction se termine. Renvoyer
> l'adresse d'une variable locale (on verra comment au chapitre 6) pointe vers une case qui n'existe
> plus : un bug grave. Pour l'instant, retiens : ce qui est local meurt à la sortie.

## Résumé

- Une fonction = **type de retour**, **nom**, **paramètres typés**, corps. `void` = ne renvoie rien.
- `return` renvoie une valeur **et** termine la fonction.
- Le C lit de haut en bas : déclare un **prototype** (`type nom(params);`) en haut, mets les
  **définitions** plus bas.
- Les arguments sont passés **par valeur** (copiés) : modifier un paramètre ne change pas l'original.
- Variables **locales** (vivent dans leur bloc) vs **globales** (partout, mais à éviter). Le local
  meurt à la fin de la fonction.

## Exercices

### Exercice 1 — Une fonction `est_pair`

Écris une fonction `int est_pair(int n)` qui renvoie 1 si `n` est pair, 0 sinon. Utilise-la dans
`main` pour tester quelques nombres. Place un prototype en haut.

<details>
<summary>Voir le corrigé</summary>

`n % 2 == 0` vaut déjà 1 ou 0 : on peut le renvoyer directement.

```c
#include <stdio.h>

int est_pair(int n);             // prototype

int main(void)
{
    printf("4 pair ? %d\n", est_pair(4));   // 1
    printf("7 pair ? %d\n", est_pair(7));   // 0
    return 0;
}

int est_pair(int n)
{
    return n % 2 == 0;           // l'expression vaut 1 (vrai) ou 0 (faux)
}
```

</details>

### Exercice 2 — Comprendre le passage par valeur

Sans l'exécuter, prédis la sortie de ce programme. Puis vérifie en le compilant.

```c
#include <stdio.h>

void incremente(int x) { x = x + 1; }

int main(void)
{
    int a = 10;
    incremente(a);
    incremente(a);
    printf("%d\n", a);
    return 0;
}
```

<details>
<summary>Voir le corrigé</summary>

La sortie est **`10`**. Chaque appel à `incremente` reçoit une **copie** de `a`. La copie est
incrémentée puis jetée à la fin de la fonction ; `a` dans `main` n'est jamais touché. Tu pourrais
appeler `incremente(a)` mille fois, `a` resterait à 10. Pour modifier `a`, il faudra passer son
**adresse** (chapitre 6).

</details>

## Quiz

**1.** Que fait `return` dans une fonction ?
- A. Affiche la valeur.
- B. Renvoie une valeur et termine la fonction.
- C. Met la fonction en pause.

**2.** Pourquoi déclarer un prototype en haut du fichier ?
- A. Pour que le compilateur connaisse la fonction avant qu'elle soit appelée.
- B. Pour accélérer le programme.
- C. C'est obligatoire pour `main`.

**3.** Une fonction reçoit `int x` en paramètre et fait `x = 0`. Quel effet sur la variable passée par
l'appelant ?
- A. Elle passe à 0.
- B. Aucun : `x` est une copie locale.
- C. Le programme plante.

**4.** Quelle affirmation est vraie ?
- A. Une variable globale n'est visible que dans `main`.
- B. Une variable locale survit après la fin de sa fonction.
- C. Les globales sont à éviter car n'importe quelle fonction peut les modifier.

<details>
<summary>Voir les réponses</summary>

1. **B** — `return` renvoie la valeur et sort immédiatement de la fonction.
2. **A** — le C lit de haut en bas ; le prototype annonce la signature avant l'appel.
3. **B** — passage par valeur : la fonction modifie une copie, pas l'original.
4. **C** — les globales sont accessibles partout, donc modifiables partout : à limiter.

</details>

## Projet fil rouge

Le `main` de `runoff` commence à mélanger affichage, saisie et validation. On **extrait des fonctions**
pour clarifier. Deux fonctions utiles dès maintenant : afficher la bannière, et lire un entier validé
dans une plage.

```c
#include <stdio.h>

void afficher_banniere(void);
int lire_entier(const char *invite, int min, int max);

int main(void)
{
    afficher_banniere();

    int nb_candidats = lire_entier("Nombre de candidats (1 a 9) ? ", 1, 9);
    int nb_votants   = lire_entier("Nombre de votants (au moins 1) ? ", 1, 1000000);

    int seuil = nb_votants / 2;
    printf("\nScrutin avec %d candidats et %d votants.\n", nb_candidats, nb_votants);
    printf("Pour gagner, un candidat doit obtenir plus de %d voix.\n", seuil);
    return 0;
}

void afficher_banniere(void)
{
    printf("=== Scrutin a vote alternatif (runoff) ===\n");
}

// Demande un entier tant qu'il n'est pas dans [min, max].
int lire_entier(const char *invite, int min, int max)
{
    int valeur;
    do
    {
        printf("%s", invite);
        scanf("%d", &valeur);
    }
    while (valeur < min || valeur > max);
    return valeur;
}
```

Le paramètre `const char *invite` est le **texte de l'invite** à afficher : c'est une chaîne de
caractères, le sujet du prochain chapitre (le `*` et le `const` te seront alors clairs). Le code de
`main` est maintenant beaucoup plus lisible : il **dit ce qu'il fait** sans noyer la logique dans les
détails. C'est tout l'intérêt des fonctions.

Au prochain chapitre, on stocke enfin les **noms des candidats** et les **bulletins** dans des
tableaux.

---

[← Chapitre précédent](03-conditions-et-boucles.md) · [Sommaire](README.md) · [Chapitre suivant →](05-tableaux-et-chaines.md)
