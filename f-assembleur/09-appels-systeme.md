# Les appels système

[← Chapitre précédent](08-fonctions-et-convention-dappel.md) · [Sommaire](README.md) · [Chapitre suivant →](10-interfacer-avec-le-c.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **appel système** et pourquoi il est nécessaire ;
- utiliser l'instruction `syscall` avec la bonne convention (numéro, arguments, retour) ;
- lire l'entrée clavier avec `read` et écrire avec `write` ;
- détecter une erreur renvoyée par un appel système.

## Pourquoi des appels système ?

Ton programme ne peut pas faire **tout** seul. Afficher du texte, lire le clavier, ouvrir un fichier,
quitter : ces actions touchent au **matériel** et aux ressources partagées. Pour des raisons de
sécurité et de stabilité, seul le **noyau** (*kernel*) du système d'exploitation y a accès direct.

Un **appel système** (*syscall*) est la porte d'entrée vers le noyau : ton programme lui demande
poliment « écris ce texte », « donne-moi ce que l'utilisateur tape ». C'est la seule façon, en
assembleur pur, d'interagir avec le monde extérieur. Tu en utilises déjà deux depuis le chapitre 1 :
`write` et `exit`.

> **À retenir** — Un programme en assembleur ne « parle » au système que par des appels système.
> Afficher, lire, quitter : tout passe par `syscall`.

## La convention d'appel système

Elle ressemble à celle des fonctions (chapitre 8), avec **deux différences** à mémoriser :

- le **numéro** de l'appel va dans `rax` ;
- les arguments vont dans `rdi`, `rsi`, `rdx`, **`r10`**, `r8`, `r9` — attention, le 4e argument est
  `r10` et **non** `rcx` ;
- on déclenche avec l'instruction `syscall` ;
- la **valeur de retour** revient dans `rax` ;
- `syscall` **écrase** `rcx` et `r11` (le noyau s'en sert). Ne compte pas sur eux après.

Quelques numéros utiles sous Linux x86-64 :

| Numéro (`rax`) | Appel | Rôle | Arguments |
| --- | --- | --- | --- |
| 0 | `read` | lire des octets | `rdi`=descripteur, `rsi`=tampon, `rdx`=taille max |
| 1 | `write` | écrire des octets | `rdi`=descripteur, `rsi`=tampon, `rdx`=longueur |
| 60 | `exit` | terminer | `rdi`=code de sortie |

Les **descripteurs** standards : `0` = entrée standard (clavier), `1` = sortie standard (écran), `2` =
sortie d'erreur.

> **Attention** — Pour `syscall`, le 4e argument est `r10`, pas `rcx`. Pour un `call` de fonction
> ordinaire, c'est `rcx`. C'est la confusion classique. Les appels `read`/`write`/`exit` n'utilisent
> que trois arguments, donc tu n'es pas piégé ici, mais retiens la règle pour la suite.

## Lire l'entrée avec `read`

`read` attend que l'utilisateur tape quelque chose, puis copie les octets saisis dans un tampon que tu
fournis. Il renvoie dans `rax` le **nombre d'octets réellement lus** (saut de ligne final inclus), ou
`0` en fin d'entrée (*EOF*).

```nasm
section .bss
    ligne resb 256              ; tampon de réception

section .text
    global _start
_start:
    mov rax, 0                  ; read
    mov rdi, 0                  ; descripteur 0 = entrée standard
    mov rsi, ligne             ; où ranger les octets lus
    mov rdx, 256               ; taille maximale à lire
    syscall                    ; rax = nombre d'octets lus

    ; rax contient combien d'octets l'utilisateur a tapés
    ; "ligne" contient le texte (non terminé par 0 : c'est à toi de gérer la longueur)

    mov rax, 60
    mov rdi, 0
    syscall
```

Deux points cruciaux :

- `read` ne met **pas** d'octet `0` à la fin (contrairement à une chaîne C). C'est `rax` qui te donne
  la longueur ; à toi de t'en servir comme borne.
- Le texte lu contient le **saut de ligne** (`10`) que l'utilisateur a tapé en validant. Tu devras
  souvent l'ignorer.

## Détecter les erreurs

Quand un appel système échoue, il renvoie dans `rax` une valeur **négative** : l'opposé d'un code
d'erreur (par exemple `-2` pour « fichier introuvable »). La convention est simple :

```nasm
    syscall
    cmp rax, 0
    jl  erreur          ; rax < 0 : un appel système a échoué
```

Pour `read` et `write`, un retour positif est le nombre d'octets traités ; `0` sur `read` signifie fin
d'entrée. Vérifier le signe de `rax` après un syscall est une bonne habitude.

> **Astuce** — En cas de doute sur un numéro de syscall ou ses arguments, la page de manuel Linux fait
> foi : `man 2 read`, `man 2 write`. La section 2 du manuel documente précisément les appels système.

## Résumé

- Un **appel système** est la seule porte vers le noyau : afficher, lire, quitter passent par
  `syscall`.
- Convention : numéro dans `rax` ; arguments dans `rdi, rsi, rdx, r10, r8, r9` ; retour dans `rax`.
  `syscall` écrase `rcx` et `r11`.
- `read` (numéro 0) lit dans un tampon et renvoie le **nombre d'octets lus** (0 = fin d'entrée) ; il
  n'ajoute pas de `0` final et conserve le saut de ligne.
- Un retour **négatif** d'un syscall signale une erreur (opposé d'un code d'erreur).

## Exercices

### Exercice 1 — Écho

Écris un programme qui lit une ligne au clavier puis la réaffiche telle quelle (un `read` suivi d'un
`write`, en réutilisant la longueur lue).

<details>
<summary>Voir le corrigé</summary>

```nasm
section .bss
    ligne resb 256
section .text
    global _start
_start:
    mov rax, 0          ; read
    mov rdi, 0
    mov rsi, ligne
    mov rdx, 256
    syscall             ; rax = octets lus

    mov rdx, rax        ; la longueur à réécrire = ce qu'on a lu
    mov rax, 1          ; write
    mov rdi, 1
    mov rsi, ligne
    syscall

    mov rax, 60
    mov rdi, 0
    syscall
```

Le secret est de **réutiliser `rax`** (octets lus) comme longueur (`rdx`) pour le `write`. On le copie
avant que `write` n'écrase `rax`.

</details>

### Exercice 2 — Erreur volontaire

Que renvoie `read` si on lui passe un descripteur invalide (par exemple `rdi = 999`) ? Comment le
détecter dans le code ?

<details>
<summary>Voir le corrigé</summary>

`read` renvoie une valeur **négative** (`-9`, soit `-EBADF`, « mauvais descripteur de fichier »). On
le détecte ainsi :

```nasm
    syscall
    cmp rax, 0
    jl  gerer_erreur    ; rax < 0 -> échec
```

C'est le réflexe à prendre après tout appel système dont l'échec est possible.

</details>

## Quiz

**1.** Où place-t-on le numéro de l'appel système ?
- A. `rdi`
- B. `rax`
- C. La pile

**2.** Pour `syscall`, quel registre porte le 4e argument ?
- A. `rcx`
- B. `r10`
- C. `r9`

**3.** Que renvoie `read` dans `rax` ?
- A. Le nombre d'octets lus (0 en fin d'entrée)
- B. Toujours 1 en cas de succès
- C. L'adresse du tampon

