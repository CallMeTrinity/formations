# Déboguer et fiabiliser : gdb, valgrind, cas limites

[← Chapitre précédent](11-visualisation-ascii.md) · [Sommaire](README.md) · [Chapitre suivant →](13-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- lancer un programme pas à pas avec **gdb** et inspecter ses variables ;
- interpréter le rapport de **valgrind** pour les fuites et les accès mémoire invalides ;
- reconnaître les **erreurs classiques** du C (segfault, débordement, mémoire non initialisée) ;
- **fiabiliser** un programme en validant ses entrées.

En C, le compilateur t'attrape les fautes de syntaxe, mais pas les fautes de **logique** ni les abus de
mémoire : ceux-là plantent à l'exécution, parfois loin de leur cause. Savoir déboguer n'est pas
optionnel, c'est une compétence à part entière. Bonne nouvelle : `gdb` et `valgrind` sont déjà dans ton
conteneur.

## Compiler pour déboguer

Les outils de débogage ont besoin des **symboles** : les noms de tes variables et les numéros de ligne.
On les ajoute avec l'option `-g`. Pour le débogage, désactive aussi les optimisations (`-O0`, le défaut)
pour que l'exécution colle à ton code source.

```bash
$ gcc -g -Wall -Wextra runoff.c -o runoff
```

Sans `-g`, `gdb` ne te montrera que des adresses, illisibles. **Prends l'habitude de compiler avec
`-g`** pendant le développement.

## gdb : exécuter pas à pas

`gdb` (*GNU Debugger*) lance ton programme sous contrôle : tu peux l'arrêter, avancer ligne par ligne,
et regarder le contenu des variables. On le lance sur l'exécutable :

```bash
$ gdb ./runoff
```

Tu obtiens une invite `(gdb)`. Les commandes essentielles :

| Commande | Abrév. | Effet |
| --- | --- | --- |
| `break tabuler` | `b` | pose un **point d'arrêt** à l'entrée de `tabuler` |
| `run bulletins.txt` | `r` | lance le programme (avec ses arguments) |
| `next` | `n` | exécute la ligne suivante (sans entrer dans les fonctions) |
| `step` | `s` | comme `next`, mais **entre** dans les fonctions |
| `print s->nb_votants` | `p` | affiche la valeur d'une variable ou expression |
| `continue` | `c` | reprend jusqu'au prochain point d'arrêt |
| `backtrace` | `bt` | affiche la **pile des appels** (qui a appelé qui) |
| `quit` | `q` | quitte gdb |

Une session typique pour comprendre un comptage suspect :

```
$ gdb ./runoff
(gdb) break chercher_gagnant       # on s'arrête au début de chaque appel
(gdb) run bulletins.txt
(gdb) print s->candidats[0].votes  # combien de voix pour Alice à ce tour ?
$1 = 2
(gdb) print s->nb_votants
$2 = 5
(gdb) continue                     # tour suivant
```

> **À retenir** — `break` + `run` + `print` te suffisent pour 90 % des cas : tu arrêtes le programme à
> l'endroit suspect et tu **regardes** les valeurs au lieu de deviner. Déboguer, c'est observer, pas
> supposer.

### Trouver où ça plante

Quand un programme segfault, `gdb` te dit **exactement** à quelle ligne, et `backtrace` montre le
chemin d'appels qui y a mené :

```
(gdb) run bulletins.txt
Program received signal SIGSEGV, Segmentation fault.
0x... in tabuler (s=0x...) at runoff.c:42
42          s->candidats[choix].votes++;
(gdb) print choix
$1 = 7
(gdb) print s->nb_candidats
$2 = 3
```

Le diagnostic saute aux yeux : `choix` vaut 7 alors qu'il n'y a que 3 candidats (indices 0 à 2). On
accède à `candidats[7]`, hors du tableau : segfault. La cause est en amont, dans un fichier de
bulletins contenant un indice invalide. **gdb te donne le lieu du crash ; à toi de remonter à la
cause.**

## valgrind : les erreurs mémoire invisibles

Un programme peut sembler marcher tout en étant truffé de bugs mémoire qui ne se manifestent que
parfois. **valgrind** (vu au [chapitre 8](08-memoire-dynamique.md)) les détecte de façon
déterministe : fuites, lectures hors bornes, usage de mémoire non initialisée, double `free`.

```bash
$ valgrind ./runoff bulletins.txt
```

Trois diagnostics fréquents et leur sens :

- **`Invalid read/write of size N`** — tu lis ou écris hors d'un bloc alloué (débordement de tableau,
  pointeur décalé). valgrind donne la ligne.
