# Visualiser le déroulé du scrutin en ASCII

[← Chapitre précédent](10-projet-runoff.md) · [Sommaire](README.md) · [Chapitre suivant →](12-deboguer.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- dessiner un **histogramme en ASCII** dans le terminal, sans aucune bibliothèque graphique ;
- mettre une valeur à l'échelle pour la convertir en longueur de barre ;
- aligner proprement des colonnes de texte avec les options de `printf` ;
- (en bonus) ajouter de la **couleur** avec les codes d'échappement ANSI.

Pas besoin de fenêtre ni de souris : un terminal affiche du texte, et du texte bien arrangé suffit à
faire un graphique lisible. C'est l'approche la plus portable qui soit (elle marche dans ton conteneur
Docker comme partout), et un excellent exercice de manipulation de chaînes et de boucles.

## L'idée : une barre proportionnelle

On veut afficher, pour chaque candidat, une **barre** dont la longueur est proportionnelle à son
nombre de voix. Quelque chose comme :

```
Alice    | ########################   3
Bob      | ########                   1
Charlie  | ################           2
```

Une barre, c'est juste une suite de caractères `#` répétés. Le travail consiste à calculer **combien**
de `#` afficher, puis à les imprimer dans une boucle.

## Mettre à l'échelle

Si on affichait un `#` par voix, un scrutin à 10 000 votants déborderait l'écran. Il faut **mettre à
l'échelle** : fixer une largeur maximale (par exemple 30 caractères) et calculer la longueur de chaque
barre proportionnellement.

```c
#define LARGEUR_MAX 30

// Nombre de # pour 'votes' voix, sachant que 'reference' voix remplissent la barre.
int longueur_barre(int votes, int reference)
{
    if (reference == 0)
        return 0;                       // évite la division par zéro
    return votes * LARGEUR_MAX / reference;
}
```

La formule `votes * LARGEUR_MAX / reference` est une **règle de trois**. Attention à l'ordre : on
**multiplie d'abord** (`votes * LARGEUR_MAX`) puis on divise. Si on divisait d'abord
(`votes / reference`), la division entière donnerait 0 dès que `votes < reference`, et toutes les
barres seraient vides. C'est le piège de la division entière du
[chapitre 2](02-types-et-entrees-sorties.md), à éviter ici en multipliant avant de diviser.

> **Attention** — Avec des entiers, `votes / reference * LARGEUR_MAX` est presque toujours faux
> (arrondi à 0 trop tôt). Écris **`votes * LARGEUR_MAX / reference`** : multiplier avant de diviser
> préserve la précision.

Comme référence, on prend le **nombre de votants** : une barre pleine signifie « tous les votants ».
On voit ainsi d'un coup d'œil qui approche de la majorité (la moitié de la barre).

## Dessiner une barre

On imprime `n` fois le caractère `#`, puis on complète avec des espaces pour aligner ce qui suit
(le nombre de voix). Une boucle suffit.

```c
#include <stdio.h>

void dessiner_barre(int votes, int reference)
{
    int n = longueur_barre(votes, reference);

    putchar('|');
    for (int i = 0; i < LARGEUR_MAX; i++)
        putchar(i < n ? '#' : ' ');     // '#' jusqu'à n, puis des espaces
    printf("| %d\n", votes);
}
```

`putchar(c)` affiche un seul caractère, plus léger qu'un `printf` pour ça. On parcourt toute la largeur
fixe : un `#` tant que `i < n`, un espace ensuite. Résultat : toutes les barres ont la **même largeur**
totale, donc le `| nombre` final est aligné.

## Afficher un tour complet

On combine le nom du candidat (aligné) et sa barre. `%-10s` aligne le nom sur 10 caractères à gauche
(rappel du chapitre 10). On saute les candidats éliminés, ou on les marque distinctement.

```c
void afficher_tour(Scrutin *s, int tour)
{
    printf("\n=== Tour %d ===\n", tour);
    for (int c = 0; c < s->nb_candidats; c++)
    {
        if (s->candidats[c].elimine)
        {
            printf("%-10s (elimine)\n", s->candidats[c].nom);
            continue;
        }
        printf("%-10s ", s->candidats[c].nom);
        dessiner_barre(s->candidats[c].votes, s->nb_votants);
    }
}
```

Branche cette fonction à la place de l'affichage texte du chapitre 10, dans la boucle `depouiller`.
Voici ce que donne le scrutin d'exemple :

```
=== Tour 1 ===
Alice      | ############             2
Bob        | ######                   1
Charlie    | ############             2

=== Tour 2 ===
Alice      | ##################       3
Charlie    | ############             2
Charlie    (elimine)
```

> **À retenir** — Un « graphique » dans un terminal, c'est du texte mis en forme : une valeur mise à
> l'échelle, une boucle qui répète un caractère, et l'alignement de `printf` (`%-10s`, largeurs). Aucun
> outil externe nécessaire.

## Bonus : la couleur avec les codes ANSI

Les terminaux comprennent des **codes d'échappement ANSI** : des suites de caractères spéciales qui ne
s'affichent pas mais changent la couleur du texte qui suit. Un code commence par `\033[` (le caractère
*escape*), suivi d'un numéro et d'un `m`.

