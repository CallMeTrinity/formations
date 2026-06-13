# Dépôt de formations

Ce dépôt regroupe des formations techniques rédigées avec l'assistance de l'agent. Chaque formation
vit dans son propre sous-dossier et est constituée de plusieurs fichiers markdown (un par chapitre)
reliés par des liens de navigation.

## Règle impérative

Toute création ou modification d'une formation **doit suivre les fichiers de consignes**. Avant de
rédiger quoi que ce soit, lire les consignes dans cet ordre :

1. [`consignes/charte-pedagogique.md`](consignes/charte-pedagogique.md) — public cible, objectif de
   sortie, principes et ton.
2. [`consignes/structure-formation.md`](consignes/structure-formation.md) — arborescence, nommage,
   navigation, statuts.
3. [`consignes/format-chapitre.md`](consignes/format-chapitre.md) — anatomie d'un fichier chapitre.
4. [`consignes/style-redactionnel.md`](consignes/style-redactionnel.md) — langue, voix, conventions.
5. [`consignes/workflow-creation.md`](consignes/workflow-creation.md) — process pas à pas (point
   d'entrée opérationnel).

## Conventions transverses

- Langue : **français**, tutoiement.
- **Aucun emoji** dans le contenu. La mise en valeur passe par des titres explicites, du **gras**,
  des blockquotes `>` à libellé, et des blocs `<details>` pour masquer corrigés et réponses.
- Slugs en `kebab-case` sans accents ; fichiers chapitres préfixés `01-`, `02-`, etc.
- Objectif de chaque formation : mener un **novice absolu** à un **niveau intermédiaire** (meilleur
  que 50 % des gens sur le sujet).

Les gabarits prêts à copier sont dans [`templates/`](templates/). L'index des formations et leurs
statuts sont dans [`README.md`](README.md).
