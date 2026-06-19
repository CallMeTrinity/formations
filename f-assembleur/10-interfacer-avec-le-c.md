# Interfacer avec le C

[← Chapitre précédent](09-appels-systeme.md) · [Sommaire](README.md) · [Chapitre suivant →](11-deboguer-avec-gdb.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- appeler une fonction de la bibliothèque C (la *libc*), par exemple `printf`, depuis l'assembleur ;
- respecter l'**alignement de la pile** sur 16 octets exigé par la libc ;
- écrire une fonction assembleur **appelée depuis un programme C** ;
- linker un mélange d'assembleur et de C avec `gcc`.

## Pourquoi parler au C ?

Tu sais déjà tout faire « à la main » avec les syscalls. Mais la **libc** (la bibliothèque standard du
C, présente sur tout système) offre des milliers de fonctions prêtes à l'emploi : `printf` pour
afficher du texte formaté, `malloc` pour allouer de la mémoire, `strlen`, `qsort`… Savoir les appeler
t'évite de tout réécrire, et savoir être appelé **par** du C te permet d'écrire en assembleur les
quelques fonctions critiques d'un programme par ailleurs en C. C'est l'usage réel de l'assembleur
aujourd'hui.

Bonne nouvelle : la libc respecte exactement l'ABI System V du chapitre 8. Tu sais déjà passer des
arguments (`rdi`, `rsi`…) et lire un retour (`rax`). Il reste deux détails à maîtriser.

## Détail 1 : déclarer la fonction externe

Une fonction de la libc n'est pas dans ton fichier. Tu préviens NASM qu'elle existe ailleurs avec
`extern` :

```nasm
extern printf           ; printf est définie dans la libc, pas ici
```

## Détail 2 : l'alignement de la pile sur 16 octets

L'ABI exige qu'au moment exact d'un `call` vers une fonction, `rsp` soit un **multiple de 16**.
Beaucoup de fonctions libc plantent (`Segmentation fault`) si cette règle n'est pas respectée, parce
qu'elles utilisent des instructions vectorielles qui exigent cet alignement.

Le mécanisme à comprendre : à l'entrée de ta fonction, `call` a empilé 8 octets (l'adresse de retour),
donc `rsp` est « décalé de 8 ». Un simple `push rbp` rajoute 8 et **réaligne** sur 16. C'est l'autre
raison d'être du prologue standard :

```nasm
mon_code:
    push rbp            ; réaligne la pile sur 16 octets
    mov rbp, rsp
    ; ... ici rsp est aligné : on peut appeler la libc en sécurité ...
    pop rbp
    ret
```

> **Attention** — Oublier l'alignement donne un `Segmentation fault` dans `printf` qui semble
> inexplicable : ton code paraît correct, mais la pile était mal alignée. Réflexe : `push rbp` /
> `mov rbp, rsp` en entrée, et empile par paires ensuite.

## Appeler `printf`

`printf` prend une **chaîne de format** (terminée par un octet `0`, comme en C) en premier argument,
puis les valeurs à insérer. Particularité des fonctions à nombre d'arguments variable (*variadiques*) :
il faut mettre dans `al` le **nombre d'arguments passés dans des registres vectoriels**. Pour des
entiers, c'est `0`, donc on fait `xor eax, eax`.

```nasm
extern printf

section .data
    fmt db "La reponse est %ld", 10, 0   ; %ld = entier long signé ; 0 = fin de chaîne C

section .text
    global main                  ; on s'appelle "main" : gcc fournira le démarrage
main:
    push rbp                     ; aligne la pile sur 16
    mov rbp, rsp

    lea rdi, [rel fmt]           ; 1er argument : la chaîne de format
    mov rsi, 42                  ; 2e argument : la valeur pour %ld
    xor eax, eax                 ; 0 argument vectoriel (obligatoire pour printf)
    call printf

    xor eax, eax                 ; code de retour 0
    pop rbp
    ret                          ; gcc s'occupe du exit
```

Remarque les changements par rapport aux chapitres précédents :

- on définit **`main`** au lieu de `_start` : quand on linke avec `gcc`, c'est le code de démarrage du
  C qui appelle `main`, met en place la libc, et fait l'`exit` à la fin (d'où le simple `ret`) ;
- `lea rdi, [rel fmt]` charge l'adresse de la chaîne de façon *relative* (compatible avec les
  exécutables modernes) ;
- `%ld` est le format d'un entier signé 64 bits ; `%d` (32 bits) afficherait n'importe quoi sur un
  `rsi` 64 bits.

### Assembler et linker avec `gcc`

```bash
nasm -f elf64 demo.s -o demo.o
gcc -no-pie demo.o -o demo        # gcc linke avec la libc
./demo
# Sortie attendue :
# La reponse est 42
```

L'option `-no-pie` simplifie l'adressage des données pour débuter (elle désactive les exécutables
« position-indépendants »). C'est `gcc`, et non `ld` seul, qui sait relier la libc.

> **Astuce** — Tableau utile des formats `printf` : `%ld` entier 64 bits signé, `%lu` non signé,
> `%lx` en hexadécimal, `%c` un caractère, `%s` une chaîne, `%%` un pourcentage littéral.

## Être appelé depuis du C

L'autre sens marche aussi : tu écris une fonction en assembleur et tu l'appelles depuis un `.c`.
Puisque les deux respectent l'ABI, il suffit d'exposer le symbole. Voici une fonction `carre` :

```nasm
; carre.s
section .text
    global carre
carre:                  ; long carre(long n) -> n*n
    mov rax, rdi        ; 1er argument
    imul rax, rax       ; n*n
    ret                 ; résultat dans rax
```

Et le programme C qui s'en sert :

