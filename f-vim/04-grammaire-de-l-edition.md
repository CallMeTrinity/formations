# La grammaire de l'édition

[← Chapitre précédent](03-se-deplacer-vite.md) · [Sommaire](README.md) · [Chapitre suivant →](05-copier-coller-registres.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- combiner un **opérateur** (`d`, `c`, `y`) avec un **mouvement** pour éditer en une commande ;
- utiliser les **text objects** (`iw`, `i"`, `ip`, `i(`…) pour agir sur « l'intérieur » d'une structure ;
- répéter la dernière modification d'une seule touche avec `.` ;
- sélectionner du texte en mode Visuel.

C'est **le** chapitre qui transforme Vim d'un éditeur bizarre en un éditeur puissant. L'idée est
simple et profonde : au lieu d'apprendre des centaines de raccourcis, tu apprends une petite
**grammaire** qui se combine.

## Opérateur + mouvement

En mode Normal, beaucoup de commandes suivent la forme :

```text
opérateur + mouvement
```

L'**opérateur** dit *quoi faire*, le **mouvement** dit *jusqu'où*. Les trois opérateurs de base :

| Opérateur | Action | Mémo |
| --- | --- | --- |
| `d` | supprimer (*delete*) | coupe le texte |
| `c` | changer (*change*) : supprime **et** passe en Insertion | *change* |
| `y` | copier (*yank*) sans supprimer | on en parle au chapitre 5 |

Tu connais déjà les mouvements du chapitre 3 (`w`, `e`, `$`, `f<c>`…). Combine-les avec un opérateur :

| Commande | Effet |
| --- | --- |
| `dw` | supprime du curseur jusqu'au début du mot suivant |
| `de` | supprime jusqu'à la fin du mot |
| `d$` | supprime jusqu'à la fin de la ligne |
| `d0` | supprime jusqu'au début de la ligne |
| `cw` | change le mot (supprime + Insertion) |
| `df)` | supprime jusqu'à la prochaine `)` incluse |
| `ct"` | change jusqu'avant le prochain `"` |

C'est combinatoire : 3 opérateurs × tous les mouvements = des dizaines de commandes que tu n'as pas à
mémoriser une par une. Tu les **composes**.

```python
# Curseur sur "total", tu veux remplacer le mot par "sous_total" :
total = total + prix
# Tape  cw  → "total" disparaît, tu es en Insertion → tape sous_total → Échap
```

> **À retenir** — Ne mémorise pas des commandes, mémorise la **grammaire**. Si tu sais supprimer
> (`d`) et te déplacer jusqu'à une cible (`f)`, `$`, `e`…), tu sais déjà supprimer jusqu'à cette
> cible : `d` + le mouvement.

### Le doublé : opérateur sur la ligne entière

Doubler l'opérateur agit sur la **ligne entière** :

| Commande | Effet |
| --- | --- |
| `dd` | supprime la ligne entière |
| `cc` | change la ligne entière (vide la ligne et passe en Insertion) |
| `yy` | copie la ligne entière |

Et le *count* fonctionne toujours : `3dd` supprime 3 lignes, `2cw` change 2 mots.

## Les text objects : agir sur une structure

Les mouvements vont *d'un point à un autre*. Les **text objects** désignent une *structure entière*,
où que soit le curseur dedans. Ils s'écrivent en deux lettres :

- `i` + délimiteur = *inner*, **l'intérieur** (sans les délimiteurs) ;
- `a` + délimiteur = *a/around*, **tout l'objet** (délimiteurs inclus).

| Text object | Désigne… |
| --- | --- |
| `iw` / `aw` | un mot (intérieur / avec l'espace autour) |
| `i"` / `a"` | l'intérieur des guillemets / les guillemets inclus |
| `i'` / `a'` | idem avec apostrophes |
| `i(` ou `ib` / `a(` | l'intérieur des parenthèses / parenthèses incluses |
| `i{` ou `iB` / `a{` | l'intérieur des accolades / incluses |
| `i[` / `a[` | l'intérieur des crochets |
| `it` / `at` | l'intérieur d'une balise HTML / la balise entière |
| `ip` / `ap` | un paragraphe |

On les combine avec un opérateur. **Le curseur n'a pas besoin d'être au bord** : il suffit qu'il soit
*à l'intérieur*.

```python
produits = [("pain", 1.20), ("lait", 0.95)]
#                ^ curseur ici, dans "pain"
```

- `ci"` (curseur n'importe où entre les guillemets) → efface `pain` et passe en Insertion. Tape
  `baguette` → on a `"baguette"`.
- `di(` (curseur dans la parenthèse) → vide l'intérieur de `(...)`.
- `daw` → supprime le mot **et** l'espace autour, proprement.

C'est l'outil le plus « magique » de Vim. Lis `ci"` comme une phrase : *change inner quotes*, « change
l'intérieur des guillemets ». `da(` : *delete around parentheses*.

> **Astuce** — `ci(`, `ci"`, `ci{` sont des commandes que tu utiliseras des dizaines de fois par jour
> en codant : changer le contenu d'un appel de fonction, d'une chaîne, d'un bloc. Mémorise-les en
> priorité.

## La touche qui change tout : le point

`.` (le point) **répète la dernière modification**. Pas un déplacement : une *modification* (insertion,
suppression, changement). C'est l'outil de productivité numéro un de Vim.

Exemple. Tu veux supprimer plusieurs mots `prix` dans un fichier :

1. Place-toi sur le premier, tape `cwmontant` puis `Échap` (tu changes `prix` en `montant`).
2. Va sur le `prix` suivant (avec `w`, `f`, recherche…).
3. Tape `.` : Vim rejoue exactement `cwmontant` + sortie. Le mot est changé.
4. Répète : déplacement, `.`, déplacement, `.`…

Tu n'as composé la modification qu'une fois ; ensuite, c'est une touche par occurrence.

> **À retenir** — Le combo gagnant de Vim, c'est **« un déplacement précis » + `.`**. On l'appelle
> parfois le *dot dance* (« la danse du point ») : tu sautes à la cible, tu répètes, tu sautes, tu
> répètes.

## Le mode Visuel : sélectionner d'abord, agir ensuite

Parfois tu préfères **voir** ta sélection avant d'agir. C'est le mode Visuel :

| Touche | Effet |
| --- | --- |
| `v` | sélection caractère par caractère |
| `V` | sélection **ligne par ligne** |
| `Ctrl-v` | sélection **en bloc** (rectangulaire, colonnes) |

Tu entres en Visuel, tu **étends la sélection avec les mouvements** (`w`, `$`, `j`, text objects…),
puis tu appliques un opérateur **sans mouvement** car la sélection en tient lieu :

```text
v   puis  e   →  sélectionne jusqu'à la fin du mot
puis  d       →  supprime la sélection
```

- `Vd` : sélectionne la ligne entière puis la supprime (équivaut à `dd`).
- `vi"d` : sélectionne l'intérieur des guillemets, puis supprime (équivaut à `di"`).
- `V` puis `j j` puis `d` : sélectionne 3 lignes et les supprime.

Le mode Visuel et la grammaire opérateur+mouvement font la même chose de deux façons. Quand tu sais
exactement quoi faire, `di"` est plus direct. Quand tu veux ajuster la sélection à l'œil, le Visuel
est plus rassurant. Les deux sont utiles.

> **Attention** — En mode Visuel, `Échap` annule la sélection sans rien modifier. Pas de panique si tu
> sélectionnes de travers : `Échap`, et tu recommences.

## Résumé

- L'édition suit une **grammaire** : `opérateur` (`d` supprimer, `c` changer, `y` copier) +
  `mouvement` (`w`, `$`, `f)`…).
- Doubler l'opérateur agit sur la ligne : `dd`, `cc`, `yy`. Le *count* marche : `3dd`.
- Les **text objects** désignent une structure : `i` = intérieur, `a` = tout l'objet. Ex. `ci"`,
  `di(`, `daw`. Le curseur juste besoin d'être *dedans*.
- `.` **répète la dernière modification**. Combo roi : déplacement précis + `.`.
- Le mode Visuel (`v`, `V`, `Ctrl-v`) sélectionne d'abord ; on applique ensuite l'opérateur.

## Exercices

### Exercice 1 — Changer le contenu d'une chaîne

Dans `panier.py`, sur la ligne des produits, remplace le texte `pain` (entre guillemets) par
`baguette` **sans** sélectionner manuellement les lettres, et où que soit ton curseur entre les
guillemets.

<details>
<summary>Voir le corrigé</summary>

La démarche : le text object `i"` désigne l'intérieur des guillemets ; combiné à `c` (changer), il
vide la chaîne et passe en Insertion.

1. Place le curseur quelque part entre les guillemets de `"pain"` (par exemple avec `f"` puis `l`).
2. Tape `ci"` → `pain` disparaît, tu es en Insertion.
3. Tape `baguette`, puis `Échap`.

Résultat : `"baguette"`. Tu n'as compté aucune lettre.

</details>

### Exercice 2 — Supprimer jusqu'à un caractère

Sur la ligne `print("Total du panier :", total)`, supprime tout depuis le curseur (placé sur le `p` de
`print`) jusqu'à la parenthèse ouvrante incluse, en une commande.

<details>
<summary>Voir le corrigé</summary>

La démarche : `d` + un mouvement `f(` qui va **sur** la parenthèse (incluse).

1. Curseur sur le `p` de `print`.
2. Tape `df(` → supprime `print(`.

Si tu voulais t'arrêter **avant** la parenthèse, tu utiliserais `dt(`.

</details>

### Exercice 3 — La danse du point

Dans `panier.py`, ajoute la chaîne `EUR ` (avec l'espace) ou fais une petite modification répétable de
ton choix sur plusieurs lignes, en composant la modification **une seule fois** puis en la rejouant
avec `.`.

<details>
<summary>Voir le corrigé</summary>

La démarche : on fait la modification une fois proprement (elle doit être « rejouable »), on se
déplace, puis `.`.

Exemple : tu veux mettre un `#` de commentaire devant plusieurs lignes.

1. Sur la première ligne à commenter, tape `I# ` puis `Échap` (insère `# ` au début).
2. Descends sur la ligne suivante avec `j`.
3. Tape `.` → Vim rejoue `I# ` + `Échap`.
4. `j`, `.`, `j`, `.` … pour chaque ligne.

Tu n'as composé l'insertion qu'une fois.

</details>

## Quiz

**1.** Que fait la commande `dw` ?
- A. Supprime le mot précédent.
- B. Supprime du curseur jusqu'au début du mot suivant.
- C. Duplique le mot.

**2.** Le curseur est au milieu d'une chaîne entre guillemets. Que fait `ci"` ?
- A. Copie la chaîne.
- B. Supprime l'intérieur des guillemets et passe en Insertion.
- C. Supprime les guillemets eux-mêmes.

**3.** À quoi sert `.` (le point) en mode Normal ?
- A. À répéter le dernier déplacement.
- B. À répéter la dernière modification.
- C. À ajouter un point au texte.

**4.** Quelle commande sélectionne la ligne entière en mode Visuel ?
- A. `v`
- B. `V`
- C. `Ctrl-v`

<details>
<summary>Voir les réponses</summary>

1. **B** — `d` (supprimer) + `w` (mouvement « mot suivant »).
2. **B** — `i"` = intérieur des guillemets ; `c` supprime puis passe en Insertion. `a"` inclurait les
   guillemets.
3. **B** — `.` répète la dernière **modification** (pas un déplacement). C'est l'outil de répétition
   central.
4. **B** — `V` sélectionne ligne par ligne ; `v` caractère par caractère, `Ctrl-v` en bloc.

</details>

## Projet fil rouge

Jalon « premier refactoring clavier ». Dans `panier.py`, effectue ce mini-refactoring **sans souris** :

1. Renomme la variable `total` en `montant` partout où elle apparaît. Astuce : sur la première
   occurrence, `cwmontant` + `Échap`, puis déplace-toi sur les suivantes et `.`.
2. Change le libellé `"Total du panier :"` en `"Montant du panier :"` avec `ci"`.
3. Supprime la ligne `remise = 0.10` que tu avais ajoutée (avec `dd`).
4. Enregistre (`:w`).

Ajoute à `cheatsheet.md` :

```markdown
## Grammaire de l'édition
- opérateurs : `d` supprimer · `c` changer · `y` copier
- combo : opérateur + mouvement → `dw`, `d$`, `df)`, `ct"`…
- ligne entière : `dd` `cc` `yy` (et `3dd`)
- text objects : `iw aw` · `i" a"` · `i( a(` · `i{ a{` · `ip`
- `.` répète la dernière modification (combo : déplacement + `.`)
- visuel : `v` caractère · `V` ligne · `Ctrl-v` bloc
```

---

[← Chapitre précédent](03-se-deplacer-vite.md) · [Sommaire](README.md) · [Chapitre suivant →](05-copier-coller-registres.md)
