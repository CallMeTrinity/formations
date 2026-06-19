# Le langage C

Le C est le langage qui a façonné l'informatique moderne. Linux, Windows, macOS, les bases de
données, les interpréteurs Python ou Ruby, le firmware de ta box internet, le code embarqué dans une
voiture : tout ça est écrit en C, en grande partie ou en totalité. Né en 1972, il est toujours là, et
pour une bonne raison. C'est un langage **petit** (une poignée de mots-clés), **rapide** (il compile
en code machine, sans machine virtuelle ni ramasse-miettes) et **proche de la machine** : quand tu
écris du C, tu manipules la mémoire presque directement.

Apprendre le C, ce n'est pas apprendre un langage de plus. C'est comprendre ce que font *vraiment* les
langages que tu utilises déjà. Une variable, c'est quoi en mémoire ? Pourquoi un tableau commence à
l'indice 0 ? Qu'est-ce qu'un pointeur, une fuite mémoire, la pile ? Le C te force à répondre à ces
questions parce qu'il ne cache rien. Cette compréhension te rend meilleur dans **tous** les autres
langages, et t'ouvre les portes du système, de l'embarqué et de la performance.

On travaille avec le compilateur **gcc** sous **Linux**, dans un conteneur **Docker** identique pour
tout le monde. Tu n'as donc besoin ni d'être sous Linux, ni d'installer quoi que ce soit d'autre que
Docker, quelle que soit ta machine.

## Prérequis

- **Avoir déjà programmé** dans un langage, peu importe lequel (Python, JavaScript, Java, PHP…). Tu
  dois être à l'aise avec les notions de variable, condition, boucle et fonction. On ne réexplique pas
  ces concepts en profondeur : on montre comment ils s'écrivent et se comportent en C, et on insiste
  sur ce qui est **propre au C** (types, compilation, pointeurs, mémoire).
- **Aucune connaissance préalable du C** n'est requise. On part de zéro sur le langage lui-même.
- Être à l'aise dans un terminal aide ; le strict nécessaire est rappelé.

Côté matériel : n'importe quel ordinateur (Windows, macOS ou Linux) capable de faire tourner
**Docker**. Le [chapitre 1](01-introduction.md) met en place un environnement identique pour tout le
monde.

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire : tu liras et écriras du C idiomatique
sans paniquer devant un pointeur ou une étoile. Concrètement, tu sauras :

- **compiler** un programme C avec `gcc`, comprendre les étapes (préprocesseur, compilation, édition
  de liens) et lire un message d'erreur du compilateur ;
- manier les **types**, les **entrées/sorties** (`printf`/`scanf`) et les opérateurs sans te faire
  piéger par les conversions implicites ;
- structurer le flot avec **conditions** et **boucles**, et découper ton code en **fonctions** avec
  prototypes ;
- manipuler **tableaux** et **chaînes de caractères** (qui n'existent pas vraiment en C : ce sont des
  tableaux de `char`) ;
- comprendre et utiliser les **pointeurs** : adresses, déréférencement, passage par référence, lien
  entre tableaux et pointeurs ;
- modéliser des données avec des **structures**, des **enums** et `typedef` ;
- gérer la **mémoire dynamique** avec `malloc`/`free`, distinguer **pile** et **tas**, et traquer les
  fuites avec **valgrind** ;
- **organiser un vrai programme** en plusieurs fichiers avec des *headers*, un **Makefile**, des
  arguments de ligne de commande et la lecture de **fichiers** ;
- **déboguer** au `gdb` et reconnaître les erreurs classiques (segfault, débordement, mémoire non
  initialisée).

## Plan de la formation

1. [Introduction : le C, la compilation et ton environnement](01-introduction.md)
2. [Types, variables et entrées/sorties](02-types-et-entrees-sorties.md)
3. [Conditions, boucles et opérateurs](03-conditions-et-boucles.md)
4. [Les fonctions](04-fonctions.md)
5. [Tableaux et chaînes de caractères](05-tableaux-et-chaines.md)
6. [Les pointeurs](06-pointeurs.md)
7. [Structures, enums et typedef](07-structures.md)
8. [La mémoire : pile, tas, malloc et free](08-memoire-dynamique.md)
9. [Organiser un programme : multi-fichiers, headers, Makefile, arguments et fichiers](09-organiser-un-programme.md)
10. [Le projet : l'algorithme de vote runoff](10-projet-runoff.md)
11. [Visualiser le déroulé du scrutin en ASCII](11-visualisation-ascii.md)
12. [Déboguer et fiabiliser : gdb, valgrind, cas limites](12-deboguer.md)
13. [Conclusion et pour aller plus loin](13-conclusion.md)

## Projet fil rouge — un algorithme de vote *runoff*

Tout au long de la formation, tu construis un vrai programme : **`runoff`**, un système de
dépouillement pour un scrutin à **vote alternatif** (en anglais *instant-runoff voting*). C'est un
mode de scrutin réel, utilisé par exemple pour élire des assemblées en Australie ou attribuer les
Oscars.

Le principe : au lieu de cocher un seul nom, chaque votant **classe** les candidats par ordre de
préférence. Le dépouillement se fait par **tours** :

1. On compte, pour chaque votant, sa première préférence parmi les candidats encore en lice.
2. Si un candidat dépasse **50 %** des voix, il est élu : c'est fini.
3. Sinon, on **élimine** le candidat avec le moins de voix, et les votants qui l'avaient classé premier
   reportent leur voix sur leur préférence suivante. On recommence au tour suivant.

C'est plus subtil qu'un scrutin classique : il faut gérer le **report des voix**, les **égalités**
(plusieurs candidats derniers), et le cas où **tous les candidats restants sont à égalité**.

Le projet grandit avec tes connaissances :

- d'abord un squelette qui compile et affiche la bannière du scrutin ;
- puis la lecture du nombre de candidats et de votants, et le calcul du seuil de majorité ;
- ensuite le stockage des **noms** des candidats et des **bulletins classés** dans des tableaux ;
- puis une modélisation propre avec des **structures** (`candidate`) et une allocation **dynamique**
  qui s'adapte à n'importe quel nombre de candidats et de votants ;
- ensuite la lecture des bulletins depuis un **fichier**, et l'organisation du code en plusieurs
  fichiers avec un Makefile ;
- enfin l'**algorithme runoff complet** (comptage, recherche du dernier, détection d'égalité,
  élimination, boucle de tours), un **affichage ASCII** du déroulé tour par tour, et une passe de
  fiabilisation au `gdb` et à `valgrind`.

À la fin, tu disposes d'un programme réel, robuste, que tu comprends de bout en bout — et de la
méthode pour en écrire d'autres.

---

Commencer par le [chapitre 1 →](01-introduction.md).
