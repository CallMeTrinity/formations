# Représentation des données : binaire, hexadécimal, entiers

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-registres-et-instructions.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- compter en **binaire** et en **hexadécimal**, et convertir d'une base à l'autre ;
- expliquer ce qu'est un *bit*, un *octet*, et combien de valeurs ils contiennent ;
- comprendre comment la machine représente les entiers **non signés** et **signés** (complément à
  deux) ;
- savoir ce qu'est l'**endianness** et pourquoi un octet peut « apparaître à l'envers » en mémoire.

## Pourquoi ce chapitre avant le code

En assembleur, il n'y a pas de type `int` ni de `string` : il n'y a que des **octets**. Toi seul sais
si un octet représente un nombre, une lettre ou autre chose. Avant de manipuler ces octets, il faut
savoir les *lire*. C'est le seul chapitre un peu théorique ; il rend tous les suivants évidents.

## Le bit et l'octet

Un **bit** est la plus petite information possible : `0` ou `1`. Un seul bit ne dit pas grand-chose,
alors on les regroupe.

Un **octet** (*byte* en anglais) est un groupe de **8 bits**. Avec 8 bits, tu peux former 2⁸ = **256**
combinaisons différentes, soit les valeurs de **0 à 255**.

```
1 bit    : 2 valeurs    (0, 1)
8 bits   : 256 valeurs  (0 à 255)        = 1 octet
16 bits  : 65 536 valeurs                = 2 octets
32 bits  : ~4,3 milliards                = 4 octets
64 bits  : ~1,8 × 10^19                  = 8 octets  <- taille d'un registre x86-64
```

> **À retenir** — Tout, en mémoire, est une suite d'octets. Le *sens* d'un octet (nombre ? lettre ?)
> dépend uniquement de ce que ton programme en fait.

## Le binaire

Le binaire est la numération en **base 2** : chaque position vaut une puissance de 2, au lieu d'une
puissance de 10 comme en décimal.

En décimal, `253` se lit : 2×100 + 5×10 + 3×1.

En binaire, le nombre `11111101` se lit de la même façon, mais avec des puissances de 2 :

```
  1     1     1     1     1     1     0     1
128    64    32    16     8     4     2     1     <- valeur de chaque position
128  + 64  + 32  + 16  + 8   + 4   + 0   + 1   = 253
```

Pour convertir un nombre décimal en binaire, tu retires la plus grande puissance de 2 possible, puis
la suivante, etc. Pour `200` :

```
200 - 128 = 72   -> bit 128 = 1
 72 - 64  = 8    -> bit 64  = 1
  8 - 8   = 0    -> bit 8   = 1, le reste à 0
200 = 11001000
```

En NASM, on préfixe un nombre binaire par `0b` : `0b11001000`.

## L'hexadécimal

Écrire des octets en binaire est vite illisible (`11001000`). On utilise donc l'**hexadécimal** (base
16), bien plus compact. Les 16 chiffres vont de 0 à 9 puis A à F :

| Déc | Hex | Binaire |
| --- | --- | --- |
| 0 | 0 | 0000 |
| 1 | 1 | 0001 |
| ... | ... | ... |
| 9 | 9 | 1001 |
| 10 | A | 1010 |
| 11 | B | 1011 |
| 12 | C | 1100 |
| 13 | D | 1101 |
| 14 | E | 1110 |
| 15 | F | 1111 |

L'astuce qui rend l'hexadécimal irremplaçable : **un chiffre hexadécimal = exactement 4 bits**. Donc
**un octet = exactement 2 chiffres hexadécimaux**. La conversion binaire ↔ hexa se fait par paquets de
4 bits, sans calcul :

```
11001000   (1 octet en binaire)
1100 1000   -> on découpe en deux paquets de 4 bits
  C    8    -> chaque paquet devient 1 chiffre hexa
= 0xC8       (et 0xC8 = 200 en décimal)
```

En NASM, on préfixe un nombre hexadécimal par `0x` : `0xC8`. Tu verras l'hexadécimal partout : dans
les adresses mémoire, dans `gdb`, dans les codes d'octets. Prends l'habitude de raisonner par paires
de chiffres = octets.

