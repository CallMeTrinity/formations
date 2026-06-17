# Vim dans ton IDE : IdeaVim et VS Code

[← Chapitre précédent](09-macros-et-marks.md) · [Sommaire](README.md) · [Chapitre suivant →](11-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- pourquoi garder ses réflexes Vim **dans** un IDE plutôt que de choisir entre les deux ;
- installer et configurer **IdeaVim** dans PhpStorm (et les IDE JetBrains) ;
- installer et configurer **Vim** dans VS Code ;
- combiner les commandes Vim avec les fonctions de l'IDE (refactoring, navigation, complétion) et
  régler les conflits de raccourcis.

Tu sais piloter Vim au clavier. Bonne nouvelle : tu n'as pas à abandonner ton IDE pour en profiter.
Les principaux éditeurs proposent un **mode Vim** qui réutilise tout ce que tu viens d'apprendre.

## Pourquoi Vim dans l'IDE

Vim autonome est imbattable pour l'édition de texte, mais un IDE apporte autre chose : autocomplétion
intelligente, navigation « aller à la définition », refactoring sûr (renommer un symbole dans tout le
projet), débogueur, intégration Git. Le mode Vim te donne **le meilleur des deux** : les mouvements et
l'édition au clavier de Vim, **plus** l'intelligence de l'IDE.

C'est aussi la transition idéale au quotidien : tu codes dans PhpStorm (ton environnement habituel)
tout en consolidant tes réflexes Vim sur du vrai code de projet.

> **À retenir** — Le mode Vim d'un IDE n'est pas « le vrai Vim » : c'est une **émulation**. Les
> mouvements, opérateurs, text objects, macros, marks, registres et `:s` fonctionnent. Ce qui change,
> c'est la configuration et les commandes très avancées. 95 % de ce cours s'applique tel quel.

## IdeaVim (PhpStorm et IDE JetBrains)

*IdeaVim* est le plugin officiel qui émule Vim dans tous les IDE JetBrains (PhpStorm, IntelliJ,
PyCharm, WebStorm…).

### Installation

1. Ouvre PhpStorm.
2. Va dans `Settings` (ou `Preferences` sur macOS) → `Plugins`.
3. Onglet `Marketplace`, cherche **IdeaVim**.
4. Clique sur `Install`, puis redémarre l'IDE si demandé.

Une fois installé, tes fichiers s'ouvrent en **mode Normal** : tu retrouves `h j k l`, `dw`, `ciw`,
`/`, `:%s`, les macros, etc., directement dans l'éditeur de PhpStorm.

### Le fichier de configuration : .ideavimrc

IdeaVim lit un fichier `~/.ideavimrc`, de **même syntaxe** que le `.vimrc`. Tu peux y reprendre une
bonne partie de ta config :

```vim
" ~/.ideavimrc — config IdeaVim
set number
set relativenumber
set ignorecase
set smartcase
set incsearch
set hlsearch
set scrolloff=5

let mapleader = " "
nnoremap <silent> <leader>h :nohlsearch<CR>
```

Recharge la config sans redémarrer avec la commande `:source ~/.ideavimrc`, ou via l'action
`IdeaVim: Reload .ideavimrc`.

### Marier Vim et les actions de l'IDE

La vraie puissance vient de mapper tes touches Vim sur les **actions de PhpStorm** via la commande
spéciale `<Action>` :

```vim
" Renommer un symbole dans tout le projet (refactoring de l'IDE)
nnoremap <leader>r :action RenameElement<CR>

" Aller à la définition sous le curseur
nnoremap gd :action GotoDeclaration<CR>

" Rechercher partout dans le projet
nnoremap <leader>f :action GotoFile<CR>

" Reformater le code (formateur de l'IDE, plus fin que gg=G)
nnoremap <leader>= :action ReformatCode<CR>
```

Pour découvrir le nom d'une action, active `:action` dans l'IDE ou utilise l'option *Track Action Ids*
d'IdeaVim (menu `Tools` → `IdeaVim` → `Track Action Ids`) : PhpStorm t'affiche alors l'identifiant de
chaque action que tu déclenches.

> **Astuce** — Garde le refactoring « renommer » (`RenameElement`) pour l'IDE plutôt que `:%s` : l'IDE
> comprend le code (portée des variables, fichiers multiples) là où `:%s` ne fait que du
> texte-à-texte. Utilise `:%s` pour le texte, les actions de l'IDE pour le code.

### Conflits de raccourcis

Certains raccourcis Vim (`Ctrl-w`, `Ctrl-n`, `Ctrl-v`…) sont aussi des raccourcis natifs de PhpStorm.
IdeaVim propose, dans `Settings` → `Editor` → `Vim`, un tableau pour décider, raccourci par raccourci,
qui gagne : **Vim** (*Handler: IDE/Vim*). Quelques réglages utiles :

- laisser `Ctrl-v` à Vim (sélection en bloc) ou à l'IDE (coller), selon ton habitude ;
- décider qui gère `Ctrl-c`/`Ctrl-v`.

Il n'y a pas de réglage « universellement correct » : ajuste selon les conflits que tu rencontres.

## Vim dans VS Code

L'extension la plus répandue s'appelle simplement **Vim** (éditeur : *vscodevim*).

### Installation

1. Ouvre VS Code.
2. Panneau `Extensions` (`Ctrl-Shift-X` / `Cmd-Shift-X`).
3. Cherche **Vim**, installe l'extension de *vscodevim*.
4. Le mode Vim s'active immédiatement.

### Configuration

VS Code ne lit pas un `.vimrc` : la config passe par le fichier `settings.json` (JSON), accessible via
`Préférences : Ouvrir les paramètres (JSON)`. Exemple :

```json
{
  "vim.leader": " ",
  "vim.hlsearch": true,
  "vim.useSystemClipboard": true,
  "vim.normalModeKeyBindingsNonRecursive": [
    { "before": ["<leader>", "h"], "commands": [":nohl"] },
    { "before": ["g", "d"], "commands": ["editor.action.revealDefinition"] }
  ]
}
```

La logique est la même qu'avec IdeaVim : tu peux mapper des touches Vim vers des **commandes VS Code**
(ici `editor.action.revealDefinition` pour « aller à la définition »).

> **Attention** — Sur macOS, si une touche maintenue ne se répète pas en mode Normal (par exemple `j`
> maintenu ne descend pas en continu), exécute une fois dans un terminal la commande indiquée par la
> documentation de l'extension pour réactiver la répétition des touches (réglage `ApplePressAndHold`).
> C'est le piège classique de vscodevim sur Mac.

## Ce qui marche, ce qui change

| Fonctionnalité Vim | Dans l'IDE (IdeaVim / vscodevim) |
| --- | --- |
| Mouvements (`hjkl`, `w`, `f`, `gg`, `G`…) | fonctionnent |
| Opérateurs + text objects (`ciw`, `di(`, `daw`…) | fonctionnent |
| Recherche `/`, `:%s`, regex | fonctionnent |
| Macros, marks, registres | fonctionnent |
| `.vimrc` / `.ideavimrc` | IdeaVim : oui (`~/.ideavimrc`). VS Code : non, config JSON |
| Splits/buffers de Vim | gérés par l'IDE (ses propres onglets/panneaux) |
| Plugins Vim avancés | non (mais l'IDE apporte l'équivalent) |
| Refactoring intelligent, débogueur | apportés par l'**IDE**, à mapper sur tes touches |

La bonne stratégie : **mouvements et édition = Vim ; navigation de projet, refactoring, débogage =
fonctions de l'IDE** déclenchées par des mappings Vim.

## Résumé

- Le mode Vim d'un IDE est une **émulation** : l'essentiel de ce cours (mouvements, opérateurs, text
  objects, recherche, macros, marks) s'applique.
- **IdeaVim** (PhpStorm/JetBrains) : installer via les plugins, config dans `~/.ideavimrc` (syntaxe
  `.vimrc`), mapper les actions de l'IDE avec `:action`.
- **vscodevim** (VS Code) : installer via les extensions, config en JSON (`vim.leader`,
  `normalModeKeyBindingsNonRecursive`), mapper les commandes VS Code.
- Réutilise `:%s` pour le texte, mais le **refactoring de l'IDE** pour renommer du code.
- Règle les **conflits de raccourcis** (qui gagne, Vim ou l'IDE) au cas par cas.

## Exercices

### Exercice 1 — Installer le mode Vim dans ton IDE

Dans l'IDE que tu utilises (PhpStorm de préférence), installe le plugin Vim et vérifie que les
mouvements de base (`h j k l`, `dw`, `ciw`, `/`) fonctionnent sur un fichier réel.

<details>
<summary>Voir le corrigé</summary>

La démarche : passer par le gestionnaire de plugins/extensions, puis tester.

PhpStorm : `Settings` → `Plugins` → `Marketplace` → chercher `IdeaVim` → `Install` → redémarrer.

Ouvre un fichier, place le curseur dans un mot et fais `ciw` : le mot disparaît et tu passes en
Insertion. Les réflexes du cours fonctionnent dans l'IDE.

</details>

### Exercice 2 — Mapper une action de l'IDE

Configure un raccourci Vim qui déclenche une action de ton IDE (par exemple « aller à la définition »
sur `gd`, ou « renommer » sur `<leader>r`).

<details>
<summary>Voir le corrigé</summary>

La démarche : on mappe une touche Vim sur une action de l'IDE.

IdeaVim — dans `~/.ideavimrc` :

```vim
nnoremap gd :action GotoDeclaration<CR>
nnoremap <leader>r :action RenameElement<CR>
```

Recharge (`:source ~/.ideavimrc`). Place le curseur sur un appel de fonction et tape `gd` : l'IDE
saute à sa définition.

VS Code — dans `settings.json` :

```json
"vim.normalModeKeyBindingsNonRecursive": [
  { "before": ["g", "d"], "commands": ["editor.action.revealDefinition"] }
]
```

</details>

### Exercice 3 — Texte vs code

Sur un fichier de code, renomme une variable de deux façons : avec `:%s/\<ancien\>/nouveau/g`, puis
avec le refactoring « renommer » de l'IDE. Compare les deux résultats.

<details>
<summary>Voir le corrigé</summary>

La démarche : observer la différence entre remplacement textuel et refactoring sémantique.

- `:%s/\<ancien\>/nouveau/g` change **toutes** les occurrences textuelles du fichier, y compris dans
  des commentaires ou des chaînes, et seulement dans **ce** fichier.
- Le refactoring de l'IDE (`RenameElement`) comprend la **portée** : il ne renomme que la variable
  concernée, dans tous les fichiers où elle est utilisée, sans toucher un mot identique mais sans
  rapport.

Conclusion : `:%s` pour du texte, le refactoring de l'IDE pour du code.

</details>

## Quiz

**1.** Le mode Vim d'un IDE est…
- A. exactement le même programme que Vim.
- B. une émulation qui reprend l'essentiel des commandes Vim.
- C. limité aux seuls déplacements `h j k l`.

**2.** Où configure-t-on IdeaVim ?
- A. Dans `~/.ideavimrc` (syntaxe `.vimrc`).
- B. Dans `settings.json`.
- C. On ne peut pas le configurer.

**3.** Pour renommer proprement une variable dans tout un projet de code, mieux vaut…
- A. `:%s/ancien/nouveau/g`.
- B. le refactoring « renommer » de l'IDE.
- C. retaper à la main.

**4.** Comment mappe-t-on une action de PhpStorm sur une touche dans IdeaVim ?
- A. `:action NomDeLAction<CR>` dans un mapping.
- B. C'est impossible.
- C. En modifiant le code source de l'IDE.

<details>
<summary>Voir les réponses</summary>

1. **B** — C'est une émulation : la grande majorité des commandes fonctionnent, mais ce n'est pas le
   binaire Vim.
2. **A** — `~/.ideavimrc`, qui partage la syntaxe du `.vimrc`. (VS Code, lui, utilise `settings.json`.)
3. **B** — Le refactoring de l'IDE comprend la portée et agit sur tout le projet sans faux positifs.
4. **A** — La commande spéciale `:action <Nom>` déclenche une action de l'IDE depuis un mapping Vim.

</details>

## Projet fil rouge

Jalon « ramener Vim dans ton vrai environnement ». Installe le mode Vim dans PhpStorm et porte ton
kit :

1. Installe IdeaVim, crée `~/.ideavimrc` en reprenant les options et la leader key de ton `~/.vimrc`.
2. Ajoute au moins deux mappings d'actions de l'IDE (par exemple `gd` pour la définition et
   `<leader>r` pour renommer).
3. Ouvre `panier.py` (ou un vrai projet) dans PhpStorm et édite-le **sans souris**, en mélangeant
   commandes Vim et actions de l'IDE.

Ajoute à `cheatsheet.md` :

```markdown
## Vim dans l'IDE
- IdeaVim (PhpStorm) : config `~/.ideavimrc` · `:action <Nom>` pour les actions de l'IDE
- vscodevim (VS Code) : config JSON `vim.leader`, `normalModeKeyBindingsNonRecursive`
- texte → `:%s` · code (renommer) → refactoring de l'IDE
- régler les conflits de raccourcis (Vim vs IDE) dans les réglages
```

---

[← Chapitre précédent](09-macros-et-marks.md) · [Sommaire](README.md) · [Chapitre suivant →](11-conclusion.md)
