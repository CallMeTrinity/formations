# La mémoire et les données

[← Chapitre précédent](04-arithmetique-et-logique.md) · [Sommaire](README.md) · [Chapitre suivant →](06-controle-du-flot.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- déclarer des données initialisées (`db`, `dw`, `dd`, `dq`) et réserver de la place (`resb`…`resq`) ;
- choisir la bonne **taille** (byte, word, dword, qword) et la préciser quand c'est nécessaire ;
- lire et écrire en mémoire avec l'**adressage indexé** `[base + index*échelle + déplacement]` ;
- parcourir un **tableau** et utiliser `lea` pour calculer une adresse.

## Déclarer des données initialisées

Dans `section .data`, tu poses des valeurs connues à l'avance. Le mot-clé indique la **taille** de
chaque élément :

| Directive | Taille | Nom |
| --- | --- | --- |
| `db` | 1 octet | *define byte* |
| `dw` | 2 octets | *define word* |
| `dd` | 4 octets | *define doubleword* |
| `dq` | 8 octets | *define quadword* |

```nasm
section .data
    age      db 25                  ; 1 octet
    annee    dw 2025                ; 2 octets
    pixels   dd 1920                ; 4 octets
    grand    dq 10000000000         ; 8 octets (ne tiendrait pas sur 4)
    message  db "OK", 10, 0         ; suite d'octets : 'O','K', saut de ligne, 0
    table    dq 3, 14, 15, 92       ; quatre quadwords consécutifs
```

L'héritage du vocabulaire x86 : le « word » fait **2 octets** ici (16 bits), pour des raisons
historiques, même si un registre moderne en fait 8. Ne te laisse pas piéger.

> **À retenir** — `db/dw/dd/dq` = octet / 2 / 4 / 8 octets. Le mot « word » en x86 signifie **2
> octets**, pas la taille d'un registre.

### Répéter une valeur avec `times`

Pour initialiser plusieurs éléments identiques, `times` répète une directive :

```nasm
tampon  times 16 db 0      ; 16 octets à zéro, d'affilée
```

## Réserver de la place sans l'initialiser

Dans `section .bss`, tu réserves de l'espace sans valeur de départ (le système le met à zéro au
lancement). Les directives `res*` réservent un **nombre d'éléments** :

| Directive | Taille d'un élément |
| --- | --- |
| `resb` | 1 octet |
| `resw` | 2 octets |
| `resd` | 4 octets |
| `resq` | 8 octets |

```nasm
section .bss
    compteur  resq 1           ; 1 quadword (8 octets)
    valeurs   resq 100         ; 100 quadwords = un tableau de 100 entiers 64 bits
    ligne     resb 256         ; un tampon de 256 octets (pour lire du texte plus tard)
```

`.bss` n'occupe pas de place dans le fichier exécutable (il décrit juste « réserve-moi tant
d'octets »), contrairement à `.data`. C'est l'endroit idéal pour les tampons et accumulateurs.

## Lire et écrire : le rôle des crochets

On l'a vu au chapitre 3 : **les crochets déréférencent**. Sans crochets, une étiquette vaut son
adresse ; avec crochets, son contenu.

```nasm
mov rax, valeurs        ; rax = ADRESSE du tableau
mov rax, [compteur]     ; rax = VALEUR stockée à l'adresse compteur
mov [compteur], rbx     ; range rbx à l'adresse compteur
```

### Préciser la taille

Quand la destination est une zone mémoire et que la source est une valeur immédiate, l'assembleur ne
peut pas deviner combien d'octets écrire. Tu dois le dire :

```nasm
mov byte  [ligne], 65       ; écrit 1 octet (le code ASCII de 'A')
mov qword [compteur], 0     ; écrit 8 octets à zéro
```

Les spécificateurs sont `byte`, `word`, `dword`, `qword` (mêmes tailles que `db/dw/dd/dq`). Quand la
source est un registre, la taille est déjà connue (par exemple `mov [compteur], rax` écrit 8 octets,
car `rax` fait 8 octets).

> **Attention** — `mov [compteur], 0` sans spécificateur provoque l'erreur NASM `operation size not
> specified`. Ajoute `qword` (ou la taille voulue).

## L'adressage indexé : parcourir un tableau

Un tableau n'est qu'une suite d'éléments contigus en mémoire. Pour atteindre l'élément numéro `i`, il
faut calculer son adresse : `adresse_de_base + i × taille_d_un_élément`. x86-64 sait faire ce calcul
**dans les crochets**, en une instruction. La forme générale :

```
[ base + index * échelle + déplacement ]
```

- **base** : un registre contenant une adresse (ou une étiquette) ;
- **index** : un registre (souvent le compteur de boucle) ;
- **échelle** : 1, 2, 4 ou 8 (la taille d'un élément) ;
- **déplacement** : une constante optionnelle.

Pour un tableau de quadwords (8 octets), l'échelle est 8 :

```nasm
section .bss
    valeurs resq 100

section .text
    global _start
_start:
    ; valeurs[3] = 42
    mov rcx, 3                          ; l'indice
    mov qword [valeurs + rcx*8], 42     ; adresse = valeurs + 3*8

    ; rax = valeurs[3]
    mov rax, [valeurs + rcx*8]          ; relit le même élément -> rax = 42

    mov rax, 60
    mov rdi, 0
    syscall
