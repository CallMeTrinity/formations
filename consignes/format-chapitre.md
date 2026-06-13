# Format d'un chapitre

Anatomie d'un fichier chapitre. Le gabarit prêt à copier est
[`../templates/chapitre.md`](../templates/chapitre.md). Les conventions de rédaction et de code sont
dans [`style-redactionnel.md`](style-redactionnel.md).

## Sections obligatoires, dans l'ordre

1. **Titre** — `# Titre du chapitre` (un seul H1).
2. **Navigation haut** — barre `[← Précédent] · [Sommaire] · [Suivant →]` (voir
   [`structure-formation.md`](structure-formation.md)).
3. **Objectifs du chapitre** — section `## Objectifs` listant ce que l'apprenant saura faire à la
   fin. Formulé du point de vue de l'apprenant (« tu sauras… »).
4. **Contenu** — une ou plusieurs sections `##` progressives, avec exemples concrets et blocs de code
   annotés. C'est le cœur du chapitre.
5. **Résumé** — section `## Résumé` : les points-clés en liste, mémorisables d'un coup d'œil.
6. **Exercices** — section `## Exercices` : énoncés pratiques numérotés. Les **corrigés** suivent,
   masqués dans un bloc `<details>` (voir ci-dessous).
7. **Quiz** — section `## Quiz` : QCM court (3 à 6 questions) pour valider la compréhension. Les
   **réponses** sont masquées dans un `<details>`.
8. **Projet fil rouge — jalon** — section `## Projet fil rouge` : l'étape que ce chapitre fait
   avancer. Absente uniquement si la formation n'a pas de projet fil rouge (rare).
9. **Navigation bas** — identique à la navigation haut.

## Longueur indicative

Un chapitre vise une lecture de 15 à 30 minutes hors exercices. S'il dépasse largement, envisager de
le scinder (voir le découpage dans [`structure-formation.md`](structure-formation.md)).

## Encarts

Mise en valeur **sans emoji**, via des blockquotes à libellé en gras :

```markdown
> **Attention** — Un piège courant ou une erreur fréquente à éviter.

> **Astuce** — Un raccourci ou une bonne pratique qui fait gagner du temps.

> **À retenir** — Un point fondamental qu'il ne faut pas oublier.
```

Utiliser les encarts avec parcimonie : un encart qui revient à chaque paragraphe ne met plus rien en
valeur.

## Exercices et corrigés

- Les énoncés sont **concrets et réalisables** avec ce qui a été vu dans le chapitre (et les
  précédents). Du plus simple au plus difficile.
- Chaque corrigé est **masqué** pour ne pas spoiler, via `<details>` :

```markdown
### Exercice 1 — <titre court>

Énoncé de l'exercice.

<details>
<summary>Voir le corrigé</summary>

Explication de la démarche, puis la solution commentée.

</details>
```

- Le corrigé explique **la démarche**, pas seulement la réponse.

## Quiz

```markdown
## Quiz

**1.** Question à choix multiple ?
- A. …
- B. …
- C. …

**2.** … 

<details>
<summary>Voir les réponses</summary>

1. **B** — courte justification.
2. … 

</details>
```

## Blocs de code

Voir [`style-redactionnel.md`](style-redactionnel.md) pour le détail. En résumé : langage toujours
précisé après les triples backticks, commentaires en français, et la **sortie attendue** montrée
quand elle aide à comprendre.
