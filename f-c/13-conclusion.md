# Conclusion et pour aller plus loin

[← Chapitre précédent](12-deboguer.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- situer ce que tu as appris et ce qui te reste à explorer ;
- appliquer les **bonnes pratiques** qui distinguent un programmeur C sérieux ;
- choisir tes prochaines pistes (ncurses, structures de données, normes, bibliothèques) ;
- finaliser et faire évoluer le projet `runoff`.

## Le chemin parcouru

Tu es parti d'un `printf("Bonjour")` et tu as fini avec un programme complet : lecture d'un fichier,
allocation dynamique, un algorithme de dépouillement non trivial, un affichage visuel, et une
fiabilisation au gdb et à valgrind. En route, tu as rencontré tout le **cœur du C** :

- la **compilation** et la chaîne `préprocesseur → compilation → édition de liens` ;
- les **types**, les conversions, et le piège de la division entière ;
- le **contrôle du flot** et les **fonctions** avec prototypes ;
- les **tableaux**, les **chaînes** (tableaux de `char` terminés par `\0`) ;
- les **pointeurs** : adresses, déréférencement, passage par référence, lien tableau/pointeur ;
- les **structures**, `enum`, `typedef` pour modéliser des données ;
- la **mémoire** : pile vs tas, `malloc`/`free`, fuites ;
- l'**organisation** multi-fichiers, les *headers*, le **Makefile**, `argv` et les fichiers ;
- le **débogage** : gdb, valgrind, et les réflexes face aux erreurs classiques.

C'est précisément le bagage qui te rend **autonome** en C : tu peux lire du code existant, écrire un
programme structuré, et te débrouiller face à un bug. Tu es au niveau intermédiaire.

> **À retenir** — Le saut le plus important de cette formation, c'est les **pointeurs et la mémoire**.
> C'est ce que les autres langages te cachent, et ce que tu comprends maintenant. Cette compréhension
> te suivra dans tout ce que tu programmeras ensuite.

## Les bonnes pratiques à garder

Ce qui sépare le code amateur du code solide tient en quelques habitudes, déjà rencontrées au fil des
chapitres. Réunies ici comme aide-mémoire :

- **Compile avec `-Wall -Wextra`, et traite chaque *warning* comme une erreur.** Le compilateur voit
  des bugs que tu ne vois pas.
- **Initialise tes variables et tes pointeurs.** Une variable non initialisée vaut n'importe quoi ; un
  pointeur non initialisé est une bombe.
- **À chaque `malloc` son `free`.** Et `valgrind` avant de dire « c'est fini ».
- **Reste dans les bornes des tableaux.** Le C ne te protège pas ; c'est ton travail.
- **Vérifie les retours** de `malloc`, `fopen`, `scanf`, `fscanf`. Ne suppose jamais que ça a marché.
- **Découpe en petites fonctions** au rôle unique, et en modules `.c`/`.h`. Un fichier de 1000 lignes
  est un fichier qu'on ne maintient pas.
- **Ne fais jamais confiance à l'entrée.** Valide, borne, refuse proprement.

> **Astuce** — Pendant le développement, durcis encore la compilation :
> `gcc -Wall -Wextra -Werror -fsanitize=address,undefined -g`. `-Werror` transforme les *warnings* en
> erreurs (tu ne peux plus les ignorer), et les *sanitizers* attrapent à l'exécution beaucoup de bugs
> mémoire, souvent plus tôt que valgrind.

## Pour aller plus loin

Tu as les bases. Voici les directions naturelles, par ordre d'accessibilité.

**Une vraie interface terminal avec ncurses.** Tu voulais un « GUI » : l'affichage ASCII du chapitre 11
en était la version sobre. L'étape au-dessus est **ncurses**, une bibliothèque (déjà installable dans
ton conteneur via `apt-get install libncurses-dev`, compilée avec `-lncurses`) qui gère un écran
plein : positionnement du curseur, couleurs, rafraîchissement, saisie au clavier sans validation. Tu
pourrais animer le dépouillement runoff tour par tour, avec les barres qui se redessinent. C'est le
pont entre « afficher du texte » et « piloter un écran ».

**Les structures de données.** Tu sais allouer ; l'étape suivante est de construire des structures
chaînées avec des pointeurs : **listes chaînées**, **piles**, **files**, **arbres**, **tables de
hachage**. Le C est le langage idéal pour les comprendre, parce que tu manipules les pointeurs
directement. C'est le prolongement le plus formateur.

