# La pile

[← Chapitre précédent](06-controle-du-flot.md) · [Sommaire](README.md) · [Chapitre suivant →](08-fonctions-et-convention-dappel.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est la **pile** (*stack*) et comment elle grandit en mémoire ;
- utiliser `push` et `pop` pour sauvegarder et restaurer des valeurs ;
- comprendre le rôle du registre `rsp` ;
- te servir de la pile pour préserver des registres pendant un calcul.

## Qu'est-ce que la pile ?

La **pile** est une zone de mémoire gérée selon le principe **LIFO** (*Last In, First Out*) : le
dernier élément ajouté est le premier retiré, comme une pile d'assiettes. Le CPU y met ce qu'il doit
mettre de côté temporairement : valeurs en transit, adresses de retour des fonctions (chapitre 8),
variables locales.

Un registre dédié, **`rsp`** (*stack pointer*, pointeur de pile), contient en permanence l'adresse du
**sommet** de la pile. Tu ne le modifies presque jamais à la main : `push` et `pop` s'en chargent.

> **Attention** — `rsp` est sacré. Si tu écris dedans n'importe comment, ou si tu fais plus de `pop`
> que de `push`, tu corromps la pile et le programme plante (souvent au `ret` d'une fonction). Manipule
> la pile par paires `push`/`pop` équilibrées.

## La pile grandit vers le bas

Détail contre-intuitif mais fondamental : sur x86-64, **la pile grandit vers les adresses
décroissantes**. Empiler une valeur **diminue** `rsp` de 8 (un quadword), désempiler l'**augmente** de
8.

```
adresses hautes
   |  ...            |
   |  ancienne donnée|
   |-----------------|  <- rsp avant push
   |  nouvelle donnée|  <- rsp après push (rsp a DIMINUÉ de 8)
   |  (libre)        |
   v
adresses basses
```

C'est purement une convention matérielle. Retiens juste : **push fait descendre `rsp`, pop le fait
remonter**.

## `push` et `pop`

`push source` empile une valeur de 8 octets : il décrémente `rsp` de 8, puis écrit la valeur au sommet.
`pop destination` fait l'inverse : il lit la valeur au sommet, puis incrémente `rsp` de 8.

```nasm
    push rax            ; empile rax : rsp -= 8, [rsp] = rax
    push rbx            ; empile rbx par-dessus
    ; ... ici, le sommet contient rbx, juste en dessous rax ...
    pop rbx             ; dépile dans rbx (LIFO : on récupère le dernier empilé)
    pop rax             ; dépile dans rax
```

Note l'ordre **inversé** des `pop` par rapport aux `push` : comme c'est du LIFO, on dépile dans
l'ordre miroir pour que chaque registre retrouve sa valeur.

> **À retenir** — `push` puis `pop` dans l'ordre inverse = sauvegarder puis restaurer fidèlement. Si
> tu dépiles dans le même ordre que tu as empilé, tu intervertis les valeurs.

## À quoi ça sert : préserver une valeur

Le cas d'usage le plus courant pour débuter : tu as besoin d'un registre déjà occupé par une valeur
que tu veux récupérer plus tard. Tu l'empiles, tu réutilises le registre, puis tu le restaures.

```nasm
    mov rax, 1234       ; une valeur précieuse dans rax

    push rax            ; on la met à l'abri sur la pile
    mov rax, 60         ; rax réutilisé pour autre chose (ici exit)
    ; ... travail qui écrase rax ...
    pop rax             ; rax retrouve 1234
```

C'est exactement ce qui se passe à chaque appel de fonction : on protège les registres qu'on ne veut
pas perdre. Le chapitre 8 formalise *qui* est responsable de sauvegarder *quoi*.

## Manipuler `rsp` directement (avec précaution)

Tu peux réserver d'un coup de la place pour plusieurs variables locales en soustrayant à `rsp`, puis
la rendre en ajoutant la même quantité :

```nasm
    sub rsp, 16         ; réserve 16 octets de place locale sur la pile
    mov qword [rsp], 7      ; première variable locale
    mov qword [rsp+8], 9    ; deuxième variable locale
    ; ... usage ...
    add rsp, 16         ; rend la place (équilibre obligatoire)
```

La règle d'or : **tout ce que tu retires à `rsp`, tu dois le lui rendre** avant de quitter la zone, ou
la pile reste déséquilibrée. On reverra cette technique pour les fonctions.

> **Astuce** — Pour simplement jeter une valeur du sommet sans la garder, `add rsp, 8` est plus rapide
> qu'un `pop` vers un registre inutile.

## Résumé

- La **pile** est une mémoire LIFO pour stocker des valeurs temporaires ; `rsp` pointe son **sommet**.
- Sur x86-64, la pile **grandit vers le bas** : `push` diminue `rsp`, `pop` l'augmente (de 8).
- `push reg` sauvegarde, `pop reg` restaure ; on **dépile dans l'ordre inverse** des empilements.
- Usage typique : préserver une valeur de registre pendant qu'on réutilise ce registre.
- Toute place prise à `rsp` (`sub rsp, n`) doit être rendue (`add rsp, n`) : la pile doit rester
  **équilibrée**.

## Exercices

### Exercice 1 — Échange par la pile

Échange le contenu de `rax` et `rbx` en n'utilisant que `push` et `pop` (sans registre temporaire ni
`xchg`).

<details>
<summary>Voir le corrigé</summary>

```nasm
    push rax            ; sommet : rax
    push rbx            ; sommet : rbx (au-dessus de rax)
    pop rax             ; rax <- rbx
    pop rbx             ; rbx <- rax (l'ancien, dépilé en second)
```

L'astuce tient au LIFO : en dépilant dans le **même** ordre que les push (et non l'ordre inverse), on
récupère les valeurs croisées. C'est le seul cas où l'ordre non miroir est volontaire.

