# Les modes et le déplacement de base

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-se-deplacer-vite.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- nommer les quatre modes de Vim et savoir dans lequel tu te trouves ;
- déplacer le curseur avec `h j k l` sans toucher aux flèches ;
- entrer en mode Insertion de plusieurs façons (`i a o O A I`) selon où tu veux écrire ;
- faire tes premières éditions simples (`x`, `r`) en mode Normal.

## Les quatre modes

Au chapitre 1, tu as vu deux modes. En voici la liste complète. Tu reconnais le mode actif à
l'indicateur en bas de l'écran (et à ce que fait ton clavier).

| Mode | On y entre par… | À quoi il sert | Indicateur en bas |
| --- | --- | --- | --- |
| **Normal** | `Échap` | Se déplacer et lancer des commandes. C'est le mode « par défaut ». | (rien) |
| **Insertion** | `i`, `a`, `o`… | Taper du texte. | `-- INSERT --` |
| **Visuel** | `v`, `V`, `Ctrl-v` | Sélectionner du texte (on l'étudie au chapitre 4). | `-- VISUAL --` |
| **Commande** | `:` | Lancer une commande longue (`:wq`, recherche…). | la ligne commence par `:` |

La règle d'or n'a pas changé : **`Échap` te ramène toujours en mode Normal**. On part toujours du
mode Normal, on y revient toujours.

> **À retenir** — Le mode Normal est ta « maison ». Tu y passes en réalité le plus clair de ton temps,
> car c'est de là qu'on se déplace et qu'on édite. Le mode Insertion, on n'y reste que le temps de
> taper le texte, puis on en sort.

Pourquoi ce mode Normal change tout ? Parce que se déplacer et transformer du texte, c'est ce qu'on
fait le plus en programmant — bien plus que taper du texte neuf. Vim optimise donc le déplacement et
l'édition, pas la frappe.

## Se déplacer : h, j, k, l

En mode Normal, on déplace le curseur d'un caractère avec quatre touches collées sous ta main droite :

```text
        k          (haut)
        ↑
   h ←     → l      (gauche / droite)
        ↓
        j          (bas)
```

| Touche | Direction | Mémo |
| --- | --- | --- |
| `h` | gauche | la plus à gauche des quatre |
| `j` | bas | la lettre a une « jambe » qui descend |
| `k` | haut | comme *up* à l'envers, ça monte |
| `l` | droite | la plus à droite des quatre |

Oui, les flèches du clavier fonctionnent aussi. **Mais force-toi à utiliser `h j k l`** : elles sont
sous tes doigts, tu n'as pas à déplacer la main pour atteindre le bloc des flèches. C'est tout
l'intérêt — gagner les allers-retours.

> **Astuce** — Pour te désintoxiquer des flèches au début, tu peux les désactiver. On verra comment
> au chapitre 8 (configuration). En attendant, la discipline suffit.

Ces touches se **combinent avec un nombre** : tape `5j` pour descendre de 5 lignes, `12l` pour avancer
de 12 caractères. Ce préfixe numérique, appelé *count*, marche avec presque toutes les commandes de
Vim. C'est un principe central : on y reviendra sans cesse.

```text
3k    → monte de 3 lignes
10l   → avance de 10 caractères
```

## Entrer en mode Insertion au bon endroit

`i` insère **avant** le curseur. Mais souvent tu veux écrire ailleurs : après le caractère, en début
ou en fin de ligne, sur une nouvelle ligne. Vim a une touche pour chaque cas, ce qui t'évite de te
déplacer avant d'écrire.

| Touche | Effet | Mémo |
| --- | --- | --- |
| `i` | insère **avant** le curseur | *insert* |
| `a` | insère **après** le curseur | *append* (ajouter à la suite) |
| `I` | insère en **début de ligne** (premier caractère non blanc) | `i` majuscule = plus fort |
| `A` | insère en **fin de ligne** | `a` majuscule = plus fort |
| `o` | ouvre une **nouvelle ligne en dessous** et y insère | *open* |
| `O` | ouvre une **nouvelle ligne au-dessus** et y insère | `o` majuscule = au-dessus |

Un exemple concret. Tu as cette ligne, curseur sur le `t` de `total` :

```python
total = 0
```

- `A` t'amène écrire **après** le `0`, en fin de ligne — pratique pour ajouter un commentaire.
- `I` t'amène écrire tout au début, avant `total` — pratique pour commenter la ligne avec `#`.
- `o` crée une ligne vide juste en dessous et t'y place — pratique pour ajouter une instruction.

> **À retenir** — Choisir la bonne touche d'entrée en Insertion (`A`, `o`, `I`…) t'évite de te
> positionner d'abord puis d'insérer. Une touche au lieu de trois. C'est là que Vim gagne du temps.

## Premières éditions en mode Normal

Sans même passer en Insertion, tu peux déjà corriger du texte depuis le mode Normal.

- `x` supprime le caractère sous le curseur. Avec un *count* : `3x` en supprime 3.
- `r` (pour *replace*) remplace le caractère sous le curseur par le suivant que tu tapes. Exemple :
  curseur sur un `a`, tu tapes `re`, le `a` devient `e`. Pas besoin de passer en Insertion.

```python
# Curseur sur le "0", tu veux le passer à 5 :
total = 0
# Tape r5  →  total = 5
```

Ces petites commandes, combinées au déplacement, suffisent déjà à faire des corrections rapides sans
jamais quitter le mode Normal.

## Résumé

- Quatre modes : **Normal** (commandes), **Insertion** (frappe), **Visuel** (sélection), **Commande**
  (`:`). `Échap` ramène toujours en Normal.
- On se déplace avec `h` (gauche), `j` (bas), `k` (haut), `l` (droite) — pas les flèches.
- Un **nombre** devant une commande la répète : `5j`, `12l`.
- Pour écrire au bon endroit sans se déplacer : `a` (après), `A` (fin de ligne), `I` (début), `o` /
  `O` (nouvelle ligne dessous / dessus).
- En mode Normal : `x` supprime un caractère, `r` en remplace un.

## Exercices

### Exercice 1 — Naviguer sans flèches

Ouvre `panier.py` (créé au chapitre 1). En n'utilisant **que** `h j k l`, déplace le curseur jusqu'au
mot `total` de la ligne `total = 0`, puis jusqu'au `print` de la dernière ligne. Interdiction de
toucher aux flèches ni à la souris.

<details>
<summary>Voir le corrigé</summary>

La démarche : on descend avec `j` jusqu'à la bonne ligne, puis on se cale horizontalement avec `h` /
`l`. Pour aller plus vite, préfixe par un nombre : par exemple `3j` pour descendre de trois lignes
d'un coup.

Il n'y a pas de séquence unique « correcte » : l'objectif est de t'obliger à utiliser `h j k l`. Si
tu y arrives sans regarder le clavier, c'est gagné.

</details>

### Exercice 2 — Ajouter une ligne sans te positionner à la main

Toujours dans `panier.py`, place le curseur n'importe où sur la ligne `total = 0` et, en **une seule**
touche d'entrée en Insertion, ajoute une nouvelle ligne juste en dessous contenant `remise = 0.10`.

<details>
<summary>Voir le corrigé</summary>

La démarche : `o` ouvre une nouvelle ligne **en dessous** et passe directement en Insertion, où que
soit le curseur sur la ligne courante. Pas besoin d'aller en fin de ligne d'abord.

1. Curseur sur la ligne `total = 0` (n'importe quelle colonne).
2. Tape `o` → une ligne vide apparaît en dessous, tu es en `-- INSERT --`.
3. Tape `remise = 0.10`.
4. `Échap` pour revenir en Normal.

</details>

### Exercice 3 — Corriger un caractère sans passer en Insertion

Dans le mot `oeufs` de `panier.py`, remplace le `o` par un `O` majuscule en mode Normal, sans entrer
en mode Insertion.

<details>
<summary>Voir le corrigé</summary>

La démarche : `r` remplace le caractère sous le curseur par le suivant tapé, sans changer de mode.

1. Place le curseur sur le `o` de `oeufs` (déplacement `h j k l`).
2. Tape `r` puis `O`.

Le `o` devient `O`. Tu es resté en mode Normal tout du long.

</details>

## Quiz

**1.** Quelle touche déplace le curseur vers le bas ?
- A. `k`
- B. `j`
- C. `l`

**2.** Tu veux ajouter du texte à la **fin** de la ligne courante. Quelle touche est la plus directe ?
- A. `a`
- B. `A`
- C. `o`

**3.** Que fait `o` en mode Normal ?
- A. Ouvre une nouvelle ligne au-dessus et passe en Insertion.
- B. Ouvre une nouvelle ligne en dessous et passe en Insertion.
- C. Supprime la ligne courante.

**4.** Que fait `5j` ?
- A. Descend de 5 lignes.
- B. Supprime 5 lignes.
- C. Insère 5 lignes.

<details>
<summary>Voir les réponses</summary>

1. **B** — `j` descend (la lettre a une jambe vers le bas). `k` monte, `h`/`l` vont à gauche/droite.
2. **B** — `A` insère en fin de ligne. `a` insère juste après le curseur, `o` crée une nouvelle ligne.
3. **B** — `o` ouvre **en dessous** ; `O` ouvre au-dessus.
4. **A** — Le nombre devant une commande la répète : `5j` descend de 5 lignes.

</details>

## Projet fil rouge

Jalon « premiers déplacements sans souris ». Dans `panier.py`, réalise ces trois micro-tâches
**uniquement au clavier** :

1. Ajoute, sous la ligne `total = 0`, une ligne `remise = 0.10` (avec `o`).
2. En fin de la ligne `print(...)`, ajoute un commentaire `# fin` (avec `A`, puis ` # fin`).
3. Enregistre (`:w`).

Puis complète `cheatsheet.md` (en ouvrant le fichier dans Vim, évidemment) avec une nouvelle section :

```markdown
## Déplacement et modes
- `h j k l` : gauche / bas / haut / droite
- `i a` : insérer avant / après le curseur · `I A` : début / fin de ligne
- `o O` : nouvelle ligne dessous / dessus
- `x` : supprimer un caractère · `r` : remplacer un caractère
- préfixe numérique : `5j`, `12l`…
```

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-se-deplacer-vite.md)
