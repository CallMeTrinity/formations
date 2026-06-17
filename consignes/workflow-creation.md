# Workflow de création d'une formation

Process à suivre pour produire une formation complète. C'est le **point d'entrée opérationnel** :
quand on lance une nouvelle formation, on déroule ces étapes.

## Étape 0 — Lire les consignes

Relire la [charte pédagogique](charte-pedagogique.md), la [structure](structure-formation.md), le
[format de chapitre](format-chapitre.md) et le [style](style-redactionnel.md). Elles priment sur les
habitudes.

## Étape 1 — Cadrer la formation

Avec l'utilisateur, fixer :

- le **slug** (kebab-case, sans accents, précédé de f- pour "formation") ;
- le **public et les prérequis** (par défaut : débutant complet, aucun prérequis) ;
- l'**objectif de sortie** décliné en « à la fin, tu sauras… » (voir la charte).

## Étape 2 — Concevoir et valider le plan de chapitres

- Proposer un **plan de 8 à 14 chapitres**, du décor initial à la consolidation finale.
- **Valider ce plan avec l'utilisateur avant de rédiger** quoi que ce soit. La rédaction ne commence
  qu'une fois le plan accepté.

## Étape 3 — Définir le projet fil rouge

- Choisir un **projet concret** qui grandit au fil de la formation et mobilise les notions vues.
- Découper le projet en **jalons rattachés aux chapitres** : chaque chapitre fait avancer le projet
  d'une étape identifiable.

## Étape 4 — Créer le squelette

- Créer le dossier `<f-slug>/`.
- Créer le `README.md` à partir de [`../templates/formation-README.md`](../templates/formation-README.md)
  (présentation, prérequis, objectifs, plan avec liens, projet fil rouge).
- Optionnel : créer `projet-fil-rouge.md` si le projet mérite une page dédiée.
- Mettre à jour le [catalogue racine](../README.md) : statut `en cours` et lien vers le dossier.

## Étape 5 — Rédiger les chapitres, un par un

Pour chaque chapitre, à partir de [`../templates/chapitre.md`](../templates/chapitre.md) :

- Respecter l'**anatomie** du [format de chapitre](format-chapitre.md) (objectifs, contenu, résumé,
  exercices + corrigés, quiz, jalon projet, navigation).
- Respecter le [style rédactionnel](style-redactionnel.md) (FR, tutoiement, pas d'emoji, code annoté).
- Renseigner la **navigation** haut et bas (liens précédent / sommaire / suivant).
- Avancer dans l'ordre ; ne pas référencer en avant une notion non encore vue.

## Étape 6 — Vérifier

- Tous les chapitres sont présents et liés dans le `README.md`.
- Les **liens de navigation** sont corrects (pas de lien mort, premier/dernier chapitre gérés).
- Les blocs `<details>` (corrigés, réponses du quiz) sont bien formés.
- La progression tient la promesse : un novice qui suit la formation atteint le niveau intermédiaire.
- Mettre à jour le statut en `terminée` dans le [catalogue racine](../README.md).

## Rythme

Sauf demande contraire, **rédiger une formation à la fois**, et au sein d'une formation, valider le
plan avant de produire les chapitres. On peut rédiger les chapitres par lots, mais en respectant
l'ordre.
