# Se repérer dans le système de fichiers

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-manipuler-fichiers.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- te représenter l'organisation des fichiers sous Unix comme un **arbre** ;
- savoir où tu te trouves avec `pwd` et lister le contenu d'un dossier avec `ls` ;
- te déplacer avec `cd`, y compris vers ton dossier personnel et le dossier parent ;
- faire la différence entre un **chemin absolu** et un **chemin relatif** et écrire les deux.

## Tout est un arbre

Sous Unix, les fichiers ne sont pas posés en vrac : ils sont rangés dans des **dossiers** (aussi
appelés *répertoires*), eux-mêmes rangés dans d'autres dossiers. L'ensemble forme un **arbre**
unique, qui part d'une racine.

```text
/                     <- la racine, le point de départ de tout
├── home
│   └── alex          <- ton dossier personnel
│       ├── documents
│       ├── photos
│       └── projets
├── etc               <- fichiers de configuration du système
├── usr               <- programmes installés
└── tmp               <- fichiers temporaires
```

Quelques mots de vocabulaire qui reviendront sans cesse :

- La **racine** (*root*) est le dossier tout en haut. Elle se note `/` (une simple barre oblique).
- Le **séparateur** entre les niveaux est aussi `/`. Ainsi `/home/alex/photos` se lit « dans la
  racine, le dossier `home`, dedans le dossier `alex`, dedans le dossier `photos` ».
- Ton **dossier personnel** (*home*) est l'endroit qui t'appartient, typiquement `/home/alex` sous
  Linux ou `/Users/alex` sous macOS. C'est là que tu travailles par défaut.

> **Attention** — Sous Unix le séparateur est la **barre oblique** `/` (penchée vers la droite).
> Windows utilise l'antislash `\` ; ne les confonds pas.

## Où suis-je ? `pwd`

Quand tu ouvres un terminal, tu es **quelque part** dans l'arbre : c'est ton **dossier courant**
(*current working directory*). Toutes tes commandes s'exécutent relativement à cet endroit, donc il
faut toujours savoir où on est. La commande `pwd` (*print working directory*) te le dit :

```bash
$ pwd
/home/alex
```

Tu es ici dans ton dossier personnel.

## Lister le contenu : `ls`

`ls` (*list*) affiche ce que contient le dossier courant :

```bash
$ ls
documents  photos  projets
```

Seul, `ls` donne juste les noms. Ses **options** le rendent bien plus utile :

```bash
$ ls -l
total 12
drwxr-xr-x 2 alex alex 4096 juin  10 09:12 documents
drwxr-xr-x 5 alex alex 4096 juin  12 18:30 photos
drwxr-xr-x 3 alex alex 4096 juin  14 08:05 projets
```

L'option `-l` (pour *long*) affiche une ligne détaillée par élément : permissions, propriétaire,
taille, date de modification, nom. On décodera la première colonne (`drwxr-xr-x`) au
[chapitre 6](06-permissions.md) ; pour l'instant, retiens que le `d` en tête signale un **dossier**.

Deux options qu'on combine souvent :

```bash
$ ls -a          # affiche aussi les fichiers cachés (dont le nom commence par un point)
.  ..  .bashrc  documents  photos  projets

$ ls -lh         # tailles "humaines" (Ko, Mo) au lieu d'octets bruts
```

Tu peux **enchaîner les options** : `ls -la` revient à `ls -l -a`. Tu peux aussi donner un dossier en
argument pour lister son contenu sans t'y déplacer :

```bash
$ ls photos
vacances  famille  ecran.png
```

> **À retenir** — `pwd` répond à « où suis-je ? », `ls` à « qu'y a-t-il ici ? ». Ce sont tes deux
> commandes de repérage.

## Se déplacer : `cd`

`cd` (*change directory*) te déplace dans un autre dossier :

```bash
$ cd photos
$ pwd
/home/alex/photos
```

Le prompt change souvent aussi pour refléter ta position. Quelques déplacements indispensables :

```bash
$ cd ..          # remonter d'un cran (vers le dossier parent)
$ cd ../..       # remonter de deux crans
$ cd /etc        # aller directement à un dossier précis
$ cd             # revenir à ton dossier personnel (cd sans argument)
$ cd -           # revenir au dossier où tu étais juste avant
```

Trois symboles à connaître par cœur :

| Symbole | Signifie |
| --- | --- |
| `.` | le dossier **courant** (là où tu es) |
| `..` | le dossier **parent** (un cran au-dessus) |
| `~` | ton **dossier personnel** (raccourci de `/home/alex`) |

Ainsi `cd ~/documents` t'emmène dans ton dossier `documents` où que tu sois, et `cd ~` est équivalent
à `cd` tout court.

> **Astuce** — Utilise la touche `Tab` pour compléter les noms de dossiers : tape `cd doc` puis
> `Tab`, le shell complète en `documents/`. Tu gagnes du temps et tu évites les fautes de frappe.

## Chemins absolus et chemins relatifs

Un **chemin** (*path*) est l'adresse d'un fichier ou d'un dossier dans l'arbre. Il en existe deux
sortes, et bien les distinguer t'évitera beaucoup d'erreurs.

