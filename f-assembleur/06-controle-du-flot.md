# Contrôle du flot : sauts, conditions et boucles

[← Chapitre précédent](05-memoire-et-donnees.md) · [Sommaire](README.md) · [Chapitre suivant →](07-la-pile.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- utiliser les étiquettes et `jmp` pour diriger l'exécution ;
- comparer deux valeurs avec `cmp` et choisir le bon **saut conditionnel** ;
- reconstruire un `if`/`else` et une **boucle** à partir de ces briques ;
- distinguer les sauts **signés** (`jl`, `jg`…) des sauts **non signés** (`jb`, `ja`…).

## Le flot d'exécution et le saut inconditionnel

Par défaut, le CPU exécute les instructions **dans l'ordre**, l'une après l'autre. Pour changer cet
ordre, on saute à une **étiquette** avec `jmp` (*jump*) :

```nasm
    jmp fin             ; saute directement à l'étiquette "fin"
    mov rax, 999        ; cette ligne n'est JAMAIS exécutée
fin:
    mov rax, 0          ; l'exécution reprend ici
```

`jmp` est inconditionnel : il saute toujours. Seul, il sert à faire des boucles infinies ou à sauter
par-dessus du code. L'intérêt vient des sauts **conditionnels**.

## Comparer : `cmp` et les flags

Pour décider, il faut d'abord comparer. L'instruction `cmp a, b` effectue la soustraction `a - b`
**sans stocker le résultat** : elle ne fait que **positionner les flags** (chapitre 4). On lit ensuite
ces flags avec un saut conditionnel.

```nasm
    cmp rax, rbx        ; compare rax et rbx (calcule rax - rbx, jette le résultat)
    je  egaux           ; saute à "egaux" si rax == rbx
```

`je` (*jump if equal*) saute si le résultat de la comparaison était nul, c'est-à-dire si `ZF = 1`,
donc si les deux valeurs sont égales. Chaque saut conditionnel lit un ou plusieurs flags.

> **À retenir** — `cmp a, b` ne modifie ni `a` ni `b` : il ne touche que les flags. Le saut qui suit
> *interprète* la comparaison. On lit donc toujours `cmp` + saut comme un couple.

## La table des sauts conditionnels

L'aspect déroutant : il existe **deux familles** de sauts pour « plus grand / plus petit », selon que
les nombres sont **signés** ou **non signés** (rappel du chapitre 2 : les mêmes octets s'interprètent
différemment).

| Condition (après `cmp a, b`) | Signé | Non signé |
| --- | --- | --- |
| a == b | `je` | `je` |
| a != b | `jne` | `jne` |
| a < b | `jl` | `jb` |
| a <= b | `jle` | `jbe` |
| a > b | `jg` | `ja` |
| a >= b | `jge` | `jae` |

Moyen mnémotechnique : pour les **signés**, on parle de *less* / *greater* (`jl`, `jg`) ; pour les
**non signés**, de *below* / *above* (`jb`, `ja`). Il existe aussi `jz`/`jnz` (saut si zéro / non
zéro), synonymes de `je`/`jne`.

> **Attention** — Utiliser un saut signé sur des données non signées (ou l'inverse) est un bug
> classique et silencieux. `stats` manipule des entiers **signés** : on utilise `jl`, `jg`, `jle`,
> `jge`.

## Reconstruire un `if`/`else`

Voici la structure haut niveau à traduire :

```c
if (rax > 10)
    rbx = 1;
else
    rbx = 2;
```

En assembleur, on inverse souvent la condition pour « sauter par-dessus » le bloc qui ne s'applique
pas :

```nasm
    cmp rax, 10
    jle sinon           ; si rax <= 10, va au bloc "else"
    ; --- bloc "if" (rax > 10) ---
    mov rbx, 1
    jmp fin_si          ; ne pas enchaîner sur le "else"
sinon:
    ; --- bloc "else" ---
    mov rbx, 2
fin_si:
    ; suite du programme
```

Le schéma se retient : on **teste l'inverse de la condition** pour sauter au bloc alternatif, et on
ajoute un `jmp` à la fin du bloc « if » pour ne pas exécuter le « else » par mégarde. Oublier ce `jmp`
est l'erreur la plus fréquente.

## Reconstruire une boucle

Une boucle `while` ou `for` se compose de trois ingrédients : une condition de sortie, le corps, et un
retour en arrière. Traduisons une somme de 1 à 5 :

```c
total = 0;
i = 1;
while (i <= 5) {
    total += i;
    i++;
}
```

En assembleur :

```nasm
    xor rax, rax        ; total = 0
    mov rcx, 1          ; i = 1
boucle:
    cmp rcx, 5
    jg  fin_boucle      ; si i > 5, on sort
    add rax, rcx        ; total += i
    inc rcx             ; i++
    jmp boucle          ; on remonte tester la condition
fin_boucle:
    ; rax = 15
```

La structure « test en haut, `jmp` en bas » correspond à un `while`. C'est le squelette de boucle le
plus courant ; garde-le en tête.

> **Astuce** — Pour les boucles qui comptent à rebours, x86-64 a l'instruction `loop` : elle
> décrémente `rcx` et saute tant qu'il n'est pas nul. Pratique, mais le schéma `cmp` + saut est plus
> général et plus lisible ; on s'y tient.

## `test` : vérifier sans soustraire

Quand tu veux seulement savoir si une valeur est nulle (ou tester des bits), `test op, op` fait un
`and` sans stocker le résultat, et positionne `ZF`. L'idiome courant :

```nasm
    test rax, rax       ; positionne ZF si rax == 0
    jz   est_zero       ; saute si rax vaut 0
```

`test rax, rax` suivi de `jz`/`jnz` est la façon idiomatique de tester « est-ce zéro ? », plus
économique que `cmp rax, 0`.

## Résumé

- L'exécution est séquentielle ; `jmp` saute inconditionnellement à une étiquette.
- `cmp a, b` calcule `a - b` sans stocker : il ne fait que positionner les **flags**.
- Les sauts conditionnels lisent les flags : `je`/`jne` (égalité), `jl/jle/jg/jge` (**signés**),
  `jb/jbe/ja/jae` (**non signés**).
- Un `if`/`else` se code en testant la condition inverse pour sauter au bloc alternatif, avec un `jmp`
  pour ne pas enchaîner.
- Une boucle = condition de sortie en haut + corps + `jmp` de retour en bas.
- `test reg, reg` + `jz`/`jnz` est l'idiome pour tester si une valeur est nulle.

## Exercices

### Exercice 1 — Valeur absolue

Écris le code qui remplace `rax` par sa valeur absolue (si `rax` est négatif, le rendre positif).

<details>
<summary>Voir le corrigé</summary>

```nasm
    cmp rax, 0
    jge positif         ; si rax >= 0, rien à faire
    neg rax             ; sinon, opposé
positif:
    ; rax contient maintenant sa valeur absolue
```

On teste le signe ; `jge` (saut signé) est correct car on raisonne sur des entiers signés. `neg`
calcule l'opposé en complément à deux.

</details>

### Exercice 2 — Compter à rebours

Écris une boucle qui part de 10 et décrémente jusqu'à 0, en additionnant chaque valeur dans `rax` (tu
dois obtenir 10+9+…+1+0 = 55).

<details>
<summary>Voir le corrigé</summary>

```nasm
    xor rax, rax        ; total = 0
    mov rcx, 10         ; i = 10
boucle:
    add rax, rcx        ; total += i
    dec rcx             ; i--
    cmp rcx, 0
    jge boucle          ; tant que i >= 0, on continue
    ; rax = 55
```

On utilise `jge` pour inclure i = 0 dans la somme. Avec `jg`, on s'arrêterait à i = 1 et on obtiendrait
54.

</details>

### Exercice 3 — Maximum de deux nombres

`rax` et `rbx` contiennent deux entiers signés. Mets le plus grand des deux dans `rcx`.

<details>
<summary>Voir le corrigé</summary>

```nasm
    mov rcx, rax        ; suppose rax le plus grand
    cmp rbx, rcx
    jle fin             ; si rbx <= rcx, rcx est déjà le max
    mov rcx, rbx        ; sinon rbx est plus grand
fin:
    ; rcx = max(rax, rbx)
```

On part d'une hypothèse (le max est `rax`) et on la corrige si `rbx` est plus grand. C'est exactement
le schéma qu'on va appliquer au tableau de `stats`.

</details>

## Quiz

**1.** Que modifie `cmp rax, rbx` ?
- A. `rax`
- B. Les flags uniquement
- C. `rbx`

**2.** Pour comparer deux entiers **signés** « strictement supérieur », quel saut ?
- A. `ja`
- B. `jg`
- C. `jne`

**3.** Dans un `if/else`, pourquoi ajoute-t-on un `jmp` à la fin du bloc « if » ?
- A. Pour revenir au début
- B. Pour ne pas exécuter le bloc « else » à la suite
- C. Pour positionner les flags

**4.** Quelle est la façon idiomatique de tester si `rax` vaut zéro ?
- A. `test rax, rax` puis `jz`
- B. `neg rax`
- C. `mov rax, 0`

<details>
<summary>Voir les réponses</summary>

1. **B** — `cmp` ne touche que les flags ; les opérandes restent intacts.
2. **B** — `jg` est le saut signé « greater ». `ja` serait pour des non signés.
3. **B** — Sans ce `jmp`, l'exécution enchaînerait sur le bloc « else ».
4. **A** — `test rax, rax` + `jz` est l'idiome standard.

</details>

## Projet fil rouge

Il est temps de calculer le **minimum** et le **maximum** de `stats` en parcourant le tableau
`valeurs`. Ajoute une boucle qui lit `nb` valeurs et met à jour deux accumulateurs :

```nasm
section .bss
    minv resq 1
    maxv resq 1

section .text
    ; ... après avoir rempli "valeurs" et "nb" ...
    mov rax, [valeurs]          ; initialise min et max avec le 1er élément
    mov [minv], rax
    mov [maxv], rax

    mov rcx, 1                  ; on commence à l'indice 1
parcours:
    cmp rcx, [nb]
    jge fin_parcours           ; sorti quand rcx >= nb
    mov rax, [valeurs + rcx*8] ; valeur courante

    cmp rax, [minv]
    jge pas_min                ; si valeur >= min, pas un nouveau min
    mov [minv], rax
pas_min:
    cmp rax, [maxv]
    jle pas_max                ; si valeur <= max, pas un nouveau max
    mov [maxv], rax
pas_max:
    inc rcx
    jmp parcours
fin_parcours:
    ; [minv] et [maxv] contiennent le min et le max
```

`stats` calcule désormais somme, nombre, min et max. Il manque la moyenne (`somme / nb`, avec `cqo` +
`idiv`) et surtout l'**affichage** des résultats : c'est l'objet des chapitres sur les fonctions et
les syscalls.

---

[← Chapitre précédent](05-memoire-et-donnees.md) · [Sommaire](README.md) · [Chapitre suivant →](07-la-pile.md)
