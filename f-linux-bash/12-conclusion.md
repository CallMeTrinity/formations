# Conclusion et pour aller plus loin

[← Chapitre précédent](11-processus-et-cron.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- situer ce que tu as appris dans une vue d'ensemble cohérente ;
- appliquer les bonnes pratiques d'écriture de scripts robustes ;
- reconnaître et éviter les pièges les plus dangereux du terminal ;
- choisir tes prochaines étapes pour progresser vers le niveau avancé.

Tu es parti d'un écran noir intimidant. Tu sais désormais te déplacer, manipuler des fichiers, filtrer
du texte, gérer permissions et processus, et écrire des scripts qui s'exécutent tout seuls. Ce chapitre
consolide l'ensemble et t'ouvre la suite.

## Vue d'ensemble du chemin parcouru

Chaque chapitre a posé une brique. Voici comment elles s'empilent :

| Chapitres | Compétence acquise |
| --- | --- |
| 1–2 | Comprendre le terminal et naviguer dans le système de fichiers |
| 3 | Créer, copier, déplacer, supprimer fichiers et dossiers |
| 4–5 | Lire et filtrer du texte ; redirections et tuyaux pour composer des commandes |
| 6 | Permissions, propriétaires et `sudo` |
| 7 | Recherche et transformation de texte (`find`, `sort`, `sed`, `awk`) |
| 8 | Variables, environnement, `PATH`, personnalisation |
| 9–10 | Écrire des scripts : arguments, conditions, boucles, fonctions |
| 11 | Processus et automatisation avec `cron` |

Le fil conducteur : **chaque petite commande fait une chose**, et on les **combine** (par des tuyaux,
des scripts, des planifications) pour résoudre de grands problèmes. C'est la philosophie Unix, et c'est
ce qui fait sa puissance durable.

## Bonnes pratiques pour tes scripts

Maintenant que tu écris des scripts, quelques habitudes te feront passer pour quelqu'un qui sait ce
qu'il fait — et surtout t'éviteront des bugs.

- **Toujours le shebang** en première ligne : `#!/bin/bash`.
- **Guillemets autour des variables** : `"$fichier"`, pas `$fichier`. C'est la cause numéro un de bugs
  dès qu'un nom contient un espace ou qu'une variable est vide.
- **Vérifier les entrées** avant d'agir : l'argument est-il fourni ? le fichier existe-t-il ? Échouer
  tôt avec un message clair (`exit 1`) vaut mieux qu'un dégât silencieux.
- **Des noms parlants** pour les variables et les fonctions : `dossier_source` plutôt que `d`.
- **Commenter le pourquoi**, pas le comment : le code dit déjà ce qu'il fait.
- **Tester sur des données jetables** avant de lancer sur de vraies données, surtout avec `rm`, `mv -i`
  ou `sed -i`.

> **Astuce** — Ajoute `set -euo pipefail` juste après le shebang de tes scripts sérieux. En résumé :
> le script s'arrête à la première erreur (`-e`), refuse les variables non définies (`-u`), et propage
> les échecs dans les tuyaux (`pipefail`). C'est un filet de sécurité que les professionnels mettent
> par réflexe.

## Les pièges à ne jamais oublier

Quelques gestes peuvent causer des dégâts irréversibles. Garde-les en tête :

- **`rm` ne pardonne pas.** Pas de corbeille. Relis toujours la ligne, surtout avec `-r` et `*`. Ne
  tape jamais `rm -rf /` ni une variante « pour voir ».
- **`>` écrase** silencieusement un fichier existant. Utilise `>>` quand tu veux ajouter.
- **`sudo` retire les garde-fous.** Comprends une commande avant de la lancer en `sudo`, surtout si tu
  l'as copiée depuis internet.
- **Les jokers sont développés par le shell.** `rm fichier *.txt` avec un espace de trop n'est pas
  `rm fichier*.txt`. Vérifie avec `ls` d'abord.

> **À retenir** — Le meilleur réflexe de sécurité est gratuit : avant une commande destructive,
> remplace-la mentalement par `ls` ou `echo` pour visualiser ce sur quoi elle va agir.

## Comment continuer à progresser

Tu as le niveau intermédiaire. Pour aller vers l'avancé, voici des pistes concrètes, par ordre
d'utilité.

- **Pratiquer pour de vrai.** Force-toi à faire au terminal ce que tu ferais à la souris : naviguer,
  renommer, chercher. C'est la répétition qui ancre.
- **Apprendre un éditeur en terminal.** `nano` suffit pour débuter, mais investir dans `vim` (ou
  `neovim`) change la vie quand on travaille beaucoup en ligne de commande. Lance `vimtutor` pour un
  tutoriel intégré.
- **Les expressions régulières** (*regex*). Tu as effleuré les motifs avec `grep` ; les regex
  démultiplient la puissance de `grep`, `sed` et `awk`. C'est un sujet à part entière, très rentable.
- **`git`**, le gestionnaire de versions, qui s'utilise principalement au terminal et dont tu maîtrises
  déjà l'environnement.
- **La connexion à distance avec `ssh`**, pour piloter des serveurs — l'usage par excellence de tout
  ce que tu as appris.
- **Lire des scripts existants.** Ceux de ton système (dans `/etc`) ou de projets open source sont une
  mine d'apprentissage.

> **Astuce** — Le réflexe le plus important n'est pas de tout savoir, mais de savoir **chercher** :
> `man commande`, `commande --help`, et formuler clairement ton problème dans un moteur de recherche.
> Un bon utilisateur du terminal est avant tout quelqu'un qui sait se débloquer seul.

## Résumé

- Tu maîtrises la navigation, la manipulation de fichiers, le filtrage de texte, les permissions, les
  processus et l'écriture de scripts automatisés.
- La philosophie Unix : de **petits outils combinables** valent mieux qu'un gros outil monolithique.
- Bonnes pratiques de script : shebang, guillemets autour des variables, vérification des entrées,
  noms parlants, `set -euo pipefail`.
- Pièges majeurs : `rm` sans corbeille, `>` qui écrase, `sudo` sans garde-fou, jokers développés par
  le shell.
- Pour progresser : pratiquer, apprendre `vim`, les regex, `git`, `ssh`, et lire le code des autres.

## Exercices

### Exercice 1 — Le défi de synthèse

Sans regarder le corrigé, écris un script `rapport-disque.sh` qui :

1. affiche la date du jour ;
2. affiche les 3 plus gros fichiers du dossier passé en argument ;
3. refuse de s'exécuter (avec un message clair et `exit 1`) si aucun argument n'est fourni ou si le
   dossier n'existe pas.

Il mobilise des notions de presque tous les chapitres : arguments, tests, `find`, tri, tuyaux.

<details>
<summary>Voir le corrigé</summary>

La démarche : vérifier l'entrée (chapitre 10), puis chercher les fichiers et les trier par taille
(chapitres 7 et 5).

```bash
#!/bin/bash
set -euo pipefail
# rapport-disque.sh - liste les plus gros fichiers d'un dossier.

dossier="${1:-}"

if [ -z "$dossier" ] || [ ! -d "$dossier" ]; then
    echo "Usage : $0 <dossier-existant>" >&2
    exit 1
fi

echo "Rapport du $(date +%Y-%m-%d) pour : $dossier"
echo "Les 3 plus gros fichiers :"
find "$dossier" -type f -exec du -h {} + | sort -rh | head -n 3
```

`${1:-}` vaut le premier argument, ou une chaîne vide s'il manque (utile avec `set -u` qui interdit
les variables non définies). `du -h` donne la taille de chaque fichier, `sort -rh` les classe du plus
gros au plus petit, `head -n 3` n'en garde que trois.

