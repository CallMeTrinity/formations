# Permissions, utilisateurs et `sudo`

[← Chapitre précédent](05-redirections-et-tuyaux.md) · [Sommaire](README.md) · [Chapitre suivant →](07-chercher-et-transformer.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- lire la ligne de permissions affichée par `ls -l` ;
- comprendre les trois droits (`r`, `w`, `x`) et les trois catégories (propriétaire, groupe, autres) ;
- modifier les permissions avec `chmod`, en notation symbolique et numérique ;
- changer le propriétaire avec `chown` et utiliser `sudo` à bon escient.

Unix est un système **multi-utilisateurs** : plusieurs personnes peuvent partager une même machine.
Les permissions sont le mécanisme qui décide qui a le droit de faire quoi sur chaque fichier. C'est
aussi ce qui rend un script « exécutable », d'où l'importance de ce chapitre avant d'écrire des
scripts.

## Lire la ligne de permissions

Reprends `ls -l` sur un dossier :

```bash
$ ls -l
-rw-r--r-- 1 alex dev  1240 juin  14 09:00 rapport.txt
drwxr-xr-x 2 alex dev  4096 juin  14 09:01 donnees
```

La première colonne, par exemple `-rw-r--r--`, encode tout. Découpons-la en quatre morceaux :

```text
 -        rw-        r--        r--
type   propriétaire  groupe    autres
```

- Le **premier caractère** est le type : `-` pour un fichier ordinaire, `d` pour un dossier
  (*directory*), `l` pour un lien.
- Les **neuf suivants** se lisent par groupes de trois, dans cet ordre : droits du **propriétaire**,
  droits du **groupe**, droits des **autres** (tout le monde).

Dans la ligne, `alex` est le propriétaire et `dev` le groupe propriétaire.

## Les trois droits : `r`, `w`, `x`

Chaque groupe de trois caractères indique trois droits, toujours dans le même ordre :

| Lettre | Sur un fichier | Sur un dossier |
| --- | --- | --- |
| `r` (*read*) | lire le contenu | lister les fichiers qu'il contient |
| `w` (*write*) | modifier le contenu | y créer/supprimer/renommer des fichiers |
| `x` (*execute*) | l'exécuter comme un programme | entrer dedans (`cd`) |

Un `-` à la place d'une lettre signifie « droit absent ». Décodons donc `-rw-r--r--` :

- `-` : c'est un fichier ordinaire ;
- `rw-` : le **propriétaire** peut lire et écrire, mais pas exécuter ;
- `r--` : le **groupe** peut seulement lire ;
- `r--` : les **autres** peuvent seulement lire.

Et `drwxr-xr-x` pour le dossier `donnees` :

- `d` : c'est un dossier ;
- `rwx` : le propriétaire peut tout faire (lister, modifier, entrer) ;
- `r-x` : le groupe peut lister et entrer, mais pas modifier ;
- `r-x` : les autres aussi.

> **À retenir** — Sur un **dossier**, `x` ne veut pas dire « exécuter » mais « **traverser** » :
> sans `x`, tu ne peux même pas faire `cd` dedans, même si tu as `r`. C'est le piège le plus courant.

## Modifier les permissions : `chmod`

`chmod` (*change mode*) change les droits. Il existe deux notations ; commence par la **symbolique**,
plus lisible.

### Notation symbolique

On combine trois éléments : **qui** (`u` propriétaire, `g` groupe, `o` autres, `a` tous), une
**opération** (`+` ajouter, `-` retirer, `=` fixer exactement) et un **droit** (`r`, `w`, `x`).

```bash
$ chmod u+x script.sh        # donne le droit d'exécution au propriétaire
$ chmod go-w rapport.txt     # retire l'écriture au groupe et aux autres
$ chmod a+r partage.txt      # tout le monde peut lire
$ chmod u=rw,go=r notes.txt  # fixe : proprio rw, groupe et autres r
```

Le cas le plus fréquent, et de loin, est `chmod u+x` pour rendre un script exécutable. Tu le feras à
chaque script écrit dans cette formation.

### Notation numérique (octale)

Chaque droit vaut un nombre : `r` = 4, `w` = 2, `x` = 1. On les additionne par catégorie, ce qui donne
un chiffre de 0 à 7, puis on écrit les trois chiffres (propriétaire, groupe, autres) à la suite.

| Chiffre | Droits | Détail |
| --- | --- | --- |
| 7 | `rwx` | 4+2+1 |
| 6 | `rw-` | 4+2 |
| 5 | `r-x` | 4+1 |
| 4 | `r--` | 4 |
| 0 | `---` | rien |

```bash
$ chmod 644 rapport.txt      # rw-r--r-- : proprio rw, groupe et autres r
$ chmod 755 script.sh        # rwxr-xr-x : proprio rwx, les autres r-x
$ chmod 600 secret.txt       # rw------- : proprio rw, personne d'autre
```

Deux valeurs reviennent partout : **644** pour un fichier de données normal et **755** pour un
programme ou un dossier. Mémorise-les, tu les retrouveras toute ta vie.

> **Astuce** — Les notations symbolique et numérique font la même chose. `chmod u+x` modifie juste un
> droit ; `chmod 755` fixe les neuf d'un coup. Utilise la symbolique pour un ajustement, la numérique
> pour poser un jeu de droits complet et connu.

## Changer le propriétaire : `chown`

`chown` (*change owner*) change le propriétaire et/ou le groupe d'un fichier :

```bash
$ chown sam rapport.txt          # rapport.txt appartient désormais à sam
$ chown sam:dev rapport.txt      # propriétaire sam, groupe dev
```

Changer le propriétaire d'un fichier que tu ne possèdes pas requiert des privilèges
d'administrateur — ce qui nous amène à `sudo`.

## L'administrateur et `sudo`

Sur un système Unix, un utilisateur spécial nommé **root** (le *super-utilisateur*) a tous les
droits, sur tous les fichiers. On ne se connecte pas en root au quotidien : c'est trop dangereux, une
faute de frappe pouvant détruire le système. À la place, on utilise `sudo` (*substitute user do*)
pour exécuter **une seule commande** avec les droits de root :

```bash
$ sudo apt update            # exécute apt update en tant qu'administrateur
[sudo] Mot de passe de alex :
```

`sudo` te demande **ton** mot de passe (pas celui de root), vérifie que tu as le droit d'agir en
administrateur, puis exécute la commande. On s'en sert pour installer des logiciels, modifier des
fichiers système (dans `/etc`), ou agir sur des fichiers qui ne t'appartiennent pas.

> **Attention** — `sudo` désactive les garde-fous. `sudo rm -r /un/dossier/systeme` s'exécutera sans
> broncher et peut casser ta machine. Avant un `sudo`, relis la commande deux fois. Ne lance jamais
> une commande `sudo` trouvée sur internet sans comprendre ce qu'elle fait.

> **À retenir** — Tu n'as pas besoin de `sudo` pour travailler dans **ton** dossier personnel : tu y
> es déjà propriétaire de tout. Si une commande dans ton home réclame `sudo`, c'est souvent le signe
> d'une erreur (mauvais chemin, fichier appartenant à root par accident).

## Résumé

- `ls -l` montre les permissions : `type` + trois triplets `rwx` pour **propriétaire**, **groupe**,
  **autres**.
- `r` = lire, `w` = écrire, `x` = exécuter (sur un dossier : traverser/`cd`).
- `chmod` modifie les droits : symbolique (`u+x`, `go-w`) ou numérique (`644`, `755`).
- `chown` change le propriétaire (souvent avec `sudo`).
- **root** est le super-utilisateur ; `sudo` exécute une commande en administrateur après ton mot de
  passe. À manier avec prudence.

## Exercices

### Exercice 1 — Décoder des permissions

Pour chacune de ces lignes de `ls -l`, dis le type de l'objet et qui peut faire quoi :

1. `-rwxr-xr-x`
2. `drw-------`
3. `-rw-rw-r--`

<details>
<summary>Voir le corrigé</summary>

On découpe en `type | u | g | o`.

1. `- rwx r-x r-x` : fichier ; le propriétaire peut lire, écrire, exécuter ; groupe et autres peuvent
   lire et exécuter mais pas modifier. (C'est typiquement un programme, équivalent `755`.)
2. `d rw- --- ---` : dossier ; le propriétaire peut lire et écrire **mais pas le traverser** (`x`
   manquant), donc il ne pourra pas faire `cd` dedans ; personne d'autre n'a aucun droit. (Permissions
   bancales, justement à cause du `x` manquant.)
3. `- rw- rw- r--` : fichier ; propriétaire et groupe peuvent lire et écrire ; les autres peuvent
   seulement lire. (Équivalent `664`.)

</details>

### Exercice 2 — Régler des droits

1. Crée un fichier `lance.sh` et rends-le exécutable pour son propriétaire uniquement, avec la
   notation symbolique. Vérifie avec `ls -l`.
2. Fixe ensuite ses droits à `rwxr-xr-x` avec la notation numérique.

<details>
<summary>Voir le corrigé</summary>

La démarche : `u+x` ajoute l'exécution au seul propriétaire ; `755` correspond à `rwxr-xr-x`.

```bash
$ touch lance.sh
$ chmod u+x lance.sh
$ ls -l lance.sh
-rwxr--r-- 1 alex dev 0 juin  14 10:00 lance.sh
$ chmod 755 lance.sh
$ ls -l lance.sh
-rwxr-xr-x 1 alex dev 0 juin  14 10:00 lance.sh
```

Après `u+x`, seul le `x` du propriétaire apparaît. Après `755`, les trois catégories ont leurs droits
fixés d'un coup.

</details>

## Quiz

**1.** Dans `-rw-r--r--`, que peut faire le **groupe** ?
- A. Lire et écrire
- B. Lire seulement
- C. Rien

**2.** Que signifie le droit `x` sur un **dossier** ?
- A. Le supprimer
- B. Y entrer (faire `cd`) et le traverser
- C. L'exécuter comme un programme

**3.** Quelle commande rend `script.sh` exécutable pour son propriétaire ?
- A. `chmod u+x script.sh`
- B. `chmod -x script.sh`
- C. `chown script.sh`

**4.** À quoi sert `sudo` ?
- A. À supprimer un fichier protégé
- B. À exécuter une commande avec les droits de l'administrateur (root)
- C. À changer ton mot de passe

<details>
<summary>Voir les réponses</summary>

1. **B** — Le triplet du groupe est `r--` : lecture seule.
2. **B** — Sur un dossier, `x` autorise à le traverser ; sans lui, pas de `cd` possible.
3. **A** — `u+x` ajoute l'exécution au propriétaire. `-x` la retirerait.
4. **B** — `sudo` exécute une commande en tant que root après vérification de ton mot de passe.

</details>

## Projet fil rouge

Ton futur `sauvegarde.sh` sera un programme : il doit donc être **exécutable**. Tu vas préparer le
fichier et régler ses droits.

1. Crée le fichier du script et constate qu'il n'est pas encore exécutable :

   ```bash
   $ touch sauvegarde.sh
   $ ls -l sauvegarde.sh
   -rw-r--r-- 1 alex dev 0 juin  14 10:10 sauvegarde.sh
   ```

   Le `x` est absent : tel quel, le système refuserait de le lancer comme programme.

2. Rends-le exécutable et vérifie :

   ```bash
   $ chmod u+x sauvegarde.sh
   $ ls -l sauvegarde.sh
   -rwxr--r-- 1 alex dev 0 juin  14 10:10 sauvegarde.sh
   ```

Le fichier est prêt à recevoir du code. Tu comprends maintenant pourquoi un script qu'on vient de
créer doit être rendu exécutable avant de tourner. Au prochain chapitre, on muscle la recherche et la
transformation de texte (`find`, `sort`, `cut`, `sed`, `awk`) avant d'attaquer la programmation.

---

[← Chapitre précédent](05-redirections-et-tuyaux.md) · [Sommaire](README.md) · [Chapitre suivant →](07-chercher-et-transformer.md)