```c
#define ROUGE   "\033[31m"
#define VERT    "\033[32m"
#define RESET   "\033[0m"       // remet la couleur par défaut

printf(VERT "Gagnant : %s" RESET "\n", nom);
```

On colore par exemple en **vert** le candidat en tête, en **rouge** celui qui va être éliminé. Pense
toujours à refermer avec `RESET`, sinon **tout** le terminal reste coloré ensuite.

```c
// Barre verte si le candidat dépasse la majorité, normale sinon.
if (votes > reference / 2)
    printf(VERT);
dessiner_barre(votes, reference);
printf(RESET);
```

> **Attention** — Les codes ANSI ne sont pas universels : certains terminaux ou journaux de log les
> affichent en clair (`\033[32m` apparaît littéralement). Garde la couleur **optionnelle** et assure-toi
> que le programme reste lisible sans elle. C'est du décor, pas de l'information.

## Résumé

- Un histogramme ASCII = pour chaque valeur, une barre de `#` dont la longueur est **mise à l'échelle**
  sur une largeur fixe.
- Mise à l'échelle : `valeur * LARGEUR_MAX / reference`. **Multiplier avant de diviser** pour éviter
  l'arrondi entier à 0.
- `putchar` imprime un caractère ; les largeurs de `printf` (`%-10s`) alignent les colonnes.
- Les **codes ANSI** (`\033[..m`) colorent le texte ; toujours refermer avec `RESET`, et garder la
  couleur optionnelle.

## Exercices

### Exercice 1 — Histogramme de notes

Écris un programme qui lit 5 notes sur 20 et affiche un histogramme ASCII, une barre par note mise à
l'échelle sur 20 caractères (une barre pleine = 20/20).

<details>
<summary>Voir le corrigé</summary>

La référence de mise à l'échelle est 20 (la note maximale). On réutilise la logique de barre.

```c
#include <stdio.h>

#define LARGEUR 20

int main(void)
{
    int notes[5];
    for (int i = 0; i < 5; i++)
    {
        printf("Note %d : ", i + 1);
        scanf("%d", &notes[i]);
    }

    for (int i = 0; i < 5; i++)
    {
        int n = notes[i] * LARGEUR / 20;        // mise à l'échelle
        printf("%2d/20 |", notes[i]);
        for (int j = 0; j < n; j++)
            putchar('#');
        printf("\n");
    }
    return 0;
}
```

`%2d` aligne la note sur 2 chiffres pour que les barres démarrent toutes à la même colonne.

</details>

### Exercice 2 — Barre en pourcentage

Modifie `dessiner_barre` pour qu'elle affiche aussi le **pourcentage** des voix (par rapport au nombre
de votants), avec une décimale, après le nombre de voix. Attention au type.

