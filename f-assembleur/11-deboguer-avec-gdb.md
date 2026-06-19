# Déboguer avec gdb

[← Chapitre précédent](10-interfacer-avec-le-c.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- lancer un programme assembleur sous `gdb` et poser des points d'arrêt ;
- exécuter pas à pas et inspecter registres, flags et mémoire ;
- lire la mémoire octet par octet (et reconnaître le little-endian) ;
- désassembler du code, y compris celui produit par un compilateur C.

## Pourquoi gdb est indispensable en assembleur

En assembleur, il n'y a pas de `print` facile pour espionner une variable : tout se passe dans les
registres et la mémoire, invisibles à l'œil nu. **`gdb`** (*GNU Debugger*) te laisse arrêter le
programme à n'importe quel endroit et **regarder l'état réel du processeur** : le contenu de chaque
registre, les flags, n'importe quelle zone mémoire. C'est l'outil qui transforme l'assembleur d'une
boîte noire en quelque chose d'observable.

## Préparer le programme et lancer gdb

Assemble avec des **informations de débogage** (`-g -F dwarf`), ce qui permet à `gdb` de relier le code
machine à tes lignes source :

```bash
nasm -f elf64 -g -F dwarf stats.s -o stats.o
gcc -no-pie stats.o -o stats        # ou : ld stats.o -o stats  (si version _start)
gdb ./stats
```

> **Attention** — Dans Docker, `gdb` a besoin d'autorisations supplémentaires pour tracer un processus.
> Lance le conteneur avec : `docker run --platform linux/amd64 --cap-add=SYS_PTRACE
> --security-opt seccomp=unconfined -it --rm -v "$PWD":/work asm bash`. Sans ça, `gdb` affiche
> « ptrace: Operation not permitted ».

Une fois dans `gdb`, demande la syntaxe Intel (celle de NASM, sinon l'affichage est en syntaxe AT&T,
déroutante) :

```
(gdb) set disassembly-flavor intel
```

## Les commandes essentielles

| Commande | Abréviation | Effet |
| --- | --- | --- |
| `break etiquette` | `b` | pose un point d'arrêt (par ex. `b _start` ou `b parse_int`) |
| `run` | `r` | lance le programme (jusqu'au prochain arrêt) |
| `stepi` | `si` | exécute **une** instruction machine |
| `nexti` | `ni` | comme `si`, mais sans entrer dans les `call` |
| `continue` | `c` | reprend jusqu'au prochain arrêt |
| `info registers` | `i r` | affiche tous les registres |
| `print $rax` | `p $rax` | affiche un registre (préfixe `$`) |
| `x/...` | | examine la mémoire (voir plus bas) |
| `disassemble` | `disas` | désassemble la fonction courante |
| `quit` | `q` | quitte gdb |

Une session typique :

```
(gdb) set disassembly-flavor intel
(gdb) break _start
(gdb) run
(gdb) stepi                 # avance d'une instruction
(gdb) info registers rax rbx rcx
(gdb) continue
```

> **Astuce** — Le mode TUI affiche le code et la position courante en continu :
> `gdb -tui ./stats`, ou la commande `layout asm` une fois dans gdb. On voit la flèche d'exécution
> avancer instruction par instruction.

## Inspecter les registres et les flags

`info registers` (ou `i r`) liste tout. Pour un seul registre, `p $rax`. Tu peux choisir le format
d'affichage :

```
(gdb) p/x $rax             # en hexadécimal
(gdb) p/d $rax             # en décimal signé
(gdb) p/t $rax             # en binaire
```

Les **flags** sont dans le registre `eflags`, affiché sous forme de liste de drapeaux actifs :

```
(gdb) i r eflags
eflags  0x202  [ ZF IF ]      # ici ZF est positionné (résultat précédent nul)
```

Tu peux ainsi vérifier *pourquoi* un saut conditionnel est pris ou non : il suffit de regarder l'état
des flags juste avant.

## Examiner la mémoire avec `x`

La commande `x` (*examine*) lit la mémoire. Sa syntaxe : `x/NFU adresse`, où **N** = nombre d'unités,
**F** = format, **U** = taille de l'unité (`b`=octet, `h`=2, `w`=4, `g`=8).

```
(gdb) x/5dg &valeurs       # 5 entiers signés (g=8 octets) à partir de valeurs
(gdb) x/8xb &valeurs       # 8 octets en hexadécimal
(gdb) x/s &message         # une chaîne de caractères
```

C'est ici que le **little-endian** (chapitre 2) se voit. Le nombre `42` (`0x2A`) stocké en quadword
s'affiche octet par octet ainsi :

```
(gdb) x/8xb &valeurs
0x...:  0x2a  0x00  0x00  0x00  0x00  0x00  0x00  0x00
        ^ octet de poids faible en premier : c'est normal
```

> **À retenir** — Quand `x/...b` montre tes octets « à l'envers », ce n'est pas un bug : c'est le
> little-endian. Avec `x/...g` (par quadwords), `gdb` réassemble le nombre dans le bon sens.

## Désassembler et lire le code d'un compilateur

`disassemble` montre le code machine d'une fonction. Au-delà du débogage de ton propre code, c'est une
mine d'or pour **apprendre** : compile un petit programme C avec optimisations et regarde ce que le
compilateur produit.

```bash
gcc -O2 -c exemple.c -o exemple.o
objdump -d -M intel exemple.o      # désassemble en syntaxe Intel
```

Tu y reconnaîtras tout ce que tu as appris : `mov`, `add`, `lea`, les conventions d'appel, `xor eax,
eax` pour mettre à zéro, les boucles avec `cmp`/`jcc`. Lire l'assembleur généré par un compilateur est
l'une des compétences les plus utiles que cette formation te donne : tu comprends enfin ce que ton code
haut niveau devient réellement.

> **Astuce** — Le site Compiler Explorer (godbolt.org) montre côte à côte un code C et son assembleur,
> en temps réel. Excellent complément à `objdump` pour expérimenter.

## Résumé

- `gdb` permet d'arrêter le programme et d'observer registres, flags et mémoire — indispensable en
  assembleur.
- Assemble avec `-g -F dwarf` ; dans Docker, ajoute `--cap-add=SYS_PTRACE --security-opt
  seccomp=unconfined`. Active la syntaxe Intel avec `set disassembly-flavor intel`.
- Commandes clés : `break`, `run`, `stepi`/`nexti`, `info registers`, `print $reg`, `continue`.
- `x/NFU adresse` examine la mémoire ; en octets, on voit le **little-endian**.
- `disassemble` / `objdump -d -M intel` révèlent le code machine, y compris celui d'un compilateur C.

## Exercices

### Exercice 1 — Suivre un échange

Reprends le programme d'échange de deux registres (chapitre 3). Sous `gdb`, pose un point d'arrêt sur
`_start`, avance en `stepi`, et vérifie après chaque instruction que `rax` et `rbx` évoluent comme
prévu.

<details>
<summary>Voir le corrigé</summary>

```
(gdb) set disassembly-flavor intel
(gdb) break _start
(gdb) run
(gdb) si            # après mov rax, 10
(gdb) p $rax        # -> 10
(gdb) si            # après mov rbx, 20
(gdb) p $rbx        # -> 20
(gdb) si            # mov rcx, rax  (sauvegarde)
(gdb) si            # mov rax, rbx
(gdb) p $rax        # -> 20
(gdb) si            # mov rbx, rcx
(gdb) p $rbx        # -> 10 : échange confirmé
```

Voir les valeurs changer pas à pas est la meilleure façon d'ancrer le rôle du registre temporaire.

</details>

### Exercice 2 — Lire un tableau en mémoire

Lance `stats` avec quelques nombres, pose un point d'arrêt après la boucle de lecture, et affiche le
contenu du tableau `valeurs` ainsi que `nb`.

<details>
<summary>Voir le corrigé</summary>

```
(gdb) break .scan_fin       # ou une ligne après la boucle
(gdb) run
# (taper les nombres si lecture interactive, ou utiliser echo | en amont)
(gdb) p (long)nb            # nombre de valeurs lues
(gdb) x/5dg &valeurs        # les 5 premières valeurs en décimal
```

Si une valeur est fausse, c'est souvent `parse_int` qui fautive : avance dedans en `si` pour voir où le
calcul dérape.

</details>

## Quiz

**1.** Quelle commande exécute une seule instruction machine sans entrer dans les `call` ?
- A. `stepi`
- B. `nexti`
- C. `continue`

**2.** Comment affiche-t-on `rax` en hexadécimal ?
- A. `p/x $rax`
- B. `x $rax`
- C. `info rax`

**3.** Avec `x/8xb &valeurs`, pourquoi les octets semblent « à l'envers » ?
- A. Un bug de gdb
- B. À cause du little-endian
- C. Parce que la mémoire est corrompue

**4.** Quel outil désassemble un fichier objet en syntaxe Intel ?
- A. `nasm -d`
- B. `objdump -d -M intel`
- C. `gcc -S`

<details>
<summary>Voir les réponses</summary>

1. **B** — `nexti` passe par-dessus les `call` ; `stepi` y entrerait.
2. **A** — Le préfixe de format `/x` affiche en hexadécimal.
3. **B** — x86-64 est little-endian : l'octet de poids faible vient en premier.
4. **B** — `objdump -d -M intel` désassemble en syntaxe Intel.

</details>

## Projet fil rouge

Mets `gdb` au service de `stats` pour traquer le bug le plus probable : une mauvaise conversion dans
`parse_int`. Pose un point d'arrêt sur `parse_int`, lance avec une entrée connue, et vérifie qu'à la
sortie `rax` contient bien la valeur attendue :

```
(gdb) break parse_int
(gdb) run
(gdb) finish            # exécute jusqu'au ret de parse_int
(gdb) p $rax            # la valeur lue : correspond-elle au nombre saisi ?
```

Teste les cas limites : un nombre négatif, un zéro, un grand nombre. Si `parse_int` se trompe sur les
négatifs, observe le drapeau `r8` (`p $r8`) après le test du signe. Tu as maintenant un programme
complet **et** la méthode pour le déboguer : le dernier chapitre consolide tout.

---

[← Chapitre précédent](10-interfacer-avec-le-c.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)
