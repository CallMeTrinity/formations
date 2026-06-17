# Écrire son premier script Bash

[← Chapitre précédent](08-environnement.md) · [Sommaire](README.md) · [Chapitre suivant →](10-logique-scripts.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer un fichier de script et le rendre exécutable ;
- comprendre le rôle du **shebang** `#!/bin/bash` ;
- lancer un script de trois façons et savoir laquelle choisir ;
- lire les **arguments** passés au script (`$1`, `$2`, `$@`…) ;
- demander une saisie à l'utilisateur avec `read`.

Jusqu'ici, tu tapais tes commandes une par une. Un **script** est simplement un fichier texte qui
contient une suite de commandes, exécutées de haut en bas. C'est le début de l'automatisation : tu
écris une fois, tu rejoues autant que tu veux.

## Un script, c'est une liste de commandes

Crée un fichier `bonjour.sh` avec un éditeur (`nano bonjour.sh`) et mets-y ceci :

```bash
#!/bin/bash
echo "Bonjour !"
echo "Nous sommes le $(date +%A %d %B)"
echo "Tu es connecte en tant que $USER"
```

Ce sont exactement des commandes que tu sais déjà taper. La nouveauté est la **première ligne**.

## Le shebang

La première ligne `#!/bin/bash` s'appelle le **shebang**. Le `#!` suivi d'un chemin indique au système
**quel programme** doit interpréter le fichier. Ici : Bash, situé dans `/bin/bash`.

Sans shebang, le système ne sait pas dans quel langage est écrit ton fichier. Avec lui, tu peux lancer
le script directement et c'est toujours le bon interprète qui s'en charge.

> **À retenir** — Le shebang doit être **la toute première ligne**, sans rien avant, pas même un
> espace ou une ligne vide. Mets-le systématiquement en tête de tes scripts Bash.

Les lignes commençant par `#` (ailleurs qu'en shebang) sont des **commentaires** : Bash les ignore.
Commente tes scripts pour expliquer le *pourquoi* :

```bash
#!/bin/bash
# Ce script affiche un message d'accueil personnalise.
echo "Bonjour $USER"
```

## Lancer un script

Il y a trois façons de l'exécuter. Comprends bien la différence.

### 1. En le rendant exécutable (la bonne méthode)

Comme vu au chapitre 6, un programme a besoin du droit `x`. On le donne, puis on lance le script en
indiquant son chemin :

```bash
$ chmod +x bonjour.sh        # une seule fois : on rend le script exécutable
$ ./bonjour.sh               # on le lance
Bonjour !
Nous sommes le samedi 14 juin
Tu es connecte en tant que alex
```

Le `./` est indispensable : il dit « le script est **dans le dossier courant** ». Sans lui, le shell
chercherait `bonjour.sh` dans le `PATH` (vu au chapitre 8) et ne le trouverait pas.

### 2. En appelant bash explicitement

```bash
$ bash bonjour.sh
```

Ici tu lances le programme `bash` en lui passant ton fichier. Pas besoin du droit `x` ni du shebang,
puisque tu désignes toi-même l'interprète. Pratique pour un test rapide.

### 3. Avec `source` (cas particulier)

```bash
$ source bonjour.sh          # ou：  . bonjour.sh
```

`source` exécute le script dans le shell **courant** au lieu d'en ouvrir un nouveau. À réserver aux
fichiers de configuration (comme `.bashrc`) : pour un script ordinaire, préfère `./script.sh`.

> **Attention** — La méthode 1 (`./script.sh`) lance le script dans un **nouveau** shell. Les
> variables qu'il définit n'existent donc pas dans ton terminal après coup. C'est normal et voulu :
> un script ne pollue pas ton environnement.

## Lire les arguments

Un script utile doit pouvoir travailler sur des données qu'on lui donne au lancement : ce sont les
**arguments**. Bash les met automatiquement à disposition dans des variables spéciales.

| Variable | Contient |
| --- | --- |
| `$1`, `$2`, `$3`… | le 1er, 2e, 3e argument |
| `$0` | le nom du script lui-même |
| `$#` | le **nombre** d'arguments reçus |
| `$@` | **tous** les arguments, un par un |

Crée `salut.sh` :

```bash
#!/bin/bash
# Salue la personne dont le nom est passe en argument.
echo "Salut $1 !"
echo "Tu as fourni $# argument(s)."
```

Puis lance-le avec un argument :

```bash
$ chmod +x salut.sh
$ ./salut.sh Alex
Salut Alex !
Tu as fourni 1 argument(s).
```

Le mot `Alex` que tu as écrit après le nom du script s'est retrouvé dans `$1`. Avec plusieurs
arguments :

```bash
$ ./salut.sh Alex Sam
Salut Alex !
Tu as fourni 2 argument(s).
```

`$1` vaut `Alex`, `$2` vaudrait `Sam`. Boucler sur `$@` pour tous les traiter sera l'objet du
[chapitre 10](10-logique-scripts.md).

> **Astuce** — Entoure toujours les arguments de guillemets dans le script : `"$1"`. Si quelqu'un
> passe un argument contenant un espace (un nom de fichier `mon dossier`), `"$1"` le garde entier
> alors que `$1` le couperait en deux.

## Demander une saisie : `read`

Plutôt que de tout passer en argument, un script peut **demander** une information à l'utilisateur
pendant son exécution avec `read` :

```bash
#!/bin/bash
echo "Comment t'appelles-tu ?"
read nom
echo "Enchante, $nom !"
```

`read nom` met en pause le script, attend que l'utilisateur tape quelque chose et appuie sur Entrée,
puis range la saisie dans la variable `nom`. L'option `-p` permet d'afficher l'invite sur la même
ligne :

```bash
read -p "Ton age : " age
echo "Tu as $age ans."
```

Arguments ou `read` ? Les **arguments** conviennent quand on veut automatiser (on fournit tout au
lancement, sans interaction) ; `read` convient pour un script interactif. Un bon script de production
privilégie les arguments, justement pour pouvoir tourner sans personne devant l'écran — c'est le cas
de notre fil rouge.

## Résumé

- Un **script** est un fichier de commandes exécutées de haut en bas.
- Le **shebang** `#!/bin/bash` (première ligne) désigne l'interprète ; `#` introduit un commentaire.
- On lance un script avec `./script.sh` (après `chmod +x`), ou `bash script.sh` pour un test ; `source`
  l'exécute dans le shell courant (réservé aux fichiers de config).
- Les **arguments** sont dans `$1`, `$2`, … ; `$#` les compte, `$@` les contient tous, `$0` est le nom
  du script. Entoure-les de guillemets : `"$1"`.
- `read variable` demande une saisie interactive (`-p` pour l'invite sur la même ligne).

## Exercices

### Exercice 1 — Script d'accueil

Écris un script `infos.sh` qui affiche, chacun sur une ligne : le nom de l'utilisateur courant, le
dossier courant, et la date du jour. Rends-le exécutable et lance-le.

<details>
<summary>Voir le corrigé</summary>

La démarche : on réutilise les variables d'environnement et `$(...)` du chapitre 8 dans un fichier
muni d'un shebang.

```bash
#!/bin/bash
# Affiche quelques informations sur la session.
echo "Utilisateur : $USER"
echo "Dossier      : $(pwd)"
echo "Date         : $(date +%Y-%m-%d)"
```

```bash
$ chmod +x infos.sh
$ ./infos.sh
Utilisateur : alex
Dossier      : /home/alex
Date         : 2026-06-14
```

</details>

### Exercice 2 — Script avec argument

Écris un script `creer-dossier.sh` qui reçoit un nom en argument et crée un dossier portant ce nom,
puis confirme la création. Teste-le avec `./creer-dossier.sh essai`.

<details>
<summary>Voir le corrigé</summary>

La démarche : `$1` contient le nom voulu ; on le passe à `mkdir`, entre guillemets pour gérer les
espaces.

```bash
#!/bin/bash
# Cree un dossier dont le nom est passe en argument.
mkdir -p "$1"
echo "Dossier '$1' cree."
```

```bash
$ chmod +x creer-dossier.sh
$ ./creer-dossier.sh essai
Dossier 'essai' cree.
```

Que se passe-t-il si on oublie l'argument ? `$1` est vide, `mkdir -p ""` ne fait rien d'utile et le
message est étrange. Gérer ce cas proprement est justement le sujet du prochain chapitre (les
conditions).

</details>

## Quiz

**1.** À quoi sert la ligne `#!/bin/bash` en tête d'un script ?
- A. C'est un commentaire sans effet
- B. Elle indique au système quel interprète utiliser (le shebang)
- C. Elle importe des commandes supplémentaires

**2.** Pourquoi lance-t-on un script avec `./script.sh` et non `script.sh` ?
- A. Le `./` rend le script plus rapide
- B. Parce que le dossier courant n'est pas dans le `PATH` ; `./` indique « ici »
- C. C'est une simple habitude, les deux marchent toujours

**3.** Dans un script lancé par `./salut.sh Alex Sam`, que vaut `$2` ?
- A. `Alex`
- B. `Sam`
- C. `salut.sh`

**4.** Que fait `read nom` ?
- A. Lit un fichier nommé `nom`
- B. Attend une saisie de l'utilisateur et la range dans la variable `nom`
- C. Affiche la valeur de la variable `nom`

<details>
<summary>Voir les réponses</summary>

1. **B** — Le shebang désigne l'interprète chargé d'exécuter le fichier.
2. **B** — Le dossier courant est volontairement hors du `PATH` ; `./` précise l'emplacement.
3. **B** — `$1` = `Alex`, `$2` = `Sam`. `$0` vaudrait `./salut.sh`.
4. **B** — `read` met le script en pause, lit l'entrée et la stocke dans la variable indiquée.

</details>

## Projet fil rouge

Il est temps d'écrire la **première version** de `sauvegarde.sh`. Elle assemble tout ce que tu as
préparé aux chapitres précédents.

Édite le fichier `sauvegarde.sh` (déjà rendu exécutable au chapitre 6) avec ce contenu :

```bash
#!/bin/bash
# sauvegarde.sh - copie un dossier source vers une archive horodatee.

# Configuration : la source vient du 1er argument, ou vaut ~/donnees par defaut.
SOURCE="$1"
DESTINATION="$HOME/sauvegardes"

# Nom d'archive horodate.
horodatage=$(date +%Y-%m-%d_%H-%M-%S)
nom_sauvegarde="sauvegarde-$horodatage"

# Copie et journalisation.
cp -r "$SOURCE" "$DESTINATION/$nom_sauvegarde"
echo "$(date) - sauvegarde de '$SOURCE' vers '$nom_sauvegarde'" >> "$DESTINATION/historique.log"

echo "Sauvegarde terminee : $DESTINATION/$nom_sauvegarde"
```

Lance-le en lui passant le dossier à sauvegarder :

```bash
$ ./sauvegarde.sh "$HOME/donnees"
Sauvegarde terminee : /home/alex/sauvegardes/sauvegarde-2026-06-14_10-45-03
```

Vérifie le résultat (`ls ~/sauvegardes`) et le journal (`cat ~/sauvegardes/historique.log`). Ton
script fonctionne — mais il ne se méfie de rien : que se passe-t-il si tu oublies l'argument, ou si le
dossier source n'existe pas ? Au prochain chapitre, tu ajoutes des **conditions** et des **boucles**
pour le rendre robuste.

---

[← Chapitre précédent](08-environnement.md) · [Sommaire](README.md) · [Chapitre suivant →](10-logique-scripts.md)
