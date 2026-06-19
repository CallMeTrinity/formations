# Le projet : l'algorithme de vote runoff

[← Chapitre précédent](09-organiser-un-programme.md) · [Sommaire](README.md) · [Chapitre suivant →](11-visualisation-ascii.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- traduire un **algorithme** décrit en français en fonctions C ;
- coder le dépouillement runoff : **comptage**, **recherche du dernier**, **détection d'égalité**,
  **élimination** ;
- orchestrer le tout dans une **boucle de tours** jusqu'à un gagnant ;
- comprendre comment toutes les notions de la formation se combinent dans un vrai programme.

C'est le chapitre d'aboutissement. On ne découvre presque plus de C : on **assemble**. Chaque fonction
ci-dessous tient en quelques lignes parce que tu as les bons outils (structures, pointeurs, tableaux).

## Rappel de l'algorithme

On a des **candidats** (chacun avec un nombre de voix et un statut « éliminé ») et des **bulletins**
(`preferences[v][rang]` = l'indice du candidat que le votant `v` place au rang `rang`). Le
dépouillement procède par tours :

1. **Compter** : chaque votant donne sa voix à son candidat préféré **encore en lice**.
2. **Gagnant ?** Si un candidat a **plus de la moitié** des voix, il est élu. Fin.
3. **Sinon, éliminer** le(s) candidat(s) avec le **moins** de voix, et recommencer. Les votants qui les
   avaient en tête reportent leur voix au tour suivant.

Deux cas particuliers à gérer : plusieurs candidats peuvent être **derniers à égalité** (on les élimine
tous), et il se peut que **tous les candidats restants soient à égalité** (impossible de départager :
ils gagnent ex æquo).

On va écrire **une fonction par étape**. C'est la bonne méthode : un algorithme se découpe en
opérations simples, testables séparément.

## La structure de données

On reprend le modèle des chapitres précédents. Pour alléger les signatures, on regroupe l'état du
scrutin dans une structure `Scrutin`.

```c
typedef struct
{
    char nom[LONGUEUR_NOM];
    int  votes;             // voix au tour courant
    bool elimine;
} Candidate;

typedef struct
{
    Candidate *candidats;   // tableau de nb_candidats
    int **preferences;      // preferences[votant][rang] = indice de candidat
    int nb_candidats;
    int nb_votants;
} Scrutin;
```

Passer un seul `Scrutin *` à chaque fonction est plus lisible que de trimballer quatre arguments. On
travaillera donc avec `Scrutin *s` et on accédera aux champs en `s->candidats`, `s->nb_votants`, etc.

## Étape 1 — Compter les voix (`tabuler`)

Pour chaque votant, on parcourt son classement du plus préféré au moins préféré, et on s'arrête au
**premier candidat non éliminé** : c'est lui qui reçoit la voix. On remet d'abord tous les compteurs à
zéro, car on recompte tout à chaque tour.

```c
void tabuler(Scrutin *s)
{
    // Remise à zéro des compteurs.
    for (int c = 0; c < s->nb_candidats; c++)
        s->candidats[c].votes = 0;

    // Chaque votant vote pour son préféré encore en lice.
    for (int v = 0; v < s->nb_votants; v++)
    {
        for (int rang = 0; rang < s->nb_candidats; rang++)
        {
            int choix = s->preferences[v][rang];
            if (!s->candidats[choix].elimine)
            {
                s->candidats[choix].votes++;
                break;          // voix attribuée : on passe au votant suivant
            }
        }
    }
}
```

Le `break` est essentiel : sans lui, le votant donnerait une voix à **chaque** candidat non éliminé de
son classement, alors qu'il n'en a qu'une à donner, pour son **premier** choix valide.

## Étape 2 — Y a-t-il un gagnant ? (`chercher_gagnant`)

Un candidat gagne dès qu'il dépasse **strictement la moitié** des votants. On renvoie son indice, ou
`-1` si personne n'atteint le seuil (une convention courante pour dire « rien trouvé »).

```c
int chercher_gagnant(Scrutin *s)
{
    for (int c = 0; c < s->nb_candidats; c++)
    {
        if (s->candidats[c].votes > s->nb_votants / 2)
            return c;           // indice du gagnant
    }
    return -1;                  // aucun gagnant ce tour-ci
}
```

Pourquoi `> nb_votants / 2` et pas `>= nb_votants / 2` ? Avec 5 votants, `5 / 2` vaut 2 (division
entière), et il faut **plus** de 2 voix, donc 3, pour avoir la majorité. Le `>` strict est exactement
la bonne condition. C'est le piège de la division entière du [chapitre 2](02-types-et-entrees-sorties.md)
qui se transforme ici en atout.

## Étape 3 — Le minimum de voix (`voix_minimum`)

On cherche le plus petit nombre de voix **parmi les candidats encore en lice** (on ignore les
éliminés). On initialise avec `-1` comme sentinelle « pas encore de valeur ».

```c
int voix_minimum(Scrutin *s)
{
    int min = -1;
    for (int c = 0; c < s->nb_candidats; c++)
    {
        if (s->candidats[c].elimine)
            continue;           // on saute les éliminés
        if (min == -1 || s->candidats[c].votes < min)
            min = s->candidats[c].votes;
    }
    return min;
}
```

Le `continue` (vu au [chapitre 3](03-conditions-et-boucles.md)) saute proprement les candidats hors
course. Sans le test `elimine`, on compterait des candidats à 0 voix déjà sortis, et on n'éliminerait
plus jamais personne.

## Étape 4 — Égalité totale ? (`egalite_totale`)

Avant d'éliminer, on vérifie un cas bloquant : si **tous** les candidats restants ont le même nombre de
voix (égal au minimum), il n'y a personne à éliminer sans tous les sortir. C'est une égalité parfaite :
ils gagnent ensemble.

```c
bool egalite_totale(Scrutin *s, int min)
{
    for (int c = 0; c < s->nb_candidats; c++)
    {
        if (s->candidats[c].elimine)
            continue;
        if (s->candidats[c].votes != min)
            return false;       // au moins un au-dessus du min : pas d'égalité totale
    }
    return true;                // tous les restants sont au min
}
```

## Étape 5 — Éliminer les derniers (`eliminer`)

On marque `elimine = true` pour **tous** les candidats en lice qui ont exactement `min` voix (il peut
y en avoir plusieurs à égalité au dernier rang).

```c
void eliminer(Scrutin *s, int min)
{
    for (int c = 0; c < s->nb_candidats; c++)
    {
        if (!s->candidats[c].elimine && s->candidats[c].votes == min)
            s->candidats[c].elimine = true;
    }
}
```

## Étape 6 — La boucle de tours

On assemble tout. La logique : compter, vérifier le gagnant, sinon traiter l'égalité ou éliminer, et
recommencer, jusqu'à ce qu'un `break` nous sorte.

```c
void depouiller(Scrutin *s)
{
    int tour = 1;
    while (true)
    {
        tabuler(s);                         // 1. compter
        printf("\n--- Tour %d ---\n", tour);
        for (int c = 0; c < s->nb_candidats; c++)
            if (!s->candidats[c].elimine)
                printf("  %-12s %d voix\n", s->candidats[c].nom, s->candidats[c].votes);

        int gagnant = chercher_gagnant(s);  // 2. gagnant ?
        if (gagnant != -1)
        {
            printf("\nGagnant : %s\n", s->candidats[gagnant].nom);
            return;
        }

        int min = voix_minimum(s);
        if (egalite_totale(s, min))         // 3a. égalité totale ?
        {
            printf("\nEgalite totale entre :");
            for (int c = 0; c < s->nb_candidats; c++)
                if (!s->candidats[c].elimine)
                    printf(" %s", s->candidats[c].nom);
            printf("\n");
            return;
        }

        eliminer(s, min);                   // 3b. éliminer les derniers
        tour++;
    }
}
```

`%-12s` aligne le nom sur 12 caractères à **gauche** (le `-`), pour des colonnes propres. La boucle
`while (true)` ne se termine que par un `return` : soit un gagnant, soit une égalité totale. Elle se
termine forcément, car à chaque tour sans gagnant on élimine au moins un candidat : le nombre de
candidats en lice décroît strictement.

> **À retenir** — Un algorithme se code en **petites fonctions** au rôle unique (`tabuler`,
> `chercher_gagnant`, `eliminer`…), puis une boucle qui les orchestre. C'est plus facile à écrire, à
> lire et à déboguer qu'un gros bloc monolithique.

## Résumé

- On découpe l'algorithme en fonctions à responsabilité unique : `tabuler`, `chercher_gagnant`,
  `voix_minimum`, `egalite_totale`, `eliminer`.
- `tabuler` donne à chaque votant **une** voix, pour son premier choix **non éliminé** (le `break` est
  crucial).
- La majorité est `votes > nb_votants / 2` (`>` strict, à cause de la division entière).
- On gère les **égalités** : plusieurs derniers éliminés ensemble, et l'**égalité totale** qui arrête
  le scrutin.
- La **boucle de tours** recompte après chaque élimination jusqu'à un gagnant ; elle se termine car le
  nombre de candidats en lice décroît à chaque tour.

## Exercices

### Exercice 1 — Tracer un dépouillement à la main

Avec les bulletins du fichier d'exemple (Alice=0, Bob=1, Charlie=2) :

```
2 0 1
0 2 1
1 0 2
2 1 0
0 1 2
```

Déroule l'algorithme sur papier : combien de voix chacun au tour 1 ? Qui est éliminé ? Qui gagne et à
quel tour ?

<details>
<summary>Voir le corrigé</summary>

**Tour 1** — premier choix de chaque votant : Charlie, Alice, Bob, Charlie, Alice.
- Alice : 2, Bob : 1, Charlie : 2. Total 5, majorité = 3 voix. Personne n'y est.
- Minimum = 1 (Bob). Pas d'égalité totale. **Bob est éliminé.**

**Tour 2** — on recompte, Bob hors course. Ses électeurs reportent : le votant 2 (Bob, Alice, Charlie)
reporte sur Alice.
- Alice : 3 (votants 1, 2, 4), Charlie : 2 (votants 0, 3).
- Alice a 3 voix > 3 ? Non, mais **> 2** (la moitié de 5) : oui. **Alice gagne au tour 2.**

Ce tracé manuel est exactement ce que ton programme va afficher. Vérifier à la main avant de coder (ou
pour valider la sortie) est une excellente habitude.

</details>

### Exercice 2 — Le `break` oublié

Que se passe-t-il dans `tabuler` si on retire le `break` ? Décris le bug, sans forcément le coder.

<details>
<summary>Voir le corrigé</summary>

Sans le `break`, la boucle interne ne s'arrête pas au premier candidat non éliminé : elle continue, et
chaque candidat non éliminé du classement du votant reçoit **une voix**. Un votant donnerait donc
jusqu'à `nb_candidats` voix au lieu d'une seule. Les totaux exploseraient (un candidat pourrait
dépasser le nombre de votants), la majorité serait faussée, et le dépouillement n'aurait plus aucun
sens. C'est un bug de **logique**, pas de syntaxe : le programme compile et tourne, mais donne un
résultat faux. D'où l'intérêt de tracer un exemple à la main (exercice 1) pour vérifier les totaux.

</details>

## Projet fil rouge

On réunit la lecture du fichier (chapitre 9) et l'algorithme (ce chapitre) dans un `main` complet. Le
programme prend le fichier de bulletins en argument, dépouille, et libère sa mémoire.

```c
// main.c — assemblage final (les fonctions ci-dessus sont supposées définies au-dessus,
// ou réparties dans des modules scrutin.c / scrutin.h comme au chapitre 9).
#include <stdio.h>
#include <stdlib.h>

int main(int argc, char *argv[])
{
    if (argc != 2)
    {
        fprintf(stderr, "Usage : %s <fichier-bulletins>\n", argv[0]);
        return 1;
    }

    Scrutin s;
    if (!charger_scrutin(argv[1], &s))      // version "Scrutin" de la fonction du chapitre 9
    {
        fprintf(stderr, "Lecture du scrutin impossible.\n");
        return 1;
    }

    printf("=== Scrutin a vote alternatif (runoff) ===\n");
    printf("%d candidats, %d votants.\n", s.nb_candidats, s.nb_votants);

    depouiller(&s);                         // l'algorithme complet

    liberer_scrutin(&s);                    // free des candidats et de la grille
    return 0;
}
```

Avec le fichier `bulletins.txt` du chapitre 9 :

```
$ ./runoff bulletins.txt
=== Scrutin a vote alternatif (runoff) ===
3 candidats, 5 votants.

--- Tour 1 ---
  Alice        2 voix
  Bob          1 voix
  Charlie      2 voix

--- Tour 2 ---
  Alice        3 voix
  Charlie      2 voix

Gagnant : Alice
```

Le programme fait exactement ce que tu as tracé à la main. **L'algorithme est complet et fonctionnel.**
Au prochain chapitre, on rend ce déroulé plus parlant avec un **affichage ASCII** : des barres
proportionnelles aux voix et un récapitulatif visuel des éliminations.

---

[← Chapitre précédent](09-organiser-un-programme.md) · [Sommaire](README.md) · [Chapitre suivant →](11-visualisation-ascii.md)