- Un **chemin absolu** part de la racine et commence donc toujours par `/`. Il est valable d'où que
  tu sois, comme une adresse postale complète :

  ```text
  /home/alex/projets/site/index.html
  ```

- Un **chemin relatif** part de ton dossier courant et ne commence **pas** par `/`. Il dépend d'où tu
  te trouves, comme « la deuxième porte à droite » :

  ```text
  projets/site/index.html      (si tu es déjà dans /home/alex)
  ```

Exemple concret. Depuis `/home/alex`, ces deux commandes mènent au même endroit :

```bash
$ cd /home/alex/projets        # chemin absolu : marche depuis n'importe où
$ cd projets                   # chemin relatif : marche seulement depuis /home/alex
```

> **Attention** — La barre de tête change tout. `cd /photos` cherche un dossier `photos` **à la
> racine** (qui n'existe sans doute pas → erreur), alors que `cd photos` cherche un dossier `photos`
> **dans le dossier courant**.

Si tu te trompes de chemin, le shell te prévient sans rien casser :

```bash
$ cd phottos
cd: phottos: Aucun fichier ou dossier de ce type
```

Comme toujours : lis le message, corrige, recommence (souvent une faute de frappe que `Tab` aurait
évitée).

## Résumé

- Les fichiers sont organisés en un **arbre** unique partant de la racine `/`.
- `pwd` indique le **dossier courant** ; `ls` liste son contenu (`-l` détaillé, `-a` cachés, `-h`
  tailles lisibles, options combinables).
- `cd` déplace : `cd nom`, `cd ..` (parent), `cd` ou `cd ~` (maison), `cd -` (précédent).
- `.` = ici, `..` = parent, `~` = dossier personnel.
- Un **chemin absolu** commence par `/` et marche partout ; un **chemin relatif** part du dossier
  courant. La barre de tête fait toute la différence.

## Exercices

### Exercice 1 — Visite guidée

Sans rien créer, déplace-toi pour répondre à ces questions :

1. Va dans ton dossier personnel et affiche son chemin absolu.
2. Liste son contenu, fichiers cachés compris.
3. Remonte à la racine `/` et liste ce qu'elle contient.
4. Reviens à ton dossier personnel en une seule commande.

<details>
<summary>Voir le corrigé</summary>

La démarche : on enchaîne repérage (`pwd`, `ls`) et déplacements (`cd`).

```bash
$ cd            # ou cd ~
$ pwd
/home/alex
$ ls -a
$ cd /
$ ls
$ cd            # cd sans argument ramène toujours à la maison
```

</details>

### Exercice 2 — Absolu contre relatif

Tu te trouves dans `/home/alex/projets`. Tu veux aller dans `/home/alex/photos`. Écris **deux**
commandes différentes qui y parviennent : une avec un chemin absolu, une avec un chemin relatif.

<details>
<summary>Voir le corrigé</summary>

La démarche : le chemin absolu part de `/`, le relatif part de `projets` — il faut donc d'abord
remonter d'un cran avec `..`.

```bash
$ cd /home/alex/photos      # absolu : valable d'où que tu sois
$ cd ../photos              # relatif : remonte vers alex, puis descend dans photos
```

Le `..` remonte de `projets` vers `alex`, puis on redescend dans `photos`. On pourrait aussi écrire
`cd ~/photos`, qui est encore plus court grâce au raccourci `~`.

</details>

## Quiz

**1.** Que fait la commande `pwd` ?
- A. Elle change de mot de passe
- B. Elle affiche le dossier dans lequel tu te trouves
- C. Elle liste les fichiers

**2.** Que signifie `..` dans un chemin ?
- A. Le dossier courant
- B. Le dossier parent (un cran au-dessus)
- C. Ton dossier personnel

**3.** Tu es dans `/home/alex`. Quelle commande t'emmène dans `/home/alex/documents` ?
- A. `cd /documents`
- B. `cd documents`
- C. `cd ..documents`

**4.** Quel chemin est **absolu** ?
- A. `projets/site`
- B. `../site`
- C. `/home/alex/site`

<details>
<summary>Voir les réponses</summary>

1. **B** — `pwd` (*print working directory*) affiche le dossier courant.
2. **B** — `..` désigne toujours le dossier parent ; `.` est le dossier courant.
3. **B** — `cd documents` (relatif) descend dans le sous-dossier. `cd /documents` chercherait un
   dossier à la racine.
4. **C** — Un chemin absolu commence par `/`. Les options A et B sont relatives.

</details>

## Projet fil rouge

Tu vas repérer le dossier que ton futur script sauvegardera.

1. Va dans ton dossier personnel (`cd`).
2. Liste son contenu en détail (`ls -lh`) et choisis un dossier réel à sauvegarder plus tard (par
   exemple `documents`). Si tu n'en as pas, ce n'est pas grave : tu en créeras un au prochain
   chapitre.
3. Note **le chemin absolu** de ce dossier ; tu le retrouveras avec :

   ```bash
   $ cd documents
   $ pwd
   /home/alex/documents
   ```

Garde ce chemin de côté : il deviendra la cible de `sauvegarde.sh`. Au prochain chapitre, tu
apprendras à créer, copier et supprimer fichiers et dossiers — et tu feras ta toute première
sauvegarde à la main.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-manipuler-fichiers.md)
