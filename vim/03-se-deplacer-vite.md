# Se déplacer vite

[← Chapitre précédent](02-modes-et-deplacement.md) · [Sommaire](README.md) · [Chapitre suivant →](04-grammaire-de-l-edition.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- te déplacer par mots avec `w`, `b`, `e` plutôt que caractère par caractère ;
- sauter en début/fin de ligne (`0`, `^`, `$`) et en début/fin de fichier (`gg`, `G`) ;
- bondir vers un caractère précis sur la ligne avec `f`, `t`, `;`, `,` ;
- te déplacer d'un écran à l'autre (`Ctrl-d`, `Ctrl-u`) et atteindre une ligne par son numéro.

Au chapitre 2, tu te déplaçais caractère par caractère. C'est lent. Ici, on apprend à couvrir de
grandes distances en quelques touches. C'est ce qui fait toute la vitesse de Vim.

## Se déplacer par mots

Plutôt que d'avancer lettre par lettre, saute de mot en mot :

| Touche | Effet | Mémo |
| --- | --- | --- |
| `w` | début du **mot suivant** | *word* |
| `b` | début du **mot précédent** | *back* |
| `e` | **fin** du mot courant (ou suivant) | *end* |

Sur cette ligne, curseur sur le `t` de `total` :

```python
total = total + prix
```

- `w` t'amène sur le `=`, puis encore `w` sur le second `total`, puis `w` sur le `+`…
- `e` t'amène sur le `l` final de `total` (la fin du mot).
- `b` te ramène en arrière de mot en mot.

Vim distingue *word* et *WORD* (avec une majuscule pour la commande) :

- `w` `b` `e` : un « mot » s'arrête à la ponctuation. `total.prix` compte comme trois éléments
  (`total`, `.`, `prix`).
- `W` `B` `E` : un « grand mot » est délimité par les espaces seulement. `total.prix` compte comme un
  seul grand mot.

```text
sur "user.name = 42"
w  saute : user → . → name → = → 42
W  saute : user.name → = → 42
```

> **Astuce** — En code, `W` `B` `E` sont souvent plus pratiques pour franchir d'un coup un identifiant
> qui contient des points, des tirets ou des slashes (`config.database.host`, `src/app/main.js`).

## Début et fin de ligne

| Touche | Effet |
| --- | --- |
| `0` | tout début de la ligne (colonne 0, même sur un blanc) |
| `^` | premier caractère **non blanc** de la ligne |
| `$` | fin de la ligne |

La différence entre `0` et `^` compte pour du code indenté : sur une ligne en retrait, `0` va sous
l'indentation, `^` va directement sur le premier vrai caractère.

```python
    total = total + prix
#   ^ ici avec ^          $ ici avec $
# 0 irait tout à gauche, avant les espaces
```

## Atteindre un caractère précis : f et t

Voici un déplacement redoutablement efficace sur une ligne. `f` (*find*) saute **sur** la prochaine
occurrence d'un caractère que tu tapes :

```python
total = total + prix
```

Curseur en début de ligne, tape `f+` : le curseur bondit directement sur le `+`. Tape `fp` : il
bondit sur le `p` de `prix`.

Les variantes :

| Touche | Effet |
| --- | --- |
| `f<c>` | saute **sur** la prochaine occurrence de `<c>` |
| `F<c>` | saute **sur** la précédente occurrence (vers la gauche) |
| `t<c>` | saute **juste avant** la prochaine occurrence (*till*) |
| `T<c>` | saute juste après la précédente occurrence |
| `;` | répète le dernier `f`/`t`/`F`/`T` (même sens) |
| `,` | répète dans le **sens inverse** |

Exemple : `f"` puis `;` te fait sauter de guillemet en guillemet sur la ligne. La nuance `f` / `t` est
subtile mais utile : `t)` s'arrête juste avant la parenthèse fermante (pratique pour se placer pile à
l'intérieur), `f)` se pose dessus.

> **À retenir** — `f<c>` est un de tes déplacements les plus rentables. Pense en termes de
> « caractère cible » : pour atteindre un point dans une ligne, vise un caractère unique et reconnais­
> sable proche de ta destination.

## Se déplacer dans le fichier

| Touche | Effet |
| --- | --- |
| `gg` | première ligne du fichier |
| `G` | dernière ligne du fichier |
| `42G` ou `:42` | aller à la ligne 42 |
| `Ctrl-d` | descendre d'un demi-écran (*down*) |
| `Ctrl-u` | monter d'un demi-écran (*up*) |
| `H` | en haut de l'écran (*high*) |
| `M` | au milieu de l'écran (*middle*) |
| `L` | en bas de l'écran (*low*) |

Pour les fichiers longs, `Ctrl-d` / `Ctrl-u` te font parcourir le fichier sans perdre le fil (le texte
défile d'un demi-écran, ton œil suit). Et quand un message d'erreur te dit « erreur ligne 87 »,
`87G` t'y emmène instantanément.

> **Astuce** — Active les numéros de ligne pour viser plus facilement : en mode Normal, tape
> `:set number` puis `Entrée`. On rendra ce réglage permanent dans le `.vimrc` au chapitre 8.

## Une carte mentale des déplacements

Range tes nouveaux outils par « portée », du plus fin au plus large :

```text
caractère :  h l            f t (sur la ligne)
mot       :  w b e   /   W B E
ligne     :  0 ^ $
écran     :  H M L   Ctrl-d Ctrl-u
fichier   :  gg G    42G
```

Avant de bouger, demande-toi : « quelle est la plus grande portée qui m'approche de ma cible ? » Tu
choisiras `G` plutôt que trente `j`, ou `f;` plutôt que dix `l`.

## Résumé

- Mots : `w` (suivant), `b` (précédent), `e` (fin). Majuscules `W B E` = délimités par les espaces.
- Ligne : `0` (début brut), `^` (premier non-blanc), `$` (fin).
- Caractère cible : `f<c>` / `t<c>` (et `F` `T` vers la gauche), répétés par `;` et `,`.
- Fichier : `gg` (début), `G` (fin), `42G` (ligne 42), `Ctrl-d`/`Ctrl-u` (demi-écran), `H M L` (écran).
- Réflexe : choisir le déplacement de plus grande portée qui approche de la cible.

## Exercices

### Exercice 1 — Par mots, pas par lettres

Dans `panier.py`, place le curseur en début de la ligne `produits = [("pain", 1.20), ...]` et atteins
le mot `lait` en utilisant uniquement des déplacements par mots (`w`, `W`, `e`…), sans `l` ni flèches.

<details>
<summary>Voir le corrigé</summary>

La démarche : enchaîne des `w` (ou `W`) en comptant les sauts, jusqu'à tomber sur `lait`. Comme la
ligne contient beaucoup de ponctuation, `W` est plus rapide : il franchit `("pain",` d'un bloc.

Exemple : `W W W …` jusqu'à `lait`. Peu importe le compte exact ; l'objectif est de ne pas avancer
lettre par lettre.

</details>

### Exercice 2 — Viser un caractère

Toujours sur la ligne des produits, depuis le début de ligne, place le curseur sur la **première
parenthèse ouvrante** `(` en une seule commande de recherche de caractère.

<details>
<summary>Voir le corrigé</summary>

La démarche : `f` saute sur la prochaine occurrence du caractère tapé.

Tape `f(` : le curseur bondit directement sur la première `(`. Pour aller à la suivante, tape `;`
(répète le dernier `f`).

</details>

### Exercice 3 — Sauter aux extrémités

Dans `panier.py`, va à la **dernière ligne** du fichier en une touche, puis reviens à la **première**
en deux touches, puis saute directement à la **ligne 3**.

<details>
<summary>Voir le corrigé</summary>

La démarche : ce sont les déplacements « fichier ».

1. `G` → dernière ligne.
2. `gg` → première ligne.
3. `3G` (ou `:3` puis `Entrée`) → ligne 3.

Si tu as activé `:set number`, tu vois les numéros à gauche, ce qui aide à viser.

</details>

## Quiz

**1.** Quelle commande place le curseur sur la fin de la ligne courante ?
- A. `0`
- B. `^`
- C. `$`

**2.** Tu veux sauter sur la prochaine virgule de la ligne. Que tapes-tu ?
- A. `f,`
- B. `t,`
- C. `,f`

**3.** Quelle est la différence entre `w` et `W` ?
- A. Aucune, ce sont des synonymes.
- B. `W` ignore la ponctuation : un mot n'est délimité que par les espaces.
- C. `W` recule au lieu d'avancer.

**4.** Comment aller directement à la ligne 87 ?
- A. `87j`
- B. `87G`
- C. `g87`

<details>
<summary>Voir les réponses</summary>

1. **C** — `$` va en fin de ligne. `0` va au tout début, `^` au premier caractère non blanc.
2. **A** — `f,` saute **sur** la prochaine virgule. `t,` s'arrêterait juste avant.
3. **B** — `W` (et `B`, `E`) traite tout ce qui n'est pas une espace comme un seul mot.
4. **B** — `87G` va à la ligne 87. `87j` descendrait de 87 lignes depuis la position courante.

</details>

## Projet fil rouge

Jalon « déplacement efficace ». Entraîne-toi sur `panier.py` à atteindre des cibles précises avec le
moins de touches possible :

1. Depuis le bas du fichier, remonte à la ligne `produits = ...` avec `gg`/`G`/`<n>G`.
2. Sur cette ligne, saute sur le prix `2.50` avec `f` puis `;`.
3. Active `:set number` pour la session et observe les numéros de ligne.

Ajoute à `cheatsheet.md` :

```markdown
## Déplacements rapides
- `w b e` (mots) · `W B E` (grands mots, ignorent la ponctuation)
- `0` début · `^` premier non-blanc · `$` fin de ligne
- `f<c>` / `t<c>` viser un caractère · `;` répéter · `,` répéter à l'envers
- `gg` début fichier · `G` fin · `42G` ligne 42 · `Ctrl-d`/`Ctrl-u` demi-écran
- `:set number` afficher les numéros de ligne
```

---

[← Chapitre précédent](02-modes-et-deplacement.md) · [Sommaire](README.md) · [Chapitre suivant →](04-grammaire-de-l-edition.md)
