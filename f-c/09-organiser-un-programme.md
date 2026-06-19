# Organiser un programme : multi-fichiers, headers, Makefile, arguments et fichiers

[← Chapitre précédent](08-memoire-dynamique.md) · [Sommaire](README.md) · [Chapitre suivant →](10-projet-runoff.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- découper un programme en plusieurs fichiers `.c` et `.h` ;
- écrire un *header* propre avec sa **garde d'inclusion** ;
- automatiser la compilation avec un **Makefile** ;
- lire les **arguments de la ligne de commande** (`argc`, `argv`) et un **fichier** texte.

## Pourquoi découper ?

Un programme d'un seul fichier de 500 lignes devient vite ingérable : on s'y perd, et **la moindre
modification force à tout recompiler**. La solution : répartir le code en **modules**, chacun
responsable d'une chose. Le projet `runoff` aura par exemple un module pour les candidats, un pour
l'algorithme, et un `main` qui orchestre.

Chaque module se compose de **deux fichiers** :

- un **`.h`** (*header*, en-tête) : les **déclarations** (prototypes, structures, constantes). C'est
  l'**interface** publique du module : ce que les autres fichiers ont le droit d'utiliser.
- un **`.c`** : les **définitions** (le vrai code des fonctions). C'est l'**implémentation**.

> **À retenir** — Le `.h` dit **ce que** le module offre (les prototypes). Le `.c` dit **comment** il
> le fait (le code). Les autres fichiers incluent le `.h` et ignorent le `.c`.

## Un module en pratique

Créons un module `candidat`. D'abord l'en-tête `candidat.h` :

```c
// candidat.h — interface du module candidat
#ifndef CANDIDAT_H
#define CANDIDAT_H

#include <stdbool.h>

#define LONGUEUR_NOM 30

typedef struct
{
    char nom[LONGUEUR_NOM];
    int  votes;
    bool elimine;
} Candidate;

// Affiche un candidat et son nombre de voix.
void afficher_candidat(const Candidate *c, int indice);

#endif
```

Les trois lignes `#ifndef` / `#define` / `#endif` forment la **garde d'inclusion** (en anglais *include
guard*). Elles évitent qu'un même header soit inclus deux fois (ce qui provoquerait des erreurs de
redéfinition). Lis-les ainsi : « si `CANDIDAT_H` n'est pas encore défini, définis-le et lis le contenu ;
sinon, ignore ». **Mets cette garde dans chaque `.h`**, avec un nom unique par fichier.

Puis l'implémentation `candidat.c` :

```c
// candidat.c — implémentation du module candidat
#include <stdio.h>
#include "candidat.h"           // guillemets : header local (pas <...>)

void afficher_candidat(const Candidate *c, int indice)
{
    printf("  [%d] %s : %d voix%s\n",
           indice, c->nom, c->votes,
           c->elimine ? " (elimine)" : "");
}
```

Note `#include "candidat.h"` avec des **guillemets** (pour tes propres fichiers), alors que les
bibliothèques standard utilisent des **chevrons** `#include <stdio.h>`. Les guillemets disent « cherche
d'abord à côté de ce fichier ».

Enfin `main.c` utilise le module en incluant son header :

```c
// main.c
#include <string.h>
#include "candidat.h"

int main(void)
{
    Candidate alice = {"Alice", 7, false};
    afficher_candidat(&alice, 0);
    return 0;
}
```

## Compiler plusieurs fichiers

On compile chaque `.c` séparément en `.o`, puis on **relie** (édition de liens) les `.o` en un
exécutable. En une commande, gcc fait tout :

```bash
$ gcc -Wall -Wextra main.c candidat.c -o runoff
```

On liste tous les `.c` (jamais les `.h` : ils sont inclus, pas compilés). gcc compile chacun et les
relie. Ça marche, mais dès que les fichiers se multiplient, taper cette ligne devient fastidieux et
**recompile tout** à chaque fois, même un fichier inchangé. D'où le Makefile.

## Le Makefile

`make` est un outil qui lit un fichier nommé `Makefile` décrivant **comment construire** ton
programme, et ne recompile que ce qui a changé. Un Makefile est une liste de **règles** :