**Le standard et la documentation.** Le C a des versions (C99, C11, C17, C23). Apprends à lire la
**documentation** (`man 3 printf` dans le terminal donne la page de manuel d'une fonction) et à fixer
le standard à la compilation (`-std=c11`). Savoir où chercher est une compétence d'intermédiaire.

**Les bibliothèques.** Au-delà de la bibliothèque standard, le monde C est vaste : **SDL** pour le
graphique et le jeu, **SQLite** pour une base embarquée, **libcurl** pour le réseau. Tu sais désormais
inclure un *header* et lier (`-l`) : tu peux les explorer.

**Outiller ton code.** Un **formateur** (`clang-format`) pour un style cohérent, un **analyseur
statique** (`clang-tidy`, `cppcheck`) qui repère des bugs sans exécuter, et des **tests** automatisés.
Ce sont les outils du quotidien d'un développeur C.

## Résumé

- Tu maîtrises le cœur du C : compilation, types, pointeurs, mémoire, structures, organisation,
  débogage. Tu es autonome.
- Les bonnes pratiques tiennent en une poignée d'habitudes : *warnings* traités, variables et pointeurs
  initialisés, mémoire libérée et vérifiée, bornes respectées, retours testés, entrées validées.
- Pistes pour continuer : **ncurses**, les **structures de données chaînées**, le **standard** et la
  doc, les **bibliothèques** externes, l'**outillage** (formateur, analyse statique, tests).

## Exercices

### Exercice 1 — Faire évoluer runoff

Choisis **une** amélioration du projet et implémente-la de bout en bout (code, compilation propre,
valgrind) :

1. lire les bulletins depuis l'**entrée standard** (`stdin`) en plus d'un fichier ;
2. afficher un **récapitulatif final** : l'ordre d'élimination des candidats, tour par tour ;
3. gérer un **départage** plus fin en cas d'égalité (par exemple, le candidat avec le plus de secondes
   préférences l'emporte).

<details>
<summary>Voir le corrigé</summary>

Il n'y a pas une seule bonne réponse : l'objectif est de **mener un changement complet** sur un vrai
programme. La démarche compte plus que la solution.

Pour la piste 2 (récapitulatif), l'idée la plus simple : ajouter un champ `int tour_elimination` à la
structure `Candidate`, le renseigner dans `eliminer` (avec le numéro du tour courant), puis afficher à
la fin les candidats triés par ce champ. Tu réutilises tout ce que tu sais : un champ de structure, une
boucle, un affichage formaté.

L'essentiel, quelle que soit la piste : que ça compile sans *warning*, que valgrind soit propre, et que
tu aies testé un cas limite. C'est la définition d'un changement « fini » qu'on a posée au chapitre 12.

</details>

### Exercice 2 — Lire du code C inconnu

Trouve un petit programme C open source (un utilitaire en ligne de commande de quelques fichiers) et
lis-le. Repère : où est `main` ? Comment le code est-il découpé en modules ? Où alloue-t-il et
libère-t-il sa mémoire ? Quels *headers* inclut-il ?

<details>
<summary>Voir le corrigé</summary>

C'est un exercice ouvert, sans corrigé unique, mais c'est **le plus utile** pour passer à
l'intermédiaire. Savoir lire du code que tu n'as pas écrit est une compétence distincte de savoir en
écrire. Tu remarqueras que les bons projets appliquent exactement les pratiques de cette formation :
fonctions courtes, modules `.h`/`.c`, vérification des retours, libération systématique. Si tu y
comprends l'essentiel, c'est que tu as atteint le niveau visé.

</details>

## Quiz

**1.** Quelle est la valeur ajoutée principale d'apprendre le C, même sans l'utiliser au quotidien ?
- A. C'est le langage le plus rapide à écrire.
- B. Comprendre la mémoire et les pointeurs, ce que les autres langages cachent.
- C. Il a la plus grande bibliothèque standard.

**2.** Avant de considérer un programme C terminé, quel outil passes-tu systématiquement ?
- A. Un formateur de code.
- B. valgrind, pour vérifier l'absence de fuites et d'erreurs mémoire.
- C. Un compilateur en ligne.

**3.** Quelle bibliothèque permettrait de transformer l'affichage ASCII en vraie interface terminal ?
- A. `<string.h>`
- B. ncurses
- C. `<math.h>`

**4.** Que fait `-Werror` à la compilation ?
- A. Cache les erreurs.
- B. Transforme les *warnings* en erreurs, forçant à les corriger.
- C. Désactive les avertissements.

<details>
<summary>Voir les réponses</summary>

1. **B** — la compréhension de la mémoire et des pointeurs est le vrai apport, transférable partout.
2. **B** — valgrind valide l'absence de fuites et d'accès mémoire invalides.
3. **B** — ncurses gère un écran terminal complet (positionnement, couleurs, saisie).
4. **B** — `-Werror` rend les *warnings* bloquants, ce qui force à tous les traiter.

</details>

## Projet fil rouge

Le projet est **terminé** : `runoff` lit un scrutin depuis un fichier, le dépouille selon l'algorithme
du vote alternatif (avec gestion des reports et des égalités), affiche le déroulé tour par tour en
histogramme ASCII, et tourne proprement sous valgrind. Tu l'as construit brique par brique, et tu
comprends chacune de ses lignes.

C'est un vrai projet de portfolio : il mobilise structures, pointeurs, allocation dynamique, lecture de
fichiers, organisation multi-fichiers et un algorithme réel. Quelques façons de le prolonger, si tu
veux le faire grandir :

- la version **ncurses** animée évoquée plus haut, pour un « déroulé du vote » vivant ;
- un jeu de **fichiers de test** couvrant les cas limites (gagnant immédiat, égalité totale, fichier
  malformé), lancés automatiquement par une cible `make test` ;
- l'export du résultat en **CSV** ou en **JSON** pour le réutiliser ailleurs.

Mais même tel quel, tu disposes d'un programme C complet, fiable et compris de bout en bout — et,
surtout, de la **méthode** pour en écrire d'autres. C'était tout l'objectif. Bonne route en C.

---

[← Chapitre précédent](12-deboguer.md) · [Sommaire](README.md)
