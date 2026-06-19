# Introduction : le C, la compilation et ton environnement

[Sommaire](README.md) · [Chapitre suivant →](02-types-et-entrees-sorties.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **langage compilé** et en quoi le C diffère d'un langage interprété comme
  Python ou JavaScript ;
- décrire les étapes qui transforment ton fichier `.c` en programme exécutable ;
- mettre en place un environnement de travail identique pour tout le monde grâce à *Docker* ;
- écrire, **compiler** et exécuter ton tout premier programme C, et comprendre chacune de ses lignes.

## Un langage compilé, proche de la machine

Tu connais sans doute des langages **interprétés** : tu écris `python script.py` ou `node app.js`, et
un programme (l'interpréteur) lit ton code et l'exécute à la volée. Le C ne marche pas comme ça. Avant
de pouvoir l'exécuter, ton code source doit être **traduit une fois pour toutes** en *code machine*,
les instructions binaires que le processeur comprend directement. Ce travail de traduction s'appelle
la **compilation**, et le programme qui le fait s'appelle un **compilateur** (on utilisera *gcc*).

Le résultat est un fichier **exécutable** : un programme autonome qui tourne sans interpréteur, à
pleine vitesse. C'est l'une des raisons pour lesquelles le C est si rapide.

> **À retenir** — Python lit et exécute ton code à chaque lancement. En C, tu **compiles** ton code
> une fois en un exécutable, et c'est cet exécutable que tu lances ensuite. Tant que tu n'as pas
> recompilé, tes modifications du `.c` n'ont aucun effet.

L'autre particularité du C : il est **proche de la machine**. Pas de ramasse-miettes (en anglais
*garbage collector*, le mécanisme qui libère automatiquement la mémoire dans la plupart des langages
récents) : c'est toi qui gères la mémoire. Pas de vérification à l'exécution qui t'empêche de sortir
d'un tableau : si tu te trompes, le programme plante (ou pire, continue avec des données corrompues).
Cette liberté est exigeante, mais c'est elle qui te fait *comprendre* la machine.

## De la source à l'exécutable

Quand tu « compiles » un programme C, il se passe en réalité plusieurs étapes. Tu n'as pas à les
lancer une par une (gcc enchaîne tout), mais il faut connaître les noms : ils reviennent dans les
messages d'erreur.

```
  hello.c                  ton code source (texte)
     |
     |  1. PRÉPROCESSEUR    traite les lignes #... (inclusions, macros)
     v
  code "déplié"
     |
     |  2. COMPILATION      traduit le C en code machine
     v
  hello.o                  fichier objet (binaire, pas encore exécutable)
     |
     |  3. ÉDITION DE LIENS assemble les .o et les bibliothèques
     v
  hello (ou a.out)         exécutable autonome
```

1. **Le préprocesseur** traite toutes les lignes qui commencent par `#`. La plus courante, `#include`,
   recopie le contenu d'un autre fichier dans le tien (on verra ça tout de suite).
2. **La compilation** traduit ton code en *code objet* : du binaire, mais incomplet.
3. **L'édition de liens** (en anglais *linking*) relie ton code aux **bibliothèques** dont il a besoin
   (par exemple celle qui contient `printf`) et produit l'exécutable final.

Retiens surtout la distinction entre une **erreur de compilation** (ton code est mal écrit, l'étape 2
échoue) et une **erreur d'édition de liens** (ton code est correct mais il manque une fonction à
l'étape 3). Les deux ne se corrigent pas pareil.

## Mettre en place ton environnement

Pour que tout le monde travaille à l'identique, on utilise **Docker** : un outil qui fait tourner un
mini-système Linux isolé (un *conteneur*) sur ta machine, quel que soit ton OS. Tu y trouveras le
compilateur et les outils dont on aura besoin, déjà installés.

Installe **Docker Desktop** (Windows, macOS) ou **Docker Engine** (Linux) depuis
[docker.com](https://www.docker.com/). Vérifie qu'il fonctionne :

```bash
$ docker --version
Docker version 27.0.3, build ...
```

Crée un dossier de travail pour la formation, et dedans un fichier `Dockerfile` (sans extension) avec
ce contenu. Il décrit l'image : un Debian avec `gcc`, `make`, `gdb` et `valgrind` (les outils qu'on
utilisera jusqu'au bout).

```dockerfile
# Dockerfile — environnement de la formation C
FROM debian:bookworm

# gcc : le compilateur ; make : l'automatisation de build ;
# gdb : le débogueur ; valgrind : le détecteur de fuites mémoire.
RUN apt-get update \
    && apt-get install -y gcc make gdb valgrind \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /code
```

Construis l'image une fois (l'option `-t` lui donne un nom) :

```bash
$ docker build -t formation-c .
```

Puis, à chaque session de travail, lance un conteneur en montant ton dossier courant dans `/code`. Tu
te retrouves dans un shell Linux où tes fichiers sont accessibles et où `gcc` est prêt :

```bash
$ docker run --rm -it -v "$PWD":/code formation-c bash
root@...:/code#
```

Décortiquons la commande : `--rm` supprime le conteneur à la sortie, `-it` ouvre un terminal
interactif, et `-v "$PWD":/code` **monte** ton dossier courant (`$PWD`) dans `/code` à l'intérieur du
conteneur. Concrètement, les fichiers que tu édites sur ta machine sont visibles dans le conteneur, et
inversement : tu codes avec ton éditeur habituel, tu compiles dans le conteneur.

> **Astuce** — Garde deux fenêtres ouvertes : ton éditeur de code d'un côté, le terminal du conteneur
> de l'autre. Tu édites à gauche, tu compiles et exécutes à droite.

## Ton premier programme

Crée un fichier `hello.c` dans ton dossier de travail :

```c
#include <stdio.h>

int main(void)
{
    printf("Bonjour, le C !\n");
    return 0;
}
```

Ligne par ligne :

- `#include <stdio.h>` demande au préprocesseur d'inclure `stdio.h` (*standard input/output*), le
  fichier qui déclare les fonctions d'entrée/sortie comme `printf`. Sans cette ligne, le compilateur
  ne connaîtrait pas `printf`.
- `int main(void)` déclare la fonction `main`. **C'est le point d'entrée du programme** : l'exécution
  commence toujours par `main`. Le `int` annonce qu'elle renvoie un entier ; `(void)` signifie qu'elle
  ne prend aucun argument.
- `printf("Bonjour, le C !\n");` affiche du texte. Le `\n` est un **retour à la ligne**. Comme partout
  en C, l'instruction se termine par un **point-virgule**.
- `return 0;` termine `main` en renvoyant `0` au système. Par convention, **0 = succès**, toute autre
  valeur = erreur. C'est ce code que le shell récupère pour savoir si ton programme s'est bien passé.

> **À retenir** — Tout programme C a exactement une fonction `main`. Les accolades `{ }` délimitent un
> **bloc** d'instructions. Chaque instruction se finit par un `;`.

## Compiler et exécuter

Dans le terminal du conteneur, compile avec `gcc`, puis lance l'exécutable :

```bash
$ gcc hello.c -o hello
$ ./hello
Bonjour, le C !
```

`gcc hello.c -o hello` compile `hello.c` et écrit l'exécutable dans le fichier `hello` (l'option `-o`,
pour *output*). Sans `-o`, gcc nomme l'exécutable `a.out` par défaut — un vestige historique. On lance
ensuite le programme avec `./hello` (le `./` signifie « dans le dossier courant »).

