# Manipuler fichiers et dossiers

[← Chapitre précédent](02-systeme-de-fichiers.md) · [Sommaire](README.md) · [Chapitre suivant →](04-lire-et-filtrer.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer des dossiers (`mkdir`) et des fichiers (`touch`) ;
- copier (`cp`), déplacer et renommer (`mv`), et supprimer (`rm`, `rmdir`) ;
- afficher le contenu d'un fichier (`cat`, `less`) ;
- éviter les pièges de la suppression, irréversible au terminal.

Ce chapitre est celui où tu passes de spectateur à acteur : tu modifies réellement le système de
fichiers. Travaille de préférence dans un dossier de test pour rester serein.

## Un terrain de jeu

Commence par te créer un espace dédié, pour ne rien risquer ailleurs :

```bash
$ cd                       # retour à la maison
$ mkdir terrain-de-jeu     # crée un dossier
$ cd terrain-de-jeu        # entre dedans
$ pwd
/home/alex/terrain-de-jeu
```

`mkdir` (*make directory*) crée un dossier. Tu peux en créer plusieurs d'un coup, et même toute une
arborescence avec l'option `-p` (*parents*), qui crée les dossiers intermédiaires manquants :

```bash
$ mkdir notes images            # deux dossiers d'un coup
$ mkdir -p projets/site/css     # crée projets, puis site, puis css
$ ls
images  notes  projets
```

Sans `-p`, `mkdir projets/site/css` échouerait si `projets` n'existe pas encore.

## Créer un fichier : `touch`

`touch` crée un fichier **vide** s'il n'existe pas (et met à jour sa date s'il existe déjà) :

```bash
$ touch notes/liste-courses.txt
$ touch notes/idees.txt notes/todo.txt     # plusieurs à la fois
$ ls notes
idees.txt  liste-courses.txt  todo.txt
```

`touch` est parfait pour créer rapidement des fichiers de test. Pour y mettre du contenu, on utilisera
un éditeur de texte ou les redirections du [chapitre 5](05-redirections-et-tuyaux.md).

## Copier : `cp`

`cp` (*copy*) duplique un fichier. La syntaxe est toujours `cp source destination` :

```bash
$ cp notes/todo.txt notes/todo-sauvegarde.txt
$ ls notes
idees.txt  liste-courses.txt  todo-sauvegarde.txt  todo.txt
```

Pour copier un **dossier** entier avec tout ce qu'il contient, il faut l'option `-r` (*recursive*,
« en descendant dans les sous-dossiers ») :

```bash
$ cp -r notes notes-copie
$ ls
images  notes  notes-copie  projets
```

> **À retenir** — La copie va toujours de la **source** vers la **destination**, dans cet ordre. Pour
> un dossier, n'oublie pas `-r`, sinon `cp` refuse de le copier.

## Déplacer et renommer : `mv`

`mv` (*move*) sert à **deux** choses, selon la destination :

```bash
$ mv todo.txt notes/             # DÉPLACER : todo.txt va dans le dossier notes/
$ mv idees.txt brouillon.txt     # RENOMMER : idees.txt devient brouillon.txt
```

Sous Unix, renommer et déplacer sont la même opération : tu donnes un nouveau chemin à un fichier. Si
la destination est un dossier existant, le fichier y est déplacé ; sinon, il est renommé. Contrairement
à `cp`, `mv` n'a **pas besoin** de `-r` pour les dossiers.

> **Attention** — Si la destination existe déjà, `mv` (comme `cp`) l'**écrase sans prévenir**.
> L'option `-i` (*interactive*) demande confirmation avant d'écraser : `mv -i a.txt b.txt`. Prends
> l'habitude de l'utiliser quand un doute existe.

## Afficher le contenu : `cat` et `less`

Pour voir ce qu'il y a dans un fichier texte, sans l'ouvrir dans un éditeur :

```bash
$ cat notes/liste-courses.txt
```

`cat` (*concatenate*) déverse tout le fichier dans le terminal d'un coup. Parfait pour un fichier
court. Pour un fichier long, l'affichage défile trop vite : utilise `less`, qui affiche page par
page :

```bash
$ less /etc/services
```

Dans `less`, tu navigues avec les flèches ou Espace (page suivante), tu cherches avec `/motcle`, et tu
**quittes avec `q`** — exactement comme dans `man`, qui repose d'ailleurs sur `less`.

| Commande | Usage |
| --- | --- |
| `cat fichier` | tout afficher d'un coup (fichiers courts) |
| `less fichier` | afficher page par page, quitter avec `q` (fichiers longs) |
| `head fichier` | afficher le **début** (on l'approfondit au chapitre 4) |
| `tail fichier` | afficher la **fin** (idem) |

## Supprimer : `rm` et `rmdir`

`rm` (*remove*) supprime un fichier :

```bash
$ rm notes/todo-sauvegarde.txt
```

Pour supprimer un **dossier et tout son contenu**, il faut `-r` :

```bash
$ rm -r notes-copie
```

`rmdir` supprime un dossier uniquement s'il est **vide** ; c'est une sécurité utile :

```bash
$ rmdir images        # marche seulement si images est vide
```

> **Attention — danger réel.** Au terminal, `rm` **ne met rien à la corbeille**. La suppression est
> **immédiate et définitive**. Il n'y a pas d'annulation. Avant de lancer un `rm`, relis la ligne.

Quelques règles de prudence qui t'éviteront des catastrophes :

- Utilise `rm -i` pour qu'on te demande confirmation avant chaque suppression.
- Méfie-toi de `rm -r` combiné à des chemins absolus ou à `*` (le caractère « tout », vu au chapitre
  4). La commande `rm -rf /` tente d'effacer **tout le système** : ne la tape jamais « pour voir ».
- En cas de doute, fais d'abord un `ls` sur la cible pour vérifier ce que tu t'apprêtes à supprimer.

## Résumé

- `mkdir` crée des dossiers (`-p` pour créer les parents) ; `touch` crée des fichiers vides.
- `cp source destination` copie (`-r` pour un dossier) ; `mv` déplace **ou** renomme selon la
  destination.
- `cat` affiche un fichier court d'un coup ; `less` le montre page par page (quitter avec `q`).
- `rm` supprime un fichier, `rm -r` un dossier, `rmdir` un dossier vide.
- La suppression au terminal est **définitive** : pas de corbeille. Vérifie avant, et garde `-i` sous
  le coude.

## Exercices

### Exercice 1 — Petite arborescence

Dans ton `terrain-de-jeu`, crée en quelques commandes la structure suivante, puis vérifie-la avec
`ls -R` (qui liste récursivement) :

```text
voyage/
├── billets/
│   └── avion.txt
└── itineraire.txt
```

<details>
<summary>Voir le corrigé</summary>

La démarche : créer l'arborescence des dossiers avec `mkdir -p`, puis les fichiers avec `touch`.

```bash
$ mkdir -p voyage/billets
$ touch voyage/itineraire.txt voyage/billets/avion.txt
$ ls -R voyage
```

`ls -R` affiche le dossier et tous ses sous-dossiers, ce qui permet de vérifier la structure d'un
coup d'œil.

</details>

### Exercice 2 — Copier, renommer, ranger, nettoyer

En repartant du dossier `voyage` de l'exercice 1 :

1. Fais une copie de `itineraire.txt` nommée `itineraire-v2.txt`.
2. Renomme `itineraire.txt` en `itineraire-old.txt`.
3. Déplace `itineraire-old.txt` dans un nouveau dossier `archives`.
4. Supprime `itineraire-v2.txt`.

<details>
<summary>Voir le corrigé</summary>

La démarche : `cp` pour copier, `mv` pour renommer puis pour déplacer, `rm` pour supprimer. On crée
`archives` avant d'y déplacer quoi que ce soit.

```bash
$ cd voyage
$ cp itineraire.txt itineraire-v2.txt
$ mv itineraire.txt itineraire-old.txt
$ mkdir archives
$ mv itineraire-old.txt archives/
$ rm itineraire-v2.txt
$ ls -R
```

Note que les étapes 2 et 3 auraient pu être fusionnées : `mv itineraire.txt archives/itineraire-old.txt`
renomme et déplace en une seule commande.

</details>

## Quiz

**1.** Quelle commande crée un dossier ainsi que tous ses dossiers parents manquants ?
- A. `touch -p`
- B. `mkdir -p`
- C. `cp -r`

**2.** Quelle affirmation sur `mv` est correcte ?
- A. `mv` ne sert qu'à déplacer, jamais à renommer
- B. `mv` déplace ou renomme selon la destination, et n'a pas besoin de `-r` pour un dossier
- C. `mv` copie le fichier en laissant l'original

**3.** Tu veux copier le dossier `notes` et tout son contenu. Quelle commande ?
- A. `cp notes notes-copie`
- B. `cp -r notes notes-copie`
- C. `mv notes notes-copie`

**4.** Que se passe-t-il après `rm fichier.txt` ?
- A. Le fichier part à la corbeille et peut être restauré
- B. Le fichier est supprimé définitivement, sans corbeille
- C. Le fichier est renommé

<details>
<summary>Voir les réponses</summary>

1. **B** — `mkdir -p` crée toute la chaîne de dossiers manquants.
2. **B** — `mv` renomme si la destination est un nouveau nom, déplace si c'est un dossier ; pas de
   `-r` nécessaire.
3. **B** — Copier un dossier exige `-r`. L'option A échouerait, et `mv` déplacerait au lieu de copier.
4. **B** — `rm` supprime sans corbeille ; l'opération est définitive.

</details>

## Projet fil rouge

Tu vas réaliser ta première sauvegarde… à la main. Cela rendra évident ce que le futur script
automatisera.

1. Crée un dossier de données à sauvegarder et mets-y quelques fichiers de test :

   ```bash
   $ cd
   $ mkdir -p donnees
   $ touch donnees/rapport.txt donnees/notes.txt
   ```

2. Crée un dossier qui accueillera les sauvegardes, puis copies-y une copie complète de `donnees` :

   ```bash
   $ mkdir -p sauvegardes
   $ cp -r donnees sauvegardes/donnees-copie
   $ ls sauvegardes
   donnees-copie
   ```

Tu viens de faire, à la main, exactement ce que `sauvegarde.sh` finira par faire tout seul : copier un
dossier source vers un dossier de sauvegarde. Garde cette structure (`donnees/` et `sauvegardes/`) :
on s'en resservira. Au prochain chapitre, tu apprendras à fouiller dans le contenu des fichiers, une
étape clé pour vérifier qu'une sauvegarde contient bien ce qu'on attend.

---

[← Chapitre précédent](02-systeme-de-fichiers.md) · [Sommaire](README.md) · [Chapitre suivant →](04-lire-et-filtrer.md)
