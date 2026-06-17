# Personnaliser Vim avec le .vimrc

[← Chapitre précédent](07-plusieurs-fichiers.md) · [Sommaire](README.md) · [Chapitre suivant →](09-macros-et-marks.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre ce qu'est le fichier `.vimrc` et où il se trouve ;
- régler les **options** essentielles (`number`, `ignorecase`, indentation…) ;
- créer tes propres **raccourcis** avec `map` et la *leader key* ;
- partir d'un `.vimrc` de base, commenté, que tu pourras faire évoluer.

Jusqu'ici, les réglages comme `:set number` disparaissaient à la fermeture de Vim. Le `.vimrc` rend
ces réglages **permanents** et te permet de façonner Vim à ta main.

## Le fichier .vimrc

Au démarrage, Vim lit un fichier de configuration et exécute chaque ligne comme une commande. Ce
fichier s'appelle `.vimrc` et vit dans ton dossier personnel :

```bash
# Vim
~/.vimrc            # macOS / Linux

# Neovim, c'est un autre emplacement :
~/.config/nvim/init.vim
```

Le `~` désigne ton dossier personnel (*home*). Le point devant le nom le rend « caché » (il
n'apparaît pas dans un `ls` normal, mais existe bien). Pour le créer ou l'éditer :

```bash
vim ~/.vimrc
```

> **À retenir** — Chaque ligne du `.vimrc` est une commande Vim, **sans** le `:` du début. Dans Vim
> tu tapes `:set number` ; dans le `.vimrc` tu écris `set number`. Les lignes commençant par `"`
> (guillemet droit) sont des **commentaires**.

Après modification, recharge la config sans redémarrer Vim :

```text
:source ~/.vimrc     " relit le fichier de config
```

## Les options essentielles

Une *option* règle un comportement de Vim. On l'active avec `set <option>` et on la désactive avec
`set no<option>`. Les plus utiles pour coder :

| Option | Effet |
| --- | --- |
| `set number` | affiche les numéros de ligne |
| `set relativenumber` | numéros **relatifs** à la ligne courante (facilite `5j`, `3k`) |
| `set ignorecase` | recherche insensible à la casse |
| `set smartcase` | …sauf si tu tapes une majuscule (recherche alors sensible) |
| `set incsearch` | saute aux résultats pendant que tu tapes la recherche |
| `set hlsearch` | surligne toutes les occurrences trouvées |
| `set expandtab` | les tabulations deviennent des espaces |
| `set shiftwidth=4` | une indentation = 4 espaces |
| `set tabstop=4` | une tabulation s'affiche comme 4 espaces |
| `set autoindent` | garde l'indentation de la ligne précédente |
| `set hidden` | autorise à changer de buffer sans enregistrer (cf. chapitre 7) |
| `set mouse=a` | active la souris (optionnel : utile au tout début, à retirer ensuite) |
| `syntax on` | colore la syntaxe du code |
| `set clipboard=unnamedplus` | fait du presse-papier système le registre par défaut |

La combinaison `number` + `relativenumber` est particulièrement utile : la ligne courante affiche son
numéro absolu, les autres affichent leur **distance**. Tu vois directement qu'une ligne est à « 7 »
et tu tapes `7j` pour y aller.

> **Astuce** — `clipboard=unnamedplus` fait que `y` et `p` utilisent directement le presse-papier
> système, sans le préfixe `"+`. Pratique, mais sache que tes copies « polluent » alors ton
> presse-papier global. À toi de voir.

## Créer ses propres raccourcis (mappings)

Un *mapping* associe une touche (ou une séquence) à une commande. La forme générale :

```text
<mode>map <ta-touche> <ce-que-ça-fait>
```

Le préfixe indique le mode où le raccourci s'applique :

- `nnoremap` : en mode **N**ormal ;
- `inoremap` : en mode **I**nsertion ;
- `vnoremap` : en mode **V**isuel.

Le `nore` au milieu signifie *no recursion* : c'est la forme **à utiliser par défaut**, elle évite
qu'un mapping en déclenche un autre en cascade. Quelques exemples parlants :

```vim
" Éteindre la surbrillance de recherche avec une touche dédiée
nnoremap <silent> <leader>h :nohlsearch<CR>

" Enregistrer rapidement
nnoremap <leader>w :w<CR>

" Se déplacer entre fenêtres sans le Ctrl-w (plus court)
nnoremap <C-h> <C-w>h
nnoremap <C-j> <C-w>j
nnoremap <C-k> <C-w>k
nnoremap <C-l> <C-w>l
```

`<CR>` représente la touche `Entrée` (*carriage return*), `<C-h>` représente `Ctrl-h`, `<silent>`
empêche d'afficher la commande. Ces notations sont la façon d'écrire des touches spéciales dans un
mapping.

### La leader key

La *leader key* est une touche « préfixe » que tu réserves à **tes** raccourcis, pour ne pas écraser
les commandes natives de Vim. Par convention, on choisit souvent l'espace ou la virgule :

```vim
" Définir la leader key (à mettre AVANT les mappings qui l'utilisent)
let mapleader = " "
```

Ensuite, `<leader>` dans un mapping représente cette touche. Avec l'espace comme leader, le mapping
`nnoremap <leader>w :w<CR>` veut dire : « Espace puis w enregistre ». Tu construis ainsi tout un
vocabulaire personnel (`<leader>w` enregistrer, `<leader>q` quitter, `<leader>h` éteindre la
surbrillance…) sans collision.

> **À retenir** — La leader key te donne un espace de raccourcis bien à toi. Définis-la **avant** les
> mappings qui s'en servent, sinon ils ne la prendront pas en compte.

## Un .vimrc de départ

Voici une base saine, entièrement commentée. Copie-la dans `~/.vimrc`, lis chaque ligne, et adapte au
fil du temps. Ne mets rien que tu ne comprends pas : c'est le principe « pas de magie » de la
formation.

```vim
" === Base ===
set nocompatible          " comportement Vim moderne (pas l'ancien vi)
syntax on                 " coloration syntaxique
filetype plugin indent on " détecte le type de fichier et adapte l'indentation

" === Affichage ===
set number                " numéros de ligne
set relativenumber        " numéros relatifs (facilite les sauts comme 7j)
set cursorline            " surligne la ligne courante
set showcmd               " montre la commande en cours de frappe (en bas à droite)
set wildmenu              " menu de complétion pour les commandes (:e <Tab>)

" === Recherche ===
set ignorecase            " recherche insensible à la casse...
set smartcase             " ...sauf si tu tapes une majuscule
set incsearch             " saute au résultat pendant la frappe
set hlsearch              " surligne toutes les occurrences

" === Indentation ===
set expandtab             " tabulations -> espaces
set shiftwidth=4          " une indentation = 4 espaces
set tabstop=4             " une tabulation affichée comme 4 espaces
set autoindent            " conserve l'indentation de la ligne précédente

" === Confort ===
set hidden                " change de buffer sans devoir enregistrer
set scrolloff=5           " garde 5 lignes de marge autour du curseur
set clipboard=unnamedplus " y/p utilisent le presse-papier système

" === Raccourcis personnels ===
let mapleader = " "                        " la barre d'espace est la leader key
nnoremap <silent> <leader>h :nohlsearch<CR> " éteindre la surbrillance
nnoremap <leader>w :w<CR>                   " enregistrer
nnoremap <leader>q :q<CR>                   " quitter

" Naviguer entre fenêtres avec Ctrl + h/j/k/l
nnoremap <C-h> <C-w>h
nnoremap <C-j> <C-w>j
nnoremap <C-k> <C-w>k
nnoremap <C-l> <C-w>l
```

> **Attention** — Ne copie pas des `.vimrc` géants trouvés en ligne « parce que ça a l'air pro ». Tu
> hériteras de comportements que tu ne maîtrises pas et que tu ne sauras pas déboguer. Commence petit,
> ajoute une ligne quand tu en comprends le besoin.

### Se désintoxiquer des flèches (optionnel)

Si tu veux te forcer à utiliser `h j k l`, désactive les flèches en mode Normal :

```vim
" Désactive les flèches en Normal (entraînement)
nnoremap <Up> <Nop>
nnoremap <Down> <Nop>
nnoremap <Left> <Nop>
nnoremap <Right> <Nop>
```

`<Nop>` veut dire « ne rien faire ». Au début c'est inconfortable, mais c'est le moyen le plus rapide
de prendre le réflexe. Retire ces lignes quand l'habitude est acquise.

## Résumé

- Le `.vimrc` (`~/.vimrc`) est lu au démarrage : chaque ligne est une commande **sans** le `:`, les
  commentaires commencent par `"`.
- `:source ~/.vimrc` recharge la config sans redémarrer.
- Options clés : `number`, `relativenumber`, `ignorecase`+`smartcase`, `incsearch`+`hlsearch`,
  `expandtab`+`shiftwidth`, `hidden`, `syntax on`.
- Mappings : `nnoremap`/`inoremap`/`vnoremap` (toujours la forme `nore`). `<CR>` = Entrée, `<C-x>` =
  Ctrl-x.
- La **leader key** (`let mapleader = " "`) réserve un espace de raccourcis personnels.

## Exercices

### Exercice 1 — Ton premier .vimrc

Crée `~/.vimrc` avec au minimum les numéros de ligne, la coloration syntaxique et la recherche
intelligente. Vérifie que les numéros s'affichent au prochain lancement de Vim.

<details>
<summary>Voir le corrigé</summary>

La démarche : créer le fichier, y mettre les options, sauvegarder, rouvrir un fichier pour constater.

```bash
vim ~/.vimrc
```

Contenu minimal :

```vim
syntax on
set number
set relativenumber
set ignorecase
set smartcase
set incsearch
set hlsearch
```

`:wq`, puis `vim panier.py` : les numéros de ligne apparaissent et la coloration est active. Tu peux
aussi faire `:source ~/.vimrc` dans un Vim déjà ouvert.

</details>

### Exercice 2 — Un raccourci leader

Ajoute la leader key (espace) et un mapping `<leader>w` qui enregistre le fichier. Teste-le.

<details>
<summary>Voir le corrigé</summary>

La démarche : définir `mapleader` **avant** le mapping, recharger, essayer.

Dans `~/.vimrc` :

```vim
let mapleader = " "
nnoremap <leader>w :w<CR>
```

Recharge avec `:source ~/.vimrc` (ou relance Vim). Modifie un fichier, puis appuie sur `Espace` puis
`w` : le fichier est enregistré. Si rien ne se passe, vérifie que `let mapleader` est bien **au-dessus**
du mapping.

</details>

### Exercice 3 — Lire avant de copier

Prends une ligne du `.vimrc` de départ que tu ne connaissais pas (par exemple `set scrolloff=5`),
change sa valeur, recharge, et observe la différence de comportement.

<details>
<summary>Voir le corrigé</summary>

La démarche : on expérimente pour comprendre, conformément au principe « pas de magie ».

1. Mets `set scrolloff=5` à `set scrolloff=999`.
2. `:source ~/.vimrc`.
3. Déplace-toi avec `j`/`k` dans un fichier long : le curseur reste désormais **au milieu** de
   l'écran, le texte défile autour. `scrolloff` est le nombre de lignes de marge gardées autour du
   curseur ; à 999, il est toujours centré.

Remets la valeur qui te convient (5 est un bon compromis).

</details>

## Quiz

**1.** Où se trouve le fichier de configuration de Vim ?
- A. `~/.vimrc`
- B. `/etc/vim.conf`
- C. `~/vim/config.txt`

**2.** Comment écrit-on un commentaire dans un `.vimrc` ?
- A. `# commentaire`
- B. `// commentaire`
- C. `" commentaire`

**3.** Que fait `let mapleader = " "` ?
- A. Définit la barre d'espace comme leader key.
- B. Ajoute un espace à chaque ligne.
- C. Active la souris.

**4.** Pourquoi préférer `nnoremap` à `nmap` ?
- A. C'est plus court.
- B. `nnoremap` évite les déclenchements en cascade entre mappings (*no recursion*).
- C. `nmap` ne fonctionne pas.

<details>
<summary>Voir les réponses</summary>

1. **A** — `~/.vimrc` (et `~/.config/nvim/init.vim` pour Neovim).
2. **C** — Un commentaire commence par un guillemet droit `"`.
3. **A** — La leader key devient l'espace, utilisée comme `<leader>` dans les mappings.
4. **B** — La forme `nore` empêche un mapping d'en déclencher un autre. C'est la forme à privilégier.

</details>

## Projet fil rouge

Jalon « premiers mappings personnels ». Ton `~/.vimrc` devient un livrable du kit :

1. Crée (ou complète) ton `~/.vimrc` à partir de la base de ce chapitre. Garde **uniquement** les
   lignes que tu comprends.
2. Ajoute au moins deux mappings personnels avec ta leader key (par exemple `<leader>w` enregistrer et
   `<leader>h` éteindre la surbrillance).
3. Copie ton `.vimrc` (ou un lien vers lui) dans le dossier `kit-vim`, par exemple
   `cp ~/.vimrc kit-vim/vimrc-de-reference`, pour le garder avec le projet.

Ajoute à `cheatsheet.md` :

```markdown
## Configuration (.vimrc)
- fichier : `~/.vimrc` · commentaire : `"` · recharger : `:source ~/.vimrc`
- options : `set number relativenumber ignorecase smartcase incsearch hlsearch expandtab shiftwidth=4 hidden`
- mappings : `nnoremap` `inoremap` `vnoremap` (forme `nore`) · `<CR>` = Entrée · `<C-x>` = Ctrl-x
- leader : `let mapleader = " "` puis `<leader>...`
```

---

[← Chapitre précédent](07-plusieurs-fichiers.md) · [Sommaire](README.md) · [Chapitre suivant →](09-macros-et-marks.md)
