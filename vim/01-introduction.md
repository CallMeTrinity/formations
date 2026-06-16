# Introduction : pourquoi Vim et comment survivre

[Sommaire](README.md) · [Chapitre suivant →](02-modes-et-deplacement.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est Vim et pourquoi l'édition *modale* rend plus rapide ;
- vérifier que Vim est installé, ou l'installer ;
- ouvrir un fichier, taper du texte, l'enregistrer et **quitter** Vim (le réflexe de survie) ;
- lancer `vimtutor`, le tutoriel interactif livré avec Vim.

## C'est quoi, Vim ?

*Vim* (pour *Vi IMproved*, « vi amélioré ») est un éditeur de texte qui se pilote **entièrement au
clavier**. Il est présent ou installable sur quasiment toutes les machines : ton ordinateur, un
serveur distant auquel tu te connectes en *SSH* (un accès à distance en ligne de commande), un
conteneur. Apprendre Vim, c'est apprendre un outil qui te suivra partout pendant des décennies.

La grande idée de Vim, c'est l'**édition modale**. Dans un éditeur classique (un traitement de texte,
ton IDE en mode normal), le clavier sert presque uniquement à écrire des caractères. Pour déplacer le
curseur ou supprimer du texte, tu prends la souris ou des raccourcis avec plusieurs touches. Dans
Vim, le clavier change de rôle selon le **mode** dans lequel tu es :

- en mode **Insertion**, tu tapes du texte, comme partout ailleurs ;
- en mode **Normal**, chaque touche est une **commande** : `w` avance d'un mot, `d` supprime, `x`
  efface un caractère.

Résultat : tes doigts restent sur la rangée centrale du clavier, tu ne vises plus rien à la souris,
et tu décris tes intentions avec des commandes courtes. C'est déroutant au début, puis ça devient une
seconde nature.

> **À retenir** — La souris n'est pas « interdite » dans Vim, elle est **inutile**. Tout ce qu'on
> ferait à la souris se fait plus vite au clavier une fois les commandes connues.

### Vim, Vi, Neovim : qui est qui ?

Tu croiseras plusieurs noms. Pas d'inquiétude, ils sont très proches :

- **vi** : l'ancêtre, présent sur tout système Unix. Minimaliste.
- **Vim** : la version moderne et améliorée de vi. C'est celle de cette formation.
- **Neovim** (commande `nvim`) : une réécriture de Vim, compatible avec l'essentiel de ce que tu vas
  apprendre. Si tu utilises Neovim, **tout ce cours s'applique** ; on signalera les rares différences.

## Installer Vim

Ouvre un terminal et vérifie d'abord si Vim est déjà là :

```bash
vim --version
```

Si une longue description s'affiche (commençant par `VIM - Vi IMproved`), c'est installé : passe à la
suite. Sinon, installe-le selon ton système.

```bash
# macOS (avec Homebrew, https://brew.sh)
brew install vim

# Debian / Ubuntu
sudo apt update && sudo apt install vim

# Fedora
sudo dnf install vim

# Windows : installe plutôt Neovim (https://neovim.io) ou utilise WSL (Linux sous Windows)
```

> **Astuce** — Sur macOS, un Vim minimaliste est déjà préinstallé. L'installer via Homebrew te donne
> une version plus complète (notamment le support du presse-papier système, utile au chapitre 5).

## Survivre : ouvrir, écrire, quitter

C'est LE moment qui bloque tout le monde au début : « je suis entré dans Vim et je n'arrive plus à
en sortir ». On règle ça tout de suite.

Crée un fichier et ouvre-le :

```bash
vim brouillon.txt
```

Tu vois un écran presque vide avec des `~` dans la marge gauche (ils marquent les lignes qui
n'existent pas encore). Tu es en **mode Normal**. Si tu tapes du texte maintenant, il ne s'écrit pas :
chaque touche est interprétée comme une commande. C'est normal.

Pour **écrire du texte**, passe en mode Insertion en appuyant sur `i` (pour *insert*) :

```text
i
```

En bas de l'écran apparaît `-- INSERT --` (ou `-- INSERTION --`). Tu peux maintenant taper
normalement :

```text
Ma première ligne dans Vim.
```

Pour **revenir en mode Normal**, appuie sur la touche `Échap` (`Esc`). Le `-- INSERT --` disparaît.

> **À retenir** — `Échap` te ramène **toujours** au mode Normal. En cas de doute sur l'état de Vim,
> le réflexe est : appuie sur `Échap`, tu es en terrain connu.

Maintenant, **enregistrer et quitter**. Depuis le mode Normal, tape `:` (deux-points). Le curseur
saute en bas de l'écran : tu entres une **commande**. Tape `wq` puis `Entrée` :

```text
:wq
```

`w` veut dire *write* (enregistrer), `q` veut dire *quit* (quitter). Tu es de retour dans le terminal,
ton fichier est sauvegardé. Vérifie :

```bash
cat brouillon.txt
# Sortie :
# Ma première ligne dans Vim.
```

Voici les commandes de sortie à connaître par cœur :

| Tu veux… | Commande | Mémo |
| --- | --- | --- |
| Enregistrer et quitter | `:wq` ou `:x` | *write* + *quit* |
| Enregistrer sans quitter | `:w` | *write* |
| Quitter (si rien à enregistrer) | `:q` | *quit* |
| **Quitter sans enregistrer** (jeter les modifs) | `:q!` | *quit* forcé |

> **Attention** — Si Vim refuse de quitter avec `E37: No write since last change` (« des
> modifications n'ont pas été enregistrées »), c'est qu'il protège ton travail. Soit tu enregistres
> (`:wq`), soit tu jettes explicitement tes changements (`:q!`). Le `!` signifie « force, je sais ce
> que je fais ».

## Annuler une bêtise

Tant qu'on apprend, on fait des fausses manipulations. Deux commandes en mode Normal te sauvent :

- `u` annule la dernière action (*undo*) ;
- `Ctrl-r` rétablit ce que tu viens d'annuler (*redo*).

Tu peux appuyer plusieurs fois sur `u` pour remonter dans l'historique. Avec ça, aucune manipulation
n'est définitive : n'aie pas peur d'expérimenter.

## vimtutor : ton terrain d'entraînement officiel

Vim est livré avec un tutoriel interactif d'une trentaine de minutes. C'est le meilleur complément à
cette formation. Lance-le depuis le terminal :

```bash
vimtutor fr
```

Le `fr` demande la version française (si elle n'existe pas sur ton système, `vimtutor` tout court
ouvre la version anglaise). Il s'ouvre dans Vim, sur un fichier que tu peux modifier sans risque, et
te guide pas à pas. Garde-le sous le coude : on y reviendra.

## Résumé

- Vim est un éditeur **modal** piloté au clavier : en mode **Normal**, les touches sont des
  commandes ; en mode **Insertion**, tu tapes du texte.
- `i` passe en Insertion, `Échap` revient en Normal.
- Pour sortir : `:wq` (enregistrer et quitter), `:q!` (quitter sans enregistrer), `:w` (enregistrer).
- `u` annule, `Ctrl-r` rétablit. Rien n'est irréversible.
- `vimtutor fr` est le tutoriel interactif officiel.

## Exercices

### Exercice 1 — Entrer et sortir sans paniquer

Ouvre un fichier `essai.txt`, écris deux lignes de ton choix, enregistre et quitte. Rouvre-le pour
vérifier que ton texte est bien là.

<details>
<summary>Voir le corrigé</summary>

La démarche : on ouvre, on passe en Insertion pour écrire, on revient en Normal, on enregistre et
quitte.

```bash
vim essai.txt
```

Dans Vim :

1. Appuie sur `i` → tu es en `-- INSERT --`.
2. Tape ta première ligne, `Entrée`, ta deuxième ligne.
3. Appuie sur `Échap` → retour en Normal.
4. Tape `:wq` puis `Entrée`.

De retour au terminal :

```bash
cat essai.txt
```

Tes deux lignes s'affichent.

</details>

### Exercice 2 — Jeter ses modifications

Rouvre `essai.txt`, ajoute n'importe quoi, puis quitte **sans** enregistrer cet ajout. Vérifie que le
fichier n'a pas changé.

<details>
<summary>Voir le corrigé</summary>

La démarche : on modifie, puis on force la sortie sans enregistrer avec `:q!`.

1. `vim essai.txt`
2. `i`, tape du texte au hasard, `Échap`.
3. Tape `:q!` puis `Entrée` (le `!` jette les modifications non enregistrées).

```bash
cat essai.txt
```

Le contenu est celui de l'exercice 1 : ton ajout n'a pas été sauvegardé. Si tu avais tapé `:q` sans
le `!`, Vim aurait refusé avec `E37` pour protéger ton travail.

</details>

### Exercice 3 — Le tutoriel officiel

Lance `vimtutor fr` et fais au moins la première leçon (déplacement de base et sortie). C'est le
meilleur entraînement pour ancrer les réflexes.

<details>
<summary>Voir le corrigé</summary>

Il n'y a pas de solution unique : suis les instructions à l'écran. L'important est d'avoir manipulé
les touches de déplacement et la sortie dans un environnement guidé. On approfondit tout ça au
chapitre suivant.

</details>

## Quiz

**1.** Tu viens d'ouvrir Vim et tu tapes du texte, mais rien ne s'affiche normalement. Que se
passe-t-il ?
- A. Vim est en panne.
- B. Tu es en mode Normal : les touches sont interprétées comme des commandes.
- C. Le fichier est en lecture seule.

**2.** Comment quitter Vim en jetant les modifications non enregistrées ?
- A. `:wq`
- B. `:q`
- C. `:q!`

**3.** À quoi sert la touche `Échap` dans Vim ?
- A. À fermer le fichier.
- B. À revenir au mode Normal depuis n'importe quel mode.
- C. À annuler la dernière action.

**4.** Quelle commande annule la dernière action en mode Normal ?
- A. `u`
- B. `Ctrl-z`
- C. `:undo!`

<details>
<summary>Voir les réponses</summary>

1. **B** — En mode Normal, chaque touche est une commande. Il faut passer en Insertion (`i`) pour
   taper du texte.
2. **C** — `:q!` force la sortie sans enregistrer. `:wq` enregistre, `:q` refuse s'il y a des
   modifications.
3. **B** — `Échap` ramène toujours au mode Normal. (Annuler, c'est `u`.)
4. **A** — `u` annule (*undo*) ; `Ctrl-r` rétablit.

</details>

## Projet fil rouge

On lance le kit. Crée un dossier de travail et récupère le fichier qu'on va éditer tout au long de la
formation :

```bash
mkdir kit-vim && cd kit-vim
```

Crée avec Vim un fichier `panier.py` contenant ce petit programme (tape-le en mode Insertion, c'est
déjà un exercice de saisie) :

```python
# Calcul du total d'un panier
produits = [("pain", 1.20), ("lait", 0.95), ("oeufs", 2.50)]

total = 0
for nom, prix in produits:
    total = total + prix

print("Total du panier :", total)
```

Crée aussi un fichier `cheatsheet.md` avec un titre et les premières commandes apprises :

```markdown
# Ma cheat-sheet Vim

## Survie
- `i` : passer en mode Insertion
- `Échap` : revenir en mode Normal
- `:w` : enregistrer · `:q` : quitter · `:wq` : enregistrer et quitter · `:q!` : quitter sans enregistrer
- `u` : annuler · `Ctrl-r` : rétablir
```

À chaque chapitre, tu enrichiras `cheatsheet.md` et tu éditeras `panier.py` **sans la souris**. Pour
l'instant, l'objectif est juste d'avoir créé ces deux fichiers entièrement dans Vim.

---

[Sommaire](README.md) · [Chapitre suivant →](02-modes-et-deplacement.md)
