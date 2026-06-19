# Fonctions et convention d'appel

[← Chapitre précédent](07-la-pile.md) · [Sommaire](README.md) · [Chapitre suivant →](09-appels-systeme.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- appeler une fonction avec `call` et en revenir avec `ret` ;
- respecter la **convention d'appel System V** (où passer les arguments, où est le retour) ;
- distinguer les registres **préservés** (callee-saved) des registres **volatils** (caller-saved) ;
- écrire `print_int`, la fonction qui affiche un entier — pierre angulaire du projet.

## `call` et `ret`

Une fonction est un bloc de code qu'on peut appeler depuis plusieurs endroits, et qui sait **revenir**
là d'où on l'a appelé. Deux instructions suffisent :

- `call etiquette` : empile l'**adresse de retour** (l'instruction juste après le `call`), puis saute
  à l'étiquette.
- `ret` : dépile cette adresse de retour et y saute.

```nasm
    call ma_fonction    ; empile l'adresse de retour, saute à ma_fonction
    ; ... l'exécution reprend ICI après le ret ...
    mov rax, 60
    mov rdi, 0
    syscall

ma_fonction:
    ; ... travail ...
    ret                 ; revient à l'instruction après le call
```

C'est la pile (chapitre 7) qui rend ça possible : `call` y range l'adresse de retour, `ret` la
récupère. D'où une règle absolue :

