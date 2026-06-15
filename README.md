# Formations

Dépôt de formations techniques conçues pour mener un débutant complet à un niveau intermédiaire sur
chaque sujet. Chaque formation est un sous-dossier, organisé en chapitres (un fichier markdown par
chapitre) avec navigation entre les pages.

Pour rédiger ou modifier une formation, suivre les [consignes](consignes/) (voir aussi
[`CLAUDE.md`](CLAUDE.md)).

## Catalogue

| Formation | Slug | Statut | Lien |
| --- | --- | --- | --- |
| Linux & Bash | `linux-bash` | terminée | [linux-bash/](linux-bash/) |
| Algorithmes et structures de données | `algorithmes-et-structures-de-donnees` | idée | — |
| TypeScript | `typescript` | idée | — |
| Symfony | `symfony` | terminée | [symfony/](symfony/) |
| Symfony avancé | `symfony-avance` | terminée | [symfony-avance/](symfony-avance/) |
| JavaFX | `javafx` | idée | — |
| Machine learning avec Python | `machine-learning-python` | idée | — |
| IA pour développeurs : agents, code et API | `ia-pour-developpeurs` | idée | — |

Statuts possibles : `idée` · `en cours` · `terminée`.

## Organisation du dépôt

```
formations/
├── CLAUDE.md          règles auto-chargées, pointe vers les consignes
├── README.md          ce fichier : catalogue des formations
├── consignes/         règles de conception à suivre pour toute formation
├── templates/         gabarits prêts à copier (README de formation, chapitre)
└── <slug>/            une formation par sous-dossier
```