</details>

### Exercice 2 — Relire un script critique

Voici un script trouvé sur internet. Sans l'exécuter, repère **trois problèmes** qui le rendent
dangereux ou fragile.

```bash
#!/bin/bash
cible=$1
cd $cible
rm -rf *
```

<details>
<summary>Voir le corrigé</summary>

La démarche : on applique les bonnes pratiques et les pièges du chapitre.

1. **Aucune vérification de l'argument.** Si `$1` est vide, `cd` (sans argument) t'emmène dans ton
   dossier personnel… puis `rm -rf *` y efface tout. Catastrophe silencieuse.
2. **Variables sans guillemets.** `cd $cible` casse si le chemin contient un espace, et le
   comportement devient imprévisible.
3. **`rm -rf *` sans filet.** Combiné à l'absence de vérification et au `cd` potentiellement raté,
   c'est une bombe. Il faudrait au minimum vérifier que `cd` a réussi (`cd "$cible" || exit 1`) et
   confirmer la cible.

Version corrigée prudente :

```bash
#!/bin/bash
set -euo pipefail
cible="${1:-}"
if [ -z "$cible" ] || [ ! -d "$cible" ]; then
    echo "Usage : $0 <dossier>" >&2
    exit 1
fi
cd "$cible" || exit 1
echo "Contenu qui serait supprime dans $(pwd) :"
ls -A          # on AFFICHE d'abord, on ne supprime pas a l'aveugle
```

