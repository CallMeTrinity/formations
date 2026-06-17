# Introduction : le terminal, le shell, Linux

[Sommaire](README.md) · [Chapitre suivant →](02-systeme-de-fichiers.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- distinguer les mots **terminal**, **shell** et **Linux**, et dire à quoi chacun sert ;
- ouvrir un terminal sur ton ordinateur, que tu sois sous Linux, macOS ou Windows ;
- taper tes premières commandes et lire ce qui s'affiche ;
- demander de l'aide à une commande quand tu es bloqué.

## Pourquoi apprendre la ligne de commande

Tu connais déjà ton ordinateur par son interface graphique : des fenêtres, des icônes, une souris. La
**ligne de commande** est une autre façon de lui parler : tu **écris** un ordre, tu appuies sur
Entrée, l'ordinateur l'exécute et te répond par du texte.

Ça paraît moins confortable, et au début ça l'est. Mais cette approche a des avantages que la souris
n'aura jamais :

- **La vitesse.** Renommer 300 fichiers d'un coup, chercher un mot dans des milliers de lignes :
  l'affaire d'une commande.
- **La répétabilité.** Une commande peut être enregistrée et rejouée à l'identique. C'est la base de
  l'automatisation, le cœur de cette formation.
- **L'accès à distance.** Les serveurs (les ordinateurs qui hébergent les sites web, les bases de
  données…) n'ont presque jamais d'écran ni de souris. On les pilote en ligne de commande.
- **La stabilité.** Les commandes de base n'ont pas changé depuis quarante ans. Ce que tu apprends
  ici te servira toute ta carrière.

## Trois mots à ne pas confondre

On emploie souvent ces trois mots comme synonymes. Ils désignent en réalité trois choses
différentes qui s'emboîtent.

- **Linux** est un *système d'exploitation* : le logiciel de base qui fait fonctionner la machine
  (gérer la mémoire, les fichiers, les programmes). C'est un cousin d'Unix, un système né dans les
  années 1970. macOS appartient aussi à cette famille Unix, ce qui explique que presque tout ce
  qu'on verra fonctionne aussi sur Mac.
- Le **terminal** (ou *terminal émulateur*) est l'**application** qui t'affiche l'écran texte et te
  laisse taper. C'est la fenêtre. À elle seule, elle ne sait rien faire : elle transmet juste ce que
  tu tapes.
- Le **shell** est le **programme** qui tourne dans le terminal et qui, lui, comprend tes commandes,
  les exécute et te renvoie le résultat. C'est l'interprète. Le shell le plus répandu s'appelle
  *Bash* (*Bourne Again SHell*) — c'est celui de cette formation.

Une image : le terminal est le **téléphone** (l'appareil), le shell est la **personne** à l'autre
bout qui comprend ta langue et agit, et Linux est l'**entreprise** derrière qui fait réellement le
travail.

> **À retenir** — Tu **tapes** dans un terminal, c'est un **shell** (Bash) qui **interprète**, et le
> tout s'exécute sur un système de la famille **Unix** (Linux ou macOS).

## Ouvrir un terminal

Choisis la section qui correspond à ton ordinateur.

### Sous Linux

Cherche l'application nommée « Terminal » dans ton menu d'applications. Selon ta distribution, le
raccourci `Ctrl` + `Alt` + `T` l'ouvre directement.

### Sous macOS

Ouvre le **Launchpad** ou la recherche **Spotlight** (`Cmd` + Espace), tape `Terminal`, valide. Le
shell par défaut de macOS récent est *Zsh*, très proche de Bash : tout ce qu'on verra dans les
premiers chapitres fonctionne à l'identique.

### Sous Windows

Windows n'est pas un système Unix, mais Microsoft fournit **WSL** (*Windows Subsystem for Linux*),
une vraie distribution Linux intégrée à Windows. C'est la voie recommandée pour suivre cette
formation.

```powershell
REM Dans le menu Démarrer, ouvre "PowerShell", puis tape :
wsl --install
```

Redémarre quand on te le demande, crée ton nom d'utilisateur et ton mot de passe Linux, et tu auras
un terminal Linux complet. Ouvre-le ensuite en cherchant **Ubuntu** (ou **WSL**) dans le menu
Démarrer.

> **Astuce** — Pas envie d'installer quoi que ce soit pour tester ? Des sites comme un terminal
> Linux en ligne te donnent un shell jetable dans le navigateur. Pratique pour les premiers pas,
> mais installe un vrai terminal dès que possible pour le projet fil rouge.

## Ta première commande

Quand le terminal s'ouvre, tu vois quelque chose comme ceci :

```text
alex@ordi:~$
```

Cette ligne s'appelle le **prompt** (l'invite de commande). Elle te dit que le shell t'attend.
Décortiquons-la : `alex` est ton nom d'utilisateur, `ordi` le nom de la machine, `~` l'endroit où tu
te trouves (on verra ça au [chapitre 2](02-systeme-de-fichiers.md)), et `$` marque la fin de
l'invite. Tu tapes **après** le `$`.

