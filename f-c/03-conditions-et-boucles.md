# Conditions, boucles et opérateurs

[← Chapitre précédent](02-types-et-entrees-sorties.md) · [Sommaire](README.md) · [Chapitre suivant →](04-fonctions.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- écrire des conditions avec `if`/`else if`/`else` et `switch` ;
- utiliser les **opérateurs** de comparaison et logiques sans te faire piéger par `=` vs `==` ;
- écrire les trois boucles du C (`while`, `do…while`, `for`) et choisir la bonne ;
- contrôler une boucle avec `break` et `continue`.

Tu connais déjà ces concepts dans un autre langage. Ce chapitre va vite : on se concentre sur la
**syntaxe du C** et ses **pièges spécifiques**.

## Conditions : `if` / `else`

La syntaxe est celle du C, reprise par presque tous les langages qui ont suivi :

```c
int age = 20;

if (age >= 18)
{
    printf("Majeur\n");
}
else if (age >= 16)
{
    printf("Bientot majeur\n");
}
else
{
    printf("Mineur\n");
}
```

La condition est entre **parenthèses**, le bloc entre **accolades**. Rappel du chapitre précédent : il
n'y a pas de vrai booléen. La condition est évaluée comme un entier, et **toute valeur non nulle est
vraie**. Donc `if (age)` est vrai dès que `age` n'est pas zéro.

> **Attention** — Si un bloc ne contient qu'**une** instruction, les accolades sont facultatives. Mais
> garde-les **toujours** : c'est la source de bugs célèbres (le « goto fail » d'Apple en 2014 venait
> exactement de là). Une ligne ajoutée plus tard sans accolades casse silencieusement la logique.

## Le piège n°1 du C : `=` au lieu de `==`

`=` est l'**affectation** (range une valeur), `==` est la **comparaison** (teste l'égalité). Les
confondre dans un `if` ne provoque pas d'erreur, juste un bug :

```c
int x = 5;

if (x = 0)          // BUG : on affecte 0 à x, puis on teste 0 -> faux. Toujours faux.
{
    printf("jamais affiche\n");
}
```

Ici `x = 0` **affecte** 0 à `x`, et la valeur de l'affectation (0) sert de condition : toujours fausse.
gcc avec `-Wall` t'avertit (`suggest parentheses around assignment used as truth value`). C'est encore
une raison de toujours compiler avec les *warnings*.

> **À retenir** — `==` compare, `=` affecte. Dans un `if`, tu veux presque toujours `==`.

## Les opérateurs

**Comparaison** (donnent 1 si vrai, 0 si faux) : `==` (égal), `!=` (différent), `<`, `>`, `<=`, `>=`.

**Logiques**, pour combiner des conditions :

| Opérateur | Sens | Vrai si… |
| --- | --- | --- |
| `&&` | ET | les deux côtés sont vrais |
| `\|\|` | OU | au moins un côté est vrai |
| `!` | NON | inverse (vrai devient faux) |

```c
if (age >= 18 && a_le_permis)
    printf("Peut conduire\n");

if (jour == 0 || jour == 6)         // 0 = dimanche, 6 = samedi
    printf("Week-end\n");
```

`&&` et `||` sont **paresseux** (en anglais *short-circuit*) : ils n'évaluent le côté droit que si
c'est nécessaire. Dans `a != 0 && 10 / a > 2`, si `a` vaut 0, le côté droit n'est jamais évalué : on
évite ainsi une division par zéro. C'est une technique de protection très courante.

> **Attention** — Ne confonds pas `&&` (ET logique) avec `&` (ET bit à bit), ni `||` avec `|`. Les
> versions simples travaillent sur les **bits** des nombres et donnent des résultats inattendus dans
> une condition. Dans un `if`, tu veux `&&` et `||`.

## `switch` : choisir parmi plusieurs cas

Quand tu compares **une même variable** à plusieurs valeurs constantes, `switch` est plus lisible
qu'une cascade de `if` :

