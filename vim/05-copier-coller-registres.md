# Copier, coller, registres

[← Chapitre précédent](04-grammaire-de-l-edition.md) · [Sommaire](README.md) · [Chapitre suivant →](06-rechercher-et-remplacer.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- copier (`y`), couper (`d`) et coller (`p`, `P`) du texte ;
- comprendre que supprimer, c'est aussi « couper » (le texte va dans un registre) ;
- utiliser les **registres nommés** pour garder plusieurs morceaux de texte en parallèle ;
- copier-coller avec le **presse-papier système** pour échanger avec d'autres applications.

## Yank et put : copier et coller

Tu connais déjà l'opérateur `y` (*yank*, copier) du chapitre 4. Le pendant pour coller est `p`
(*put*) :

| Commande | Effet |
| --- | --- |
| `yy` | copie la ligne courante |
| `yw` | copie du curseur jusqu'au mot suivant |
| `yi"` | copie l'intérieur des guillemets |
| `p` | colle **après** le curseur (ou en dessous, pour une ligne) |
| `P` | colle **avant** le curseur (ou au-dessus) |

Le comportement de `p` dépend de ce que tu as copié :

- si tu as copié une **ligne entière** (`yy`), `p` la colle sur une **nouvelle ligne en dessous**, et
  `P` au-dessus ;
- si tu as copié un **bout de ligne** (`yw`, `yi"`), `p` le colle **juste après** le curseur, sur la
  même ligne.

```python
total = total + prix
# Curseur sur cette ligne, tape  yy  (copie la ligne)
# Tape  p  → la ligne est dupliquée juste en dessous :
total = total + prix
total = total + prix
```

Le *count* fonctionne : `3p` colle trois fois.

## Supprimer, c'est couper

Voici un point qui surprend les débutants : dans Vim, **`d` et `x` ne détruisent pas le texte, ils le
coupent**. Ce que tu supprimes est gardé et peut être collé avec `p`.

```python
total = total + prix
# dd  supprime la ligne… mais la garde
# va sur une autre ligne, p  → la ligne supprimée réapparaît ici
```

C'est pour ça qu'on « déplace » du texte en deux temps :

1. `dd` (couper la ligne),
2. se déplacer où tu veux,
3. `p` (coller).

> **À retenir** — Couper = supprimer puis garder. `d`, `c`, `x` mettent tous le texte dans un
> registre. Le « copier-coller » et le « couper-coller » utilisent le même `p` pour coller.

### Le piège du « coller efface ma copie »

Quand tu colles `p` un texte sur une sélection que tu viens de supprimer, le registre par défaut peut
être écrasé. Concrètement : tu copies un mot avec `yiw`, tu vas sur un autre mot, tu fais `viwp` pour
le remplacer… et la deuxième fois, tu colles le mot que tu venais de remplacer, pas l'original. C'est
déroutant. La solution propre, ce sont les **registres nommés**, juste en dessous.

## Les registres nommés

Un *registre* est une petite mémoire qui stocke du texte. Par défaut, `y`, `d`, `x` utilisent le
registre « sans nom ». Mais tu peux en cibler un **nommé**, identifié par une lettre `a` à `z`, en le
préfixant de `"` (guillemet droit) :

| Commande | Effet |
| --- | --- |
| `"ayy` | copie la ligne dans le registre `a` |
| `"ap` | colle le contenu du registre `a` |
| `"byiw` | copie le mot sous le curseur dans le registre `b` |
| `"bp` | colle le registre `b` |

Lis `"ayy` comme : « registre `a`, *yank* la ligne ». Tu disposes ainsi de 26 presse-papiers
indépendants, ce qui te permet de transporter plusieurs morceaux à la fois.

```text
"ayy   → mémorise la ligne dans a
…descends 10 lignes plus bas…
"byy   → mémorise une autre ligne dans b
…
"ap    → colle la première
"bp    → colle la seconde
```

> **Astuce** — Pour voir le contenu de tous les registres, tape `:registers` (ou `:reg`). Très utile
> pour comprendre où est passé ce que tu as copié.

Vim gère aussi des registres spéciaux automatiques, par exemple `"0` qui contient **toujours le
dernier yank** (jamais une suppression). Donc après une copie puis une suppression, `"0p` recolle ta
**copie** d'origine, même si le registre sans nom a été écrasé. C'est la parade au piège vu plus haut.

## Échanger avec les autres applications : le presse-papier système

Par défaut, ce que tu copies dans Vim reste dans Vim. Pour copier-coller avec ton navigateur, ton
IDE, etc., utilise le **registre presse-papier système** :

- `"+` : le presse-papier système (celui de `Ctrl-C` / `Ctrl-V` ailleurs) ;
- `"*` : la sélection primaire (surtout sous Linux/X11) — en pratique, `"+` suffit.

| Commande | Effet |
| --- | --- |
| `"+yy` | copie la ligne vers le presse-papier système |
| `"+yi"` | copie l'intérieur des guillemets vers le système |
| `"+p` | colle depuis le presse-papier système |

```text
"+yy   → la ligne est dans ton presse-papier système : tu peux la coller dans n'importe quelle appli
```

> **Attention** — Ces registres ne marchent que si ton Vim a été compilé avec le support du
> presse-papier. Vérifie avec `vim --version | grep clipboard` : tu dois voir `+clipboard` (avec un
> `+`). Si tu vois `-clipboard`, installe une version complète (chapitre 1 : `brew install vim` sur
> macOS, ou utilise Neovim qui le gère bien). On verra au chapitre 8 comment faire de `"+` le registre
> par défaut pour ne plus à y penser.

## Coller du texte externe sans tout casser

Quand tu colles du code copié ailleurs **en mode Insertion** (avec `Ctrl-V` du terminal, pas le `p` de
Vim), Vim ré-indente parfois chaque ligne, ce qui produit un escalier monstrueux. Si ça t'arrive,
active le mode collage avant de coller :

```text
:set paste     " désactive la ré-indentation automatique
" … tu colles ton texte …
:set nopaste   " réactive le comportement normal
```

Mais si tu colles avec `"+p` depuis le **mode Normal**, ce problème ne se pose pas : préfère cette
méthode.

## Résumé

- `y` copie, `p` colle après (ou dessous), `P` colle avant (ou dessus). `yy` copie la ligne.
- `d`, `c`, `x` **coupent** : le texte supprimé est gardé et collable avec `p`.
- Déplacer du texte = `dd` (couper) → se déplacer → `p` (coller).
- **Registres nommés** : `"a`…`"z` pour garder plusieurs morceaux. Ex. `"ayy` puis `"ap`.
- Registre `"0` = dernier yank (parade au registre écrasé). `:reg` liste tout.
- Presse-papier système : `"+y` pour copier vers l'extérieur, `"+p` pour coller depuis l'extérieur.

## Exercices

### Exercice 1 — Dupliquer une ligne

Dans `panier.py`, duplique la ligne `total = total + prix` (ou son équivalent renommé) pour en avoir
deux copies consécutives, sans la retaper.

<details>
<summary>Voir le corrigé</summary>

La démarche : copier la ligne avec `yy`, puis la coller en dessous avec `p`.

1. Place le curseur sur la ligne.
2. Tape `yy` (copie la ligne).
3. Tape `p` (colle une copie juste en dessous).

Tu as maintenant deux lignes identiques. (`dd` puis `p` la déplacerait au lieu de la dupliquer.)

</details>

### Exercice 2 — Déplacer une ligne

Déplace la ligne `print(...)` pour qu'elle se retrouve **avant** la ligne `total = 0` (ou `montant =
0`), juste pour t'exercer, puis remets-la à sa place avec `u`.

<details>
<summary>Voir le corrigé</summary>

La démarche : couper avec `dd`, se déplacer, coller au bon endroit.

1. Curseur sur la ligne `print(...)`.
2. `dd` (coupe la ligne, le fichier se referme dessus).
3. Monte jusqu'à la ligne `total = 0` avec `k`.
4. `P` (colle **au-dessus**) — ou place-toi sur la ligne d'avant et `p`.

Pour annuler tout ça : `u` autant de fois que nécessaire.

</details>

### Exercice 3 — Deux morceaux en parallèle

Copie le mot `pain` dans le registre `a` et le mot `lait` dans le registre `b`, puis colle l'un puis
l'autre en bas du fichier.

<details>
<summary>Voir le corrigé</summary>

La démarche : on cible un registre nommé avec `"<lettre>` avant l'opérateur.

1. Curseur sur `pain` (dans les guillemets) : `"ayi"` copie `pain` dans `a`.
2. Curseur sur `lait` : `"byi"` copie `lait` dans `b`.
3. Va en fin de fichier (`G`), ouvre une ligne (`o`, `Échap`).
4. `"ap` colle `pain`, puis `"bp` colle `lait`.

Vérifie avec `:reg` que `a` contient `pain` et `b` contient `lait`.

</details>

## Quiz

**1.** Tu fais `dd` puis `p` sur une autre ligne. Que se passe-t-il ?
- A. Rien, `dd` détruit définitivement la ligne.
- B. La ligne supprimée est collée à la nouvelle position (déplacement).
- C. La ligne est dupliquée à sa position d'origine.

**2.** Quelle commande copie une ligne dans le registre nommé `c` ?
- A. `c"yy`
- B. `"cyy`
- C. `yyc`

**3.** Comment copier du texte de Vim vers ton navigateur ?
- A. `yy` suffit toujours.
- B. `"+yy` (registre presse-papier système).
- C. `:copy system`

**4.** Quelle est la différence entre `p` et `P` ?
- A. `p` colle après/en dessous, `P` colle avant/au-dessus.
- B. `P` colle plusieurs fois.
- C. Aucune.

<details>
<summary>Voir les réponses</summary>

1. **B** — `d` coupe (garde le texte) ; `p` le recolle ailleurs : c'est un déplacement.
2. **B** — On préfixe l'opérateur par `"c` : `"cyy`.
3. **B** — `"+` est le presse-papier système (nécessite `+clipboard`).
4. **A** — `p` colle après le curseur (ou en dessous pour une ligne), `P` avant (ou au-dessus).

</details>

## Projet fil rouge

Jalon « réorganiser sans retaper ». Dans `panier.py`, sans rien retaper :

1. Ajoute un nouveau produit `("beurre", 1.80)` à la liste, en **copiant** un tuple existant avec
   `yi(` / `p` puis en changeant les valeurs avec `ci"` et `r`.
2. Copie la ligne `print(...)` vers ton presse-papier système (`"+yy`) et vérifie que tu peux la coller
   dans une autre application (un éditeur de notes, ton IDE…).
3. Enregistre (`:w`).

Ajoute à `cheatsheet.md` :

```markdown
## Copier / coller / registres
- `y` copier · `yy` copier la ligne · `yi"` copier l'intérieur des guillemets
- `p` coller après/dessous · `P` coller avant/dessus
- `d` `x` `c` coupent (texte gardé) → déplacer = `dd` puis `p`
- registres nommés : `"ayy` copier dans a · `"ap` coller a · `:reg` lister
- `"0p` recoller le dernier yank · `"+y` / `"+p` presse-papier système
```

---

[← Chapitre précédent](04-grammaire-de-l-edition.md) · [Sommaire](README.md) · [Chapitre suivant →](06-rechercher-et-remplacer.md)
