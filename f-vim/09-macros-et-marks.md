# Passer la vitesse supérieure : macros et marks

[← Chapitre précédent](08-personnaliser-vim.md) · [Sommaire](README.md) · [Chapitre suivant →](10-vim-dans-l-ide.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- enregistrer et rejouer une **macro** pour automatiser une suite d'actions répétitives ;
- poser des **marks** (repères) et y revenir instantanément ;
- naviguer dans ton historique de positions avec la **liste de sauts** ;
- déclencher l'**autocomplétion** native et ré-indenter du code.

Tu sais déjà beaucoup. Ce chapitre ajoute les outils qui font gagner le plus de temps sur les tâches
répétitives : les macros et les marks.

## Les macros : enregistrer une séquence d'actions

Le `.` (chapitre 4) rejoue **une** modification. Une **macro** rejoue **toute une séquence** de
commandes : déplacements, éditions, recherches… C'est l'automatisation reine de Vim.

Le principe se déroule en trois temps :

1. `q<lettre>` : commence à **enregistrer** dans le registre `<lettre>` (par exemple `qa`).
2. Tu effectues normalement tes actions ; Vim les mémorise.
3. `q` : **arrête** l'enregistrement.

Puis tu **rejoues** avec `@<lettre>`, et `@@` répète la dernière macro jouée.

Prenons un cas concret. Tu as une liste de noms à transformer en chaînes Python :

```python
pain
lait
oeufs
beurre
```

Tu veux obtenir `"pain",` sur chaque ligne. Enregistre la transformation d'**une** ligne, puis
applique-la aux autres :

```text
qa            " commence à enregistrer dans le registre a
I"            " insère un guillemet au début de ligne
Échap
A",           " ajoute guillemet + virgule en fin de ligne
Échap
j             " descend à la ligne suivante (préparé pour la prochaine exécution)
q             " arrête l'enregistrement
```

La première ligne est déjà transformée (tu viens de le faire en enregistrant). Pour les trois autres :

```text
3@a           " rejoue la macro a, 3 fois
```

Résultat :

```python
"pain",
"lait",
"oeufs",
"beurre",
```

> **À retenir** — La clé d'une bonne macro, c'est de **finir par un déplacement qui prépare la
> répétition** (souvent `j` pour passer à la ligne suivante). Ainsi, chaque rejouage se positionne
> tout seul pour le suivant, et tu peux faire `99@a` sans réfléchir.

Comme une macro est stockée dans un registre, tu peux l'inspecter (`:reg a`) et même l'éditer comme du
texte. Et puisque c'est le même espace de registres qu'au chapitre 5, attention à ne pas écraser une
macro précieuse avec un yank dans le même registre.

> **Astuce** — Si une macro « part en vrille » à la première exécution, c'est presque toujours un
> problème de position de départ ou un déplacement non déterministe. Refais-la proprement : `qa` …
> `q`. Une macro juste se rejoue à l'identique des dizaines de fois.

## Les marks : poser des repères

Un *mark* est un signet posé à une position précise, auquel tu reviens instantanément. Très utile dans
les fichiers longs.

| Commande | Effet |
| --- | --- |
| `m<lettre>` | pose un mark nommé `<lettre>` à la position du curseur |
| `` `<lettre> `` | saute **exactement** à la position du mark (ligne **et** colonne) |
| `'<lettre>` | saute au **début de la ligne** du mark |
| `:marks` | liste les marks posés |

```text
ma            " pose le mark a ici (par exemple sur une fonction en cours d'écriture)
…tu vas explorer ailleurs dans le fichier, voire dans un autre…
`a            " reviens pile où tu étais
```

Certains marks sont automatiques et bien pratiques :

- `` `` `` (deux backticks, ou `` `. ``) : la position **avant ton dernier saut** ;
- `` `. `` : l'endroit de ta **dernière modification** ;
- `` `^ `` : l'endroit où tu as quitté le mode Insertion pour la dernière fois.

Le double backtick `` `` `` est précieux : tu fais une recherche qui t'emmène loin, tu regardes, puis
`` `` `` te ramène d'où tu venais.

## La liste de sauts : revenir sur tes pas

