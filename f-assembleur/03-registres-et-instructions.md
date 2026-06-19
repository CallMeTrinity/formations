# Registres et premières instructions

[← Chapitre précédent](02-representation-des-donnees.md) · [Sommaire](README.md) · [Chapitre suivant →](04-arithmetique-et-logique.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- nommer les **registres généraux** de x86-64 et leurs sous-parties (`rax`, `eax`, `ax`, `al`) ;
- utiliser l'instruction `mov` sous ses différentes formes (valeur immédiate, registre, mémoire) ;
- décrire l'anatomie d'un fichier NASM : sections `.data`, `.bss`, `.text` ;
- reconnaître les combinaisons interdites (deux accès mémoire, tailles incompatibles).

## Les registres généraux

x86-64 offre **16 registres généraux** de **64 bits** (8 octets) chacun. Ce sont les cases de travail
du CPU dont on a parlé au chapitre 1. Voici les plus utilisés :

| Registre | Rôle conventionnel (on le détaillera) |
| --- | --- |
| `rax` | accumulateur ; valeur de retour, numéro de syscall |
| `rbx` | usage général (préservé entre fonctions) |
| `rcx` | compteur de boucle, 4e argument |
| `rdx` | données, 3e argument |
| `rsi` | « source » ; 2e argument |
| `rdi` | « destination » ; 1er argument |
| `rbp` | base de la pile (chapitre 7) |
| `rsp` | sommet de la pile (chapitre 7) |
| `r8` à `r15` | huit registres généraux supplémentaires |

Pour l'instant, vois-les comme **des variables matérielles** dans lesquelles tu déposes des valeurs.
Les « rôles conventionnels » prendront tout leur sens aux chapitres 8 et 9.

> **Attention** — `rsp` (et dans une moindre mesure `rbp`) a un rôle spécial : c'est le sommet de la
> pile. Ne t'en sers pas comme d'un registre ordinaire tant que tu n'as pas vu le chapitre 7.

### Les sous-registres : 64, 32, 16 et 8 bits

Chaque registre 64 bits contient des « sous-registres » plus petits qui en désignent les octets de
poids faible. Pour `rax` :

```
| 63 ........................................ 0 |   <- rax  (64 bits, 8 octets)
                      | 31 ............... 0 |       <- eax  (32 bits, 4 octets)
                              | 15 ..... 0 |         <-  ax  (16 bits, 2 octets)
                              | 15 .. 8 |            <-  ah  (octet de poids fort de ax)
                                      | 7 .. 0 |     <-  al  (octet de poids faible)
```

Le tableau de correspondance pour les registres courants :

| 64 bits | 32 bits | 16 bits | 8 bits (bas) |
| --- | --- | --- | --- |
| `rax` | `eax` | `ax` | `al` |
| `rbx` | `ebx` | `bx` | `bl` |
| `rcx` | `ecx` | `cx` | `cl` |
| `rdx` | `edx` | `dx` | `dl` |
| `rsi` | `esi` | `si` | `sil` |
| `rdi` | `edi` | `di` | `dil` |
| `r8` | `r8d` | `r8w` | `r8b` |

On choisit la taille selon la donnée manipulée : un caractère ASCII tient dans `al` (8 bits), un grand
entier dans `rax` (64 bits).

> **Attention** — Écrire dans un registre 32 bits **met à zéro les 32 bits de poids fort** du registre
> 64 bits. Ainsi `mov eax, 5` rend `rax` égal à 5. En revanche, `mov al, 5` ne touche **que** l'octet
> bas et laisse le reste de `rax` inchangé. Cette asymétrie surprend tout le monde au début.

## L'instruction `mov`

`mov` (de *move*) est l'instruction la plus fréquente. Elle **copie** une valeur vers une destination.
Sa forme générale, en syntaxe Intel (celle de NASM) :

```nasm
mov destination, source     ; destination <- source (la source est inchangée)
```

La **destination est toujours à gauche**, la source à droite. Retiens bien ce sens : c'est l'inverse
de l'ordre de lecture naturel pour beaucoup de gens.

Les formes utiles :

```nasm
mov rax, 42         ; valeur immédiate : range la constante 42 dans rax
mov rbx, rax        ; registre vers registre : copie rax dans rbx
mov rax, [nombre]   ; mémoire vers registre : charge la valeur à l'adresse "nombre"
mov [nombre], rax   ; registre vers mémoire : range rax à l'adresse "nombre"
```

Les **crochets** `[ ]` signifient « le contenu à cette adresse » (une lecture ou écriture en mémoire).
Sans crochets, `nombre` désignerait l'adresse elle-même. C'est une distinction capitale, qu'on
approfondit au chapitre 5.

> **À retenir** — `mov rax, nombre` met l'**adresse** dans `rax`. `mov rax, [nombre]` met la
> **valeur** stockée à cette adresse. Les crochets = déréférencement.

### Les combinaisons interdites

Le CPU ne sait pas tout faire en une instruction. Deux interdits à connaître tout de suite :

```nasm
mov [a], [b]        ; INTERDIT : pas deux accès mémoire en une seule instruction
```

Pour copier de la mémoire vers la mémoire, il faut passer par un registre :

```nasm
mov rax, [b]        ; correct : on charge dans un registre...
mov [a], rax        ; ...puis on range
```

Et la taille doit être déterminable :

```nasm
mov [a], 5          ; AMBIGU si l'assembleur ne connaît pas la taille de [a]
mov qword [a], 5    ; correct : qword = 8 octets ; on précise la taille
```

On reverra ces mot-clés de taille (`byte`, `word`, `dword`, `qword`) au chapitre 5.

## Anatomie d'un fichier NASM

Un programme NASM s'organise en **sections**, chacune introduite par `section`. Trois nous concernent :

```nasm
section .data       ; données INITIALISÉES (valeurs connues à l'écriture)
    age db 25       ; un octet valant 25, étiqueté "age"
    pi dq 3         ; un quadword (8 octets) valant 3

section .bss        ; données NON initialisées (place réservée, contenu indéfini)
    resultat resq 1 ; réserve 1 quadword (8 octets) pour "resultat"

section .text       ; le CODE
    global _start
_start:
    ; instructions ici
```

- **`.data`** : les données dont tu connais la valeur de départ. `db` = *define byte*, `dq` = *define
  quadword* (8 octets). On détaille au chapitre 5.
- **`.bss`** : de la place réservée, sans valeur initiale (le système la met à zéro). `resq 1` =
  *reserve 1 quadword*. C'est moins coûteux qu'une zone `.data` quand tu n'as pas besoin de valeur de
  départ.
- **`.text`** : ton code. C'est la seule section exécutable.

Un mot comme `age`, `resultat` ou `_start` placé devant `:` ou une donnée est une **étiquette**
(*label*) : un nom lisible que l'assembleur remplace par l'adresse correspondante. Tu manipules des
noms, l'assembleur gère les adresses.

## Un exemple complet : échanger deux registres

Mettons en pratique `mov`. On veut échanger le contenu de deux registres. Naïvement :

```nasm
mov rax, rbx        ; FAUX pour un échange : on écrase rax avant de l'avoir sauvé
mov rbx, rax
```

Cela ne marche pas : après la première ligne, l'ancien `rax` est perdu. Il faut un registre temporaire :

```nasm
section .text
    global _start
_start:
    mov rax, 10         ; rax = 10
    mov rbx, 20         ; rbx = 20

    mov rcx, rax        ; sauvegarde rax dans rcx (temporaire)
    mov rax, rbx        ; rax = 20
    mov rbx, rcx        ; rbx = 10  -> échange réussi

    mov rax, 60         ; exit
    mov rdi, 0
    syscall
```

Ce programme ne *montre* rien à l'écran (on ne sait pas encore afficher un nombre), mais tu peux
vérifier l'échange au débogueur (chapitre 11). En attendant, le raisonnement « il faut un temporaire »
est exactement celui qu'on retrouve dans tous les langages.

> **Astuce** — x86-64 possède une instruction dédiée, `xchg rax, rbx`, qui échange en une ligne.
> Mais comprendre la version manuelle est plus formateur : c'est elle qui révèle le piège de
> l'écrasement.

## Résumé

- x86-64 a **16 registres généraux** de 64 bits. `rax` se décline en `eax` (32), `ax` (16), `al` (8).
- Écrire dans la partie **32 bits** (`eax`) met à zéro le haut du registre ; écrire dans `al` ne
  touche que l'octet bas.
- `mov destination, source` **copie** une valeur ; la destination est **à gauche**.
- Les **crochets** `[ ]` signifient « contenu à cette adresse » (accès mémoire). Sans crochets, c'est
  l'adresse.
- Interdits : deux accès mémoire dans un même `mov` ; taille ambiguë (préciser `qword`, etc.).
- Un fichier NASM se découpe en `.data` (initialisé), `.bss` (réservé), `.text` (code). Les
  **étiquettes** sont des noms d'adresses.

## Exercices

### Exercice 1 — Repérer les erreurs

Pour chaque ligne, dis si elle est valide ; sinon, explique pourquoi et corrige.

```nasm
mov rax, rdi
mov [total], [age]
mov al, 300
mov [total], 5
```

<details>
<summary>Voir le corrigé</summary>

- `mov rax, rdi` — **valide** : registre vers registre.
- `mov [total], [age]` — **invalide** : deux accès mémoire. Corriger en deux temps :
  `mov rax, [age]` puis `mov [total], rax`.
- `mov al, 300` — **invalide** : `al` fait 8 bits, donc 0 à 255 ; 300 ne tient pas. Utiliser un
  registre plus grand (`mov ax, 300`) ou une valeur ≤ 255.
- `mov [total], 5` — **ambigu** : taille inconnue. Préciser, par exemple `mov qword [total], 5`.

</details>

### Exercice 2 — Charger trois valeurs

Écris un programme qui place 7 dans `rax`, 8 dans `rbx`, et la **somme** prévue (15) directement dans
`rcx` comme valeur immédiate, puis quitte avec le code de sortie 0. (On calculera la somme pour de vrai
au chapitre suivant ; ici, juste des `mov`.)

<details>
<summary>Voir le corrigé</summary>

```nasm
section .text
    global _start
_start:
    mov rax, 7
    mov rbx, 8
    mov rcx, 15         ; valeur immédiate, pas encore un vrai calcul

    mov rax, 60         ; exit
    mov rdi, 0
    syscall
```

L'objectif est de t'entraîner aux `mov` et au squelette `.text` / `_start` / `exit`.

</details>

## Quiz

**1.** Dans `mov rbx, rax`, que devient `rax` ?
- A. Il est mis à zéro
- B. Il est inchangé (seul `rbx` est modifié)
- C. Il prend la valeur de `rbx`

**2.** Que fait `mov rax, [valeur]` ?
- A. Met l'adresse de `valeur` dans `rax`
- B. Met le contenu stocké à l'adresse `valeur` dans `rax`
- C. Range `rax` à l'adresse `valeur`

**3.** Quelle section accueille des données dont tu ne connais pas la valeur de départ ?
- A. `.text`
- B. `.data`
- C. `.bss`

**4.** Après `mov eax, 5`, que vaut `rax` (supposé non nul avant) ?
- A. 5 (le haut est mis à zéro)
- B. Indéterminé
- C. L'ancienne valeur avec 5 dans le bas

<details>
<summary>Voir les réponses</summary>

1. **B** — `mov` copie la source sans la modifier ; seule la destination change.
2. **B** — Les crochets déréférencent : on lit le contenu à l'adresse.
3. **C** — `.bss` réserve de la place non initialisée.
4. **A** — Écrire dans un registre 32 bits met à zéro les 32 bits de poids fort.

</details>

## Projet fil rouge

Prépare la mémoire de `stats`. Ajoute une section `.bss` pour réserver la place des accumulateurs et un
tableau de valeurs (capacité fixe, par exemple 100 entiers de 8 octets), et initialise les compteurs à
zéro au début de `_start` :

```nasm
section .bss
    valeurs resq 100        ; jusqu'à 100 entiers de 8 octets
    nb      resq 1          ; nombre de valeurs saisies
    somme   resq 1          ; somme courante

section .text
    global _start
_start:
    mov qword [nb], 0       ; on part de 0 valeur
    mov qword [somme], 0    ; somme initiale nulle

    ; ... (affichage du titre, comme au jalon 1)

    mov rax, 60             ; exit
    mov rdi, 0
    syscall
```

Tu as maintenant l'espace mémoire dans lequel `stats` rangera ses données. Les chapitres suivants vont
le remplir.

---

[← Chapitre précédent](02-representation-des-donnees.md) · [Sommaire](README.md) · [Chapitre suivant →](04-arithmetique-et-logique.md)