- **`Conditional jump depends on uninitialised value`** — tu utilises une variable jamais initialisée
  (rappel du chapitre 2 : une variable non initialisée vaut n'importe quoi).
- **`definitely lost: X bytes`** — une fuite : de la mémoire allouée jamais libérée.

L'objectif final, sur le projet : `valgrind ./runoff bulletins.txt` doit afficher **zéro erreur** et
`All heap blocks were freed`. Un programme C « fini », c'est un programme **propre sous valgrind**.

> **Astuce** — Combine les deux outils selon le symptôme. **Ça plante** ? → `gdb` pour localiser le
> crash. **Ça marche mais c'est louche** (résultats instables, fuite) ? → `valgrind`. Ils sont
> complémentaires.

## Les erreurs classiques du C

La plupart des bugs C tombent dans une poignée de catégories. Les reconnaître t'en fait gagner la
moitié :

| Symptôme | Cause probable | Réflexe |
| --- | --- | --- |
| *Segmentation fault* | pointeur `NULL`, non initialisé, ou indice hors bornes | `gdb`, vérifier les `&` et les bornes |
| Résultat faux mais pas de crash | division entière, `=` au lieu de `==`, `break` oublié | relire la logique, tracer à la main |
| Plantage aléatoire | variable/mémoire non initialisée | `valgrind`, initialiser à la déclaration |
| Consommation qui grimpe | fuite mémoire | `valgrind`, vérifier chaque `malloc`/`free` |
| `undefined reference` | fonction non définie ou `.c` oublié à l'édition de liens | vérifier le Makefile / la commande gcc |

> **Attention** — Le plus dangereux n'est pas le programme qui plante, c'est celui qui **semble**
> marcher avec un bug mémoire latent. Il passera tes tests et plantera chez l'utilisateur. D'où la
> règle : **toujours** passer `valgrind` avant de considérer un programme C terminé.

## Fiabiliser : valider les entrées

Un programme robuste ne fait jamais confiance à son entrée. Le fichier de bulletins peut être mal
formé : indice de candidat hors plage, ligne manquante, nombre incohérent. Une version naïve plante ;
une version fiable **détecte** et **refuse** proprement.

Quelques garde-fous à ajouter au projet :

```c
// Vérifier que fscanf a bien lu ce qu'on attendait.
if (fscanf(f, "%d %d", nb_candidats, nb_votants) != 2)
{
    fprintf(stderr, "En-tete du fichier invalide.\n");
    fclose(f);
    return false;
}

// Vérifier qu'un indice de bulletin est dans les bornes.
int choix;
if (fscanf(f, "%d", &choix) != 1 || choix < 0 || choix >= *nb_candidats)
{
    fprintf(stderr, "Bulletin invalide : indice %d hors plage.\n", choix);
    return false;
}
```

`fscanf` **renvoie le nombre de valeurs lues** : le tester (`!= 2`, `!= 1`) détecte un fichier
tronqué ou mal formé. Et borner chaque indice (`>= 0 && < nb_candidats`) empêche le segfault qu'on a
diagnostiqué plus haut au gdb. **Valider en amont coûte trois lignes ; déboguer en aval coûte une
soirée.**

> **À retenir** — Robustesse = ne jamais supposer l'entrée correcte. Tester le retour des fonctions de
> lecture, borner les indices, gérer les `NULL`. C'est ce qui sépare un exercice d'un vrai programme.

## Résumé

- Compile avec **`-g`** pour déboguer (symboles et numéros de ligne).
- **gdb** : `break` / `run` / `next` / `step` / `print` / `backtrace`. Tu arrêtes le programme et tu
  **observes** les variables. Sur un crash, il donne la ligne exacte ; `bt` montre le chemin d'appels.
- **valgrind** détecte fuites et accès mémoire invalides. Objectif : zéro erreur, « all heap blocks
  freed ».
- Les bugs C classiques se rangent en catégories (segfault, résultat faux, plantage aléatoire, fuite,
  `undefined reference`) avec chacune son réflexe.
- **Fiabiliser** = valider les entrées : tester le retour de `fscanf`, borner les indices, gérer les
  `NULL`.

## Exercices

### Exercice 1 — Diagnostiquer un segfault au gdb

Ce programme plante. Compile-le avec `-g`, lance-le sous `gdb`, et identifie la ligne et la cause.

```c
#include <stdio.h>

int main(void)
{
    int tab[3] = {1, 2, 3};
    int *p = NULL;
    for (int i = 0; i <= 3; i++)     // deux bugs se cachent ici et plus bas
        printf("%d\n", tab[i]);
    printf("%d\n", *p);
    return 0;
}
```

<details>
<summary>Voir le corrigé</summary>

Deux problèmes. D'abord la boucle `i <= 3` accède à `tab[3]`, hors du tableau (indices valides : 0 à
2) : c'est un débordement (le `<=` au lieu de `<`, piège du chapitre 3). Ensuite `*p` déréférence un
pointeur `NULL` : segfault garanti.

Sous gdb :

