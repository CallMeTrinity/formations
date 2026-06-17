# Variables, environnement et personnalisation

[← Chapitre précédent](07-chercher-et-transformer.md) · [Sommaire](README.md) · [Chapitre suivant →](09-premier-script.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer et utiliser des **variables** dans le shell ;
- distinguer une variable de shell d'une **variable d'environnement** ;
- comprendre le rôle du `PATH` et savoir comment le shell trouve les commandes ;
- utiliser la **substitution de commande** `$(...)` ;
- rendre tes réglages permanents et créer des **alias** dans le `.bashrc`.

Ce chapitre fait le pont entre l'utilisation interactive du terminal et l'écriture de scripts : les
variables sont le matériau de base de tout programme Bash.

## Créer une variable

Une **variable** est une boîte nommée qui stocke une valeur. On l'affecte avec `=` (sans espace
autour, c'est strict) et on lit son contenu en préfixant son nom de `$` :

```bash
$ prenom=Alex
$ echo $prenom
Alex
$ echo "Bonjour $prenom"
Bonjour Alex
```

> **Attention** — Pas d'espace autour du `=`. `prenom = Alex` échoue : le shell croit que tu lances
> une commande nommée `prenom`. Écris bien `prenom=Alex`, collé.

Si la valeur contient des espaces, entoure-la de guillemets :

```bash
$ message="bonjour tout le monde"
$ echo $message
bonjour tout le monde
```

### Guillemets simples ou doubles

C'est une source d'erreurs classique, autant la clarifier tout de suite :

- Les **guillemets doubles** `"..."` autorisent l'expansion : les `$variable` à l'intérieur sont
  remplacés par leur valeur.
- Les **guillemets simples** `'...'` sont littéraux : rien n'est interprété, le texte est pris tel
  quel.

```bash
$ echo "L'utilisateur est $prenom"
L'utilisateur est Alex
$ echo 'L utilisateur est $prenom'
L utilisateur est $prenom
```

> **À retenir** — En cas de doute, **mets des guillemets doubles** autour de tes variables :
> `"$prenom"`. Cela évite quantité de bugs, notamment quand une valeur est vide ou contient des
> espaces. On y reviendra dans les scripts.

## Variables de shell et variables d'environnement

Une variable que tu crées comme `prenom` n'existe que dans **ton shell courant**. Si tu lances un
programme depuis ce shell, il ne la voit pas. Pour qu'une variable soit **transmise** aux programmes
que tu lances, il faut l'**exporter** : elle devient alors une **variable d'environnement**.

```bash
$ export EDITEUR=nano        # transmise aux programmes lancés ensuite
```

Par convention, les variables d'environnement s'écrivent en MAJUSCULES. Le système en définit déjà
beaucoup ; affiche-les avec `env`, ou consulte-en une avec `echo` :

```bash
$ echo $HOME            # chemin de ton dossier personnel
/home/alex
$ echo $USER            # ton nom d'utilisateur
alex
$ echo $SHELL           # le shell par défaut
/bin/bash
```

Ces variables sont disponibles partout, y compris dans tes scripts. `$HOME` est particulièrement
utile pour écrire des chemins qui marchent quel que soit l'utilisateur.

## Le `PATH` : comment le shell trouve les commandes

Quand tu tapes `ls`, comment le shell sait-il où se trouve le programme `ls` ? Grâce à la variable
d'environnement `PATH`, qui contient une liste de dossiers séparés par `:` :

```bash
$ echo $PATH
/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin
```

À chaque commande, le shell parcourt ces dossiers **dans l'ordre** et exécute le premier programme
portant ce nom. C'est pourquoi `ls` (situé dans `/usr/bin`) fonctionne de partout, alors qu'un script
que tu viens d'écrire, lui, n'est pas dans le `PATH`.

C'est aussi l'explication de l'erreur « commande introuvable » du chapitre 1 : le shell a parcouru
tout le `PATH` sans trouver de programme de ce nom.

> **À retenir** — Pour lancer un programme **qui n'est pas dans le `PATH`** (comme ton script dans le
> dossier courant), tu dois donner son chemin explicitement : `./mon-script.sh`. Le `./` signifie
> « dans le dossier courant », justement parce que le dossier courant n'est (volontairement) pas dans
> le `PATH`.