```c
int choix;
scanf("%d", &choix);

switch (choix)
{
    case 1:
        printf("Ajouter un vote\n");
        break;
    case 2:
        printf("Afficher les resultats\n");
        break;
    case 3:
        printf("Quitter\n");
        break;
    default:
        printf("Choix inconnu\n");
        break;
}
```

Chaque `case` se termine par `break`. **Sans `break`, l'exécution « tombe » dans le cas suivant**
(*fall-through*) : c'est presque toujours un bug. `default` capte tout le reste (l'équivalent du
`else`). `switch` ne marche qu'avec des entiers et des caractères, pas des `double` ni des chaînes.

> **Attention** — Le `break` oublié dans un `switch` est un classique. Si tes cas s'exécutent « en
> chaîne », cherche un `break` manquant.

## Les boucles

### `while` — tant que la condition tient

```c
int i = 0;
while (i < 5)
{
    printf("%d ", i);
    i++;                // i++ équivaut à i = i + 1
}
// Sortie : 0 1 2 3 4
```

On teste **avant** chaque tour. Si la condition est fausse d'entrée, le corps n'est jamais exécuté.

### `do…while` — au moins une fois

```c
int n;
do
{
    printf("Entre un nombre positif : ");
    scanf("%d", &n);
}
while (n <= 0);         // on redemande tant que n n'est pas positif
```

On teste **après** le tour : le corps s'exécute donc **au moins une fois**. Idéal pour valider une
saisie (on demande, puis on vérifie). Note le `;` après le `while`.

### `for` — quand tu connais le nombre de tours

La boucle reine pour parcourir un tableau ou répéter un nombre connu de fois. Elle regroupe
**initialisation ; condition ; mise à jour** sur une seule ligne :

```c
for (int i = 0; i < 5; i++)
{
    printf("%d ", i);
}
// Sortie : 0 1 2 3 4
```

Lis-la ainsi : « `i` part de 0 ; tant que `i < 5` ; à la fin de chaque tour `i++` ». Les trois boucles
sont interchangeables, mais on choisit selon l'intention : `for` pour un compteur, `while` pour une
attente, `do…while` pour une saisie validée.

> **À retenir** — En C, on **indice à partir de 0**. Pour parcourir un tableau de `n` éléments, on va
> de `0` à `n - 1` : `for (int i = 0; i < n; i++)`. Le `< n` (et non `<= n`) évite le débordement.

### `break` et `continue`

`break` **sort** immédiatement de la boucle. `continue` **saute** au tour suivant.

```c
for (int i = 0; i < 10; i++)
{
    if (i == 5)
        break;          // on arrête tout à 5
    if (i % 2 == 0)
        continue;       // on saute les pairs
    printf("%d ", i);   // Sortie : 1 3
}
```

`%` est l'opérateur **modulo** (le reste de la division entière) : `i % 2 == 0` teste la parité.

## Résumé

- `if`/`else if`/`else` : condition entre `( )`, bloc entre `{ }`. Garde toujours les accolades.
- **Piège majeur** : `=` affecte, `==` compare. `-Wall` t'avertit si tu te trompes.
- Opérateurs logiques : `&&` (ET), `||` (OU), `!` (NON), **paresseux** (court-circuit). Ne pas les
  confondre avec `&` et `|` (bit à bit).
- `switch` compare un entier/caractère à des `case` ; **chaque cas a besoin d'un `break`**.
- Trois boucles : `while` (test avant), `do…while` (test après, au moins un tour), `for` (compteur).
- On indice à partir de **0** ; `break` sort, `continue` saute un tour ; `%` est le modulo.

## Exercices

### Exercice 1 — FizzBuzz

Affiche les nombres de 1 à 20. Pour les multiples de 3, affiche `Fizz` à la place ; pour les multiples
de 5, `Buzz` ; pour les multiples des deux, `FizzBuzz`.

<details>
<summary>Voir le corrigé</summary>

Le piège est l'ordre des tests : il faut tester `15` (3 **et** 5) **avant** 3 seul et 5 seul, sinon on
n'y arrive jamais.

