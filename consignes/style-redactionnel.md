# Style rédactionnel

Conventions d'écriture communes à toutes les formations.

## Langue et voix

- **Français**, **tutoiement**.
- Phrases courtes, **voix active**. Préférer « tu lances la commande » à « la commande est lancée ».
- S'adresser directement à l'apprenant. Éviter le « nous » académique et le passif impersonnel.
- Pas de remplissage : chaque phrase apporte une information.

## Termes techniques

- Les termes techniques anglais qui font référence (ex. *shell*, *commit*, *array*) sont **gardés en
  anglais** et **expliqués à leur première apparition**, en italique la première fois.
- On ne traduit pas un terme qui n'est jamais traduit dans la pratique du domaine.
- Une fois un terme défini, on l'emploie sans le redéfinir.

## Aucun emoji

**Pas d'emoji**, nulle part dans le contenu. Pour mettre en valeur :

- des **titres de section** explicites ;
- du **gras** pour les libellés et les mots-clés importants ;
- des **blockquotes** `>` à libellé pour les encarts (voir
  [`format-chapitre.md`](format-chapitre.md)) ;
- des blocs **`<details>`** pour masquer corrigés et réponses.

## Code

- Toujours préciser le **langage** après les triples backticks : ` ```bash `, ` ```ts `, ` ```python `,
  ` ```java `, etc.
- **Commentaires de code en français.**
- Les **commandes**, **chemins**, **noms de fichiers** et **identifiants** dans le texte sont en
  `code inline`.
- Les exemples sont **reproductibles** : l'apprenant doit pouvoir les taper et obtenir le même
  résultat.
- Montrer la **sortie attendue** quand elle aide à comprendre, dans un bloc séparé ou en commentaire,
  en indiquant clairement que c'est une sortie (ex. préfixe `# Sortie :` ou bloc distinct).
- Pour les sessions de terminal, distinguer ce qu'on tape de ce qui s'affiche (ex. prompt `$` pour la
  commande).

## Mise en forme markdown

- Un seul `#` (H1) par fichier. Hiérarchie `##`, `###` ensuite, sans sauter de niveau.
- Listes à puces pour les énumérations, listes numérotées pour les étapes ordonnées.
- Tableaux markdown pour les comparaisons et les références (options, raccourcis…).
- Liens **relatifs** entre fichiers de formation, jamais d'absolu.
- Pas d'images binaires : si une capture est nécessaire, la **décrire textuellement** ou la
  reproduire en bloc de texte/ASCII.

## Captures et schémas

- Préférer un **bloc de code** ou un **schéma ASCII** à une image quand c'est possible (versionnable,
  copiable, lisible partout).
- Si une interface graphique doit être décrite, décrire **les étapes et ce qu'on voit** plutôt que de
  dépendre d'une image.