```

L'échelle `*8` est ce qui rend le code indépendant de l'indice : change `rcx`, tu accèdes à un autre
élément. C'est exactement `valeurs[rcx]` d'un langage haut niveau, mais explicite.

> **Astuce** — L'échelle correspond à `sizeof` de l'élément : 1 pour des octets, 4 pour des `dd`, 8
> pour des `dq`. Te tromper d'échelle, c'est lire entre deux éléments.

## `lea` : calculer une adresse sans accéder à la mémoire

Parfois tu veux l'**adresse** d'un élément, pas son contenu. `lea` (*load effective address*) calcule
l'expression entre crochets et en met le **résultat** (l'adresse) dans un registre, **sans lire la
mémoire** :

```nasm
lea rsi, [valeurs + rcx*8]      ; rsi = adresse de valeurs[rcx] (aucune lecture mémoire)
```

`lea` est précieux pour passer l'adresse d'un élément à une fonction (chapitre 8) ou à un syscall
(chapitre 9). Petit bonus : comme il sait faire `base + index*échelle + déplacement`, on le détourne
parfois pour de l'arithmétique rapide (`lea rax, [rbx + rbx*4]` calcule `rbx*5`).

> **À retenir** — `mov rax, [adr]` lit la **valeur**. `lea rax, [adr]` calcule l'**adresse**. L'un
> touche la mémoire, l'autre non.

## Résumé

- `.data` : données initialisées avec `db/dw/dd/dq` (1/2/4/8 octets). `times n` répète une valeur.
- `.bss` : place réservée non initialisée avec `resb/resw/resd/resq` (compte des **éléments**).
- Les **crochets** déréférencent ; sans crochets, on a l'adresse.
- Quand la taille est ambiguë (mémoire ← immédiat), la préciser : `byte/word/dword/qword`.
- L'adressage `[base + index*échelle + déplacement]` calcule l'adresse d'un élément de tableau ;
  l'**échelle** = taille de l'élément.
- `lea` calcule une **adresse** sans lire la mémoire.

## Exercices

### Exercice 1 — Initialiser et relire

Déclare en `.data` un tableau `notes` de cinq quadwords valant 12, 15, 8, 17, 10. Écris le code qui
charge la troisième note (indice 2) dans `rax`.

<details>
<summary>Voir le corrigé</summary>

```nasm
section .data
    notes dq 12, 15, 8, 17, 10

section .text
    global _start
_start:
    mov rcx, 2                  ; indice de la 3e note
    mov rax, [notes + rcx*8]    ; rax = notes[2] = 8

    mov rax, 60
    mov rdi, 0
    syscall
```

L'échelle `*8` est indispensable : chaque `dq` occupe 8 octets.

</details>

### Exercice 2 — Remplir un tableau

Avec un tableau `valeurs resq 4` en `.bss`, écris à la main (sans boucle, on la verra au chapitre 6)
les valeurs 100, 200, 300, 400 aux indices 0 à 3.

<details>
<summary>Voir le corrigé</summary>

```nasm
section .bss
    valeurs resq 4

section .text
    global _start
_start:
    mov qword [valeurs + 0*8], 100
    mov qword [valeurs + 1*8], 200
    mov qword [valeurs + 2*8], 300
    mov qword [valeurs + 3*8], 400

    mov rax, 60
    mov rdi, 0
    syscall
```

`qword` est obligatoire ici : la source est immédiate, la destination mémoire. On peut écrire
directement `i*8` car NASM calcule la constante à l'assemblage.

</details>

### Exercice 3 — `mov` ou `lea` ?

On a `lea rsi, [notes + rcx*8]` puis `mov rax, [notes + rcx*8]`. Quelle est la différence de contenu
entre `rsi` et `rax` après ces deux lignes ?

<details>
<summary>Voir le corrigé</summary>

`rsi` contient l'**adresse** de `notes[rcx]` (où se trouve la valeur). `rax` contient la **valeur**
elle-même. `lea` calcule sans toucher la mémoire ; `mov` avec crochets lit la mémoire à cette adresse.

</details>

## Quiz

**1.** Combien d'octets réserve `valeurs resq 10` ?
- A. 10
- B. 40
- C. 80

**2.** Quelle est la bonne échelle pour indexer un tableau de `dq` ?
- A. 1
- B. 4
- C. 8

**3.** Que met `lea rax, [tab + rcx*8]` dans `rax` ?
- A. La valeur `tab[rcx]`
- B. L'adresse de `tab[rcx]`
- C. La valeur de `rcx`

**4.** Pourquoi `mov [compteur], 0` est-il refusé par NASM ?
- A. `0` n'est pas une valeur valide
- B. La taille de l'écriture est ambiguë
- C. On ne peut pas écrire en mémoire

<details>
<summary>Voir les réponses</summary>

1. **C** — 10 éléments × 8 octets = 80 octets.
2. **C** — Un `dq` fait 8 octets, donc échelle 8.
3. **B** — `lea` charge l'adresse calculée, sans lire la mémoire.
4. **B** — Il faut préciser la taille : `mov qword [compteur], 0`.

</details>

## Projet fil rouge

`stats` doit stocker les valeurs saisies dans son tableau `valeurs` (réservé au chapitre 3) et tenir
l'indice à jour. Réécris l'accumulation du chapitre 4 pour qu'elle **range chaque valeur dans le
tableau** en plus de mettre à jour la somme :

```nasm
    ; ajoute une valeur (dans rax) au "modèle" stats
    ; rcx = indice courant = nb
    mov rcx, [nb]
    mov [valeurs + rcx*8], rax      ; valeurs[nb] = rax
    inc qword [nb]                  ; une valeur de plus

    mov rdx, [somme]
    add rdx, rax
    mov [somme], rdx                ; somme += rax
```

Tu disposes maintenant d'un tableau rempli et d'un compteur cohérent. Au chapitre suivant, une
**boucle** parcourra ce tableau pour trouver le minimum et le maximum.

---

[← Chapitre précédent](04-arithmetique-et-logique.md) · [Sommaire](README.md) · [Chapitre suivant →](06-controle-du-flot.md)