> **Attention** — À l'entrée d'une fonction, le sommet de la pile contient l'adresse de retour. Si tu
> empiles sans dépiler (ou l'inverse) avant le `ret`, celui-ci saute à une mauvaise adresse et le
> programme plante. Chaque fonction doit laisser la pile **équilibrée**.

## La convention d'appel System V

Pour que des fonctions écrites séparément (la tienne, la libc, le code d'un compilateur) coopèrent, il
faut un **contrat** commun : qui met les arguments où, qui récupère le résultat où. Sous Linux x86-64,
ce contrat s'appelle l'**ABI System V** (*Application Binary Interface*).

**Les six premiers arguments entiers** passent dans ces registres, dans l'ordre :

| Argument | 1 | 2 | 3 | 4 | 5 | 6 |
| --- | --- | --- | --- | --- | --- | --- |
| Registre | `rdi` | `rsi` | `rdx` | `rcx` | `r8` | `r9` |

**La valeur de retour** est dans **`rax`**.

C'est pour ça que les « rôles conventionnels » du chapitre 3 existaient : `rdi` = 1er argument, `rsi` =
2e, etc. Une fonction qui calcule `f(a, b)` lit `a` dans `rdi` et `b` dans `rsi`, et rend son résultat
dans `rax`.

```nasm
    mov rdi, 8          ; 1er argument
    mov rsi, 5          ; 2e argument
    call addition       ; appelle addition(8, 5)
    ; rax contient maintenant 13

addition:
    mov rax, rdi
    add rax, rsi        ; rax = rdi + rsi
    ret                 ; le résultat est dans rax
```

## Registres volatils et registres préservés

Quand tu appelles une fonction, elle a le droit d'écraser certains registres mais pas d'autres. L'ABI
les classe en deux catégories :

| Catégorie | Registres | Qui doit les sauvegarder |
| --- | --- | --- |
| **Volatils** (caller-saved) | `rax`, `rcx`, `rdx`, `rsi`, `rdi`, `r8`–`r11` | l'**appelant**, s'il y tient |
| **Préservés** (callee-saved) | `rbx`, `rbp`, `r12`–`r15`, `rsp` | la **fonction appelée** |

Concrètement :

- Si tu as une valeur précieuse dans un registre **volatil** (`rax`, `rcx`…) avant un `call`,
  sauvegarde-la toi-même (sur la pile) : la fonction peut l'écraser.
- Si **ta fonction** utilise un registre **préservé** (`rbx`, `r12`…), tu dois le `push` en début de
  fonction et le `pop` avant le `ret` : l'appelant compte sur sa valeur.

> **À retenir** — Volatil = « l'appelé peut l'abîmer, l'appelant se débrouille ». Préservé = « l'appelé
> doit le rendre intact ». `rbx` et `r12`–`r15` sont préservés : si ta fonction les utilise, sauve-les.

## Le prologue et l'épilogue

Beaucoup de fonctions commencent et finissent par un motif standard qui met en place un **cadre de
pile** (*stack frame*) à l'aide de `rbp` :

```nasm
ma_fonction:
    push rbp            ; prologue : sauvegarde l'ancien rbp
    mov rbp, rsp        ; rbp pointe le cadre de cette fonction
    sub rsp, 16         ; réserve éventuellement de la place locale

    ; ... corps ...

    mov rsp, rbp        ; épilogue : libère la place locale
    pop rbp             ; restaure l'ancien rbp
    ret
```

Ce cadre sert de point de repère stable pour les variables locales et facilite le débogage. Pour des
fonctions simples qui n'ont pas de locales, on peut s'en passer, mais le motif est si répandu qu'il
faut le reconnaître.

> **Astuce** — La règle d'**alignement de la pile** : au moment d'un `call`, `rsp` doit être un
> multiple de 16. Tu n'as pas à t'en soucier tant que tu n'appelles que tes propres fonctions et des
> syscalls. Ça deviendra crucial au chapitre 10 quand on appellera la libc (`printf`).

## `print_int` : afficher un entier

On ne sait toujours pas afficher un nombre, parce qu'écrire à l'écran attend du **texte** (des codes
ASCII), pas un entier binaire. Il faut **convertir** l'entier en sa représentation décimale, chiffre
par chiffre. C'est l'exercice le plus formateur de l'assembleur : il combine division, boucle, pile et
écriture.

L'algorithme : diviser le nombre par 10 en boucle ; chaque **reste** est un chiffre (de droite à
gauche) ; on le transforme en caractère en ajoutant `'0'` (code ASCII 48), et on remplit un tampon
**depuis la fin**.

```nasm
section .bss
    num_buf resb 32             ; tampon : 20 chiffres max + signe + saut de ligne

section .text
; print_int(rdi) : affiche l'entier signé rdi suivi d'un saut de ligne
print_int:
    push rbx                    ; rbx et r12 sont préservés : on les sauve
    push r12

    mov rax, rdi                ; rax = valeur à convertir
    lea rsi, [num_buf + 31]     ; rsi pointe le dernier octet du tampon
    mov byte [rsi], 10          ; on place le saut de ligne en bout de tampon

    xor r12, r12                ; r12 = drapeau "négatif" (0 = positif)
    cmp rax, 0
    jge .convertir
    mov r12, 1                  ; nombre négatif
    neg rax                     ; on convertit sa valeur absolue

.convertir:
    mov rbx, 10                 ; diviseur
.boucle:
    cqo                         ; étend le signe dans rdx (rax >= 0 ici)
    idiv rbx                    ; rax = rax/10, rdx = chiffre (reste)
    add dl, '0'                 ; chiffre 0-9 -> caractère ASCII '0'-'9'
    dec rsi                     ; recule d'une case dans le tampon
    mov [rsi], dl               ; écrit le caractère
    test rax, rax
    jnz .boucle                 ; tant qu'il reste quelque chose à convertir

    test r12, r12               ; faut-il un signe moins ?
    jz .ecrire
    dec rsi
    mov byte [rsi], '-'

.ecrire:
    lea rdx, [num_buf + 32]     ; fin du tampon
    sub rdx, rsi                ; rdx = longueur à écrire
    mov rax, 1                  ; write
    mov rdi, 1                  ; sortie standard
    ; rsi pointe déjà le début du texte à écrire
    syscall

    pop r12                     ; on restaure les registres préservés
    pop rbx
    ret
```

Plusieurs idées vues précédemment se combinent ici : la division signée avec `cqo`/`idiv` (chapitre
4), la boucle « tant qu'il reste » (chapitre 6), la préservation de `rbx`/`r12` par la pile (chapitre
7), et le `write` (chapitre 1). Le cas du nombre **zéro** est géré naturellement : la boucle s'exécute
une fois et écrit `'0'`.

> **À retenir** — Les noms commençant par `.` (comme `.boucle`) sont des **étiquettes locales** :
> elles n'appartiennent qu'à la fonction courante, donc tu peux réutiliser `.boucle` dans une autre
> fonction sans conflit.

Pour t'en servir :

```nasm
    mov rdi, 42
    call print_int      ; affiche "42" suivi d'un saut de ligne
```

## Résumé

- `call` empile l'adresse de retour et saute ; `ret` y revient. La fonction doit laisser la pile
  **équilibrée**.
- ABI System V : arguments entiers dans `rdi, rsi, rdx, rcx, r8, r9` ; retour dans `rax`.
- Registres **volatils** (`rax, rcx, rdx, rsi, rdi, r8–r11`) : l'appelant les protège s'il y tient.
  Registres **préservés** (`rbx, rbp, r12–r15`) : la fonction appelée doit les rendre intacts.