Vim mémorise tes « grands » déplacements (recherches, `G`, sauts de ligne…) dans une **liste de
sauts** (*jump list*). Tu la parcours comme un historique de navigateur :

- `Ctrl-o` : recule vers la position précédente (*older*) ;
- `Ctrl-i` : avance vers la position suivante (*newer*).

C'est l'équivalent des flèches « page précédente / suivante » d'un navigateur, mais pour tes positions
dans le code. Tu sautes à une définition à l'autre bout du fichier avec `/`, tu lis, puis `Ctrl-o` te
ramène, et `Ctrl-o` encore te ramène à la position d'avant.

> **À retenir** — `Ctrl-o` / `Ctrl-i` et les marks sont complémentaires : la liste de sauts est
> **automatique** (Vim retient tes déplacements), les marks sont **volontaires** (tu poses un repère
> sur un endroit que tu veux retrouver).

## L'autocomplétion native

Tu n'as pas besoin de plugin pour compléter un mot déjà présent dans tes fichiers ouverts. En mode
Insertion :

- `Ctrl-n` : complète avec le mot **suivant** qui correspond (*next*) ;
- `Ctrl-p` : complète avec le mot **précédent** (*previous*).

Tape le début d'un identifiant long (`prod`), puis `Ctrl-n` : Vim propose `produits` (s'il existe dans
le fichier). Pratique pour ne pas retaper de longs noms et éviter les fautes de frappe.

Il existe des complétions plus ciblées, toutes préfixées par `Ctrl-x` en Insertion :

- `Ctrl-x Ctrl-f` : complète un **chemin de fichier** ;
- `Ctrl-x Ctrl-l` : complète une **ligne entière**.

## Ré-indenter du code

Pour remettre l'indentation d'aplomb, l'opérateur est `=` (il suit la même grammaire opérateur +
mouvement que `d`, `c`, `y`) :

| Commande | Effet |
| --- | --- |
| `==` | ré-indente la ligne courante |
| `=ap` | ré-indente le paragraphe (text object `ap`) |
| `=G` | ré-indente du curseur jusqu'à la fin du fichier |
| `gg=G` | ré-indente **tout le fichier** (va au début, ré-indente jusqu'à la fin) |

`gg=G` est le « formatage rapide » de Vim quand un fichier a une indentation anarchique. Le résultat
dépend des règles du langage détecté (grâce à `filetype indent on` du `.vimrc`).

Pour décaler manuellement l'indentation d'un bloc, en mode Visuel : `>` décale d'un cran vers la
droite, `<` vers la gauche.

## Résumé

- **Macros** : `q<lettre>` enregistre, `q` arrête, `@<lettre>` rejoue, `@@` répète. Finis la macro
  par un déplacement qui prépare la répétition (`j`).
- **Marks** : `m<lettre>` pose, `` `<lettre> `` y revient (ligne+colonne), `'<lettre>` au début de
  ligne. `` `` `` = position avant le dernier saut.
- **Liste de sauts** : `Ctrl-o` recule, `Ctrl-i` avance dans l'historique des positions.
- **Autocomplétion** (Insertion) : `Ctrl-n`/`Ctrl-p` sur les mots présents ; `Ctrl-x Ctrl-f` pour les
  chemins.
- **Ré-indentation** : `==`, `=ap`, `gg=G` ; `>`/`<` en Visuel pour décaler.

## Exercices

### Exercice 1 — Une macro de transformation

Crée un fichier avec ces quatre lignes :

```text
pain
lait
oeufs
beurre
```

Transforme chaque ligne en `"mot",` à l'aide d'une **macro** enregistrée une seule fois.

<details>
<summary>Voir le corrigé</summary>

La démarche : on enregistre la transformation d'une ligne en finissant par `j`, puis on rejoue.

1. Curseur sur la première ligne.
2. `qa` (enregistre dans `a`).
3. `I"` `Échap` (guillemet au début), `A",` `Échap` (guillemet + virgule à la fin), `j` (ligne
   suivante).
4. `q` (stop).
5. La première ligne est faite ; pour les trois autres : `3@a`.

Vérifie le résultat. Si une ligne est mal formée, `u` pour annuler et recommence la macro proprement.

</details>

### Exercice 2 — Aller-retour avec un mark

Dans `panier.py`, pose un mark sur la ligne `produits = ...`, va explorer la fin du fichier, puis
reviens d'un coup à ton mark.

<details>
<summary>Voir le corrigé</summary>

La démarche : `m<lettre>` pose, `` `<lettre> `` revient.

1. Curseur sur la ligne `produits = ...`.
2. `ma` (pose le mark `a`).
3. `G` (file à la fin du fichier), regarde autour.
4. `` `a `` (backtick + a) → retour exact à la ligne et la colonne du mark.

Tu peux aussi tester `` `` `` (double backtick) : il te ramène à la position d'avant ton dernier saut.

</details>

### Exercice 3 — Reformater un fichier

Dérègle volontairement l'indentation de `panier.py` (ajoute des espaces au hasard devant quelques
lignes), puis remets tout d'aplomb en une commande.