> **Convention de cette formation** — Dans les exemples, une ligne qui commence par `$` est une
> commande **que tu tapes** (sans retaper le `$`). Les lignes sans `$` qui suivent sont la **sortie**
> affichée par l'ordinateur.

Tape `whoami` (« qui suis-je ») et appuie sur Entrée :

```bash
$ whoami
alex
```

Le shell répond par ton nom d'utilisateur. Essayons-en deux autres :

```bash
$ date
lundi 14 juin 2026, 10:42:07 (UTC+0200)

$ echo Bonjour le terminal
Bonjour le terminal
```

`date` affiche la date et l'heure. `echo` **répète** le texte que tu lui donnes : c'est une commande
qu'on retrouvera sans cesse, notamment dans les scripts.

### Anatomie d'une commande

La plupart des commandes suivent le même moule :

```text
commande  -options   arguments
   |          |          |
  quoi      réglages   sur quoi
```

Par exemple, `ls -l mes-photos` : la **commande** est `ls`, l'**option** `-l` (un réglage qui change
son comportement), et l'**argument** `mes-photos` (la cible). Les options commencent presque toujours
par un tiret. On rencontrera des centaines de commandes, mais elles partagent toutes cette grammaire.

> **Attention** — Le terminal est **sensible à la casse** : `Date` n'est pas `date`, et `Whoami`
> renverra une erreur. De même, les espaces comptent. Tape exactement ce qui est écrit.

## Quand une commande échoue

Tu vas te tromper, et c'est normal. Vois ce qui se passe si tu inventes une commande :

```bash
$ datte
datte : commande introuvable
```

Le shell ne connaît pas `datte` (faute de frappe pour `date`) et te le dit clairement. **Lis toujours
le message d'erreur** : la plupart du temps, il pointe le problème. « commande introuvable » signifie
presque toujours une faute de frappe ou un programme non installé.

> **À retenir** — Une erreur n'a rien cassé. Le terminal exécute ce que tu écris ; s'il ne comprend
> pas, il refuse et explique. Corrige et recommence.

## Demander de l'aide

Tu n'as pas à tout retenir. Chaque commande sait se présenter.

- L'option `--help` affiche un résumé rapide :

  ```bash
  $ ls --help
  ```

- La commande `man` (pour *manual*) ouvre le manuel complet d'une commande :

  ```bash
  $ man ls
  ```

  Tu navigues avec les flèches, et tu **quittes en appuyant sur `q`**. C'est le raccourci le plus
  utile à connaître dès maintenant : `q` pour *quit*.

> **Astuce** — Le réflexe d'un bon utilisateur du terminal n'est pas de tout mémoriser, mais de
> savoir **où chercher** : `--help` pour un rappel rapide, `man` pour les détails.

### Petits gestes qui font gagner du temps

| Touche | Effet |
| --- | --- |
| Flèche haut / bas | Rappeler les commandes précédentes |
| `Tab` | Compléter automatiquement un nom de commande ou de fichier |
| `Ctrl` + `C` | Interrompre une commande en cours |
| `Ctrl` + `L` | Effacer l'écran (équivalent de la commande `clear`) |

La touche `Tab` mérite une mention spéciale : commence à taper, appuie sur `Tab`, et le shell
complète pour toi. C'est plus rapide **et** ça évite les fautes de frappe.

## Résumé

- La **ligne de commande** consiste à donner des ordres écrits à l'ordinateur ; elle est rapide,
  répétable et fonctionne à distance.