À partir de maintenant, compile **toujours** avec les options d'avertissement activées :

```bash
$ gcc -Wall -Wextra hello.c -o hello
```

`-Wall` et `-Wextra` (*warnings: all* et *extra*) demandent à gcc de te signaler le code suspect : une
variable jamais utilisée, une comparaison douteuse, un type qui ne colle pas. Ces avertissements
t'évitent des heures de débogage. **Prends l'habitude de les laisser toujours actifs** et de les
traiter comme des erreurs à corriger.

> **Attention** — Si tu modifies `hello.c` mais oublies de recompiler, `./hello` exécute toujours
> l'ancienne version. Le réflexe en C : **éditer → compiler → exécuter**, dans cet ordre, à chaque
> fois.

### Lire un message d'erreur

Oublie volontairement le point-virgule après le `printf` et recompile. gcc refuse :

```
hello.c: In function 'main':
hello.c:5:34: error: expected ';' before 'return'
    5 |     printf("Bonjour, le C !\n")
      |                                  ^
      |                                  ;
```

Le compilateur t'indique le **fichier**, la **ligne** (`5`), la **colonne** (`34`), la nature du
problème (`expected ';'`) et même la correction suggérée. Apprends à lire ces messages calmement :
corrige **toujours la première erreur d'abord**, puis recompile. Une seule erreur en haut peut en
provoquer dix autres en cascade plus bas.

## Résumé

- Le C est un langage **compilé** : on traduit le source en **exécutable** une fois, puis on lance
  l'exécutable. Recompiler est obligatoire après chaque modification.
- La chaîne de construction passe par le **préprocesseur**, la **compilation** (produit des `.o`) et
  l'**édition de liens** (produit l'exécutable).
