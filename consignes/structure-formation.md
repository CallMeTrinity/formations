# Structure d'une formation

Comment une formation est rangée et naviguée. Voir [`format-chapitre.md`](format-chapitre.md) pour le
contenu interne d'un chapitre.

## Arborescence

Une formation est un sous-dossier direct du dépôt, nommé par son slug. Elle contient un `README.md`
(page d'accueil) et **un fichier markdown par chapitre**.

```
<slug-formation>/
├── README.md                       présentation, prérequis, objectifs, plan, projet fil rouge
├── 01-introduction.md
├── 02-<titre-chapitre>.md
├── 03-<titre-chapitre>.md
├── ...
└── projet-fil-rouge.md             énoncé global du projet + rappel des jalons (optionnel)
```

`projet-fil-rouge.md` est optionnel : on le crée quand le projet mérite une page dédiée (énoncé
détaillé, ressources, état final attendu). Sinon, le projet est décrit dans le `README.md` et ses
jalons apparaissent chapitre par chapitre.

## Nommage

- **Slugs** en `kebab-case`, sans accents ni majuscules : `machine-learning-python`,
  `algorithmes-et-structures-de-donnees`.
- **Fichiers chapitres** préfixés d'un numéro à deux chiffres pour garantir l'ordre : `01-`, `02-`,
  …, `10-`, `11-`. Le reste du nom est un slug court du titre du chapitre.
- Un seul titre `# H1` par fichier, qui correspond au titre du chapitre.

## Découpage en chapitres

- Viser **8 à 14 chapitres**. En dessous, la progression est trop abrupte ; au-dessus, la formation
  devient difficile à tenir.
- Un chapitre = **une grande notion** cohérente. Si un chapitre devient trop long ou couvre deux
  idées indépendantes, le scinder.
- Le **premier chapitre** pose le décor (qu'est-ce que c'est, pourquoi, installation/mise en place).
- Le **dernier chapitre** consolide : récapitulatif, ouverture vers le niveau avancé, et finalisation
  du projet fil rouge.

## Navigation

La navigation se fait par **liens relatifs markdown**, jamais par chemins absolus.

- Le `README.md` de la formation liste **tous les chapitres dans l'ordre**, chacun avec un lien.
- Chaque fichier chapitre porte une **barre de navigation en haut et en bas**, identiques :

  ```markdown
  [← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-...md)
  ```

- Le premier chapitre n'a pas de « précédent » (le remplacer par `Sommaire`), le dernier n'a pas de
  « suivant ».

## Page d'accueil de la formation (`README.md`)

Construite à partir de [`../templates/formation-README.md`](../templates/formation-README.md). Elle
contient au minimum :

1. Titre et présentation du sujet (pourquoi l'apprendre, à quoi ça sert).
2. **Prérequis** (ou « aucun prérequis »).
3. **Ce que tu sauras faire à la fin** (déclinaison de l'objectif de sortie de la charte).
4. **Plan de la formation** : liste numérotée des chapitres avec liens.
5. **Projet fil rouge** : présentation du projet construit au fil de la formation.

## Statuts et mise à jour du catalogue

Quand l'état d'une formation change, mettre à jour la table du [`README.md` racine](../README.md) :

- `idée` — listée mais non commencée.
- `en cours` — au moins un chapitre rédigé.
- `terminée` — tous les chapitres rédigés et navigation vérifiée.

Renseigner aussi le lien vers le dossier de la formation dès qu'il existe.