```makefile
# Makefile — construction de runoff
CC = gcc
CFLAGS = -Wall -Wextra -g

runoff: main.o candidat.o
	$(CC) $(CFLAGS) main.o candidat.o -o runoff

main.o: main.c candidat.h
	$(CC) $(CFLAGS) -c main.c

candidat.o: candidat.c candidat.h
	$(CC) $(CFLAGS) -c candidat.c

clean:
	rm -f runoff *.o
```

Décryptage :

- `CC` et `CFLAGS` sont des **variables** (le compilateur et ses options), réutilisées via `$(...)`.
- Une **règle** s'écrit `cible: dépendances`, suivie des **commandes** indentées par une **tabulation**
  (pas des espaces — c'est l'erreur classique).
- `runoff` dépend des `.o` ; chaque `.o` dépend de son `.c` et des `.h` qu'il utilise. `make` compare
  les dates : si `candidat.c` n'a pas changé, il ne recompile pas `candidat.o`.
- `-c` demande à gcc de **compiler sans lier** (produire un `.o`).
- `clean` est une cible utilitaire qui supprime les fichiers générés.

On construit avec une seule commande, et on nettoie avec `make clean` :

```bash
$ make
gcc -Wall -Wextra -g -c main.c
gcc -Wall -Wextra -g -c candidat.c
gcc -Wall -Wextra -g main.o candidat.o -o runoff
$ ./runoff
  [0] Alice : 7 voix
```

> **Attention** — Les commandes d'une règle Make doivent être indentées par une **vraie tabulation**,
> pas des espaces. Sinon : `Makefile:5: *** missing separator. Stop.`. Si ton éditeur insère des
> espaces, configure-le pour mettre une tabulation dans les Makefiles.

> **À retenir** — `make` recompile **uniquement ce qui a changé** en comparant les dates des fichiers.
> Sur un gros projet, c'est la différence entre 1 seconde et plusieurs minutes de compilation.

## Les arguments de la ligne de commande

Jusqu'ici `main` était `int main(void)`. Sa forme complète reçoit les **arguments** passés au
programme sur la ligne de commande :

```c
int main(int argc, char *argv[])
```

- `argc` (*argument count*) : le **nombre** d'arguments, y compris le nom du programme.
- `argv` (*argument values*) : un **tableau de chaînes**. `argv[0]` est le nom du programme,
  `argv[1]` le premier vrai argument, etc.

```c
#include <stdio.h>

int main(int argc, char *argv[])
{
    printf("Nom du programme : %s\n", argv[0]);
    printf("Recu %d argument(s) :\n", argc - 1);
    for (int i = 1; i < argc; i++)
        printf("  argv[%d] = %s\n", i, argv[i]);
    return 0;
}
```

```bash
$ ./prog bulletins.txt 3
Nom du programme : ./prog
Recu 2 argument(s) :
  argv[1] = bulletins.txt
  argv[2] = 3
```

C'est ainsi qu'on passera le **fichier de bulletins** au programme : `./runoff bulletins.txt`.

> **Attention** — `argv` contient toujours des **chaînes**, même pour un nombre. `argv[2]` vaut la
> chaîne `"3"`, pas l'entier 3. Pour obtenir l'entier, on convertit avec `atoi(argv[2])` (de
> `<stdlib.h>`). Pense aussi à vérifier `argc` avant d'accéder à `argv[1]` : sinon, segfault si
> l'argument manque.

## Lire un fichier

Lire un fichier suit toujours le même cycle : **ouvrir → lire → fermer**. On ouvre avec `fopen`, qui
renvoie un `FILE *` (un pointeur vers le fichier), ou `NULL` si l'ouverture échoue.

```c
#include <stdio.h>

int main(int argc, char *argv[])
{
    if (argc < 2)
    {
        fprintf(stderr, "Usage : %s <fichier>\n", argv[0]);
        return 1;
    }

    FILE *f = fopen(argv[1], "r");      // "r" = lecture (read)
    if (f == NULL)
    {
        fprintf(stderr, "Impossible d'ouvrir %s\n", argv[1]);
        return 1;
    }

    char ligne[100];
    while (fgets(ligne, sizeof(ligne), f) != NULL)   // lit ligne par ligne
    {
        printf("> %s", ligne);
    }

    fclose(f);                          // toujours fermer ce qu'on a ouvert
    return 0;
}
```

