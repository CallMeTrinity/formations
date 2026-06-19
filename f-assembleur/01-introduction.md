# Introduction : la machine, l'assembleur et ton environnement

[Sommaire](README.md) · [Chapitre suivant →](02-representation-des-donnees.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est l'assembleur et en quoi il diffère d'un langage haut niveau ;
- décrire le modèle mental d'un ordinateur : *CPU*, registres, mémoire, instructions ;
- installer un environnement de travail Linux identique pour tout le monde grâce à *Docker* ;
- assembler, linker et exécuter ton tout premier programme en assembleur.

## Qu'est-ce que l'assembleur ?

Un processeur (*CPU*, pour *Central Processing Unit*, l'unité centrale de calcul) ne comprend qu'une
seule chose : des suites d'octets appelées **langage machine**. Chaque suite encode une opération
élémentaire — additionner deux nombres, déplacer une valeur, sauter ailleurs dans le programme. Ces
octets sont illisibles pour un humain : `48 89 c3` ne veut rien dire à l'œil nu.

L'**assembleur** (en anglais *assembly*) est la traduction lisible de ce langage machine. À chaque
instruction machine correspond un nom court, le **mnémonique**. Par exemple, ces mêmes octets
`48 89 c3` s'écrivent en assembleur :

```nasm
mov rbx, rax        ; copie le contenu de rax dans rbx
```

C'est tout l'assembleur : une correspondance quasi directe entre ce que tu écris et ce que le
processeur exécute. Le programme qui transforme ton texte en octets s'appelle un **assembleur** lui
aussi (le logiciel) — on utilisera *NASM*.

> **À retenir** — Assembleur = une instruction lisible pour une instruction machine. C'est la couche
> la plus basse qu'un humain écrive encore à la main. En dessous, il n'y a que des octets.

### Assembleur vs langage haut niveau

Dans un langage haut niveau, tu écris :

```c
int z = x + y;
```

Une seule ligne. En assembleur, il n'y a ni variable `x`, ni type `int`, ni `+`. Tu dois dire
explicitement *où* sont les valeurs et *quoi* faire, étape par étape :

```nasm
mov rax, [x]        ; charge la valeur stockée à l'adresse x dans rax
add rax, [y]        ; ajoute la valeur stockée à l'adresse y
mov [z], rax        ; range le résultat à l'adresse z
```

C'est plus verbeux, mais il n'y a **aucune magie** : tu vois exactement ce que la machine fait. C'est
précisément pour ça qu'on apprend l'assembleur — pas pour écrire des applications entières avec, mais
pour comprendre ce que font *vraiment* tous les autres langages.

## Le modèle mental de la machine

Garde en tête ce schéma simplifié pendant toute la formation :

```
        +---------------------------+
        |            CPU            |
        |   +-------------------+   |
        |   |    Registres      |   |   <- petites cases ultra-rapides
        |   | rax rbx rcx ...   |   |      dans le processeur
        |   +-------------------+   |
        |   |   Unité de calcul |   |   <- exécute add, sub, mov...
        |   +-------------------+   |
        +-------------|-------------+
                      |  bus
        +-------------|-------------+
        |           Mémoire (RAM)   |   <- grande, plus lente, adressée
        |  adresse 0 1 2 3 4 ...    |      par numéro (adresse)
        +---------------------------+
```

Trois éléments à retenir :

- **Le CPU** exécute les instructions, une par une.
- **Les registres** sont un tout petit nombre de cases (une douzaine d'utiles) situées *dans* le CPU.
  Elles sont minuscules (8 octets chacune en x86-64) mais d'un accès instantané. C'est l'espace de
  travail du processeur.
- **La mémoire** (la RAM) est vaste mais plus lente. Chaque octet y a une **adresse** : un numéro qui
  permet de le retrouver, comme le numéro d'une case dans un immense casier.

Le travail d'un programme en assembleur consiste essentiellement à faire des allers-retours entre
registres et mémoire, et à calculer dans les registres.

> **À retenir** — Le CPU calcule dans les **registres**. La **mémoire** sert à stocker ce qui ne tient
> pas dans les registres. On passe son temps à déplacer des valeurs entre les deux.

## Pourquoi x86-64, NASM et Linux

- **x86-64** (aussi appelé *AMD64* ou *Intel 64*) est l'architecture des PC de bureau et des serveurs.
  C'est celle que tu rencontres partout, et la mieux documentée.
- **NASM** (*Netwide Assembler*) est un assembleur populaire à la syntaxe claire (dite « syntaxe
  Intel »), idéale pour apprendre.
- **Linux** offre une interface simple et stable entre ton programme et le système (on le verra au
  chapitre 9). Et grâce à Docker, tu l'utilises quelle que soit ta machine.

## Mettre en place ton environnement avec Docker

Tu n'as pas besoin d'être sous Linux ni d'avoir un processeur x86-64. **Docker** te fournit un Linux
x86-64 jetable et identique pour tout le monde. Si Docker n'est pas installé, récupère **Docker
Desktop** (Windows, macOS) ou le paquet `docker` (Linux) avant de continuer.

Crée un dossier de travail, et dedans un fichier nommé `Dockerfile` (sans extension) :

```dockerfile
# Dockerfile : image Linux x86-64 avec les outils d'assembleur
FROM ubuntu:24.04
RUN apt-get update \
    && apt-get install -y nasm binutils gdb gcc \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /work
```

Construis l'image une fois (l'option `--platform linux/amd64` garantit du x86-64 même sur une machine
ARM comme un Mac Apple Silicon) :

```bash
docker build --platform linux/amd64 -t asm .
```

Puis lance un conteneur, en partageant ton dossier courant pour pouvoir éditer tes fichiers depuis ta
machine :

```bash
docker run --platform linux/amd64 -it --rm -v "$PWD":/work asm bash
```

Tu obtiens un *shell* (l'invite de commande) Linux. Vérifie que NASM répond :

```bash
nasm --version
# Sortie attendue (le numéro peut varier) :
# NASM version 2.16.01
```

> **Astuce** — Édite tes fichiers `.s` avec ton éditeur habituel sur ta machine ; ils apparaissent
> instantanément dans `/work` côté conteneur grâce à l'option `-v`. Le conteneur ne sert qu'à
> assembler et exécuter.

> **Attention** — Sur une machine non-x86 (Mac Apple Silicon, certains PC ARM), les binaires x86-64
> tournent par **émulation**. C'est plus lent, mais totalement transparent pour cette formation.

## Ton premier programme

Crée un fichier `hello.s` dans ton dossier de travail :

```nasm
; hello.s — affiche un message puis quitte proprement
section .data                   ; zone des données initialisées
    message db "Bonjour, assembleur !", 10   ; le texte ; 10 = saut de ligne
    longueur equ $ - message    ; longueur = adresse courante moins début du texte

section .text                   ; zone du code
    global _start               ; rend _start visible pour le linker

_start:                         ; point d'entrée du programme
    mov rax, 1                  ; numéro de l'appel système "write"
    mov rdi, 1                  ; 1er argument : descripteur 1 = sortie standard
    mov rsi, message            ; 2e argument : adresse du texte
    mov rdx, longueur           ; 3e argument : nombre d'octets à écrire
    syscall                     ; demande au noyau d'exécuter "write"

    mov rax, 60                 ; numéro de l'appel système "exit"
    mov rdi, 0                  ; code de sortie 0 = succès
    syscall                     ; demande au noyau de terminer le programme
```

Ne cherche pas à tout comprendre maintenant : beaucoup de détails (registres, sections, `syscall`)
arrivent dans les chapitres suivants. Pour l'instant, retiens le **squelette** : une zone `.data`
pour les données, une zone `.text` pour le code, et un point d'entrée `_start`.

### Assembler, linker, exécuter

Trois étapes, dans le conteneur :

```bash
nasm -f elf64 hello.s -o hello.o   # 1. assemble : .s -> fichier objet .o
ld hello.o -o hello                # 2. linke : .o -> exécutable
./hello                            # 3. exécute
# Sortie attendue :
# Bonjour, assembleur !
```

Que s'est-il passé ?

1. **`nasm`** traduit ton texte en **fichier objet** (`hello.o`), un fichier d'octets machine pas
   encore exécutable. `-f elf64` précise le format Linux 64 bits (*ELF*).
2. **`ld`** (le *linker*, l'éditeur de liens) transforme le fichier objet en **exécutable** prêt à
   lancer. C'est lui qui cherche `_start` comme point de départ.
3. **`./hello`** lance le programme.

> **Attention** — L'erreur `ld: warning: cannot find entry symbol _start` signifie que tu as oublié
> `global _start` ou mal orthographié `_start`. Le linker ne sait alors pas par où commencer.

Félicitations : tu viens d'exécuter un programme dont tu contrôles chaque octet.

## Résumé

- L'assembleur est la traduction lisible du **langage machine** : une instruction lisible pour une
  instruction exécutée par le CPU.
- Le **CPU** calcule dans des **registres** (rapides, peu nombreux) ; la **mémoire** (RAM) stocke le
  reste et s'adresse par numéro.
- On travaille en **x86-64**, avec **NASM**, sous **Linux** fourni par **Docker** (`--platform
  linux/amd64`) pour être reproductible sur n'importe quelle machine.
- Un programme NASM se structure en `section .data` (données) et `section .text` (code), avec un point
  d'entrée `_start`.
- La chaîne de fabrication est : `nasm` (assemble) → `ld` (linke) → exécutable.

## Exercices

### Exercice 1 — Mettre en place et exécuter

Construis l'image Docker, lance le conteneur, crée `hello.s`, et obtiens l'affichage `Bonjour,
assembleur !`. C'est l'exercice fondateur : sans environnement qui marche, rien ne suit.

<details>
<summary>Voir le corrigé</summary>

Dans un dossier vide, crée le `Dockerfile` donné plus haut, puis :

```bash
docker build --platform linux/amd64 -t asm .
docker run --platform linux/amd64 -it --rm -v "$PWD":/work asm bash
```

Dans le conteneur, après avoir créé `hello.s` :

```bash
nasm -f elf64 hello.s -o hello.o
ld hello.o -o hello
./hello
```

Si rien ne s'affiche, vérifie que tu as bien recopié le texte du `mov rax, 1` (write) et la longueur.

</details>

### Exercice 2 — Modifier le message

Change le texte affiché par `Salut le monde !` et fais en sorte que la longueur reste correcte
automatiquement.

<details>
<summary>Voir le corrigé</summary>

Il suffit de modifier la chaîne. La ligne `longueur equ $ - message` recalcule la longueur toute
seule : `$` est l'adresse courante (juste après la chaîne), on soustrait l'adresse du début.

```nasm
    message db "Salut le monde !", 10
    longueur equ $ - message
```

C'est tout l'intérêt de `equ $ - message` : tu n'as jamais à compter les caractères à la main.

</details>

## Quiz

**1.** Où le processeur effectue-t-il ses calculs ?
- A. Directement dans la mémoire (RAM)
- B. Dans les registres
- C. Sur le disque dur

**2.** À quoi sert la commande `ld` ?
- A. À assembler le texte en fichier objet
- B. À exécuter le programme
- C. À transformer le fichier objet en exécutable

**3.** Pourquoi utilise-t-on Docker dans cette formation ?
- A. Pour rendre le programme plus rapide
- B. Pour disposer d'un Linux x86-64 identique sur n'importe quelle machine
- C. Parce que NASM n'existe que dans Docker

<details>
<summary>Voir les réponses</summary>

1. **B** — Les registres sont l'espace de travail du CPU ; la mémoire ne fait que stocker.
2. **C** — `ld` est le linker : il fabrique l'exécutable à partir du ou des fichiers objets.
3. **B** — Docker fournit un environnement Linux reproductible partout, indépendant de ton OS et de
   ton processeur.

</details>

## Projet fil rouge

Premier jalon : poser le squelette de `stats`. Crée `stats.s` en repartant de `hello.s`, mais avec un
message d'accueil et un nom de point d'entrée plus parlant dans les commentaires. Le programme doit
simplement afficher une ligne de présentation, puis quitter avec le code 0.

```nasm
; stats.s — outil de statistiques (jalon 1 : squelette qui s'exécute)
section .data
    titre db "stats : nombre, somme, min, max, moyenne", 10
    titre_len equ $ - titre

section .text
    global _start

_start:
    mov rax, 1                  ; write
    mov rdi, 1                  ; sortie standard
    mov rsi, titre
    mov rdx, titre_len
    syscall

    mov rax, 60                 ; exit
    mov rdi, 0
    syscall
```

Assemble-le et exécute-le comme `hello`. Tu obtiens la base sur laquelle on va construire tout le
reste de la formation.

---

[Sommaire](README.md) · [Chapitre suivant →](02-representation-des-donnees.md)