## La substitution de commande : `$(...)`

Tu as vu `$(date)` au chapitre 5. La **substitution de commande** exécute une commande et **remplace**
l'expression par sa sortie. On peut ainsi stocker un résultat dans une variable :

```bash
$ aujourdhui=$(date +%Y-%m-%d)
$ echo $aujourdhui
2026-06-14
$ nb_fichiers=$(ls | wc -l)
$ echo "Il y a $nb_fichiers elements ici"
Il y a 7 elements ici
```

Ici `date +%Y-%m-%d` produit une date au format `année-mois-jour`. C'est exactement ce qu'il faut
pour nommer une sauvegarde de façon horodatée. La substitution `$(...)` est l'un des mécanismes les
plus utiles pour construire des scripts dynamiques.

> **Astuce** — Tu verras parfois l'ancienne syntaxe avec des accents graves : `` `date` ``. Elle fait
> la même chose mais se lit mal et s'imbrique mal. Préfère toujours `$(...)`.

## Rendre les réglages permanents : le `.bashrc`

Toutes les variables et tous les réglages que tu définis dans un terminal disparaissent quand tu le
fermes. Pour qu'ils soient appliqués **à chaque ouverture** d'un shell, place-les dans le fichier
`~/.bashrc`, lu automatiquement à chaque démarrage de Bash.

```bash
$ nano ~/.bashrc        # ouvre le fichier dans l'éditeur nano
```

Tu peux y ajouter, par exemple :

```bash
# Mes variables personnelles
export EDITEUR=nano
export SAUVEGARDES="$HOME/sauvegardes"
```

Après modification, applique les changements sans rouvrir de terminal avec :

```bash
$ source ~/.bashrc      # relit le fichier dans le shell courant
```

`source` exécute le contenu d'un fichier dans le shell **actuel** (contrairement à `./script.sh` qui
lance un nouveau shell). On s'en sert aussi pour recharger une configuration.

> **Note** — Sous macOS avec Zsh, le fichier équivalent est `~/.zshrc`. La logique est identique.

## Les alias : des raccourcis sur mesure

Un **alias** est un surnom pour une commande (souvent longue). On le définit ainsi :

```bash
$ alias ll='ls -lh'
$ alias ll
alias ll='ls -lh'
$ ll                    # exécute désormais ls -lh
```

Les alias définis dans le terminal disparaissent à la fermeture ; place-les dans ton `.bashrc` pour
les conserver. Quelques classiques très répandus :

```bash
alias ll='ls -lah'         # liste détaillée, fichiers cachés, tailles lisibles
alias ..='cd ..'           # remonter d'un dossier
alias grep='grep --color'  # colorer les résultats de grep
```

> **Attention** — Un alias peut **masquer** une commande existante si tu lui donnes le même nom (par
> exemple `alias rm='rm -i'`). C'est parfois voulu (sécurité), mais souviens-toi que tu as modifié le
> comportement par défaut, pour ne pas être surpris sur une autre machine.

## Résumé

- Une **variable** se crée avec `nom=valeur` (sans espace) et se lit avec `$nom` ; entoure-la de
  guillemets doubles `"$nom"` par sécurité.
- **Guillemets doubles** : les variables sont remplacées ; **guillemets simples** : tout est
  littéral.
- `export` transforme une variable en **variable d'environnement**, transmise aux programmes lancés.
- Le **`PATH`** liste les dossiers où le shell cherche les commandes ; un script hors `PATH` se lance
  avec `./script.sh`.
- La **substitution** `$(commande)` remplace l'expression par la sortie de la commande.
- Le **`.bashrc`** rend les réglages et **alias** permanents ; `source` recharge un fichier dans le
  shell courant.

## Exercices

### Exercice 1 — Variables et substitution

1. Crée une variable `projet` valant `linux-bash` et affiche la phrase `Je suis le projet linux-bash`.
2. Stocke dans une variable `date_jour` la date du jour au format `année-mois-jour`, puis affiche-la.
3. Stocke dans une variable `nb` le nombre de fichiers `.md` du dossier courant et affiche-le.