- `fopen(nom, "r")` ouvre en lecture. D'autres modes existent : `"w"` (écriture, écrase), `"a"` (ajout).
- `fgets(ligne, taille, f)` lit une ligne du fichier (au lieu de `stdin`). Il renvoie `NULL` à la fin
  du fichier : c'est la condition d'arrêt de la boucle.
- `fclose(f)` ferme le fichier. **Toujours fermer ce qu'on a ouvert**, comme on libère ce qu'on alloue.

Pour lire des nombres formatés, on a `fscanf`, le `scanf` des fichiers : `fscanf(f, "%d", &n)`. On
s'en servira pour parser les bulletins.

> **À retenir** — Lire un fichier = `fopen` (tester le `NULL`) → lire avec `fgets`/`fscanf` → `fclose`.
> Le même pattern « acquérir / utiliser / relâcher » que `malloc`/`free`.

## Résumé

- Un module = un **`.h`** (interface : prototypes, structs, constantes, avec **garde d'inclusion**
  `#ifndef`/`#define`/`#endif`) et un **`.c`** (implémentation). On inclut le `.h` avec des
  `"guillemets"`.
- On compile tous les `.c` ensemble (`gcc a.c b.c -o prog`) ; les `.h` ne se compilent pas.
- Un **Makefile** automatise la construction et ne recompile que ce qui a changé. Commandes indentées
  par **tabulation**. `make clean` nettoie.
- `int main(int argc, char *argv[])` reçoit les arguments : `argc` (nombre), `argv` (tableau de
  chaînes, `argv[0]` = nom du programme). Convertir un argument numérique avec `atoi`.
- Lire un fichier : `fopen` (tester `NULL`) → `fgets`/`fscanf` → `fclose`.

## Exercices

### Exercice 1 — Un module `calcul`

Crée un module `calcul` (`calcul.h` + `calcul.c`) exposant `int somme(int a, int b)` et
`int produit(int a, int b)`. Utilise-le depuis un `main.c`, et compile-le avec un Makefile.

<details>
<summary>Voir le corrigé</summary>

