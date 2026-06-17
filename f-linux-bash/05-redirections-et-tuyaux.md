# Redirections et tuyaux

[← Chapitre précédent](04-lire-et-filtrer.md) · [Sommaire](README.md) · [Chapitre suivant →](06-permissions.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre les trois flux d'une commande : `stdin`, `stdout`, `stderr` ;
- rediriger la sortie d'une commande vers un fichier avec `>` et `>>` ;
- capter ou ignorer les erreurs séparément du résultat ;
- enchaîner des commandes avec le **tuyau** `|` pour composer des traitements puissants.

Ce chapitre contient l'idée la plus importante de toute la formation. Comprends bien les tuyaux, et
le terminal change de dimension.

## Trois flux par commande

Chaque commande Unix dispose de trois canaux de communication, qu'on appelle des **flux**
(*streams*) :

- l'**entrée standard**, ou `stdin` : par où la commande **reçoit** des données (par défaut, ton
  clavier) ;
- la **sortie standard**, ou `stdout` : par où elle **envoie son résultat** normal (par défaut,
  l'écran) ;
- la **sortie d'erreur**, ou `stderr` : par où elle envoie ses **messages d'erreur** (aussi l'écran
  par défaut).

```text
                 ┌─────────────┐
   stdin  ─────► │  commande   │ ─────► stdout  (le résultat)
  (clavier)      └─────────────┘ ─────► stderr  (les erreurs)
```

À l'écran, `stdout` et `stderr` se mélangent et tu ne les distingues pas. Mais ils sont séparés, et
c'est ce qui permet de **rediriger le résultat sans emporter les erreurs**, ou l'inverse. Tout le
chapitre consiste à détourner ces flux ailleurs que vers l'écran et le clavier.

## Rediriger la sortie vers un fichier : `>` et `>>`

Le symbole `>` envoie `stdout` dans un fichier au lieu de l'écran :

```bash
$ echo "premiere ligne" > notes.txt
$ cat notes.txt
premiere ligne
```

Rien ne s'est affiché après le `echo` : le texte est parti dans `notes.txt`. C'est ainsi qu'on
fabriquait le fichier `journal.log` au chapitre précédent.

> **Attention** — `>` **écrase** le fichier : son contenu précédent est perdu sans avertissement.
> Pour **ajouter à la fin** sans effacer, utilise `>>` (deux chevrons) :

```bash
$ echo "deuxieme ligne" >> notes.txt
$ cat notes.txt
premiere ligne
deuxieme ligne
```

Retiens la distinction, elle est cruciale :

| Opérateur | Effet | Mnémo |
| --- | --- | --- |
| `>` | écrit dans le fichier en **écrasant** | un seul chevron, on remplace |
| `>>` | **ajoute** à la fin du fichier | deux chevrons, on empile |

C'est `>>` qu'on emploiera pour qu'un script écrive son journal sans effacer l'historique à chaque
exécution.

## Rediriger l'entrée : `<`

Symétriquement, `<` fait lire à une commande un fichier au lieu du clavier :

```bash
$ wc -l < journal.log
6
```

Ici `wc` lit son `stdin` depuis le fichier. En pratique on écrit plus souvent `wc -l journal.log`,
mais comprendre `<` éclaire la logique des flux : une commande ne sait pas d'où viennent ses données,
elle se contente de lire son entrée.

## Séparer le résultat des erreurs

Les flux `stdout` et `stderr` portent des numéros : `1` pour `stdout`, `2` pour `stderr`. On s'en sert
pour les rediriger séparément.

```bash
$ ls donnees dossier-inexistant
```

Cette commande produit à la fois un résultat (le contenu de `donnees`, sur `stdout`) et une erreur
(`dossier-inexistant` n'existe pas, sur `stderr`). On peut les séparer :

```bash
$ ls donnees dossier-inexistant > resultat.txt 2> erreurs.txt
```

Le résultat normal va dans `resultat.txt` (`>` vise `stdout`, le flux 1), et les erreurs dans
`erreurs.txt` (`2>` vise `stderr`, le flux 2). Deux variantes très courantes :

```bash
$ commande 2>/dev/null            # jette les erreurs (les rend invisibles)
$ commande > tout.txt 2>&1        # envoie résultat ET erreurs dans le même fichier
```

`/dev/null` est un « trou noir » du système : tout ce qu'on y envoie disparaît. `2>&1` se lit
« envoie le flux 2 là où va déjà le flux 1 ».

> **Astuce** — `2>/dev/null` est précieux quand une commande affiche des erreurs sans gravité (par
> exemple des « permission refusée » lors d'une recherche) qui noient le vrai résultat.

## Le tuyau : `|`

Voici l'idée maîtresse. Le **tuyau** (*pipe*), noté `|`, branche le `stdout` d'une commande
directement sur le `stdin` de la suivante. Au lieu de passer par un fichier intermédiaire, le résultat
de la première commande devient l'entrée de la seconde :

```text
commande1 │ commande2 │ commande3
   stdout ─┘   stdout ─┘
   devient      devient
   stdin de     stdin de
  commande2    commande3
```

Un exemple : combien de fichiers `.txt` dans le dossier courant ?

```bash
$ ls *.txt | wc -l
3
```

`ls *.txt` produit la liste, le `|` l'envoie à `wc -l` qui la compte. Aucune des deux commandes n'a
été conçue pour l'autre : c'est toute la beauté de la philosophie Unix, où chaque outil fait **une**
chose et se combine avec les autres.

### Composer des traitements

La force des tuyaux apparaît quand on en enchaîne plusieurs. Reprenons `journal.log` :

```bash
# Combien de lignes ERROR dans le journal ?
$ grep ERROR journal.log | wc -l
2

# Les 3 dernières lignes contenant "connexion"
$ grep connexion journal.log | tail -n 3

# Les niveaux de log présents, triés et dédoublonnés
$ grep -oE 'INFO|ERROR|WARNING' journal.log | sort | uniq -c
      1 WARNING
      2 ERROR
      3 INFO
```

Ce dernier exemple introduit trois compagnons des tuyaux, qu'on approfondira au
[chapitre 7](07-chercher-et-transformer.md) :

- `sort` trie les lignes ;
- `uniq` regroupe les lignes identiques **consécutives** (d'où le `sort` avant) ; `-c` les compte ;
- la combinaison `sort | uniq -c` est l'idiome classique pour « compter les occurrences ».

> **À retenir** — Un tuyau `|` connecte des commandes en chaîne : chaque maillon transforme le flux
> et le passe au suivant. C'est en composant de petites commandes qu'on résout de gros problèmes.

> **Attention** — Ne confonds pas `|` (tuyau, entre deux commandes) et `>` (redirection, vers un
> fichier). `commande > fichier` écrit dans un fichier ; `commande1 | commande2` envoie vers une
> autre commande. On termine souvent une chaîne de tuyaux par une redirection :
> `grep ERROR journal.log | sort > erreurs-triees.txt`.

## Résumé

- Toute commande a trois flux : `stdin` (entrée), `stdout` (résultat), `stderr` (erreurs).
- `>` redirige `stdout` vers un fichier en l'**écrasant** ; `>>` **ajoute** à la fin ; `<` lit
  l'entrée depuis un fichier.
- `2>` redirige les erreurs ; `2>/dev/null` les jette ; `> f 2>&1` met résultat et erreurs dans le
  même fichier.
- Le tuyau `|` branche la sortie d'une commande sur l'entrée de la suivante ; on enchaîne les
  maillons pour composer des traitements.
- `sort | uniq -c` compte les occurrences (à retenir).

## Exercices

### Exercice 1 — Construire et nourrir un fichier

1. Crée un fichier `fruits.txt` contenant `pomme` sur la première ligne, en écrasant tout contenu
   précédent.
2. Ajoute `banane` puis `pomme` à la suite, sans effacer.
3. Affiche le fichier pour vérifier.

<details>
<summary>Voir le corrigé</summary>

La démarche : `>` pour la première écriture (écrase), `>>` pour les ajouts.

```bash
$ echo pomme > fruits.txt
$ echo banane >> fruits.txt
$ echo pomme >> fruits.txt
$ cat fruits.txt
pomme
banane
pomme
```

Si tu avais utilisé `>` partout, seul le dernier `pomme` resterait : chaque `>` écrase le précédent.

</details>

### Exercice 2 — Compter avec un tuyau

En repartant du `fruits.txt` de l'exercice 1 :

1. Compte le nombre total de lignes avec un tuyau (sans donner le fichier en argument à `wc`).
2. Affiche chaque fruit avec son nombre d'occurrences.

<details>
<summary>Voir le corrigé</summary>

La démarche : `cat` envoie le contenu dans le tuyau ; `wc -l` compte ; `sort | uniq -c` regroupe et
compte les doublons.

```bash
$ cat fruits.txt | wc -l
3
$ cat fruits.txt | sort | uniq -c
      1 banane
      2 pomme
```

Le `sort` est indispensable avant `uniq`, qui ne regroupe que les lignes **identiques consécutives**.
Sans tri, les deux `pomme` séparés par `banane` ne seraient pas regroupés.

</details>

## Quiz

**1.** Quelle est la différence entre `>` et `>>` ?
- A. Aucune, ce sont des synonymes
- B. `>` écrase le fichier, `>>` ajoute à la fin
- C. `>` ajoute à la fin, `>>` écrase le fichier

**2.** Que fait `ls | wc -l` ?
- A. Liste les fichiers puis les supprime
- B. Compte le nombre d'éléments listés par `ls`
- C. Écrit la liste des fichiers dans un fichier nommé `wc`

**3.** À quoi sert `2>/dev/null` ?
- A. À envoyer le résultat normal dans un fichier
- B. À faire disparaître les messages d'erreur
- C. À compter les erreurs

**4.** Dans une chaîne `a | b`, que reçoit la commande `b` ?
- A. Le code source de `a`
- B. La sortie standard (`stdout`) de `a`, sur son entrée standard
- C. Rien, le tuyau ne transmet pas de données

<details>
<summary>Voir les réponses</summary>

1. **B** — `>` remplace tout le contenu, `>>` empile à la fin.
2. **B** — `ls` produit la liste, le tuyau l'envoie à `wc -l` qui compte les lignes.
3. **B** — `2>` vise le flux d'erreur, `/dev/null` l'absorbe : les erreurs deviennent invisibles.
4. **B** — Le tuyau connecte le `stdout` de la gauche au `stdin` de la droite.

</details>

## Projet fil rouge

Ton script devra **garder une trace** de ses actions. Les redirections sont exactement l'outil pour
ça : tu vas créer, à la main, le journal des sauvegardes.

1. Écris une première entrée dans un fichier de log dédié, puis vérifie :

   ```bash
   $ echo "$(date) - sauvegarde lancee" >> sauvegardes/historique.log
   $ cat sauvegardes/historique.log
   ```

   Le `$(date)` insère la date du moment ; c'est une **substitution de commande** qu'on détaillera au
   chapitre 8. Retiens pour l'instant qu'on horodate chaque ligne.

2. Refais une copie de `donnees` et enregistre le résultat de l'opération dans le même journal, en
   séparant un éventuel message d'erreur :

   ```bash
   $ cp -r donnees sauvegardes/donnees-copie2 2>> sauvegardes/historique.log \
       && echo "$(date) - copie OK" >> sauvegardes/historique.log
   ```

Tu utilises déjà `>>` pour ne jamais perdre l'historique et `2>>` pour y consigner les erreurs : ce
sera le squelette du journal de `sauvegarde.sh`. Au prochain chapitre, on s'intéresse aux
**permissions** — qui a le droit de lire, modifier ou exécuter un fichier — une notion indispensable
avant de rendre un script exécutable.

---

[← Chapitre précédent](04-lire-et-filtrer.md) · [Sommaire](README.md) · [Chapitre suivant →](06-permissions.md)