- Le **prologue/épilogue** (`push rbp` / `mov rbp, rsp` … `pop rbp`) installe un cadre de pile.
- `print_int` convertit un entier en texte décimal par divisions successives par 10 et l'affiche.

## Exercices

### Exercice 1 — Fonction `max`

Écris une fonction `max(a, b)` (arguments dans `rdi`, `rsi`) qui renvoie le plus grand des deux dans
`rax`, en respectant l'ABI.

<details>
<summary>Voir le corrigé</summary>

```nasm
; max(rdi, rsi) -> rax
max:
    mov rax, rdi        ; suppose a le plus grand
    cmp rsi, rax
    jle .fin            ; si b <= a, a est le max
    mov rax, rsi        ; sinon b
.fin:
    ret
```

On n'utilise que des registres volatils (`rax`), donc rien à sauvegarder. Le résultat part dans `rax`,
conformément à la convention.

</details>

### Exercice 2 — Préserver un registre

Une fonction `somme_tableau` a besoin de `rbx` comme accumulateur. Montre le squelette correct
(sauvegarde/restauration) pour ne pas violer l'ABI.

<details>
<summary>Voir le corrigé</summary>

```nasm
somme_tableau:
    push rbx            ; rbx est préservé : on le sauve à l'entrée
    xor rbx, rbx        ; accumulateur = 0
    ; ... boucle qui ajoute dans rbx ...
    mov rax, rbx        ; résultat dans rax
    pop rbx             ; on restaure rbx avant de rendre la main
    ret
```

Sans le `push`/`pop` de `rbx`, l'appelant retrouverait son `rbx` corrompu : un bug très difficile à
traquer.

</details>

### Exercice 3 — Tester `print_int`

Écris un petit programme qui affiche successivement `0`, `7`, `-15` et `1000000` en appelant
`print_int`.

<details>
<summary>Voir le corrigé</summary>

```nasm
section .text
    global _start
_start:
    mov rdi, 0
    call print_int
    mov rdi, 7
    call print_int
    mov rdi, -15
    call print_int
    mov rdi, 1000000
    call print_int

    mov rax, 60
    mov rdi, 0
    syscall
; (print_int et num_buf collés à la suite, comme plus haut)
```

Sortie attendue : `0`, `7`, `-15`, `1000000`, chacun sur sa ligne. Si `-15` s'affiche sans le signe,
revois le drapeau `r12`.

</details>

## Quiz

**1.** Où passe le premier argument entier d'une fonction (ABI System V) ?
- A. `rax`
- B. `rdi`
- C. La pile

**2.** Quel registre contient la valeur de retour ?
- A. `rbx`
- B. `rdi`
- C. `rax`

**3.** Ta fonction utilise `rbx`. Que dois-tu faire ?
- A. Rien, `rbx` est volatil
- B. Le `push` en entrée et le `pop` en sortie (il est préservé)
- C. Le mettre à zéro avant `ret`

**4.** Que fait `call` avant de sauter ?
- A. Il empile l'adresse de retour
- B. Il met à zéro `rsp`
- C. Il sauvegarde tous les registres

<details>
<summary>Voir les réponses</summary>

1. **B** — `rdi` est le premier registre d'argument.
2. **C** — La valeur de retour transite par `rax`.
3. **B** — `rbx` est préservé : il faut le sauvegarder et le restaurer.
4. **A** — `call` empile l'adresse de retour avant de sauter à la fonction.

</details>

## Projet fil rouge

Intègre `print_int` à `stats` et affiche les résultats calculés jusqu'ici. Ajoute aussi le calcul de
la **moyenne** (`somme / nb`), maintenant que tu maîtrises la division et les fonctions :

```nasm
    ; affichage des statistiques
    mov rdi, [nb]
    call print_int          ; nombre de valeurs
    mov rdi, [somme]
    call print_int          ; somme
    mov rdi, [minv]
    call print_int          ; minimum
    mov rdi, [maxv]
    call print_int          ; maximum

    ; moyenne = somme / nb
    mov rax, [somme]
    cqo
    idiv qword [nb]         ; rax = somme / nb
    mov rdi, rax
    call print_int          ; moyenne (division entière)
```

`stats` affiche désormais ses cinq statistiques. Il reste à les **lire** depuis le clavier plutôt que
de les coder en dur : c'est tout l'objet du chapitre suivant sur les appels système.

---

[← Chapitre précédent](07-la-pile.md) · [Sommaire](README.md) · [Chapitre suivant →](09-appels-systeme.md)