> **Astuce** — Mémorise les bornes : `0xFF` = 255 (un octet plein), `0xFFFF` = 65 535 (deux octets),
> `0xFFFFFFFF` ≈ 4,3 milliards (quatre octets). Ça te donne tout de suite l'ordre de grandeur.

## Les entiers non signés

Un entier **non signé** (*unsigned*) ne représente que des valeurs positives ou nulles. Tous les bits
servent à coder la grandeur. Sur 8 bits : de 0 à 255. Sur 64 bits : de 0 à environ 1,8 × 10¹⁹.

Simple, mais incomplet : comment représenter `-5` ?

## Les entiers signés et le complément à deux

Pour coder les nombres négatifs, la machine utilise le **complément à deux** (*two's complement*).
L'idée : on sacrifie le bit de poids le plus fort (celui le plus à gauche) pour indiquer le signe.

Sur 8 bits, au lieu d'aller de 0 à 255, on va de **-128 à +127** :

- si le bit de gauche vaut `0`, le nombre est positif, lu normalement ;
- si le bit de gauche vaut `1`, le nombre est négatif.

La recette pour trouver le codage de `-5` sur 8 bits :

```
1. Écris +5 en binaire :        0000 0101
2. Inverse tous les bits :      1111 1010   (c'est le "complément à un")
3. Ajoute 1 :                   1111 1011   = -5
```

Vérification : `1111 1011` lu comme non signé vaut 251. Et en effet, 256 - 5 = 251. C'est toute la
beauté du complément à deux : `-5` est codé comme `256 - 5`.

Pourquoi ce système, plutôt qu'un simple « bit de signe » ? Parce que l'addition fonctionne **sans cas
particulier**. Calcule `5 + (-5)` en binaire sur 8 bits :

```
  0000 0101   (+5)
+ 1111 1011   (-5)
-----------
1 0000 0000   le 9e bit déborde et est jeté -> il reste 0000 0000 = 0
```

Le CPU additionne signés et non signés avec **la même instruction** `add`. C'est toi qui décides
ensuite comment *interpréter* le résultat. C'est un point fondamental de l'assembleur.

> **À retenir** — Les mêmes octets représentent un nombre non signé **ou** signé. `0xFB` (8 bits) vaut
> 251 si tu le lis non signé, et -5 si tu le lis signé. L'octet ne « sait » pas ; c'est ton programme
> qui choisit.

## L'endianness : l'ordre des octets en mémoire

Un nombre de plusieurs octets, par exemple le 32 bits `0x12345678`, occupe 4 cases en mémoire. Dans
quel ordre ? Deux conventions existent :

- **Big-endian** : l'octet de poids fort en premier (`12 34 56 78`). Logique pour un humain.
- **Little-endian** : l'octet de poids faible en premier (`78 56 34 12`). « À l'envers ».

x86-64 est **little-endian**. Le nombre `0x12345678` stocké à partir de l'adresse 1000 ressemble à :

```
adresse :  1000  1001  1002  1003
octet   :   78    56    34    12      <- little-endian : poids faible d'abord
```

Tu n'as pas à t'en soucier pour calculer : les instructions gèrent ça pour toi. Mais quand tu
**inspecteras la mémoire octet par octet** dans `gdb` (chapitre 11), tu verras tes nombres
« à l'envers ». Ce n'est pas un bug : c'est le little-endian.

> **Attention** — Ne confonds pas les *chiffres* et les *octets*. À l'intérieur d'un octet, rien n'est
> inversé : `0x78` reste `0x78`. C'est l'ordre **des octets entre eux** qui change.

## Résumé

- Un **bit** vaut 0 ou 1 ; un **octet** = 8 bits = 256 valeurs (0 à 255).
- Le **binaire** est la base 2 ; chaque position est une puissance de 2.
- L'**hexadécimal** (base 16) est compact : 1 chiffre = 4 bits, 1 octet = 2 chiffres hexa. Préfixes
  NASM : `0b` (binaire), `0x` (hexa).
- Un entier **non signé** code uniquement des positifs ; un entier **signé** utilise le **complément à
  deux**, où le bit de gauche indique le signe.
- Les mêmes octets se lisent comme signés **ou** non signés : c'est ton programme qui décide.
- x86-64 est **little-endian** : en mémoire, l'octet de poids faible vient en premier.

## Exercices

### Exercice 1 — Conversions

Convertis à la main : (a) `42` en binaire et en hexa ; (b) `0b10110001` en décimal ; (c) `0xA5` en
binaire et en décimal.

<details>
<summary>Voir le corrigé</summary>

(a) `42` : 32 + 8 + 2 = `0b00101010`. Par paquets de 4 bits : `0010 1010` = `0x2A`.

(b) `0b10110001` = 128 + 32 + 16 + 1 = `177`.

(c) `0xA5` : `A` = `1010`, `5` = `0101`, donc `0b10100101`. En décimal : 128 + 32 + 4 + 1 = `165`.

La méthode des paquets de 4 bits rend la conversion hexa ↔ binaire mécanique.

</details>

### Exercice 2 — Complément à deux

Sur 8 bits, donne le codage binaire (et la valeur hexa) de `-1` et de `-128`. Que vaut `0xFF` lu comme
entier signé sur 8 bits ?

<details>
<summary>Voir le corrigé</summary>

`-1` : +1 = `0000 0001`, on inverse → `1111 1110`, on ajoute 1 → `1111 1111` = `0xFF`. Donc `-1` est
codé `0xFF`. C'est pour ça que `0xFF` lu **signé** sur 8 bits vaut **-1** (et 255 lu non signé).

`-128` : c'est la borne basse. +128 ne tient pas sur 8 bits signés, mais le codage est `1000 0000` =
`0x80`. Vérification : c'est le seul nombre dont l'opposé n'est pas représentable sur 8 bits signés
(+128 dépasse +127).

</details>

### Exercice 3 — Endianness

Le nombre 32 bits `0x0000002A` est stocké à partir de l'adresse 2000 sur une machine little-endian.
Donne le contenu des octets aux adresses 2000, 2001, 2002, 2003.

<details>
<summary>Voir le corrigé</summary>

`0x0000002A` se découpe en octets `00 00 00 2A` (poids fort à gauche). En little-endian, l'octet de
poids faible vient en premier :

```
2000 -> 2A
2001 -> 00
2002 -> 00
2003 -> 00
```

`0x2A` = 42 : c'est bien la valeur, simplement rangée poids faible d'abord.

</details>

## Quiz

**1.** Combien de valeurs distinctes peut contenir un octet ?
- A. 8
- B. 256
- C. 1024

**2.** Combien de bits représente un chiffre hexadécimal ?
- A. 2
- B. 4
- C. 8

**3.** Sur 8 bits signés, que vaut `0xFF` ?
- A. 255
- B. -1
- C. 127

**4.** Sur une machine little-endian, comment est rangé `0xAABBCCDD` en mémoire (de l'adresse basse à
l'adresse haute) ?
- A. AA BB CC DD
- B. DD CC BB AA
- C. BB AA DD CC

<details>
<summary>Voir les réponses</summary>

1. **B** — 2⁸ = 256, soit 0 à 255.
2. **B** — Un chiffre hexa code exactement 4 bits, donc 2 chiffres = 1 octet.
3. **B** — `0xFF` est le complément à deux de 1 sur 8 bits, soit -1 (et 255 si lu non signé).
4. **B** — Little-endian = octet de poids faible (`DD`) en premier.

</details>

## Projet fil rouge

Pas de code nouveau ici, mais une décision de conception pour `stats` : ses valeurs seront des entiers
**signés sur 64 bits** (la taille d'un registre x86-64), pour accepter des nombres négatifs et de
grandes sommes. Note dès maintenant, en commentaire en tête de `stats.s`, ce choix :

```nasm
; stats.s — les valeurs sont des entiers signés 64 bits (complément à deux)
```

Ce détail guidera les chapitres suivants : taille des cases mémoire, instruction de division signée,
et conversion texte ↔ entier.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-registres-et-instructions.md)
