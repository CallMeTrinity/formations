# Conclusion et pour aller plus loin

[← Chapitre précédent](11-deboguer-avec-gdb.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- récapituler le chemin parcouru et situer ce que tu maîtrises ;
- assembler le projet `stats` dans sa version finale complète ;
- identifier les pistes pour continuer (SIMD, optimisation, autres architectures, sécurité) ;
- où chercher quand tu rencontres une instruction ou un concept inconnu.

## Le chemin parcouru

Tu es parti d'octets illisibles et tu arrives à un programme complet écrit à la main. Récapitulons ce
que tu sais faire :

- **Penser la machine** : CPU, registres, mémoire, et la représentation binaire/hexa des entiers
  signés (complément à deux) et l'endianness (chapitres 1-2).
- **Manipuler les registres** et la mémoire avec `mov`, l'adressage indexé, les bonnes tailles
  (chapitres 3, 5).
- **Calculer** : arithmétique, division avec `cqo`/`idiv`, logique bit à bit, décalages, et la lecture
  des flags (chapitre 4).
- **Structurer** : `if`/`else` et boucles à partir de `cmp` et des sauts conditionnels (chapitre 6).
- **Organiser** : la pile, les fonctions, l'ABI System V, les registres préservés/volatils (chapitres
  7-8).
- **Interagir** : appels système pour lire et écrire, et interfaçage avec le C et la libc (chapitres
  9-10).
- **Observer** : déboguer au `gdb` et lire l'assembleur produit par un compilateur (chapitre 11).

C'est exactement le niveau intermédiaire visé : tu lis de l'assembleur, tu en écris, et surtout tu
comprends ce que font *vraiment* les langages au-dessus.

## Le projet `stats` au complet

Voici la version finale assemblée, qui réunit tous les jalons. Recopie-la dans `stats.s`.

```nasm
; stats.s — lit des entiers signés et affiche nombre, somme, min, max, moyenne
; assembler : nasm -f elf64 -g -F dwarf stats.s -o stats.o
; linker    : gcc -no-pie stats.o -o stats
; tester    : echo "12 -4 30 7 25" | ./stats

extern printf

section .bss
    entree  resb 1024           ; tampon d'entrée brute
    valeurs resq 100            ; jusqu'à 100 entiers
    nb      resq 1              ; nombre de valeurs
    somme   resq 1              ; somme
    minv    resq 1              ; minimum
    maxv    resq 1              ; maximum

section .data
    fmt_nb     db "Nombre  : %ld", 10, 0
    fmt_somme  db "Somme   : %ld", 10, 0
    fmt_min    db "Min     : %ld", 10, 0
    fmt_max    db "Max     : %ld", 10, 0
    fmt_moy    db "Moyenne : %ld", 10, 0
    msg_vide   db "Aucune valeur saisie.", 10, 0

section .text
    global main

main:
    push rbp                    ; prologue : aligne la pile pour printf
    mov rbp, rsp
    mov qword [nb], 0
    mov qword [somme], 0

    ; --- lecture de toute l'entrée d'un coup ---
    mov rax, 0                  ; read
    mov rdi, 0                  ; entrée standard
    mov rsi, entree
    mov rdx, 1024
    syscall
    lea r15, [entree + rax]     ; r15 = fin des données lues
    mov r14, entree            ; r14 = curseur

.scan:
    cmp r14, r15
    jae .scan_fin
    movzx rcx, byte [r14]
    cmp cl, '-'
    je .nombre
    cmp cl, '0'
    jb .sep
    cmp cl, '9'
    jbe .nombre
.sep:
    inc r14                    ; séparateur : on avance
    jmp .scan
.nombre:
    mov rdi, r14
    call parse_int             ; rax = valeur, rdi avancé
    mov r14, rdi
    mov rcx, [nb]
    mov [valeurs + rcx*8], rax ; range la valeur
    inc qword [nb]
    add [somme], rax           ; met à jour la somme
    jmp .scan
.scan_fin:

    ; --- cas particulier : aucune valeur ---
    cmp qword [nb], 0
    jne .calcul
    lea rdi, [rel msg_vide]
    xor eax, eax
    call printf
    jmp .fin

.calcul:
    ; --- min et max ---
    mov rax, [valeurs]
    mov [minv], rax
    mov [maxv], rax
    mov rcx, 1
.mm:
    cmp rcx, [nb]
    jge .mm_fin
    mov rax, [valeurs + rcx*8]
    cmp rax, [minv]
    jge .pas_min
    mov [minv], rax
.pas_min:
    cmp rax, [maxv]
    jle .pas_max
    mov [maxv], rax
.pas_max:
    inc rcx
    jmp .mm
.mm_fin:

    ; --- affichage des résultats ---
    lea rdi, [rel fmt_nb]
    mov rsi, [nb]
    xor eax, eax
    call printf
    lea rdi, [rel fmt_somme]
    mov rsi, [somme]
    xor eax, eax
    call printf
    lea rdi, [rel fmt_min]
    mov rsi, [minv]
    xor eax, eax
    call printf
    lea rdi, [rel fmt_max]
    mov rsi, [maxv]
    xor eax, eax
    call printf

    ; moyenne = somme / nb (division entière)
    mov rax, [somme]
    cqo
    idiv qword [nb]
    lea rdi, [rel fmt_moy]
    mov rsi, rax
    xor eax, eax
    call printf

.fin:
    xor eax, eax               ; code de retour 0
    pop rbp
    ret

; parse_int(rdi sur '-' ou un chiffre) -> rax = valeur, rdi avancé
parse_int:
    xor rax, rax
    xor r8, r8                 ; drapeau négatif
    cmp byte [rdi], '-'
    jne .chiffres
    mov r8, 1
    inc rdi
.chiffres:
    movzx rcx, byte [rdi]
    cmp cl, '0'
    jb .fini
    cmp cl, '9'
    ja .fini
    sub cl, '0'
    imul rax, rax, 10
    add rax, rcx
    inc rdi
    jmp .chiffres
.fini:
    test r8, r8
    jz .pos
    neg rax
.pos:
    ret
```

