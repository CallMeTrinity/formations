# Arithmétique, logique et flags

[← Chapitre précédent](03-registres-et-instructions.md) · [Sommaire](README.md) · [Chapitre suivant →](05-memoire-et-donnees.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- additionner, soustraire, multiplier et diviser des entiers en assembleur ;
- gérer la particularité de la **division** (paire `rdx:rax`, extension de signe) ;
- utiliser les opérations **logiques bit à bit** (`and`, `or`, `xor`, `not`) et les **décalages** ;
- comprendre les **flags** (drapeaux) que chaque opération met à jour.

## L'addition et la soustraction

`add` ajoute la source à la destination ; `sub` la soustrait. Le résultat remplace la destination :

```nasm
mov rax, 10
add rax, 5          ; rax = 10 + 5 = 15  (rax <- rax + 5)
sub rax, 3          ; rax = 15 - 3 = 12
add rax, rbx        ; rax = rax + rbx  (additionne deux registres)
```

Comme pour `mov`, tu peux additionner une valeur immédiate, un registre, ou un contenu mémoire — mais
jamais deux accès mémoire à la fois.

Pour les variations de 1, deux instructions dédiées, plus courtes :

```nasm
inc rax             ; rax = rax + 1  (increment)
dec rax             ; rax = rax - 1  (decrement)
```

Et pour changer le signe d'un nombre (son opposé en complément à deux) :

```nasm
neg rax             ; rax = -rax
```

## La multiplication

Il existe deux familles : `mul` (non signée) et `imul` (signée, *integer multiply*). Comme `stats`
manipule des entiers signés, on utilise `imul`. Sa forme moderne à deux opérandes est la plus simple :

```nasm
mov rax, 6
imul rax, 7         ; rax = 6 * 7 = 42
imul rax, rbx       ; rax = rax * rbx
imul rcx, rdx, 4    ; forme à trois opérandes : rcx = rdx * 4
```

> **Attention** — `mul` (non signée) a une forme à un seul opérande qui écrit le résultat sur 128 bits
> dans la paire `rdx:rax`. Pour débuter, préfère la forme `imul dest, source` : elle se comporte comme
> une multiplication classique et suffit dans l'immense majorité des cas.

## La division : le cas particulier à connaître

La division est l'opération la plus piégeuse de x86-64. `idiv source` (division signée) divise un
**dividende de 128 bits** réparti dans la paire `rdx:rax` par la `source`, et range :

- le **quotient** dans `rax` ;
- le **reste** (modulo) dans `rdx`.

Le piège : avant la division, tu dois préparer `rdx`. Si tu ne le fais pas, un reliquat dans `rdx`
fausse tout, voire fait planter le programme. Pour une division signée, l'instruction `cqo` étend le
signe de `rax` dans `rdx` (elle remplit `rdx` de 0 si `rax` est positif, de 1 s'il est négatif) :

```nasm
mov rax, 17         ; dividende
mov rbx, 5          ; diviseur
cqo                 ; étend le signe de rax dans rdx (prépare rdx:rax)
idiv rbx            ; rax = 17 / 5 = 3  (quotient) ; rdx = 17 % 5 = 2 (reste)
```

> **À retenir** — Recette de la division signée : mettre le dividende dans `rax`, faire `cqo`, puis
> `idiv diviseur`. Quotient dans `rax`, reste dans `rdx`. Oublier `cqo` est l'erreur n°1 des débutants.

> **Attention** — Diviser par zéro déclenche une exception qui **arrête brutalement** le programme
> (`Floating point exception`, malgré le nom trompeur). On apprendra à tester le diviseur avant la
> division au chapitre 6.

## Les opérations logiques bit à bit

Ces opérations agissent **bit par bit**, indépendamment, sur chaque position :

| Instruction | Effet sur chaque bit |
| --- | --- |
| `and` | 1 si **les deux** bits valent 1 |
| `or` | 1 si **au moins un** bit vaut 1 |
| `xor` | 1 si les bits sont **différents** |
| `not` | inverse chaque bit |

```nasm
mov rax, 0b1100
and rax, 0b1010     ; rax = 0b1000  (seul le bit commun reste)
mov rax, 0b1100
or  rax, 0b1010     ; rax = 0b1110
mov rax, 0b1100
xor rax, 0b1010     ; rax = 0b0110  (bits différents)
```

Au-delà des calculs, ces instructions servent à **manipuler des bits précis** (les *masques*) :

```nasm
and rax, 0xFF       ; ne garde que l'octet de poids faible (met le reste à 0)
or  rax, 1          ; force le bit 0 à 1
```

Une idiome incontournable : **`xor` d'un registre avec lui-même le met à zéro**, plus efficacement que
`mov reg, 0` :

```nasm
xor rax, rax        ; rax = 0  (façon idiomatique de remettre à zéro)
```

> **Astuce** — Quand tu vois `xor rax, rax` dans du code, lis « `rax = 0` ». C'est partout, y compris
> dans le code généré par les compilateurs.

## Les décalages

Décaler les bits vers la gauche ou la droite revient à multiplier ou diviser par des puissances de 2,
très rapidement :

```nasm
mov rax, 5          ; 0b00000101
shl rax, 1          ; décalage à gauche de 1 : 0b00001010 = 10  (x2)
shl rax, 2          ; décalage à gauche de 2 : x4
shr rax, 1          ; décalage logique à droite : divise par 2 (non signé)
sar rax, 1          ; décalage arithmétique à droite : divise par 2 (signé, garde le signe)
```

- `shl` (*shift left*) : multiplie par 2 à chaque cran.
- `shr` (*shift right logical*) : divise par 2, en injectant des 0 à gauche (pour les non signés).
- `sar` (*shift right arithmetic*) : divise par 2 en **préservant le bit de signe** (pour les signés).

> **Attention** — Pour un nombre **signé** négatif, utilise `sar`, pas `shr` : `shr` injecte un 0 en
> tête et transforme un négatif en grand positif.

## Les flags : ce que chaque opération laisse derrière elle

Le CPU possède un registre spécial, **`RFLAGS`**, dont certains bits (les *flags*, ou drapeaux)
résument le résultat de la dernière opération arithmétique ou logique. Tu ne les écris pas
directement : les instructions les mettent à jour automatiquement. Les quatre essentiels :

| Flag | Nom | Vaut 1 quand… |
| --- | --- | --- |
| **ZF** | Zero Flag | le résultat est **zéro** |
| **SF** | Sign Flag | le résultat est **négatif** (bit de poids fort à 1) |
| **CF** | Carry Flag | il y a eu une **retenue** (débordement **non signé**) |
| **OF** | Overflow Flag | il y a eu un **débordement signé** |

Exemple : après `sub rax, rax`, le résultat est 0, donc **ZF = 1**.

Ces flags sont la clé du chapitre 6 : c'est en les lisant qu'on construit les `if` et les boucles. Une
instruction de comparaison fera un calcul *uniquement* pour positionner les flags, sans garder le
résultat.

> **À retenir** — Les opérations arithmétiques et logiques laissent une trace dans les **flags**. Les
> sauts conditionnels (chapitre 6) prennent leurs décisions en lisant ces flags.

## Résumé

- `add`, `sub`, `inc`, `dec`, `neg` couvrent l'arithmétique de base.
- Multiplication signée : `imul dest, source`. Division signée : dividende dans `rax`, `cqo`, puis
  `idiv diviseur` → quotient dans `rax`, reste dans `rdx`.
- Diviser par zéro **plante** le programme : il faudra tester le diviseur.
- Opérations bit à bit : `and`, `or`, `xor`, `not` ; `xor reg, reg` met à zéro. Décalages : `shl`,
  `shr` (non signé), `sar` (signé).
- Les **flags** (`ZF`, `SF`, `CF`, `OF`) résument la dernière opération et servent aux décisions du
  chapitre suivant.

## Exercices

### Exercice 1 — Une expression composée

Traduis en assembleur le calcul `r = (a + b) * 2 - 3` avec `a = 4` et `b = 9` (valeurs immédiates),
résultat final dans `rax`.

<details>
<summary>Voir le corrigé</summary>

```nasm
mov rax, 4          ; a
add rax, 9          ; a + b = 13
imul rax, 2         ; (a + b) * 2 = 26
sub rax, 3          ; - 3 = 23
```

On garde tout dans `rax` en enchaînant les opérations dans l'ordre des priorités.

</details>

### Exercice 2 — Division et reste

Calcule le quotient et le reste de `47 / 6`. Mets le quotient dans `rax` et le reste dans `rbx` à la
fin.

<details>
<summary>Voir le corrigé</summary>

```nasm
mov rax, 47         ; dividende
mov rcx, 6          ; diviseur
cqo                 ; prépare rdx:rax (extension de signe)
idiv rcx            ; rax = 7 (quotient), rdx = 5 (reste)
mov rbx, rdx        ; on déplace le reste dans rbx comme demandé
```

Ne pas oublier `cqo` avant `idiv` : c'est ce qui prépare correctement le dividende 128 bits.

</details>

### Exercice 3 — Masque de bits

Sans utiliser `and`, propose une instruction qui force le bit de poids faible de `rax` à 0 (rendre
`rax` pair). Puis donne la version avec `and`.

<details>
<summary>Voir le corrigé</summary>

Avec `and`, on masque le bit 0 :

```nasm
and rax, -2         ; -2 = ...11111110 : tous les bits à 1 sauf le bit 0
```

`-2` en complément à deux est `...1111 1110`, donc le `and` garde tout sauf le bit de poids faible.
Une autre approche revient à décaler puis re-décaler : `shr rax, 1` puis `shl rax, 1` (on perd le bit
0). Les deux rendent le nombre pair ; la version `and` est plus directe.

</details>

## Quiz

**1.** Après `imul rax, rbx`, où est le résultat (forme à deux opérandes) ?
- A. Dans `rdx`
- B. Dans `rax`
- C. Dans la paire `rdx:rax`

**2.** Quelle instruction faut-il exécuter juste avant `idiv` pour une division signée ?
- A. `xor rdx, rdx`
- B. `cqo`
- C. `neg rax`

**3.** Que fait `xor rax, rax` ?
- A. Met `rax` à 0
- B. Double `rax`
- C. Inverse tous les bits de `rax`

**4.** Quel flag vaut 1 lorsque le résultat d'une opération est zéro ?
- A. SF
- B. CF
- C. ZF

<details>
<summary>Voir les réponses</summary>

1. **B** — La forme `imul dest, source` range le résultat dans la destination.
2. **B** — `cqo` étend le signe de `rax` dans `rdx` pour préparer le dividende 128 bits signé.
3. **A** — Un nombre XOR lui-même donne 0 ; c'est l'idiome de mise à zéro.
4. **C** — ZF (Zero Flag) signale un résultat nul.

</details>

## Projet fil rouge

`stats` doit accumuler une somme. Ajoute le code qui ajoute deux valeurs d'exemple à `somme` et
incrémente `nb`, en réutilisant la mémoire réservée au chapitre 3 :

```nasm
    ; deux valeurs d'exemple (en attendant la vraie saisie au chapitre 9)
    mov rax, [somme]
    add rax, 12         ; première valeur
    mov [somme], rax
    inc qword [nb]

    mov rax, [somme]
    add rax, 30         ; seconde valeur
    mov [somme], rax
    inc qword [nb]
    ; [somme] vaut maintenant 42, [nb] vaut 2
```

La logique d'accumulation est posée. Au chapitre 6, on remplacera ces ajouts manuels par une boucle ;
pour la moyenne (`somme / nb`), tu sais désormais qu'il faudra `cqo` puis `idiv`.

---

[← Chapitre précédent](03-registres-et-instructions.md) · [Sommaire](README.md) · [Chapitre suivant →](05-memoire-et-donnees.md)