```
(gdb) run
Program received signal SIGSEGV, Segmentation fault.
0x... in main () at bug.c:9
9           printf("%d\n", *p);
```

gdb pointe la ligne 9 (`*p`). On corrige : `i < 3` pour la boucle, et on ne déréférence `p` que s'il
pointe vraiment quelque part. valgrind, lui, aurait aussi signalé l'`Invalid read` du `tab[3]`, que
gdb peut laisser passer s'il ne provoque pas de crash immédiat.

</details>

### Exercice 2 — Rendre une lecture robuste

Reprends une lecture d'entiers avec `scanf("%d", &n)`. Que se passe-t-il si l'utilisateur tape `abc` ?
Comment le détecter et réagir proprement ?

<details>
<summary>Voir le corrigé</summary>

Si l'utilisateur tape `abc`, `scanf("%d", &n)` **échoue** : `n` n'est pas modifié (il garde sa valeur
précédente, peut-être indéterminée) et `scanf` renvoie 0 (zéro valeur lue). Le code naïf continue avec
une valeur fausse. La parade : tester le retour de `scanf`.

```c
#include <stdio.h>

int main(void)
{
    int n;
    printf("Un entier : ");
    if (scanf("%d", &n) != 1)
    {
        fprintf(stderr, "Entree invalide.\n");
        return 1;
    }
    printf("Tu as saisi %d\n", n);
    return 0;
}
```

`scanf` renvoie le nombre de valeurs correctement lues : `!= 1` signale une saisie non numérique. Ce
réflexe (tester le retour de `scanf`/`fscanf`) est exactement ce qui fiabilise la lecture du fichier de
bulletins.

</details>

## Quiz

**1.** À quoi sert l'option `-g` de gcc ?
- A. À optimiser le programme.
- B. À inclure les symboles de débogage (noms, numéros de ligne) pour gdb/valgrind.
- C. À activer les avertissements.

**2.** Dans gdb, que fait `print maVariable` ?
- A. Imprime la variable sur l'imprimante.
- B. Affiche sa valeur courante.
- C. La met à zéro.

**3.** valgrind affiche `definitely lost: 40 bytes`. Que signifie ce message ?
- A. Un accès hors d'un tableau.
- B. Une fuite mémoire : un bloc alloué jamais libéré.
- C. Une division par zéro.

**4.** Pourquoi tester le retour de `fscanf` (`!= 2`, etc.) ?
- A. Pour accélérer la lecture.
- B. Pour détecter un fichier mal formé ou tronqué au lieu de continuer avec des valeurs fausses.
- C. C'est purement décoratif.

<details>
<summary>Voir les réponses</summary>

1. **B** — `-g` ajoute les symboles nécessaires au débogage.
2. **B** — `print` affiche la valeur courante d'une variable ou expression.
3. **B** — `definitely lost` désigne une fuite : de la mémoire allouée et jamais rendue.
4. **B** — `fscanf` renvoie le nombre de valeurs lues ; le tester détecte une entrée invalide.

</details>

## Projet fil rouge

On fait la **passe de fiabilisation finale** sur `runoff` :

1. **Compiler proprement** : `-Wall -Wextra` sans aucun avertissement. Un *warning* est un bug en
   puissance ; on les traite tous.
2. **Valider le fichier** : tester le retour de chaque `fscanf`, refuser un en-tête incohérent
   (`nb_candidats <= 0`…), et borner chaque indice de bulletin dans `[0, nb_candidats[`.
3. **Passer valgrind** : `valgrind ./runoff bulletins.txt` doit afficher zéro erreur et « all heap
   blocks were freed ». On vérifie que `liberer_scrutin` libère bien chaque ligne de `preferences`,
   puis le tableau de lignes, puis les candidats.
4. **Tester les cas limites** : un seul candidat (gagne d'office), une égalité totale (2 candidats à
   égalité parfaite), un fichier vide ou inexistant (message d'erreur propre, pas de crash).

```bash
$ gcc -g -Wall -Wextra main.c scrutin.c affichage.c -o runoff   # zéro warning attendu
$ valgrind --leak-check=full ./runoff bulletins.txt
...
All heap blocks were freed -- no leaks are possible
ERROR SUMMARY: 0 errors from 0 contexts
```

Quand ces quatre points sont verts, `runoff` n'est plus un exercice : c'est un programme **fiable**, du
niveau de ce qu'on attend en production. C'est la dernière marche avant l'intermédiaire.

Au dernier chapitre, on prend du recul : ce que tu sais désormais, les bonnes pratiques à garder, et les
pistes pour aller plus loin (dont la vraie interface graphique avec ncurses, si tu veux pousser la
visualisation).

---

[← Chapitre précédent](11-visualisation-ascii.md) · [Sommaire](README.md) · [Chapitre suivant →](13-conclusion.md)