- On travaille dans un conteneur **Docker** uniforme avec `gcc` (commande
  `docker run --rm -it -v "$PWD":/code formation-c bash`).
- Tout programme a une fonction `int main(void)` ; chaque instruction finit par `;` ; `return 0`
  signale le succès.
- On compile avec `gcc -Wall -Wextra source.c -o programme` et on exécute avec `./programme`.
- On corrige **la première erreur du compilateur en premier**, puis on recompile.

## Exercices

### Exercice 1 — Faire dire bonjour à ta façon

Modifie `hello.c` pour qu'il affiche **trois lignes** : ton prénom, ton langage préféré, et l'année.
Compile avec `-Wall -Wextra` et exécute.

<details>
<summary>Voir le corrigé</summary>

L'idée est d'enchaîner plusieurs `printf`, chacun avec son `\n`. On peut aussi tout mettre dans un
seul `printf`.

```c
#include <stdio.h>

int main(void)
{
    printf("Antonin\n");
    printf("Python\n");
    printf("2026\n");
    return 0;
}
```

```bash
$ gcc -Wall -Wextra hello.c -o hello
$ ./hello
Antonin
Python
2026
```

</details>

### Exercice 2 — Provoquer et lire une erreur

Dans `hello.c`, remplace `printf` par `printff` (avec deux `f`). Compile et **lis le message**.
S'agit-il d'une erreur de compilation ou d'édition de liens ? Pourquoi ?

<details>
<summary>Voir le corrigé</summary>

gcc compile sans broncher (un appel à une fonction inconnue produit au pire un *warning*), mais
l'**édition de liens** échoue : il n'existe aucune fonction `printff` dans les bibliothèques.

```
/usr/bin/ld: ... undefined reference to `printff'
collect2: error: ld returned 1 exit status
```

`ld` est l'éditeur de liens, et `undefined reference` est sa façon de dire « cette fonction n'existe
nulle part ». C'est donc une **erreur d'édition de liens**, pas de compilation : la syntaxe de ton
code était correcte, mais une fonction manquait à l'assemblage final.

</details>

## Quiz

**1.** Que fait la commande `gcc hello.c -o hello` ?
- A. Elle exécute `hello.c`.
- B. Elle compile `hello.c` en un exécutable nommé `hello`.
- C. Elle ouvre `hello.c` dans un éditeur.

**2.** À quoi sert la ligne `#include <stdio.h>` ?
- A. À importer le code des fonctions d'entrée/sortie comme `printf`.
- B. À afficher du texte à l'écran.
- C. À déclarer la fonction `main`.

**3.** Que signifie `return 0;` à la fin de `main` ?
- A. Le programme renvoie le chiffre zéro à l'utilisateur.
- B. Le programme signale au système qu'il s'est terminé avec succès.
- C. Le programme s'arrête sans rien faire.

**4.** Tu modifies ton `.c` mais `./hello` affiche toujours l'ancien résultat. Pourquoi ?
- A. Docker met les fichiers en cache.
- B. Tu as oublié de recompiler : l'exécutable date d'avant ta modification.
- C. Le `.c` est corrompu.

<details>
<summary>Voir les réponses</summary>

1. **B** — `gcc` compile ; `-o hello` nomme l'exécutable produit.
2. **A** — `#include` fait inclure les déclarations de `stdio.h`, sans quoi `printf` est inconnu.
3. **B** — `return 0` depuis `main` signale le succès au système (convention 0 = OK).
4. **B** — un exécutable est figé à la compilation ; sans recompiler, tes changements ne s'appliquent
   pas.

</details>

## Projet fil rouge

On démarre **`runoff`**, le programme de dépouillement. Pour l'instant, juste un squelette qui compile
et affiche la bannière du scrutin. Crée `runoff.c` :

```c
#include <stdio.h>

int main(void)
{
    printf("=== Scrutin a vote alternatif (runoff) ===\n");
    printf("Depouillement tour par tour.\n");
    return 0;
}
```

```bash
$ gcc -Wall -Wextra runoff.c -o runoff
$ ./runoff
=== Scrutin a vote alternatif (runoff) ===
Depouillement tour par tour.
```

On évite volontairement les accents dans les chaînes affichées : selon la configuration du terminal,
un `é` peut s'afficher de travers. On garde les accents pour les commentaires et la documentation, pas
pour la sortie du programme. Au prochain chapitre, on rendra ce squelette interactif en lui faisant
lire des nombres.

---

[Sommaire](README.md) · [Chapitre suivant →](02-types-et-entrees-sorties.md)
