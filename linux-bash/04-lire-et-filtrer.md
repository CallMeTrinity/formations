# Lire et filtrer du texte

[← Chapitre précédent](03-manipuler-fichiers.md) · [Sommaire](README.md) · [Chapitre suivant →](05-redirections-et-tuyaux.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- afficher le début et la fin d'un fichier avec `head` et `tail` ;
- compter lignes, mots et caractères avec `wc` ;
- rechercher du texte dans des fichiers avec `grep` et ses options les plus utiles ;
- désigner plusieurs fichiers d'un coup grâce aux **jokers** (`*`, `?`).

Sous Unix, presque tout est du texte : logs, fichiers de configuration, données exportées, code
source. Savoir le lire et le filtrer rapidement est une compétence centrale.

## Un fichier d'exemple

Pour que les exemples soient reproductibles, fabrique un petit journal d'événements. On utilise ici
une redirection (`>`) que tu découvriras en détail au [chapitre 5](05-redirections-et-tuyaux.md) ;
contente-toi de la recopier pour l'instant.

```bash
$ cd
$ printf '%s\n' \
  "2026-06-10 INFO   demarrage du service" \
  "2026-06-10 INFO   connexion de alex" \
  "2026-06-11 ERROR  echec de connexion" \
  "2026-06-11 INFO   connexion de sam" \
  "2026-06-12 WARNING disque presque plein" \
  "2026-06-12 ERROR  service interrompu" > journal.log
$ cat journal.log
2026-06-10 INFO   demarrage du service
2026-06-10 INFO   connexion de alex
2026-06-11 ERROR  echec de connexion
2026-06-11 INFO   connexion de sam
2026-06-12 WARNING disque presque plein
2026-06-12 ERROR  service interrompu
```

## Le début et la fin : `head` et `tail`

Sur un gros fichier, tu veux souvent juste un aperçu. `head` montre les premières lignes, `tail` les
dernières :

```bash
$ head -n 2 journal.log
2026-06-10 INFO   demarrage du service
2026-06-10 INFO   connexion de alex

$ tail -n 2 journal.log
2026-06-12 WARNING disque presque plein
2026-06-12 ERROR  service interrompu
```

L'option `-n` fixe le nombre de lignes (10 par défaut). `tail` a un super-pouvoir avec `-f`
(*follow*) : il affiche la fin du fichier **et reste à l'écoute**, montrant les nouvelles lignes en
temps réel. C'est l'outil numéro un pour surveiller un journal pendant qu'une application tourne :

```bash
$ tail -f journal.log      # affiche les nouvelles lignes au fil de l'eau ; Ctrl+C pour arrêter
```

## Compter : `wc`

`wc` (*word count*) compte. Par défaut, il affiche lignes, mots et octets :

```bash
$ wc journal.log
 6 36 234 journal.log
```

Soit 6 lignes, 36 mots, 234 octets. On utilise surtout l'option `-l` (*lines*) pour ne compter que
les lignes :

```bash
$ wc -l journal.log
6 journal.log
```

« Combien de lignes dans ce fichier ? » est une question si fréquente que `wc -l` deviendra un
réflexe.

## Rechercher : `grep`

`grep` est sans doute la commande la plus emblématique d'Unix. Elle **cherche un motif dans du texte
et affiche les lignes qui correspondent**. Syntaxe : `grep motif fichier`.

```bash
$ grep ERROR journal.log
2026-06-11 ERROR  echec de connexion
2026-06-12 ERROR  service interrompu
```

Tu obtiens uniquement les lignes contenant `ERROR`. C'est le filtre par excellence pour isoler ce qui
t'intéresse dans un fichier volumineux. Ses options les plus utiles :

| Option | Effet |
| --- | --- |
| `-i` | ignore la casse (`error`, `ERROR`, `Error` se valent) |
| `-n` | affiche le numéro de chaque ligne trouvée |
| `-c` | compte le nombre de lignes correspondantes au lieu de les afficher |
| `-v` | inverse : affiche les lignes qui **ne** correspondent **pas** |
| `-r` | cherche récursivement dans tous les fichiers d'un dossier |

Quelques exemples parlants :

```bash
$ grep -n connexion journal.log      # avec les numéros de ligne
2:2026-06-10 INFO   connexion de alex
3:2026-06-11 ERROR  echec de connexion
4:2026-06-11 INFO   connexion de sam

$ grep -c INFO journal.log           # combien de lignes contiennent INFO ?
3

$ grep -v INFO journal.log           # tout SAUF les lignes INFO
2026-06-11 ERROR  echec de connexion
2026-06-12 WARNING disque presque plein
2026-06-12 ERROR  service interrompu
```

> **Astuce** — `grep -rn "motif" .` cherche un mot dans **tous** les fichiers du dossier courant et
> de ses sous-dossiers, avec les numéros de ligne. C'est ainsi qu'on retrouve où une fonction est
> définie dans un projet de code.

> **Attention** — Si ton motif contient des espaces ou des caractères spéciaux, entoure-le de
> guillemets : `grep "echec de connexion" journal.log`. Sans guillemets, `grep` croirait que `de` et
> `connexion` sont des noms de fichiers.

## Désigner plusieurs fichiers : les jokers

Tu n'as pas besoin de répéter une commande pour chaque fichier. Le shell comprend des **jokers**
(*wildcards*, ou *globs*) qui désignent des groupes de noms. Le shell les remplace par la liste des
fichiers correspondants **avant** d'exécuter la commande.

| Joker | Signifie | Exemple |
| --- | --- | --- |
| `*` | n'importe quelle suite de caractères (même vide) | `*.txt` = tous les fichiers en `.txt` |
| `?` | exactement un caractère | `photo?.jpg` = `photo1.jpg`, `photo7.jpg`… |
| `[...]` | un caractère parmi un ensemble | `fichier[12].txt` = `fichier1.txt` ou `fichier2.txt` |

Mettons-les en pratique :

```bash
$ touch note1.txt note2.txt rapport.txt image.png
$ ls *.txt                 # tous les .txt
note1.txt  note2.txt  rapport.txt
$ ls note?.txt             # note + un caractère + .txt
note1.txt  note2.txt
$ grep ERROR *.log         # cherche ERROR dans tous les fichiers .log
```

> **À retenir** — C'est le **shell**, pas la commande, qui développe les jokers. `rm *.txt` est
> remplacé par `rm note1.txt note2.txt rapport.txt` avant exécution. D'où la prudence : un `*` mal
> placé dans un `rm` peut supprimer bien plus que prévu. Teste toujours avec `ls` d'abord.

## Résumé

- `head -n N` et `tail -n N` montrent le début et la fin d'un fichier ; `tail -f` suit un fichier en
  temps réel (idéal pour les logs).
- `wc -l` compte les lignes ; `wc` seul donne lignes, mots et octets.
- `grep motif fichier` affiche les lignes contenant le motif. Options clés : `-i` (casse), `-n`
  (numéros), `-c` (comptage), `-v` (inversion), `-r` (récursif).
- Les jokers `*`, `?`, `[...]` désignent plusieurs fichiers ; c'est le **shell** qui les développe
  avant d'exécuter la commande.

## Exercices

### Exercice 1 — Interroger le journal

À partir du fichier `journal.log` créé au début du chapitre, trouve les commandes qui répondent à :

1. Combien le journal contient-il de lignes ?
2. Affiche uniquement les lignes contenant `ERROR`.
3. Combien y a-t-il de lignes `INFO` ?
4. Affiche toutes les lignes **sauf** celles de niveau `INFO`, avec leur numéro de ligne.

<details>
<summary>Voir le corrigé</summary>

La démarche : `wc -l` pour compter, puis `grep` avec les options `-c`, `-v` et `-n`.

```bash
$ wc -l journal.log
6 journal.log
$ grep ERROR journal.log
$ grep -c INFO journal.log
3
$ grep -vn INFO journal.log
```

On combine `-v` (inverser) et `-n` (numéros) en `-vn` : l'ordre des lettres n'a pas d'importance.

</details>

### Exercice 2 — Jokers

Dans un dossier de test, crée les fichiers `a.txt`, `b.txt`, `c.log`, `notes.md`. Sans les nommer un
par un :

1. Liste seulement les fichiers `.txt`.
2. Liste les fichiers dont le nom fait exactement une lettre suivie de `.txt`.

<details>
<summary>Voir le corrigé</summary>

La démarche : `*` pour « n'importe quoi », `?` pour « un seul caractère ».

```bash
$ touch a.txt b.txt c.log notes.md
$ ls *.txt
a.txt  b.txt
$ ls ?.txt
a.txt  b.txt
```

Ici `*.txt` et `?.txt` donnent le même résultat parce que tous les `.txt` ont un nom d'une lettre.
Ajoute `notes.txt` et relance : `?.txt` ne le montrera pas, `*.txt` si.

</details>

## Quiz

**1.** Que fait `grep -v ERROR fichier` ?
- A. Affiche seulement les lignes contenant `ERROR`
- B. Affiche les lignes qui ne contiennent **pas** `ERROR`
- C. Compte les lignes contenant `ERROR`

**2.** Quelle commande compte le nombre de lignes d'un fichier ?
- A. `wc -l fichier`
- B. `head fichier`
- C. `grep -c fichier`

**3.** À quoi sert `tail -f journal.log` ?
- A. À supprimer la fin du fichier
- B. À afficher la fin du fichier et suivre les nouvelles lignes en temps réel
- C. À afficher le début du fichier

**4.** Dans `ls *.txt`, qui remplace `*.txt` par la liste des fichiers ?
- A. La commande `ls` elle-même
- B. Le shell, avant d'exécuter `ls`
- C. Le système Linux après l'exécution

<details>
<summary>Voir les réponses</summary>

1. **B** — `-v` inverse la sélection : on garde ce qui ne correspond pas.
2. **A** — `wc -l` compte les lignes. `grep -c` compte les lignes correspondant à un motif, mais il
   lui faut un motif.
3. **B** — `-f` (*follow*) suit le fichier et affiche les nouvelles lignes au fur et à mesure.
4. **B** — Le shell développe les jokers avant de lancer la commande.

</details>

## Projet fil rouge

Tu vas apprendre à **vérifier** une sauvegarde plutôt que de l'examiner à l'œil nu.

1. Reprends la structure du chapitre précédent (`donnees/` et `sauvegardes/donnees-copie/`).
2. Compte combien de fichiers la copie contient, pour la comparer à la source :

   ```bash
   $ ls donnees | wc -l
   2
   $ ls sauvegardes/donnees-copie | wc -l
   2
   ```

   (`ls | wc -l` enchaîne deux commandes avec un **tuyau** `|`, sujet du prochain chapitre. Pour
   l'instant, lis-le comme « compte les éléments listés ».)

3. Imagine qu'un de tes fichiers contienne le mot `confidentiel`. Tu pourrais vérifier qu'aucune
   sauvegarde ne l'expose par mégarde :

   ```bash
   $ grep -r confidentiel sauvegardes/
   ```

Vérifier le nombre de fichiers et chercher un motif sont exactement les contrôles qu'un bon script de
sauvegarde effectue. Au prochain chapitre, tu découvres les **redirections** et les **tuyaux** : la
mécanique qui permet d'enchaîner ces commandes et d'enregistrer leurs résultats.

---

[← Chapitre précédent](03-manipuler-fichiers.md) · [Sommaire](README.md) · [Chapitre suivant →](05-redirections-et-tuyaux.md)