<details>
<summary>Voir le corrigé</summary>

Le pourcentage est `votes / reference * 100`, mais en entiers ça donne 0 : il faut un `double`. On
caste (rappel du chapitre 2).

```c
void dessiner_barre(int votes, int reference)
{
    int n = longueur_barre(votes, reference);

    putchar('|');
    for (int i = 0; i < LARGEUR_MAX; i++)
        putchar(i < n ? '#' : ' ');

    double pct = (double) votes / reference * 100.0;   // (double) avant la division
    printf("| %d (%.1f%%)\n", votes, pct);             // %% affiche un vrai %
}
```

Deux pièges réunis ici : le **cast en `double`** pour une vraie division, et `%%` dans le format pour
afficher un caractère `%` littéral (un seul `%` serait interprété comme un spécificateur).

</details>

## Quiz

**1.** Pourquoi écrire `votes * LARGEUR / reference` plutôt que `votes / reference * LARGEUR` ?
- A. C'est plus rapide.
- B. La division entière de `votes / reference` arrondit à 0 trop tôt ; multiplier d'abord préserve la
  valeur.
- C. Les deux sont équivalents.

**2.** Que fait `putchar('#')` ?
- A. Affiche la chaîne `"#"` puis un retour à la ligne.
- B. Affiche un seul caractère `#`.
- C. Lit un caractère au clavier.

**3.** À quoi sert `\033[0m` (RESET) après un code couleur ANSI ?
- A. À effacer l'écran.
- B. À remettre la couleur du terminal par défaut.
- C. À passer à la ligne.

**4.** Comment afficher un caractère `%` littéral avec `printf` ?
- A. `%`
- B. `\%`
- C. `%%`

<details>
<summary>Voir les réponses</summary>

1. **B** — multiplier avant de diviser évite l'arrondi entier prématuré à 0.
2. **B** — `putchar` affiche un unique caractère, sans retour à la ligne.
3. **B** — `RESET` rétablit la couleur par défaut ; sans lui, la coloration « bave » sur la suite.
4. **C** — `%%` produit un `%` littéral ; un seul `%` serait pris pour un spécificateur.

</details>

## Projet fil rouge

On remplace l'affichage texte de `depouiller` (chapitre 10) par l'histogramme ASCII, et on signale le
candidat éliminé à chaque tour. Le déroulé devient visuel et se lit d'un coup d'œil.

```c
void depouiller(Scrutin *s)
{
    int tour = 1;
    while (true)
    {
        tabuler(s);
        afficher_tour(s, tour);             // l'histogramme ASCII de ce tour

        int gagnant = chercher_gagnant(s);
        if (gagnant != -1)
        {
            printf("\n" VERT "Gagnant : %s" RESET "\n", s->candidats[gagnant].nom);
            return;
        }

        int min = voix_minimum(s);
        if (egalite_totale(s, min))
        {
            printf("\nEgalite totale, pas de gagnant unique.\n");
            return;
        }

        // On annonce qui est éliminé avant de le faire.
        printf("\nElimine(s) ce tour :");
        for (int c = 0; c < s->nb_candidats; c++)
            if (!s->candidats[c].elimine && s->candidats[c].votes == min)
                printf(" %s", s->candidats[c].nom);
        printf("\n");

        eliminer(s, min);
        tour++;
    }
}
```

Le programme raconte maintenant **l'histoire** du scrutin : à chaque tour, les forces en présence en
barres, qui sort, et finalement le gagnant en vert. C'est exactement le « déroulé du vote » que tu
voulais visualiser, sans dépendance graphique lourde.

Au prochain chapitre, on **fiabilise** ce programme : `gdb` pour traquer un plantage pas à pas,
`valgrind` pour les fuites, et une revue des entrées invalides (fichier mal formé, indices hors
bornes) qui feraient planter une version naïve.

---

[← Chapitre précédent](10-projet-runoff.md) · [Sommaire](README.md) · [Chapitre suivant →](12-deboguer.md)