```c
// main.c
#include <stdio.h>
long carre(long n);                 // déclaration : définie en assembleur

int main(void) {
    printf("%ld\n", carre(9));      // affiche 81
    return 0;
}
```

On compile les deux et on les linke ensemble :

```bash
nasm -f elf64 carre.s -o carre.o
gcc -no-pie main.c carre.o -o prog
./prog
# Sortie attendue :
# 81
```

Le C ne voit aucune différence : pour lui, `carre` est une fonction comme une autre. C'est toute la
puissance d'une ABI commune.

## Résumé

- La **libc** offre des fonctions toutes faites (`printf`, `malloc`…) ; on les déclare avec `extern`.
- Au moment d'un `call` vers la libc, `rsp` doit être **aligné sur 16 octets** : le prologue
  `push rbp` / `mov rbp, rsp` s'en charge.
- Pour une fonction **variadique** comme `printf`, mettre dans `al` le nombre d'arguments vectoriels
  (`xor eax, eax` pour des entiers).
- En liant avec `gcc`, on définit **`main`** (pas `_start`) et on termine par `ret` ; on linke avec
  `gcc -no-pie`.
- Une fonction assembleur respectant l'ABI est **appelable depuis du C** sans effort.

## Exercices

### Exercice 1 — Afficher deux valeurs

Écris un `main` qui affiche `x = 7, y = -3` avec un seul `printf` (deux `%ld` dans la même chaîne).

<details>
<summary>Voir le corrigé</summary>

```nasm
extern printf
section .data
    fmt db "x = %ld, y = %ld", 10, 0
section .text
    global main
main:
    push rbp
    mov rbp, rsp
    lea rdi, [rel fmt]
    mov rsi, 7          ; premier %ld
    mov rdx, -3         ; second %ld (3e argument = rdx)
    xor eax, eax
    call printf
    xor eax, eax
    pop rbp
    ret
```

Le 3e argument va dans `rdx` (chapitre 8) : les `%ld` consomment les arguments dans l'ordre `rsi`,
`rdx`, `rcx`…

</details>

### Exercice 2 — Fonction appelée depuis le C

Écris en assembleur une fonction `somme3(a, b, c)` qui renvoie `a + b + c`, et un `main.c` qui affiche
`somme3(10, 20, 12)`.

<details>
<summary>Voir le corrigé</summary>

```nasm
; somme3.s
section .text
    global somme3
somme3:                 ; long somme3(long a, long b, long c)
    mov rax, rdi
    add rax, rsi
    add rax, rdx        ; a + b + c
    ret
```

```c
// main.c
#include <stdio.h>
long somme3(long a, long b, long c);
int main(void) { printf("%ld\n", somme3(10, 20, 12)); return 0; }   // 42
```

```bash
nasm -f elf64 somme3.s -o somme3.o
gcc -no-pie main.c somme3.o -o prog && ./prog
```

Les trois arguments arrivent dans `rdi`, `rsi`, `rdx` ; le résultat repart dans `rax`.

</details>

## Quiz

**1.** Pourquoi `xor eax, eax` avant un `call printf` ?
- A. Pour mettre le résultat à zéro
- B. Pour indiquer 0 argument vectoriel (printf est variadique)
- C. Ce n'est pas nécessaire

**2.** Quel alignement de `rsp` la libc exige-t-elle au moment du `call` ?
- A. 8 octets
- B. 16 octets
- C. Aucun

**3.** Quand on linke avec `gcc`, quel symbole sert de point d'entrée du code qu'on écrit ?
- A. `_start`
- B. `main`
- C. `begin`

**4.** Quel format `printf` affiche un entier signé 64 bits ?
- A. `%d`
- B. `%ld`
- C. `%s`

<details>
<summary>Voir les réponses</summary>

1. **B** — Les fonctions variadiques attendent dans `al` le nombre d'arguments vectoriels ; 0 pour des
   entiers.
2. **B** — L'ABI impose un `rsp` multiple de 16 au point du `call`.
3. **B** — Le démarrage C appelle `main` ; on n'écrit pas `_start` soi-même.
4. **B** — `%ld` correspond à un `long` (64 bits) signé.

</details>

## Projet fil rouge

Rends l'affichage de `stats` lisible grâce à `printf`, avec des libellés. Bascule le programme en
version « liée à la libc » : remplace `_start` par `main`, supprime les `exit` manuels (un `ret`
suffit), et remplace les `call print_int` finaux par des `printf` étiquetés.

```nasm
extern printf
section .data
    fmt_nb     db "Nombre  : %ld", 10, 0
    fmt_somme  db "Somme   : %ld", 10, 0
    fmt_min    db "Min     : %ld", 10, 0
    fmt_max    db "Max     : %ld", 10, 0
    fmt_moy    db "Moyenne : %ld", 10, 0

section .text
    global main
main:
    push rbp
    mov rbp, rsp
    ; ... lecture (chapitre 9) et calculs (chapitres 4-6) ...

    lea rdi, [rel fmt_nb]
    mov rsi, [nb]
    xor eax, eax
    call printf
    lea rdi, [rel fmt_somme]
    mov rsi, [somme]
    xor eax, eax
    call printf
    ; ... idem pour min, max, moyenne ...

    xor eax, eax
    pop rbp
    ret
```

Compile désormais avec `gcc -no-pie stats.o -o stats`. Teste : `echo "12 -4 30 7 25" | ./stats` doit
afficher un tableau propre. `stats` est fonctionnellement complet ; il ne reste qu'à le déboguer
sereinement, au chapitre suivant.

---

[← Chapitre précédent](09-appels-systeme.md) · [Sommaire](README.md) · [Chapitre suivant →](11-deboguer-avec-gdb.md)
