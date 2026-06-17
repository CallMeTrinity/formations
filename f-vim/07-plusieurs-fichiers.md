# Plusieurs fichiers à la fois

[← Chapitre précédent](06-rechercher-et-remplacer.md) · [Sommaire](README.md) · [Chapitre suivant →](08-personnaliser-vim.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- ouvrir plusieurs fichiers et passer de l'un à l'autre avec les **buffers** ;
- partager l'écran en plusieurs **fenêtres** (splits) et te déplacer entre elles ;
- utiliser les **onglets** pour organiser des espaces de travail ;
- parcourir l'arborescence d'un projet avec l'explorateur intégré.

Jusqu'ici tu éditais un seul fichier. Un vrai projet, c'est des dizaines de fichiers. Vim gère ça
avec trois concepts qu'il ne faut pas confondre : **buffers**, **fenêtres**, **onglets**.

## Les trois niveaux : buffer, fenêtre, onglet

- Un **buffer** est un fichier chargé en mémoire. Ouvrir 10 fichiers = 10 buffers, même si tu n'en
  vois qu'un à l'écran.
- Une **fenêtre** (*window*, ou *split*) est une vue qui **affiche** un buffer. Tu peux découper
  l'écran en plusieurs fenêtres pour voir plusieurs buffers côte à côte.
- Un **onglet** (*tab*) est une **disposition de fenêtres**. Chaque onglet peut avoir son propre
  découpage.

L'analogie : les buffers sont tes documents ouverts ; les fenêtres sont les cadres dans lesquels tu
les affiches ; les onglets sont des bureaux différents. **Un buffer existe même s'il n'est affiché
dans aucune fenêtre.**

> **À retenir** — Contrairement à beaucoup d'éditeurs, dans Vim « fermer la fenêtre » ne ferme pas
> forcément le fichier : le buffer peut rester en mémoire. C'est la source de confusion numéro un au
> début.

## Les buffers : tous tes fichiers ouverts

Ouvre plusieurs fichiers d'un coup depuis le terminal :

```bash
vim panier.py cheatsheet.md
```

Les deux sont chargés comme buffers ; tu vois le premier. Pour ouvrir un fichier de plus **depuis
Vim** :

```text
:e autre-fichier.py     " edit : ouvre le fichier dans un nouveau buffer
```

Les commandes de gestion des buffers :

| Commande | Effet |
| --- | --- |
| `:ls` ou `:buffers` | liste les buffers ouverts (avec leur numéro) |
| `:bn` | buffer suivant (*buffer next*) |
| `:bp` | buffer précédent |
| `:b 3` | aller au buffer numéro 3 |
| `:b pan` | aller au buffer dont le nom contient `pan` (complétion) |
| `Ctrl-^` | basculer entre le buffer courant et le précédent (l'« alterné ») |
| `:bd` | fermer le buffer courant (*buffer delete*) |

`:ls` te montre quelque chose comme :

```text
:ls
# Sortie :
#   1 %a   "panier.py"        ligne 1
#   2      "cheatsheet.md"    ligne 1
```

Le `%` marque le buffer affiché, le `a` qu'il est actif. Le `Ctrl-^` (souvent noté `Ctrl-6`) est le
plus pratique au quotidien : il fait des allers-retours entre les deux derniers fichiers, comme
`Alt-Tab` pour des fenêtres.

> **Attention** — Si tu fais `:bn` alors que ton buffer a des modifications non enregistrées, Vim
> refuse de changer (`E37`). Enregistre (`:w`) d'abord, ou autorise les buffers cachés (option
> `hidden`, qu'on mettra dans le `.vimrc` au chapitre 8).

## Les fenêtres (splits) : voir plusieurs fichiers

Découpe l'écran pour voir deux fichiers à la fois :

| Commande | Effet |
| --- | --- |
| `:split` ou `:sp` | découpe **horizontalement** (deux fenêtres l'une au-dessus de l'autre) |
| `:vsplit` ou `:vs` | découpe **verticalement** (côte à côte) |
| `:sp autre.py` | ouvre `autre.py` dans une nouvelle fenêtre horizontale |
| `Ctrl-w s` | split horizontal (raccourci clavier) |
| `Ctrl-w v` | split vertical |

Pour te **déplacer entre les fenêtres**, le préfixe est `Ctrl-w` suivi d'une direction :

| Raccourci | Effet |
| --- | --- |
| `Ctrl-w h/j/k/l` | va à la fenêtre de gauche / bas / haut / droite |
| `Ctrl-w w` | passe à la fenêtre suivante (cycle) |
| `Ctrl-w q` | ferme la fenêtre courante |
| `Ctrl-w o` | ne garde que la fenêtre courante (*only*) |
| `Ctrl-w =` | égalise la taille des fenêtres |

Remarque la cohérence : `Ctrl-w` puis `h j k l`, les mêmes touches que pour déplacer le curseur, mais
pour déplacer le **focus** entre fenêtres.

```text
:vs cheatsheet.md     " ouvre cheatsheet.md à droite
Ctrl-w h              " reviens à la fenêtre de gauche (panier.py)
Ctrl-w l              " repasse à droite
```

> **Astuce** — Un split vertical (`:vs`) est idéal pour comparer deux fichiers, ou lire une fonction
> dans l'un en écrivant dans l'autre. Pour voir deux endroits **du même fichier**, `:sp` sans
> argument te donne deux vues sur le même buffer.

## Les onglets : des espaces de travail

Un onglet regroupe une disposition de fenêtres. Utile pour séparer des « contextes » (par exemple :
un onglet pour le code, un pour les tests).

| Commande | Effet |
| --- | --- |
| `:tabnew fichier` | ouvre un fichier dans un nouvel onglet |
| `gt` | onglet suivant |
| `gT` | onglet précédent |
| `2gt` | aller à l'onglet 2 |
| `:tabclose` | fermer l'onglet courant |

En pratique, beaucoup d'utilisateurs de Vim vivent surtout avec **buffers + splits** et n'utilisent les
onglets que ponctuellement. Ne te force pas à tout utiliser : commence par les buffers, ajoute les
splits, et les onglets viendront si tu en ressens le besoin.

## Explorer l'arborescence : netrw

Vim embarque un explorateur de fichiers, *netrw*. Ouvre-le sur le dossier courant :

```text
:Explore     " ou :Ex
```

Tu vois la liste des fichiers et dossiers. Déplace-toi avec `j`/`k`, ouvre avec `Entrée`. Quelques
touches dans netrw :

- `Entrée` : ouvrir le fichier/dossier sous le curseur ;
- `-` : remonter d'un dossier ;
- `%` : créer un nouveau fichier ;
- `d` : créer un nouveau dossier.

Lancer `vim .` (avec un point) sur un dossier ouvre directement netrw sur ce dossier. C'est suffisant
pour naviguer dans un projet sans souris. (Beaucoup installent ensuite un plugin d'explorateur plus
riche, mais ce n'est pas nécessaire pour être efficace.)

## Résumé

- **Buffer** = fichier en mémoire, **fenêtre** = vue affichant un buffer, **onglet** = disposition de
  fenêtres. Un buffer survit même sans fenêtre.
- Buffers : `:e fichier` ouvrir, `:ls` lister, `:bn`/`:bp` naviguer, `:b nom` cibler, `Ctrl-^`
  alterner, `:bd` fermer.
- Splits : `:sp` (horizontal), `:vs` (vertical) ; navigation `Ctrl-w h/j/k/l`, fermer `Ctrl-w q`.
- Onglets : `:tabnew`, `gt`/`gT` pour naviguer.
- `:Explore` (ou `vim .`) ouvre l'explorateur de fichiers netrw.

## Exercices

### Exercice 1 — Jongler entre deux fichiers

Ouvre `panier.py` et `cheatsheet.md` en même temps, liste les buffers, et bascule de l'un à l'autre.

<details>
<summary>Voir le corrigé</summary>

La démarche : ouvrir les deux, inspecter avec `:ls`, alterner avec `Ctrl-^`.

```bash
vim panier.py cheatsheet.md
```

Dans Vim :

1. `:ls` → tu vois les deux buffers numérotés.
2. `:bn` → passe au second. `:bp` → reviens au premier.
3. `Ctrl-^` → bascule rapidement entre les deux derniers utilisés.
4. `:b che` → saute au buffer dont le nom contient `che` (cheatsheet).

</details>

### Exercice 2 — Côte à côte

Affiche `panier.py` et `cheatsheet.md` côte à côte dans deux fenêtres verticales, puis déplace le
focus de l'une à l'autre sans souris.

<details>
<summary>Voir le corrigé</summary>

La démarche : un split vertical, puis navigation `Ctrl-w`.

1. Ouvre `panier.py` : `vim panier.py`.
2. `:vs cheatsheet.md` → `cheatsheet.md` s'ouvre à droite.
3. `Ctrl-w h` → focus à gauche (panier.py). `Ctrl-w l` → focus à droite.
4. Pour tout refermer sauf la fenêtre courante : `Ctrl-w o`.

</details>

### Exercice 3 — Explorer un dossier

Depuis le dossier `kit-vim`, ouvre l'explorateur de fichiers de Vim et ouvre `panier.py` depuis la
liste, sans le nommer dans la commande.

<details>
<summary>Voir le corrigé</summary>

La démarche : netrw liste les fichiers, on navigue au clavier.

```bash
vim .
```

Dans netrw :

1. Descends avec `j` jusqu'à `panier.py`.
2. `Entrée` → le fichier s'ouvre.

(Tu peux aussi lancer `:Explore` depuis un Vim déjà ouvert.)

</details>

## Quiz

**1.** Quelle est la différence entre un buffer et une fenêtre ?
- A. Aucune, ce sont des synonymes.
- B. Un buffer est un fichier en mémoire ; une fenêtre est une vue qui affiche un buffer.
- C. Une fenêtre est un fichier, un buffer est un onglet.

**2.** Comment ouvrir un second fichier côte à côte, à droite ?
- A. `:sp fichier`
- B. `:vs fichier`
- C. `:tabnew fichier`

**3.** Quel raccourci déplace le focus vers la fenêtre de gauche ?
- A. `Ctrl-w h`
- B. `h`
- C. `:bp`

**4.** À quoi sert `Ctrl-^` ?
- A. Fermer le buffer courant.
- B. Basculer entre le buffer courant et le précédent.
- C. Ouvrir un nouvel onglet.

<details>
<summary>Voir les réponses</summary>

1. **B** — Le buffer est le contenu en mémoire ; la fenêtre est l'affichage. Un buffer peut exister
   sans être affiché.
2. **B** — `:vs` (vertical split) place le fichier à côté. `:sp` le mettrait au-dessus/en dessous.
3. **A** — `Ctrl-w` puis une direction `h/j/k/l` déplace le focus entre fenêtres.
4. **B** — `Ctrl-^` alterne avec le buffer précédemment affiché, comme un `Alt-Tab`.

</details>

## Projet fil rouge

Jalon « travailler comme sur un vrai projet ». Mets-toi en condition réelle :

1. Ouvre `panier.py` et `cheatsheet.md` dans un split vertical (`vim panier.py` puis `:vs cheatsheet.md`).
2. Édite `panier.py` à gauche tout en consultant ta cheat-sheet à droite, en passant de l'une à
   l'autre avec `Ctrl-w h`/`l`.
3. Ajoute un nouveau fichier `remise.py` au projet avec `:e remise.py`, écris-y une ligne, enregistre.

Ajoute à `cheatsheet.md` :

```markdown
## Plusieurs fichiers
- buffers : `:e f` ouvrir · `:ls` lister · `:bn`/`:bp` · `:b nom` · `Ctrl-^` alterner · `:bd` fermer
- splits : `:sp` horizontal · `:vs` vertical · `Ctrl-w h/j/k/l` naviguer · `Ctrl-w q` fermer · `Ctrl-w o` only
- onglets : `:tabnew` · `gt`/`gT`
- explorateur : `:Explore` (ou `vim .`)
```

---

[← Chapitre précédent](06-rechercher-et-remplacer.md) · [Sommaire](README.md) · [Chapitre suivant →](08-personnaliser-vim.md)
