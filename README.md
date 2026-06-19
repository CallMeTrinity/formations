# Formations

Dépôt de formations techniques conçues pour mener un débutant complet à un niveau intermédiaire sur
chaque sujet. Chaque formation est un sous-dossier, organisé en chapitres (un fichier markdown par
chapitre) avec navigation entre les pages.

Pour rédiger ou modifier une formation, suivre les [consignes](consignes/) (voir aussi
[`CLAUDE.md`](CLAUDE.md)).

## Catalogue

| Formation | Slug | Statut | Lien                                   |
| --- | --- | --- |----------------------------------------|
| Linux & Bash | `linux-bash` | terminée | [f-linux-bash/](f-linux-bash/)         |
| Le langage C | `c` | en cours | [f-c/](f-c/)                            |
| Algorithmes et structures de données | `algorithmes-et-structures-de-donnees` | idée | —                                      |
| TypeScript | `typescript` | idée | —                                      |
| Symfony | `symfony` | terminée | [f-symfony/](f-symfony/)               |
| Symfony avancé | `symfony-avance` | terminée | [f-symfony-avance/](f-symfony-avance/) |
| JavaFX | `javafx` | idée | —                                      |
| Machine learning avec Python | `machine-learning-python` | idée | —                                      |
| IA pour développeurs : agents, code et API | `ia-pour-developpeurs` | idée | —                                      |
| Vim : coder sans souris | `vim` | terminée | [f-vim/](f-vim/)                       |
| Assembleur x86-64 | `assembleur` | terminée | [f-assembleur/](f-assembleur/)         |

Statuts possibles : `idée` · `en cours` · `terminée`.

## Organisation du dépôt

```
formations/
├── CLAUDE.md          règles auto-chargées, pointe vers les consignes
├── README.md          ce fichier : catalogue des formations
├── consignes/         règles de conception à suivre pour toute formation
├── templates/         gabarits prêts à copier (README de formation, chapitre)
├── <f-slug>/          une formation par sous-dossier (contenu markdown)
└── site/              application Symfony qui importe et sert ce contenu
```

Le dépôt a **deux facettes** : le **contenu** (ce README et les dossiers de formation, source de
vérité en markdown) et le **site** ([`site/`](site/)), une application Symfony 8 + MariaDB qui importe
ce contenu en base pour le consulter, suivre sa progression et recevoir des recommandations. La
documentation technique du site vit dans [`site/docs/`](site/docs/) ; voir [`CLAUDE.md`](CLAUDE.md)
pour les détails d'architecture.
