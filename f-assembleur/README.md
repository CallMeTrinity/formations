# Assembleur x86-64

L'assembleur, c'est le langage le plus proche du processeur qu'un humain écrive encore à la main.
Chaque ligne correspond à **une instruction** que le CPU exécute directement : déplacer une valeur,
additionner deux nombres, sauter à un autre endroit du programme. Pas de variables typées, pas de
boucle `for`, pas de fonction toute faite : seulement des registres, de la mémoire et des octets.

Pourquoi apprendre ça aujourd'hui, alors que personne n'écrit une application entière en assembleur ?
Parce que c'est là que tombe le voile. Tu comprends enfin ce que *fait vraiment* un `if`, où vivent
tes variables, pourquoi une fonction « coûte » quelque chose, ce qu'est un *stack overflow* pour de
vrai, comment un programme parle au système d'exploitation. Cette compréhension te rend meilleur dans
**tous** les langages haut niveau, te permet de lire le code généré par un compilateur, de déboguer
au niveau machine, et d'aborder la sécurité, l'embarqué ou l'optimisation sans te sentir perdu.

On travaille en **assembleur x86-64** (l'architecture des PC et serveurs modernes) sous **Linux**,
avec l'assembleur **NASM**. C'est le couple le mieux documenté et le plus représentatif de
l'industrie.

## Prérequis

- **Savoir programmer dans un langage haut niveau**, peu importe lequel (C, Python, Java, JavaScript,
  PHP…). Tu dois être à l'aise avec les notions de variable, condition, boucle et fonction. On ne
  réexplique pas ces concepts : on montre comment ils existent au niveau machine.
- **Aucune connaissance préalable de l'assembleur, du binaire ou de l'architecture des processeurs.**
  On part de zéro sur tout ce qui est bas niveau.
- Être à l'aise dans un terminal aide, mais le strict nécessaire est rappelé.

Côté matériel : n'importe quel ordinateur (Windows, macOS ou Linux) capable de faire tourner
**Docker**. Le [chapitre 1](01-introduction.md) met en place un environnement Linux identique pour
tout le monde, quelle que soit ta machine. Tu n'as donc pas besoin d'être sous Linux ni d'avoir un
processeur particulier.

## Ce que tu sauras faire à la fin

À la fin de cette formation, tu seras au niveau intermédiaire : tu liras et écriras de l'assembleur
x86-64 sans paniquer, et tu comprendras la machine mieux que la moitié des développeurs. Concrètement,
tu sauras :

- expliquer comment un programme est représenté en **binaire et hexadécimal**, et comment le CPU
  stocke des entiers (signés, non signés, complément à deux) ;
- décrire le rôle des **registres** et écrire un programme NASM complet, l'**assembler** et le
  **linker** en exécutable ;
- réaliser des calculs avec l'**arithmétique et la logique** machine, et interpréter les **flags** ;
- déclarer et manipuler des **données en mémoire**, avec les bonnes tailles et le bon adressage ;
- reconstruire `if`/`else` et les **boucles** à partir de `cmp` et des sauts conditionnels ;
- utiliser la **pile** et écrire de vraies **fonctions** respectant la convention d'appel (ABI System
  V) ;
- faire des **appels système** pour lire l'entrée et écrire la sortie, et **interfacer ton code avec
  le C** (appeler `printf`, être appelé depuis un programme C) ;
- **déboguer** un programme avec `gdb` et lire le désassemblage produit par un compilateur ;
- savoir où chercher (documentation, tables d'instructions) quand tu rencontres une instruction
  inconnue.

## Plan de la formation

1. [Introduction : la machine, l'assembleur et ton environnement](01-introduction.md)
2. [Représentation des données : binaire, hexadécimal, entiers](02-representation-des-donnees.md)
3. [Registres et premières instructions](03-registres-et-instructions.md)
4. [Arithmétique, logique et flags](04-arithmetique-et-logique.md)
5. [La mémoire et les données](05-memoire-et-donnees.md)
6. [Contrôle du flot : sauts, conditions et boucles](06-controle-du-flot.md)
7. [La pile](07-la-pile.md)
8. [Fonctions et convention d'appel](08-fonctions-et-convention-dappel.md)
9. [Les appels système](09-appels-systeme.md)
10. [Interfacer avec le C](10-interfacer-avec-le-c.md)
11. [Déboguer avec gdb](11-deboguer-avec-gdb.md)
12. [Conclusion et pour aller plus loin](12-conclusion.md)

## Projet fil rouge

Tout au long de la formation, tu construis pas à pas un véritable outil en ligne de commande :
**`stats`**. Le programme lit une suite d'entiers tapés par l'utilisateur et affiche le **nombre de
valeurs, leur somme, le minimum, le maximum et la moyenne**.

Il grandit avec tes connaissances :

- d'abord un squelette qui s'exécute et affiche un message ;
- puis un programme qui stocke et affiche un nombre, calcule une somme, range des valeurs dans un
  tableau en mémoire ;
- ensuite une boucle qui parcourt les valeurs pour trouver le minimum et le maximum ;
- puis des fonctions réutilisables `print_int` et `parse_int` (la conversion entier ↔ texte ASCII,
  l'exercice le plus formateur de tout l'assembleur) ;
- enfin la lecture réelle de l'entrée utilisateur, un affichage propre via `printf`, et une passe de
  débogage au `gdb`.

À la fin, tu disposes d'un programme réel, écrit entièrement en assembleur, que tu comprends de bout
en bout — et de la méthode pour en écrire d'autres.

---

Commencer par le [chapitre 1 →](01-introduction.md).