`calcul.h` (avec garde d'inclusion) :

```c
#ifndef CALCUL_H
#define CALCUL_H

int somme(int a, int b);
int produit(int a, int b);

#endif
```

`calcul.c` :

```c
#include "calcul.h"

int somme(int a, int b)   { return a + b; }
int produit(int a, int b) { return a * b; }
```

`main.c` :

```c
#include <stdio.h>
#include "calcul.h"

int main(void)
{
    printf("%d %d\n", somme(3, 4), produit(3, 4));   // 7 12
    return 0;
}
```

`Makefile` (attention aux tabulations) :

```makefile
CC = gcc
CFLAGS = -Wall -Wextra

prog: main.o calcul.o
	$(CC) $(CFLAGS) main.o calcul.o -o prog

main.o: main.c calcul.h
	$(CC) $(CFLAGS) -c main.c

calcul.o: calcul.c calcul.h
	$(CC) $(CFLAGS) -c calcul.c

clean:
	rm -f prog *.o
```

</details>

### Exercice 2 — Compter les lignes d'un fichier

Écris un programme qui prend un nom de fichier en argument et affiche son nombre de lignes. Gère le cas
où l'argument manque ou le fichier n'existe pas.

<details>
<summary>Voir le corrigé</summary>

On vérifie `argc`, on teste le retour de `fopen`, puis on compte les appels réussis à `fgets`.

```c
#include <stdio.h>

int main(int argc, char *argv[])
{
    if (argc < 2)
    {
        fprintf(stderr, "Usage : %s <fichier>\n", argv[0]);
        return 1;
    }

    FILE *f = fopen(argv[1], "r");
    if (f == NULL)
    {
        fprintf(stderr, "Fichier introuvable : %s\n", argv[1]);
        return 1;
    }

    int lignes = 0;
    char tampon[256];
    while (fgets(tampon, sizeof(tampon), f) != NULL)
        lignes++;

    fclose(f);
    printf("%d lignes\n", lignes);
    return 0;
}
```

Les deux gardes (`argc < 2` et `f == NULL`) sont ce qui distingue un programme jouet d'un programme
robuste : on ne suppose jamais que l'entrée est correcte.

</details>

## Quiz

**1.** À quoi sert la garde d'inclusion `#ifndef`/`#define`/`#endif` dans un `.h` ?
- A. À accélérer la compilation.
- B. À éviter qu'un header soit inclus (et ses déclarations dupliquées) plusieurs fois.
- C. À cacher le code aux autres fichiers.

**2.** Que contient `argv[0]` ?
- A. Le premier argument utilisateur.
- B. Le nom du programme.
- C. Le nombre d'arguments.

**3.** Pourquoi les commandes d'une règle Make doivent-elles être indentées par une tabulation ?
- A. Pour la lisibilité uniquement.
- B. C'est la syntaxe imposée par `make` ; des espaces provoquent `missing separator`.
- C. Ce n'est pas obligatoire.

**4.** Que renvoie `fopen` si le fichier n'existe pas en lecture ?
- A. Une chaîne vide.
- B. `0` tout court.
- C. `NULL`.

<details>
<summary>Voir les réponses</summary>

1. **B** — la garde empêche la double inclusion et les redéfinitions.
2. **B** — `argv[0]` est le nom du programme ; les vrais arguments commencent à `argv[1]`.
3. **B** — `make` exige une tabulation ; des espaces donnent `missing separator. Stop.`.
4. **C** — `fopen` renvoie `NULL` en cas d'échec ; il faut toujours le tester.

</details>

## Projet fil rouge

On structure `runoff` proprement et on lui fait lire les bulletins depuis un **fichier**, format bien
plus pratique que la saisie au clavier pour un vrai scrutin. On définit le **format de fichier** :

```
3 5
Alice
Bob
Charlie
2 0 1
0 2 1
1 0 2
2 1 0
0 1 2
```

La première ligne donne `nb_candidats nb_votants`. Suivent les **noms** des candidats (un par ligne).
Puis un **bulletin par ligne** : chaque bulletin liste les indices des candidats, du plus préféré au
moins préféré. Ici le votant 1 préfère le candidat 2, puis 0, puis 1.

On lit ce fichier avec `fopen`/`fscanf`/`fgets`, en réutilisant l'allocation dynamique du chapitre 8.
Voici la fonction de lecture (le `main` complet et le découpage en modules arriveront au chapitre
suivant, quand on assemblera l'algorithme) :

```c
// Lit l'en-tête, les noms et les bulletins depuis le fichier.
// Alloue candidats et preferences ; renvoie true si tout s'est bien passé.
bool charger_scrutin(const char *chemin, Candidate **candidats, int ***preferences,
                     int *nb_candidats, int *nb_votants)
{
    FILE *f = fopen(chemin, "r");
    if (f == NULL)
        return false;

    fscanf(f, "%d %d", nb_candidats, nb_votants);   // 1re ligne : tailles

    *candidats = malloc(*nb_candidats * sizeof(Candidate));
    for (int c = 0; c < *nb_candidats; c++)
    {
        fscanf(f, "%29s", (*candidats)[c].nom);     // %29s : au plus 29 caractères, évite le débordement
        (*candidats)[c].votes = 0;
        (*candidats)[c].elimine = false;
    }

    *preferences = malloc(*nb_votants * sizeof(int *));
    for (int v = 0; v < *nb_votants; v++)
    {
        (*preferences)[v] = malloc(*nb_candidats * sizeof(int));
        for (int rang = 0; rang < *nb_candidats; rang++)
            fscanf(f, "%d", &(*preferences)[v][rang]);
    }

    fclose(f);
    return true;
}
```

Les paramètres `Candidate **candidats` et `int ***preferences` sont des **pointeurs vers des
pointeurs** : la fonction doit pouvoir **affecter** les tableaux qu'elle alloue aux variables de
l'appelant (rappel du chapitre 6 : pour modifier une variable, on passe son adresse). C'est dense,
mais c'est l'application directe de tout ce que tu as vu. On lance désormais le programme ainsi :

```bash
$ ./runoff bulletins.txt
```

Au prochain chapitre, **le grand assemblage** : on code l'algorithme runoff complet (comptage des voix,
recherche du dernier, gestion des égalités, boucle de tours) par-dessus ces données chargées.

---

[← Chapitre précédent](08-memoire-dynamique.md) · [Sommaire](README.md) · [Chapitre suivant →](10-projet-runoff.md)