</details>

### Exercice 2 — Protéger un registre

Tu dois calculer `60` dans `rax` (pour un futur `exit`) tout en conservant une valeur importante déjà
présente dans `rax`. Montre comment la pile règle le problème, puis restaure la valeur.

<details>
<summary>Voir le corrigé</summary>

```nasm
    ; rax contient une valeur à garder
    push rax            ; sauvegarde
    mov rax, 60         ; rax sert à autre chose
    ; ... usage de rax = 60 ...
    pop rax             ; restauration de la valeur d'origine
```

C'est le réflexe à acquérir : avant d'écraser un registre dont tu auras besoin, empile-le.

</details>

## Quiz

**1.** Que fait `push rax` à `rsp` ?
- A. L'augmente de 8
- B. Le diminue de 8
- C. Le laisse inchangé

**2.** Dans quel ordre dépile-t-on pour restaurer fidèlement après `push rax` / `push rbx` ?
- A. `pop rax` puis `pop rbx`
- B. `pop rbx` puis `pop rax`
- C. L'ordre n'a pas d'importance

**3.** Que désigne `rsp` ?
- A. Le sommet de la pile
- B. Le résultat du dernier calcul
- C. L'adresse du code

**4.** Après `sub rsp, 16`, que faut-il faire avant de quitter la zone ?
- A. `add rsp, 16`
- B. `pop rsp`
- C. Rien

<details>
<summary>Voir les réponses</summary>

1. **B** — La pile grandit vers le bas : empiler diminue `rsp`.
2. **B** — LIFO : on dépile dans l'ordre inverse des empilements.
3. **A** — `rsp` pointe en permanence le sommet de la pile.
4. **A** — Il faut rendre la place réservée pour garder la pile équilibrée.

</details>

## Projet fil rouge

La boucle min/max de `stats` (chapitre 6) utilise `rcx` comme indice. Bientôt, elle appellera des
fonctions qui risquent d'écraser `rcx`. Prends dès maintenant le réflexe de **protéger l'indice** par
la pile autour d'un traitement qui pourrait l'abîmer :

```nasm
parcours:
    ; ... corps de boucle ...
    push rcx            ; protège l'indice avant un éventuel traitement
    ; (plus tard : appel à print_int, qui peut modifier rcx)
    pop rcx             ; restaure l'indice
    inc rcx
    jmp parcours
```

Ce réflexe deviendra indispensable au chapitre suivant, quand `stats` extraira de vraies fonctions
`print_int` et `parse_int` : la pile est précisément ce qui rend les appels de fonctions possibles.

---

[← Chapitre précédent](06-controle-du-flot.md) · [Sommaire](README.md) · [Chapitre suivant →](08-fonctions-et-convention-dappel.md)