```c
#include <stdio.h>

int main(void)
{
    for (int i = 1; i <= 20; i++)
    {
        if (i % 3 == 0 && i % 5 == 0)
            printf("FizzBuzz\n");
        else if (i % 3 == 0)
            printf("Fizz\n");
        else if (i % 5 == 0)
            printf("Buzz\n");
        else
            printf("%d\n", i);
    }
    return 0;
}
```

</details>

### Exercice 2 — Saisie validée

Demande à l'utilisateur un nombre entre 1 et 9. Tant qu'il tape une valeur hors de cette plage,
redemande. Affiche enfin le nombre accepté. Quelle boucle est la plus adaptée ?

<details>
<summary>Voir le corrigé</summary>

`do…while` : on demande **d'abord**, on valide **après**, et on recommence tant que c'est invalide.

```c
#include <stdio.h>

int main(void)
{
    int n;
    do
    {
        printf("Un nombre entre 1 et 9 : ");
        scanf("%d", &n);
    }
    while (n < 1 || n > 9);

    printf("Accepte : %d\n", n);
    return 0;
}
```

La condition `n < 1 || n > 9` est vraie quand la saisie est **hors plage** : on reboucle alors.

</details>

## Quiz

**1.** Que fait `if (x = 3)` ?
- A. Teste si `x` vaut 3.
- B. Affecte 3 à `x`, et la condition est toujours vraie (3 est non nul).
- C. Provoque une erreur de compilation.

**2.** Quelle boucle exécute son corps **au moins une fois** ?
- A. `while`
- B. `for`
- C. `do…while`

**3.** Dans un `switch`, qu'arrive-t-il si on oublie un `break` ?
- A. Rien, c'est facultatif.
- B. L'exécution continue dans le `case` suivant (*fall-through*).
- C. Le programme plante.

**4.** Pour parcourir un tableau de `n` éléments indicés à partir de 0, on écrit :
- A. `for (int i = 0; i <= n; i++)`
- B. `for (int i = 1; i < n; i++)`
- C. `for (int i = 0; i < n; i++)`

<details>
<summary>Voir les réponses</summary>

1. **B** — `=` affecte ; la valeur affectée (3) sert de condition, donc toujours vraie.
2. **C** — `do…while` teste après le tour, le corps s'exécute au moins une fois.
3. **B** — sans `break`, on tombe dans le cas suivant (*fall-through*), source de bugs.
4. **C** — de `0` à `n - 1`, donc `i < n`. `<= n` déborderait d'une case.

</details>

## Projet fil rouge

On exploite le nombre de votants pour calculer le **seuil de majorité** : un candidat est élu dès
qu'il dépasse la moitié des voix. On en profite pour **valider** les saisies avec une boucle.

Le seuil : un candidat gagne s'il obtient **strictement plus de la moitié** des votants. Pour 5
votants, il faut au moins 3 voix (`5 / 2 = 2`, donc « plus de 2 »). On reverra ce calcul au cœur de
l'algorithme.

```c
#include <stdio.h>

int main(void)
{
    printf("=== Scrutin a vote alternatif (runoff) ===\n");

    int nb_candidats;
    do
    {
        printf("Nombre de candidats (1 a 9) ? ");
        scanf("%d", &nb_candidats);
    }
    while (nb_candidats < 1 || nb_candidats > 9);

    int nb_votants;
    do
    {
        printf("Nombre de votants (au moins 1) ? ");
        scanf("%d", &nb_votants);
    }
    while (nb_votants < 1);

    int seuil = nb_votants / 2;     // "plus de la moitie" = strictement > seuil
    printf("\nScrutin avec %d candidats et %d votants.\n", nb_candidats, nb_votants);
    printf("Pour gagner, un candidat doit obtenir plus de %d voix.\n", seuil);
    return 0;
}
```

```
Nombre de candidats (1 a 9) ? 3
Nombre de votants (au moins 1) ? 5

Scrutin avec 3 candidats et 5 votants.
Pour gagner, un candidat doit obtenir plus de 2 voix.
```

Au prochain chapitre, on découpe ce code en **fonctions** pour qu'il reste lisible quand il grandira.

---

[← Chapitre précédent](02-types-et-entrees-sorties.md) · [Sommaire](README.md) · [Chapitre suivant →](04-fonctions.md)
