# Chercher et transformer du texte

[← Chapitre précédent](06-permissions.md) · [Sommaire](README.md) · [Chapitre suivant →](08-environnement.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- retrouver des fichiers par nom, type ou date avec `find` ;
- extraire des colonnes avec `cut`, trier avec `sort`, dédoublonner avec `uniq` ;
- remplacer du texte à la volée avec `sed` ;
- extraire et reformater des champs avec `awk`, et combiner tous ces outils par des tuyaux.

Ce chapitre rassemble les outils « couteau suisse » du traitement de texte. Tu n'as pas besoin de
tout maîtriser à fond : l'objectif est de savoir qu'ils existent, ce que chacun fait, et de pouvoir
les recombiner.

## Retrouver des fichiers : `find`

`grep` cherche **dans** les fichiers ; `find` cherche **les fichiers eux-mêmes**, par leurs
caractéristiques. Sa syntaxe : `find <où> <critères>`.

```bash
$ find . -name "*.log"          # tous les .log sous le dossier courant (récursif)
./journal.log
./sauvegardes/historique.log
```

Le `.` indique de chercher à partir du dossier courant, en descendant dans tous les sous-dossiers.
Les critères les plus utiles :

| Critère | Sélectionne |
| --- | --- |
| `-name "motif"` | par nom (jokers acceptés, entre guillemets) |
| `-iname "motif"` | comme `-name` mais insensible à la casse |
| `-type f` / `-type d` | seulement les fichiers / seulement les dossiers |
| `-size +10M` | plus gros que 10 méga-octets (`+`/`-` pour plus/moins) |
| `-mtime -7` | modifié il y a moins de 7 jours |

```bash
$ find . -type d -name "archives"      # les dossiers nommés archives
$ find . -type f -size +1M             # les fichiers de plus de 1 Mo
$ find . -name "*.tmp" -mtime +30      # les .tmp vieux de plus de 30 jours
```

`find` peut aussi **agir** sur ce qu'il trouve avec `-delete` ou `-exec` :

```bash
$ find . -name "*.tmp" -delete                 # supprime tous les .tmp trouvés
$ find . -name "*.txt" -exec wc -l {} \;       # exécute wc -l sur chaque .txt
```

Dans `-exec`, le `{}` est remplacé par chaque fichier trouvé, et `\;` marque la fin de la commande.

> **Attention** — `find ... -delete` est puissant et **irréversible**. Lance d'abord la commande
> **sans** `-delete` pour voir la liste de ce qui sera supprimé, puis ajoute `-delete` seulement si
> la liste est correcte.

## Extraire des colonnes : `cut`

Beaucoup de fichiers sont organisés en colonnes séparées par un caractère (espace, virgule,
deux-points). `cut` en extrait les colonnes voulues. Prenons un extrait de `/etc/passwd`, où les
champs sont séparés par `:` :

```bash
$ head -n 2 /etc/passwd
root:x:0:0:root:/root:/bin/bash
alex:x:1000:1000:Alex:/home/alex:/bin/bash

$ cut -d: -f1 /etc/passwd | head -n 2
root
alex
```

`-d:` précise le **délimiteur** (ici `:`), `-f1` le **champ** (la première colonne). On peut demander
plusieurs champs :

```bash
$ cut -d: -f1,7 /etc/passwd | head -n 2     # nom et shell de connexion
root:/bin/bash
alex:/bin/bash
```

## Trier et dédoublonner : `sort` et `uniq`

Tu as déjà croisé ces deux-là au chapitre 5. Précisons leurs options utiles.

```bash
$ sort fruits.txt          # tri alphabétique
$ sort -r fruits.txt       # ordre inverse
$ sort -n nombres.txt      # tri numérique (sinon "10" passe avant "2")
$ sort -u fruits.txt       # trie ET supprime les doublons
```

`uniq` regroupe les lignes identiques **consécutives** ; on le fait donc précéder de `sort`. L'option
`-c` compte les occurrences :

```bash
$ sort fruits.txt | uniq -c
      1 banane
      2 pomme
```

Un idiome très courant : **le classement par fréquence**, qui répond à « quelles sont les valeurs les
plus fréquentes ? »

```bash
# Les niveaux de log les plus fréquents, du plus au moins fréquent
$ grep -oE 'INFO|ERROR|WARNING' journal.log | sort | uniq -c | sort -rn
      3 INFO
      2 ERROR
      1 WARNING
```

On lit cette chaîne de gauche à droite : extraire les niveaux, les trier, les compter, puis trier le
résultat par nombre décroissant (`-rn`). C'est un schéma que tu réutiliseras sans cesse.

## Remplacer du texte : `sed`

`sed` (*stream editor*) transforme un flux de texte. Son usage le plus fréquent, et le seul à retenir
pour l'instant, est le **remplacer** : `s/ancien/nouveau/`.

```bash
$ echo "j'aime les pommes" | sed 's/pommes/poires/'
j'aime les poires
```

Par défaut, `sed` ne remplace que la **première** occurrence de chaque ligne. Le drapeau `g`
(*global*) remplace toutes les occurrences :

```bash
$ echo "a-b-c-d" | sed 's/-/ /g'
a b c d
```

Appliqué à un fichier, `sed` affiche le résultat transformé **sans modifier le fichier** :

```bash
$ sed 's/ERROR/ERREUR/g' journal.log        # affiche la version traduite
```

Pour modifier le fichier sur place, on ajoute `-i` (*in place*). C'est puissant et **définitif** :
travaille sur une copie tant que tu n'es pas sûr.

```bash
$ sed -i 's/ERROR/ERREUR/g' journal.log     # modifie réellement le fichier
```

> **Astuce** — Le séparateur de `sed` n'est pas obligé d'être `/`. Pour remplacer un chemin contenant
> des `/`, utilise un autre caractère : `sed 's#/home/alex#/home/sam#'` évite d'échapper chaque barre.

## Extraire et reformater : `awk`

`awk` est un mini-langage de traitement de texte orienté **colonnes**. Il découpe automatiquement
chaque ligne en champs nommés `$1`, `$2`, … (`$0` = la ligne entière). Pour un débutant, retiens deux
usages.

Afficher des colonnes précises (par défaut, le séparateur est l'espace) :

```bash
$ awk '{print $1, $2}' journal.log         # la date et le niveau de chaque ligne
2026-06-10 INFO
2026-06-10 INFO
2026-06-11 ERROR
...
```

Filtrer des lignes selon une condition, puis afficher un champ :

```bash
$ awk '$2 == "ERROR" {print $0}' journal.log     # les lignes dont le 2e champ vaut ERROR
2026-06-11 ERROR  echec de connexion
2026-06-12 ERROR  service interrompu
```

`awk` recoupe les rôles de `grep` et `cut`, mais en plus souple, car il combine condition et
sélection de colonnes. Inutile d'en faire un expert : sache l'utiliser pour « affiche la colonne N »
et « garde les lignes où la colonne N vaut X ».

> **À retenir** — Choisis l'outil le plus simple qui fait le travail : `grep` pour filtrer des lignes,
> `cut` pour découper sur un délimiteur fixe, `awk` quand il faut combiner condition et colonnes,
> `sed` pour remplacer. Et toujours, le tuyau `|` pour les enchaîner.

## Résumé

- `find <où> <critères>` retrouve des fichiers par nom (`-name`), type (`-type`), taille (`-size`),
  date (`-mtime`), et peut agir avec `-delete` ou `-exec`.
- `cut -d<sep> -f<champs>` extrait des colonnes ; `sort` trie (`-n` numérique, `-r` inverse, `-u`
  unique) ; `uniq -c` compte les doublons consécutifs.
- L'idiome `sort | uniq -c | sort -rn` classe par fréquence.
- `sed 's/ancien/nouveau/g'` remplace du texte (`-i` pour modifier le fichier, irréversible).
- `awk '{print $2}'` et `awk '$2=="X"{print}'` extraient et filtrent par colonnes.

## Exercices

### Exercice 1 — Inventaire avec `find`

Dans ton dossier personnel :

1. Liste tous les fichiers `.log` présents, où qu'ils soient.
2. Liste uniquement les **dossiers** (pas les fichiers) du dossier courant et de ses sous-dossiers.
3. Trouve les fichiers modifiés il y a moins de 1 jour.

<details>
<summary>Voir le corrigé</summary>

La démarche : un critère `find` par question.

```bash
$ find . -name "*.log"
$ find . -type d
$ find . -type f -mtime -1
```

`-mtime -1` signifie « modifié il y a moins de 1 jour ». Un nombre positif (`+1`) signifierait « il y
a plus de 1 jour ».

</details>

### Exercice 2 — Classement des utilisateurs connectés

À partir de `journal.log`, on veut savoir **qui s'est connecté le plus souvent**. Les lignes de
connexion ressemblent à `... connexion de alex`. Construis une chaîne de commandes qui affiche chaque
prénom avec son nombre de connexions, du plus fréquent au moins fréquent.

<details>
<summary>Voir le corrigé</summary>

La démarche : isoler les lignes de connexion (`grep`), extraire le prénom (dernier champ avec `awk`),
puis appliquer l'idiome de classement par fréquence.

```bash
$ grep "connexion de" journal.log | awk '{print $NF}' | sort | uniq -c | sort -rn
      1 sam
      1 alex
```

`$NF` désigne le **dernier** champ de la ligne (*Number of Fields*), pratique quand on ignore sa
position exacte. Avec notre petit journal, alex et sam ont chacun une connexion ; sur un vrai journal,
le classement ferait ressortir les plus actifs.

</details>

## Quiz

**1.** Quelle commande retrouve tous les fichiers nommés `*.txt` sous le dossier courant ?
- A. `grep "*.txt" .`
- B. `find . -name "*.txt"`
- C. `ls -r *.txt`

**2.** Que fait `cut -d: -f1 /etc/passwd` ?
- A. Supprime la première colonne
- B. Affiche la première colonne, en prenant `:` comme séparateur
- C. Trie le fichier par la première colonne

**3.** Pourquoi écrit-on souvent `sort | uniq` plutôt que `uniq` seul ?
- A. Parce que `uniq` ne regroupe que les lignes identiques **consécutives**
- B. Parce que `uniq` ne fonctionne pas sans `sort`
- C. Parce que `sort` supprime les doublons et `uniq` trie

**4.** Que fait `sed 's/a/b/g'` sur une ligne ?
- A. Remplace le premier `a` par `b`
- B. Remplace tous les `a` par `b`
- C. Supprime tous les `a`

<details>
<summary>Voir les réponses</summary>

1. **B** — `find` cherche les fichiers par leurs caractéristiques ; `grep` cherche dans leur contenu.
2. **B** — `-d:` fixe le séparateur, `-f1` sélectionne le premier champ.
3. **A** — `uniq` ne regroupe que des doublons adjacents, d'où le tri préalable.
4. **B** — Le drapeau `g` (global) remplace toutes les occurrences de la ligne, pas seulement la
   première.

</details>

## Projet fil rouge

Ton journal de sauvegarde va grandir. Tu vas apprendre à l'**interroger** comme un vrai outil
d'exploitation.

1. Ajoute quelques entrées à `sauvegardes/historique.log` (relance les commandes du chapitre 5
   plusieurs fois, ou ajoute-en à la main).
2. Compte combien de sauvegardes ont réussi :

   ```bash
   $ grep -c "copie OK" sauvegardes/historique.log
   ```

3. Affiche la date de la dernière opération enregistrée :

   ```bash
   $ tail -n 1 sauvegardes/historique.log
   ```

4. Cherche d'éventuelles erreurs consignées :

   ```bash
   $ grep -i error sauvegardes/historique.log
   ```

Ces requêtes seront exactement ce que tu lanceras pour vérifier que `sauvegarde.sh` fait bien son
travail. Au prochain chapitre, on aborde les **variables** et l'**environnement** : la dernière brique
avant d'écrire de vrais scripts.

---

[← Chapitre précédent](06-permissions.md) · [Sommaire](README.md) · [Chapitre suivant →](08-environnement.md)