<details>
<summary>Voir le corrigé</summary>

La démarche : `gg=G` va au début et ré-indente jusqu'à la fin selon les règles du langage.

1. Tape `gg=G`.
2. L'indentation est recalculée pour tout le fichier.

Pour que ça fonctionne bien sur du Python, ton `.vimrc` doit contenir `filetype plugin indent on`
(présent dans la base du chapitre 8). Note : l'indentation Python automatique a ses limites ; sur des
cas tordus, ajuste à la main avec `>`/`<` en Visuel.

</details>

## Quiz

**1.** Comment démarre-t-on l'enregistrement d'une macro dans le registre `a` ?
- A. `@a`
- B. `qa`
- C. `ma`

**2.** Que fait `@@` ?
- A. Rejoue la dernière macro exécutée.
- B. Enregistre une nouvelle macro.
- C. Liste les macros.

**3.** Tu as posé un mark `b`. Quelle commande t'y ramène exactement (ligne et colonne) ?
- A. `'b`
- B. `` `b ``
- C. `mb`

**4.** À quoi sert `Ctrl-o` en mode Normal ?
- A. À ouvrir un fichier.
- B. À reculer vers la position précédente dans la liste de sauts.
- C. À ré-indenter.

<details>
<summary>Voir les réponses</summary>

1. **B** — `q` + une lettre démarre l'enregistrement ; un second `q` l'arrête. `ma` poserait un mark.
2. **A** — `@@` rejoue la dernière macro ; `@a` rejouerait spécifiquement la macro `a`.
3. **B** — Le backtick `` `b `` va à la position exacte ; `'b` irait au début de la ligne du mark.
4. **B** — `Ctrl-o` recule dans l'historique des positions, `Ctrl-i` avance.

</details>

## Projet fil rouge

Jalon « automatiser le répétitif ». Dans ton projet `kit-vim` :

1. Dans `panier.py`, ajoute trois nouveaux produits sous forme de liste de noms, puis transforme-les en
   tuples `("nom", 0.0),` à l'aide d'une **macro** (enregistre la transformation d'un, rejoue pour les
   autres).
2. Pose un mark `t` en haut du fichier ; après avoir édité ailleurs, reviens-y avec `` `t ``.
3. Reformate le fichier avec `gg=G`, puis enregistre.

Ajoute à `cheatsheet.md` :

```markdown
## Macros, marks, navigation
- macro : `q<l>` enregistrer · `q` stop · `@<l>` rejouer · `@@` répéter · `99@<l>` en masse
- marks : `m<l>` poser · `` `<l> `` aller (ligne+col) · `'<l>` (début de ligne) · `` `` `` avant le saut
- liste de sauts : `Ctrl-o` reculer · `Ctrl-i` avancer
- autocomplétion (Insertion) : `Ctrl-n`/`Ctrl-p` · chemins : `Ctrl-x Ctrl-f`
- indenter : `==` · `gg=G` (tout) · `>`/`<` en Visuel
```

---

[← Chapitre précédent](08-personnaliser-vim.md) · [Sommaire](README.md) · [Chapitre suivant →](10-vim-dans-l-ide.md)