**4.** Comment savoir qu'un appel système a échoué ?
- A. `rax` vaut 0
- B. `rax` est négatif
- C. Le programme plante toujours

<details>
<summary>Voir les réponses</summary>

1. **B** — Le numéro va dans `rax`.
2. **B** — `syscall` utilise `r10` comme 4e argument, à la différence d'un `call`.
3. **A** — `read` renvoie le nombre d'octets lus, ou 0 à la fin de l'entrée.
4. **B** — Un retour négatif signale une erreur.

</details>

## Projet fil rouge

C'est le grand moment : `stats` va lire de **vrais** nombres tapés par l'utilisateur. On lit toute
l'entrée d'un coup dans un tampon, puis on parcourt ce texte en extrayant les nombres avec une fonction
`parse_int` (conversion texte → entier, le pendant de `print_int`).

```nasm
section .bss
    entree resb 1024            ; tampon d'entrée

section .text
; parse_int(rdi sur '-' ou un chiffre) -> rax = valeur, rdi avancé après le nombre
parse_int:
    xor rax, rax                ; résultat = 0
    xor r8, r8                  ; drapeau négatif
    cmp byte [rdi], '-'
    jne .chiffres
    mov r8, 1
    inc rdi
.chiffres:
    movzx rcx, byte [rdi]       ; caractère courant
    cmp cl, '0'
    jb .fin
    cmp cl, '9'
    ja .fin
    sub cl, '0'                 ; caractère -> chiffre 0-9
    imul rax, rax, 10           ; résultat = résultat*10 + chiffre
    add rax, rcx
    inc rdi
    jmp .chiffres
.fin:
    test r8, r8
    jz .positif
    neg rax
.positif:
    ret
```

Et la boucle de lecture dans `_start`, qui remplit le modèle `stats` (`valeurs`, `nb`, `somme`) :

```nasm
    mov rax, 0                  ; read tout l'input
    mov rdi, 0
    mov rsi, entree
    mov rdx, 1024
    syscall
    lea r15, [entree + rax]     ; r15 = fin des données lues
    mov r14, entree            ; r14 = curseur de lecture

.scan:
    cmp r14, r15
    jae .scan_fin              ; plus rien à lire
    movzx rcx, byte [r14]
    cmp cl, '-'
    je .nombre
    cmp cl, '0'
    jb .separateur
    cmp cl, '9'
    jbe .nombre
.separateur:
    inc r14                    ; espace, saut de ligne... : on avance
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
    ; nb, somme et valeurs[] sont remplis à partir de la vraie saisie
```

`stats` lit maintenant des nombres réels (séparés par des espaces ou des sauts de ligne). Combine cette
lecture avec la boucle min/max (chapitre 6) et l'affichage (chapitre 8) : tu as un programme complet de
bout en bout. Pour le tester :

```bash
echo "12 -4 30 7 25" | ./stats
```

Au chapitre suivant, on rendra l'affichage plus présentable en appelant `printf` de la libc.

---

[← Chapitre précédent](08-fonctions-et-convention-dappel.md) · [Sommaire](README.md) · [Chapitre suivant →](10-interfacer-avec-le-c.md)
