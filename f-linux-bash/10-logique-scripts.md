# Logique dans les scripts

[← Chapitre précédent](09-premier-script.md) · [Sommaire](README.md) · [Chapitre suivant →](11-processus-et-cron.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- prendre des décisions avec `if`, `elif`, `else` ;
- écrire des **tests** sur des nombres, des chaînes et des fichiers ;
- répéter des actions avec les boucles `for` et `while` ;
- regrouper du code réutilisable dans des **fonctions** ;
- combiner des commandes avec `&&` et `||` et gérer les codes de retour.

C'est le chapitre qui transforme une simple liste de commandes en véritable **programme** : capable
de réagir, de répéter, et de se protéger des erreurs.

## Le code de retour : la base des décisions

Chaque commande, en se terminant, renvoie un **code de retour** (*exit status*) : `0` si tout s'est
bien passé, un autre nombre en cas d'échec. C'est invisible mais fondamental, car c'est sur ce code
que reposent toutes les décisions. On le consulte via la variable spéciale `$?` :

```bash
$ ls /home
alex
$ echo $?
0                 # succès
$ ls /dossier-inexistant
ls: ... : Aucun fichier ou dossier de ce type
$ echo $?
2                 # échec (code non nul)
```

> **À retenir** — Dans le monde Unix, **0 = succès**, tout le reste = échec. C'est l'inverse de
> l'intuition (où 0 voudrait dire « faux »), mais c'est la convention partout dans le shell.

## Prendre une décision : `if`

La structure `if` exécute un bloc seulement si une condition est vraie. Sa forme :

```bash
if condition; then
    # commandes si la condition est vraie
else
    # commandes sinon
fi
```

Remarque le `fi` (« if » à l'envers) qui ferme le bloc, et le `then` après la condition. La condition
est en réalité une **commande** : `if` regarde son code de retour (0 = on entre dans le `then`).

## Écrire des tests : `[ ... ]`

Pour comparer des valeurs ou examiner des fichiers, on utilise la commande de test, qui s'écrit entre
crochets. **Les espaces autour des crochets et des opérateurs sont obligatoires.**

### Tester des fichiers

```bash
if [ -f "$fichier" ]; then
    echo "C'est un fichier qui existe."
fi
```

| Test | Vrai si… |
| --- | --- |
| `-e chemin` | l'élément existe |
| `-f chemin` | c'est un fichier ordinaire existant |
| `-d chemin` | c'est un dossier existant |
| `-r` / `-w` / `-x` | on peut le lire / écrire / exécuter |
| `-z "$x"` | la chaîne est vide |
| `-n "$x"` | la chaîne n'est pas vide |

### Comparer des nombres et des chaînes

Attention, les opérateurs **diffèrent** selon qu'on compare des nombres ou du texte :

| Nombres | Chaînes | Signifie |
| --- | --- | --- |
| `-eq` | `=` | égal |
| `-ne` | `!=` | différent |
| `-lt` | — | strictement inférieur (*less than*) |
| `-gt` | — | strictement supérieur (*greater than*) |
| `-le` / `-ge` | — | inférieur/supérieur ou égal |

```bash
age=20
if [ "$age" -ge 18 ]; then
    echo "Majeur"
else
    echo "Mineur"
fi
```

Un exemple complet avec plusieurs branches via `elif` :

```bash
#!/bin/bash
note=$1
if [ "$note" -ge 16 ]; then
    echo "Tres bien"
elif [ "$note" -ge 10 ]; then
    echo "Admis"
else
    echo "A revoir"
fi
```

> **Attention** — Deux pièges classiques. D'abord, **les espaces** : `[ "$age" -ge 18 ]` fonctionne,
> `["$age"-ge 18]` non. Ensuite, **les guillemets** : si `$age` est vide, `[ -ge 18 ]` plante ;
> `[ "$age" -ge 18 ]` est plus sûr. Mets toujours tes variables entre guillemets dans un test.

## Enchaîner avec `&&` et `||`

Pour les décisions simples, on n'a pas toujours besoin d'un `if` complet. Deux opérateurs enchaînent
des commandes selon leur succès :

- `cmd1 && cmd2` : exécute `cmd2` **seulement si** `cmd1 a réussi` (code 0).
- `cmd1 || cmd2` : exécute `cmd2` **seulement si** `cmd1 a échoué`.

```bash
$ mkdir sauvegarde && echo "Dossier cree"        # le echo n'a lieu qu'en cas de succès
$ cd /dossier-inexistant || echo "Echec du cd"   # le echo n'a lieu qu'en cas d'échec
```

C'est concis et très répandu. On lit `&&` comme « et alors » et `||` comme « sinon ».

## Répéter : la boucle `for`

La boucle `for` parcourt une liste d'éléments et exécute le même bloc pour chacun :

```bash
for fruit in pomme banane cerise; do
    echo "J'aime la $fruit"
done
```

Comme `if` se ferme par `fi`, `for` se ferme par `done`. La vraie puissance vient de l'itération sur
des **fichiers** grâce aux jokers du chapitre 4 :

```bash
# Renommer tous les .txt en .md
for fichier in *.txt; do
    mv "$fichier" "${fichier%.txt}.md"
done
```

`${fichier%.txt}` retire le suffixe `.txt` du nom : c'est une manipulation de chaîne intégrée à Bash.
On boucle aussi très souvent sur les arguments du script avec `"$@"` :

```bash
#!/bin/bash
# Affiche chaque argument recu.
for arg in "$@"; do
    echo "Argument : $arg"
done
```

## Répéter sous condition : la boucle `while`

`while` répète **tant qu'**une condition reste vraie :

```bash
compteur=1
while [ "$compteur" -le 3 ]; do
    echo "Tour numero $compteur"
    compteur=$((compteur + 1))     # arithmetique entre $(( ... ))
done
```

`$(( ... ))` effectue un calcul arithmétique. Sans incrémenter `compteur`, la condition resterait
toujours vraie : ce serait une **boucle infinie** (on l'arrête avec `Ctrl` + `C`).

`while` sert aussi à lire un fichier ligne par ligne, motif extrêmement courant :

```bash
while read ligne; do
    echo "Lu : $ligne"
done < journal.log
```

## Regrouper du code : les fonctions

Une **fonction** est un bloc de code nommé, qu'on définit une fois et qu'on appelle autant qu'on veut.
Elle évite les répétitions et clarifie un script :

```bash
#!/bin/bash

# Definition de la fonction.
saluer() {
    echo "Bonjour $1, il est $(date +%H:%M)"
}

# Appels (les arguments se passent comme a un script : $1, $2...).
saluer Alex
saluer Sam
```

À l'intérieur d'une fonction, `$1`, `$2`… désignent les arguments **de la fonction**, exactement
comme pour un script. C'est l'outil pour découper un gros script en morceaux compréhensibles.

> **À retenir** — Dès qu'un bloc de commandes se répète ou mérite un nom parlant, fais-en une
> fonction. Un script bien découpé en petites fonctions est plus facile à lire, à tester et à
> corriger.

## Résumé

- Chaque commande renvoie un **code de retour** : `0` = succès, autre = échec (lisible dans `$?`).
- `if condition; then ... elif ... else ... fi` décide selon le code de retour d'une commande.
- Les **tests** `[ ... ]` (espaces obligatoires) examinent fichiers (`-f`, `-d`, `-e`), chaînes
  (`-z`, `=`) et nombres (`-eq`, `-lt`, `-ge`…).
- `&&` enchaîne en cas de succès, `||` en cas d'échec.
- `for x in liste; do ... done` répète sur chaque élément ; `while [ cond ]; do ... done` répète tant
  que la condition tient ; `$(( ... ))` calcule.
- Une **fonction** `nom() { ... }` regroupe du code réutilisable ; ses arguments sont `$1`, `$2`…

## Exercices

### Exercice 1 — Vérifier un argument

Écris un script `verifie.sh` qui reçoit un chemin en argument et affiche : `C'est un dossier`,
`C'est un fichier`, ou `Introuvable`, selon le cas.

<details>
<summary>Voir le corrigé</summary>

La démarche : on teste d'abord si c'est un dossier (`-d`), sinon si c'est un fichier (`-f`), sinon
c'est qu'il n'existe pas. On protège `$1` par des guillemets.

```bash
#!/bin/bash
cible="$1"
if [ -d "$cible" ]; then
    echo "C'est un dossier"
elif [ -f "$cible" ]; then
    echo "C'est un fichier"
else
    echo "Introuvable"
fi
```

```bash
$ ./verifie.sh /home
C'est un dossier
$ ./verifie.sh /etc/hostname
C'est un fichier
$ ./verifie.sh /nimporte-quoi
Introuvable
```

</details>

### Exercice 2 — Compter les lignes de plusieurs fichiers

Écris un script `compter.sh` qui reçoit une liste de fichiers en arguments et affiche, pour chacun,
son nom et son nombre de lignes. Ignore proprement les arguments qui ne sont pas des fichiers.

<details>
<summary>Voir le corrigé</summary>

La démarche : boucler sur `"$@"`, tester chaque argument avec `-f`, puis compter avec `wc -l`. La
substitution `$(...)` récupère le nombre.

```bash
#!/bin/bash
for fichier in "$@"; do
    if [ -f "$fichier" ]; then
        nb=$(wc -l < "$fichier")
        echo "$fichier : $nb lignes"
    else
        echo "$fichier : ignore (pas un fichier)"
    fi
done
```

```bash
$ ./compter.sh journal.log absent.txt
journal.log : 6 lignes
absent.txt : ignore (pas un fichier)
```

On utilise `wc -l < "$fichier"` (redirection) plutôt que `wc -l "$fichier"` pour que la sortie ne
contienne que le nombre, sans le nom du fichier.

</details>

## Quiz

**1.** Quel code de retour signifie « succès » sous Unix ?
- A. `1`
- B. `0`
- C. `-1`

**2.** Pourquoi `[$age -ge 18]` est-il incorrect ?
- A. Il manque les espaces autour des crochets et des opérandes
- B. `-ge` n'existe pas
- C. Il faudrait des accolades

**3.** Que fait `mkdir sauv && cd sauv` ?
- A. Crée le dossier puis entre dedans seulement si la création a réussi
- B. Crée le dossier et entre dedans dans tous les cas
- C. Entre dans le dossier puis le crée

**4.** Quelle boucle convient pour « tant que le compteur est inférieur à 10 » ?
- A. `for`
- B. `while`
- C. `if`

<details>
<summary>Voir les réponses</summary>

1. **B** — `0` = succès ; tout autre code signale un échec.
2. **A** — Les tests `[ ... ]` exigent des espaces : `[ "$age" -ge 18 ]`.
3. **A** — `&&` n'exécute la commande de droite que si celle de gauche a réussi.
4. **B** — `while` répète tant qu'une condition est vraie ; `for` parcourt une liste finie.

</details>

## Projet fil rouge

Tu vas rendre `sauvegarde.sh` **robuste** : il doit refuser de travailler dans le vide et signaler
clairement les problèmes, au lieu de produire des sauvegardes vides ou des messages d'erreur obscurs.

Reprends `sauvegarde.sh` et fais-le évoluer ainsi :

```bash
#!/bin/bash
# sauvegarde.sh - copie un dossier source vers une archive horodatee, avec verifications.

SOURCE="$1"
DESTINATION="$HOME/sauvegardes"

# 1. Verifier qu'un argument a ete fourni.
if [ -z "$SOURCE" ]; then
    echo "Usage : $0 <dossier-a-sauvegarder>"
    exit 1
fi

# 2. Verifier que la source existe et est un dossier.
if [ ! -d "$SOURCE" ]; then
    echo "Erreur : '$SOURCE' n'est pas un dossier."
    exit 1
fi

# 3. Creer le dossier de destination s'il manque.
mkdir -p "$DESTINATION"

# 4. Construire le nom horodate et copier.
horodatage=$(date +%Y-%m-%d_%H-%M-%S)
nom_sauvegarde="sauvegarde-$horodatage"

if cp -r "$SOURCE" "$DESTINATION/$nom_sauvegarde"; then
    echo "$(date) - OK - $SOURCE -> $nom_sauvegarde" >> "$DESTINATION/historique.log"
    echo "Sauvegarde terminee : $DESTINATION/$nom_sauvegarde"
else
    echo "$(date) - ECHEC - $SOURCE" >> "$DESTINATION/historique.log"
    echo "Erreur pendant la copie." >&2
    exit 1
fi
```

Deux nouveautés à noter :

- `exit 1` arrête le script en signalant un échec (code non nul), juste après avoir affiché un
  message clair.
- On place le `cp` directement comme condition du `if` : on journalise « OK » ou « ECHEC » selon que
  la copie a réussi.

Teste les cas limites pour vérifier la robustesse :

```bash
$ ./sauvegarde.sh                 # sans argument
Usage : ./sauvegarde.sh <dossier-a-sauvegarder>
$ ./sauvegarde.sh /pas-la         # dossier inexistant
Erreur : '/pas-la' n'est pas un dossier.
$ ./sauvegarde.sh "$HOME/donnees" # cas normal
Sauvegarde terminee : ...
```

Ton script se comporte maintenant comme un vrai outil. Au prochain chapitre, tu apprends à gérer les
**processus** et à le faire tourner **automatiquement** grâce à `cron`.

---

[← Chapitre précédent](09-premier-script.md) · [Sommaire](README.md) · [Chapitre suivant →](11-processus-et-cron.md)
