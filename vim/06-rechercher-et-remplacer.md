# Rechercher et remplacer

[← Chapitre précédent](05-copier-coller-registres.md) · [Sommaire](README.md) · [Chapitre suivant →](07-plusieurs-fichiers.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- chercher du texte avec `/` et `?`, et naviguer entre les occurrences (`n`, `N`, `*`) ;
- remplacer du texte avec la commande `:substitute` (`:s`) ;
- cibler une portion du fichier avec une **plage** (range) ;
- utiliser les bases des **expressions régulières** pour des recherches puissantes.

Chercher et remplacer, c'est ce qui te fait passer de « éditeur de texte » à « outil de refactoring ».
Renommer une variable partout, reformater une série de lignes, corriger une faute répétée : tout ça
en quelques touches.

## Chercher : / et ?

En mode Normal, tape `/` puis ton motif et `Entrée` : Vim saute à la **prochaine** occurrence.

```text
/prix      cherche "prix" vers l'avant
?prix      cherche "prix" vers l'arrière
```

Une fois la recherche lancée, navigue entre les résultats :

| Touche | Effet |
| --- | --- |
| `n` | occurrence **suivante** (dans le sens de la recherche) |
| `N` | occurrence **précédente** (sens inverse) |
| `*` | cherche le **mot sous le curseur** vers l'avant |
| `#` | cherche le mot sous le curseur vers l'arrière |

`*` est un bijou : place le curseur sur une variable, tape `*`, et tu sautes d'usage en usage sans
rien retaper.

> **Astuce** — Active la surbrillance des résultats et la recherche « au fur et à mesure » :
> `:set hlsearch` (surligne toutes les occurrences) et `:set incsearch` (saute pendant que tu tapes).
> On les met dans le `.vimrc` au chapitre 8. Pour éteindre la surbrillance après coup : `:nohlsearch`
> (ou `:noh`).

La recherche est sensible à la casse par défaut (`Prix` ≠ `prix`). Pour l'ignorer ponctuellement,
ajoute `\c` dans le motif : `/prix\c`. Pour le régler globalement : `:set ignorecase`.

## Remplacer : la commande :substitute

La commande de remplacement est `:substitute`, presque toujours abrégée en `:s`. Sa forme générale :

```text
:[plage]s/motif/remplacement/[options]
```

Lis-la morceau par morceau. Sur la **ligne courante** :

```text
:s/prix/montant/      remplace la 1ʳᵉ occurrence de "prix" par "montant" sur la ligne
:s/prix/montant/g     remplace TOUTES les occurrences de la ligne (g = global sur la ligne)
```

L'option `g` (*global*) est presque toujours ce que tu veux : sans elle, seule la première occurrence
de la ligne est touchée.

### La plage : sur quelles lignes agir

Devant le `s`, tu indiques **où** remplacer :

| Plage | Signifie |
| --- | --- |
| (rien) | la ligne courante seulement |
| `%` | **tout le fichier** |
| `1,10` | les lignes 1 à 10 |
| `.,$` | de la ligne courante à la fin |
| `'<,'>` | la sélection visuelle (apparaît tout seul si tu lances `:s` depuis le mode Visuel) |

Le grand classique du refactoring, **remplacer partout dans le fichier** :

```text
:%s/prix/montant/g      remplace toutes les occurrences de "prix" par "montant" dans tout le fichier
```

> **À retenir** — `:%s/ancien/nouveau/g` est la commande de renommage par excellence. `%` = tout le
> fichier, `g` = toutes les occurrences de chaque ligne. Grave-la.

### Confirmer chaque remplacement

Ajoute l'option `c` (*confirm*) pour valider au cas par cas :

```text
:%s/prix/montant/gc
```

Vim s'arrête sur chaque occurrence et te demande quoi faire. Réponds par une touche :

- `y` : oui, remplace celle-ci ;
- `n` : non, passe à la suivante ;
- `a` : oui, et toutes les suivantes (*all*) ;
- `q` : arrête là ;
- `l` : celle-ci puis arrête (*last*).

C'est le mode le plus sûr quand tu n'es pas certain que toutes les occurrences doivent changer.

## Les expressions régulières, version utile

Une *expression régulière* (souvent *regex*) est un motif qui décrit non pas un texte exact, mais une
**famille** de textes. Vim s'en sert dans `/` et dans `:s`. Voici le minimum vital :

| Motif | Correspond à… |
| --- | --- |
| `.` | n'importe quel caractère |
| `*` | « le motif précédent, répété 0 fois ou plus » |
| `^` | le **début** de ligne |
| `$` | la **fin** de ligne |
| `\d` | un chiffre |
| `\w` | un caractère de « mot » (lettre, chiffre, `_`) |
| `\s` | une espace ou tabulation |
| `\<` `\>` | début / fin de mot (frontières) |
| `[abc]` | un des caractères `a`, `b` ou `c` |

Quelques exemples concrets dans `:s` :

```text
:%s/\s\+$//          supprime les espaces en fin de ligne (\s\+ = une ou +, $ = fin)
:%s/^/# /            ajoute "# " au début de chaque ligne (^ = début de ligne)
:%s/\<prix\>/montant/g   remplace le mot entier "prix" (pas "prixHT" ni "leprix")
```

> **Attention** — En regex Vim, certains caractères ont un sens spécial (`.`, `*`, `$`, `[`…). Pour
> les chercher **littéralement**, échappe-les avec `\` : `\.` cherche un vrai point, `\$` un vrai
> signe dollar. Et `\+` (« une fois ou plus ») a besoin du backslash en mode regex Vim par défaut,
> contrairement à `*`.

### Réutiliser ce qui a été trouvé

Tu peux capturer une partie du motif et la replacer dans le remplacement :

- `&` dans le remplacement = tout le texte trouvé ;
- `\(` `...` `\)` capture un groupe, qu'on rappelle avec `\1`, `\2`…

```text
:%s/total/[&]/g                entoure chaque "total" de crochets → [total]
:%s/\(\w\+\)=\(\w\+\)/\2=\1/    inverse "a=b" en "b=a"
```

Ces deux dernières sont avancées : ne les mémorise pas maintenant, retiens juste qu'elles existent et
reviens-y quand tu en auras besoin.

## Résumé

- `/motif` cherche vers l'avant, `?motif` vers l'arrière. `n`/`N` naviguent ; `*` cherche le mot sous
  le curseur.
- Remplacer : `:[plage]s/motif/remplacement/[options]`.
- Plages clés : aucune (ligne courante), `%` (tout le fichier), `1,10` (lignes 1 à 10).
- Options clés : `g` (toutes les occurrences de la ligne), `c` (confirmer chacune).
- La commande reine : `:%s/ancien/nouveau/g`.
- Regex de base : `.` `*` `^` `$` `\d` `\w` `\s` `\<\>` ; échapper les caractères spéciaux avec `\`.

## Exercices

### Exercice 1 — Naviguer entre les occurrences

Dans `panier.py`, place le curseur sur le mot `prix` (ou `montant`) et saute à toutes ses occurrences
sans retaper le mot.

<details>
<summary>Voir le corrigé</summary>

La démarche : `*` lance une recherche sur le mot sous le curseur, puis `n` enchaîne.

1. Curseur sur une occurrence de `prix`.
2. Tape `*` → tu sautes à l'occurrence suivante (le mot est surligné si `hlsearch` est actif).
3. `n` pour la suivante, `N` pour revenir en arrière.

</details>

### Exercice 2 — Renommer partout

Renomme toutes les occurrences de `produits` en `articles` dans tout le fichier `panier.py`, en une
seule commande.

<details>
<summary>Voir le corrigé</summary>

La démarche : `:%s` avec la plage `%` (tout le fichier) et l'option `g` (toutes les occurrences).

```text
:%s/produits/articles/g
```

Si tu veux valider chaque changement, ajoute `c` : `:%s/produits/articles/gc`, puis réponds `y`/`n`.

</details>

### Exercice 3 — Nettoyer avec une regex

Imagine que certaines lignes de `panier.py` se terminent par des espaces parasites (ajoute-en
quelques-uns pour tester, en mode Insertion). Supprime tous les espaces de fin de ligne du fichier en
une commande.

<details>
<summary>Voir le corrigé</summary>

La démarche : on cible « une ou plusieurs espaces (`\s\+`) suivies de la fin de ligne (`$`) » et on les
remplace par rien.

```text
:%s/\s\+$//
```

`\s` = espace, `\+` = une fois ou plus, `$` = fin de ligne, et le remplacement est vide (rien entre les
deux derniers `/`). Pas d'option `g` nécessaire ici : il n'y a qu'une fin de ligne par ligne.

</details>

## Quiz

**1.** Que fait `:%s/foo/bar/g` ?
- A. Remplace la première occurrence de `foo` sur la ligne courante.
- B. Remplace toutes les occurrences de `foo` par `bar` dans tout le fichier.
- C. Cherche `foo` sans rien remplacer.

**2.** À quoi sert l'option `c` dans `:%s/foo/bar/gc` ?
- A. À copier le résultat.
- B. À confirmer chaque remplacement.
- C. À rendre la recherche sensible à la casse.

**3.** Que fait `*` en mode Normal ?
- A. Sélectionne le mot.
- B. Cherche le mot sous le curseur vers l'avant.
- C. Supprime le mot.

**4.** Dans une regex Vim, que représente `$` ?
- A. Un signe dollar littéral.
- B. La fin de ligne.
- C. Un chiffre.

<details>
<summary>Voir les réponses</summary>

1. **B** — `%` = tout le fichier, `g` = toutes les occurrences de chaque ligne.
2. **B** — `c` (*confirm*) demande validation (`y`/`n`/`a`/`q`/`l`) à chaque occurrence.
3. **B** — `*` recherche vers l'avant le mot sous le curseur ; `#` vers l'arrière.
4. **B** — `$` ancre la fin de ligne. Pour un vrai dollar, on écrirait `\$`.

</details>

## Projet fil rouge

Jalon « remplacement en masse par regex ». Dans `panier.py`, sans souris :

1. Renomme toutes les occurrences de `nom` en `libelle` avec `:%s/\<nom\>/libelle/g` (les `\<\>`
   évitent de toucher un mot qui contiendrait `nom`).
2. Commente toutes les lignes d'un bloc de ton choix d'un coup, par exemple en sélectionnant des
   lignes en Visuel-ligne (`V` + `j`) puis `:s/^/# /` (la plage `'<,'>` se met toute seule).
3. Nettoie d'éventuels espaces de fin de ligne avec `:%s/\s\+$//`.
4. Enregistre (`:w`).

Ajoute à `cheatsheet.md` :

```markdown
## Rechercher / remplacer
- `/motif` avant · `?motif` arrière · `n`/`N` suivant/précédent · `*` mot sous le curseur
- `:noh` éteindre la surbrillance
- `:s/a/b/` ligne · `:s/a/b/g` toute la ligne · `:%s/a/b/g` tout le fichier
- option `c` : confirmer (`y`/`n`/`a`/`q`/`l`)
- regex : `.` `^` `$` `\d` `\w` `\s` `\<\>` · échapper les spéciaux avec `\`
```

---

[← Chapitre précédent](05-copier-coller-registres.md) · [Sommaire](README.md) · [Chapitre suivant →](07-plusieurs-fichiers.md)