- **Terminal** = la fenêtre ; **shell** (Bash) = l'interprète qui exécute ; **Linux/Unix** = le
  système en dessous.
- On ouvre un terminal nativement sous Linux et macOS, et via **WSL** sous Windows.
- Une commande suit le moule `commande -options arguments` et le terminal est **sensible à la casse**.
- Une erreur ne casse rien : on **lit le message** et on corrige.
- Pour s'aider : `commande --help`, `man commande` (quitter avec `q`), la touche `Tab` pour
  compléter et les flèches pour l'historique.

## Exercices

### Exercice 1 — Premiers contacts

Ouvre un terminal et trouve les commandes pour répondre à ces questions :

1. Quel est ton nom d'utilisateur ?
2. Quelle est la date et l'heure actuelles ?
3. Fais afficher la phrase `J'apprends Bash` par le terminal.

<details>
<summary>Voir le corrigé</summary>

La démarche : on réutilise les trois commandes du chapitre.

```bash
$ whoami
$ date
$ echo J'apprends Bash
```

Pour la dernière, l'apostrophe peut perturber le shell selon les cas. Si tu obtiens un comportement
bizarre (un prompt qui attend la suite), appuie sur `Ctrl` + `C` et entoure le texte de guillemets :

```bash
$ echo "J'apprends Bash"
J'apprends Bash
```

On reviendra sur le rôle des guillemets au chapitre sur les variables.

</details>

### Exercice 2 — Explorer un manuel

Ouvre le manuel de la commande `echo`, lis la première phrase qui décrit ce qu'elle fait, puis
quitte le manuel proprement.

<details>
<summary>Voir le corrigé</summary>

La démarche : `man` ouvre le manuel, les flèches font défiler, `q` quitte.

```bash
$ man echo
```

Tu lis en haut une description du type « display a line of text » (afficher une ligne de texte).
Appuie sur `q` pour revenir au prompt. Si `man echo` n'est pas disponible sur ton système, essaie
`echo --help`.

</details>

## Quiz

**1.** Dans la ligne `alex@ordi:~$`, que représente le `$` ?
- A. Une erreur
- B. La fin de l'invite de commande (le shell attend que tu tapes)
- C. Une variable d'argent

**2.** Quelle est la différence entre le terminal et le shell ?
- A. Aucune, ce sont des synonymes
- B. Le terminal est l'application (la fenêtre), le shell est le programme qui interprète les commandes
- C. Le terminal interprète les commandes, le shell les affiche

**3.** Tu tapes `Date` et obtiens « commande introuvable ». Pourquoi ?
- A. La commande `date` n'existe pas
- B. Le terminal est sensible à la casse : il faut `date` en minuscules
- C. Il faut redémarrer l'ordinateur

**4.** Comment quitte-t-on un manuel ouvert avec `man` ?
- A. En fermant la fenêtre du terminal
- B. Avec `Ctrl` + `C`
- C. En appuyant sur `q`

<details>
<summary>Voir les réponses</summary>

1. **B** — Le `$` marque la fin du prompt ; tu tapes juste après.
2. **B** — Le terminal est le contenant (la fenêtre), le shell (Bash) est l'interprète.
3. **B** — `Date` avec une majuscule n'est pas reconnu ; le terminal distingue les majuscules des
   minuscules.
4. **C** — `q` (pour *quit*) ferme le manuel et te ramène au prompt.

</details>

## Projet fil rouge

Notre fil rouge est un outil de sauvegarde, `sauvegarde.sh`. Pour l'instant, tu n'écris pas encore de
script : tu prépares juste le terrain.

1. Ouvre ton terminal.
2. Vérifie que tout répond en lançant `whoami` et `date`.
3. Avec `echo`, écris dans le terminal une phrase qui décrit l'objectif du projet, par exemple :

   ```bash
   $ echo "Objectif : sauvegarder automatiquement un dossier"
   Objectif : sauvegarder automatiquement un dossier
   ```

Au chapitre suivant, tu apprendras à te déplacer dans le système de fichiers pour repérer **quel
dossier** tu voudras sauvegarder.

---

[Sommaire](README.md) · [Chapitre suivant →](02-systeme-de-fichiers.md)