<details>
<summary>Voir le corrigé</summary>

La démarche : affectation simple, puis substitution `$(...)` pour les valeurs calculées.

```bash
$ projet=linux-bash
$ echo "Je suis le projet $projet"
Je suis le projet linux-bash
$ date_jour=$(date +%Y-%m-%d)
$ echo "$date_jour"
2026-06-14
$ nb=$(ls *.md 2>/dev/null | wc -l)
$ echo "$nb"
```

Le `2>/dev/null` évite un message d'erreur disgracieux s'il n'y a aucun `.md` dans le dossier.

</details>

### Exercice 2 — Un alias utile

Crée un alias `taille` qui affiche la taille des éléments du dossier courant, triés du plus gros au
plus petit. Rends-le permanent.

<details>
<summary>Voir le corrigé</summary>

La démarche : `du -sh *` donne la taille de chaque élément ; on trie par taille décroissante. On
définit l'alias, on le teste, puis on l'ajoute au `.bashrc`.

```bash
$ alias taille='du -sh * | sort -rh'
$ taille
```

`du` (*disk usage*) mesure l'espace occupé, `-s` résume par élément, `-h` en format lisible ; `sort
-rh` trie ces tailles lisibles en ordre décroissant. Pour le rendre permanent, ajoute la même ligne
`alias taille='du -sh * | sort -rh'` à la fin de `~/.bashrc`, puis `source ~/.bashrc`.

</details>

## Quiz

**1.** Comment lit-on le contenu d'une variable nommée `couleur` ?
- A. `echo couleur`
- B. `echo $couleur`
- C. `echo &couleur`

**2.** Quelle est la différence entre `"$x"` et `'$x'` ?
- A. Aucune
- B. Les doubles remplacent `$x` par sa valeur ; les simples affichent littéralement `$x`
- C. Les simples remplacent `$x` ; les doubles affichent littéralement `$x`

**3.** À quoi sert la variable `PATH` ?
- A. À stocker ton mot de passe
- B. À lister les dossiers où le shell cherche les commandes
- C. À mémoriser le dernier dossier visité

**4.** Que fait `racine=$(pwd)` ?
- A. Crée un fichier nommé `pwd`
- B. Stocke le chemin du dossier courant dans la variable `racine`
- C. Renomme le dossier courant en `racine`

<details>
<summary>Voir les réponses</summary>

1. **B** — On préfixe le nom de `$` pour lire la valeur.
2. **B** — Doubles = expansion des variables ; simples = texte brut.
3. **B** — Le `PATH` est la liste des dossiers parcourus pour trouver les programmes.
4. **B** — La substitution `$(pwd)` est remplacée par la sortie de `pwd`, stockée dans `racine`.

</details>

## Projet fil rouge

Avant d'écrire le script, tu vas exprimer ses paramètres sous forme de **variables** et fabriquer un
nom de sauvegarde **horodaté** — exactement ce que le script fera en interne.

1. Définis les deux paramètres clés comme variables :

   ```bash
   $ SOURCE="$HOME/donnees"
   $ DESTINATION="$HOME/sauvegardes"
   ```

2. Construis un nom d'archive horodaté avec une substitution de commande :

   ```bash
   $ horodatage=$(date +%Y-%m-%d_%H-%M-%S)
   $ nom_sauvegarde="donnees-$horodatage"
   $ echo "$nom_sauvegarde"
   donnees-2026-06-14_10-30-12
   ```

3. Réalise la copie en réutilisant ces variables :

   ```bash
   $ cp -r "$SOURCE" "$DESTINATION/$nom_sauvegarde"
   $ ls "$DESTINATION"
   ```

Tu as posé toutes les pièces du futur script : variables de configuration, nom horodaté, copie,
journal. Au prochain chapitre, tu les assembles dans un vrai fichier `sauvegarde.sh`.

---

[← Chapitre précédent](07-chercher-et-transformer.md) · [Sommaire](README.md) · [Chapitre suivant →](09-premier-script.md)