```bash
nasm -f elf64 -g -F dwarf stats.s -o stats.o
gcc -no-pie stats.o -o stats
echo "12 -4 30 7 25" | ./stats
# Sortie attendue :
# Nombre  : 5
# Somme   : 70
# Min     : -4
# Max     : 30
# Moyenne : 14
```

Ce programme réunit *tout* : lecture par syscall, parsing texte → entier, tableau en mémoire, boucle de
calcul, division signée, et affichage formaté via la libc. Tu le comprends ligne par ligne — c'est ça,
la maîtrise.

## Pour aller plus loin

L'assembleur que tu connais ouvre plusieurs portes :

- **SIMD (SSE/AVX)** : des instructions qui traitent plusieurs données en parallèle (additionner 8
  entiers d'un coup). C'est le cœur de l'optimisation moderne. Registres `xmm`/`ymm`, instructions
  `paddd`, `mulps`…
- **Optimisation** : comprendre les caches, le *pipeline* du processeur, la prédiction de branchement.
  Pourquoi un code « équivalent » peut être 10× plus rapide.
- **Autres architectures** : **ARM64** (AArch64), qui équipe les téléphones et les Mac récents, avec un
  jeu d'instructions plus régulier. Tes acquis (registres, pile, ABI) se transposent directement.
- **Sécurité et rétro-ingénierie** : lire le désassemblage d'un binaire, comprendre les *buffer
  overflows*, analyser un programme sans son code source. L'assembleur est la langue de ce domaine.
- **Embarqué et systèmes** : démarrage d'un système, pilotes, code au plus près du matériel.

## Où chercher

Quand tu butes sur une instruction ou un concept :

- **Le manuel Intel** (*Intel 64 and IA-32 Architectures Software Developer's Manual*) : la référence
  absolue de chaque instruction x86-64.
- **`man 2 <appel>`** pour les appels système Linux (`man 2 read`, `man 2 write`).
- **Compiler Explorer (godbolt.org)** : écris du C, vois l'assembleur correspondant en direct. Le
  meilleur terrain d'expérimentation.
- **La documentation NASM** pour les directives et la syntaxe.
- **`objdump -d -M intel`** et **`gdb`** pour disséquer n'importe quel binaire toi-même.

> **À retenir** — Personne ne retient toutes les instructions x86-64 par cœur : il y en a des
> centaines. Ce qui compte, c'est le **modèle mental** (registres, mémoire, pile, flags, ABI) et de
> savoir où chercher le détail. Ça, tu l'as.

## Résumé

- Tu maîtrises les fondamentaux de l'assembleur x86-64 : données, registres, calcul, contrôle du flot,
  pile, fonctions, syscalls, interfaçage C et débogage.
- Le projet `stats` met tout en œuvre dans un programme réel, compréhensible de bout en bout.
- Les suites possibles : SIMD, optimisation, ARM64, sécurité/rétro-ingénierie, embarqué.
- L'essentiel est le modèle mental de la machine et la capacité à trouver l'information — pas la
  mémorisation exhaustive des instructions.

## Exercices

### Exercice 1 — Étendre `stats`

Ajoute une statistique : l'**amplitude** (max − min). Affiche-la avec son propre `printf`.

<details>
<summary>Voir le corrigé</summary>

Après le calcul de `minv` et `maxv`, et avant la moyenne :

```nasm
section .data
    fmt_amp db "Amplitude : %ld", 10, 0

section .text
    mov rax, [maxv]
    sub rax, [minv]            ; amplitude = max - min
    lea rdi, [rel fmt_amp]
    mov rsi, rax
    xor eax, eax
    call printf
```

C'est une simple soustraction réutilisant les valeurs déjà calculées. Le réflexe « charger, calculer,
afficher » est désormais naturel.

</details>

### Exercice 2 — Lire le code d'un compilateur

Écris en C une fonction qui calcule la somme des entiers de 1 à `n`, compile-la avec `gcc -O2 -S`, et
identifie dans l'assembleur produit la boucle (ou son absence, si le compilateur a appliqué la formule
`n(n+1)/2`).

<details>
<summary>Voir le corrigé</summary>

```c
long somme(long n) {
    long total = 0;
    for (long i = 1; i <= n; i++) total += i;
    return total;
}
```

```bash
gcc -O2 -S somme.c -o somme.s
```

Avec `-O2`, `gcc` reconnaît souvent la somme arithmétique et génère **sans boucle** une `lea` et un
`imul` correspondant à `n*(n+1)/2`. Tu observes en direct une optimisation que seul l'assembleur révèle
— exactement le genre de compréhension que cette formation t'apporte.

</details>

## Quiz

**1.** Quel est le plus important à retenir de l'assembleur ?
- A. La liste exhaustive des instructions
- B. Le modèle mental (registres, mémoire, pile, ABI) et où chercher
- C. La syntaxe AT&T

**2.** Quelle technologie traite plusieurs données en parallèle dans un registre ?
- A. SIMD (SSE/AVX)
- B. La pile
- C. Les syscalls

**3.** Quel outil montre le C et son assembleur côte à côte en direct ?
- A. `gdb`
- B. Compiler Explorer (godbolt.org)
- C. `nasm`

**4.** Dans `stats`, qu'est-ce qui convertit le texte saisi en entiers ?
- A. `printf`
- B. `parse_int`
- C. `read`

<details>
<summary>Voir les réponses</summary>

1. **B** — Le modèle mental et la capacité à trouver l'information priment sur la mémorisation.
2. **A** — SIMD (SSE/AVX) traite plusieurs valeurs en une instruction.
3. **B** — Compiler Explorer affiche C et assembleur en temps réel.
4. **B** — `parse_int` transforme les caractères lus en valeurs entières.

</details>

## Projet fil rouge

Dernier jalon : ton `stats` est terminé. Pour le boucler proprement :

1. Vérifie qu'il gère les cas limites : aucune entrée (`echo "" | ./stats` doit afficher « Aucune
   valeur saisie. »), une seule valeur, des nombres négatifs.
2. Relis chaque fonction et assure-toi de pouvoir expliquer **chaque ligne** — c'est le vrai test de
   maîtrise.
3. Choisis une extension et implémente-la : l'amplitude (exercice 1), le tri des valeurs, ou la
   médiane. Tu as désormais tous les outils pour le faire seul.

Bravo : tu as écrit, du premier octet au dernier, un programme complet en assembleur, et tu comprends
la machine comme peu de développeurs. C'est une base solide pour tout ce qui touche au bas niveau.

---

[← Chapitre précédent](11-deboguer-avec-gdb.md) · [Sommaire](README.md)