Le réflexe clé : un script copié doit toujours être **lu et compris** avant d'être lancé, surtout
s'il contient `rm`, `sudo` ou `dd`.

</details>

## Quiz

**1.** Quelle est la philosophie centrale d'Unix ?
- A. Un seul gros programme qui fait tout
- B. De petits outils spécialisés que l'on combine
- C. Tout faire à la souris

**2.** Pourquoi entoure-t-on les variables de guillemets dans un script ?
- A. Pour les rendre plus lisibles uniquement
- B. Pour éviter les bugs quand une valeur est vide ou contient des espaces
- C. C'est obligatoire sous peine d'erreur de syntaxe

**3.** Que fait `set -e` en tête d'un script ?
- A. Active le mode silencieux
- B. Arrête le script à la première commande qui échoue
- C. Efface l'écran

**4.** Avant une commande destructive comme `rm -r`, quel réflexe est le plus sûr ?
- A. La lancer vite pour ne pas perdre de temps
- B. La remplacer mentalement par `ls`/`echo` pour visualiser la cible
- C. Ajouter `sudo` devant

<details>
<summary>Voir les réponses</summary>

1. **B** — De petits outils combinables : c'est ce qui rend le terminal si puissant.
2. **B** — Les guillemets protègent contre les valeurs vides ou contenant des espaces.
3. **B** — `-e` interrompt le script dès qu'une commande renvoie un code d'échec.
4. **B** — Visualiser la cible avec `ls`/`echo` avant d'agir évite les catastrophes irréversibles.

</details>

## Projet fil rouge

Ton outil `sauvegarde.sh` est fonctionnel et automatisé. Pour clore le projet, applique-lui les bonnes
pratiques de ce chapitre et offre-lui une amélioration de ton choix. Quelques idées, de la plus simple
à la plus ambitieuse :

- **Robustesse** : ajoute `set -euo pipefail` en tête et relis chaque variable pour t'assurer qu'elle
  est entre guillemets.
- **Compression** : remplace la copie par une vraie archive compressée avec `tar` :

  ```bash
  tar -czf "$DESTINATION/$nom_sauvegarde.tar.gz" "$SOURCE"
  ```

  (`tar -czf archive.tar.gz dossier` crée une archive compressée ; `tar -xzf archive.tar.gz` la
  restaure.)
- **Rotation** : supprime automatiquement les sauvegardes de plus de 30 jours avec
  `find "$DESTINATION" -name 'sauvegarde-*' -mtime +30 -delete` (teste sans `-delete` d'abord !).
- **Notification** : à la fin, affiche un résumé (taille de l'archive, nombre de fichiers sauvegardés)
  en t'appuyant sur `du -h` et `find ... | wc -l`.

Tu disposes maintenant d'un outil réel que tu comprends de bout en bout, **et** de la méthode pour en
écrire d'autres. C'est exactement la définition du niveau intermédiaire visé par cette formation :
autonome, capable de te débloquer seul, et conscient des bonnes pratiques.

Bravo d'être allé au bout. Le terminal n'est plus un écran noir intimidant : c'est ton outil.

---

[← Chapitre précédent](11-processus-et-cron.md) · [Sommaire](README.md)
