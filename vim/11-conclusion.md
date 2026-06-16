# Conclusion : s'entraîner et progresser

[← Chapitre précédent](10-vim-dans-l-ide.md) · [Sommaire](README.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- situer ce que tu maîtrises et ce qu'il te reste à automatiser ;
- te donner un **plan d'entraînement** réaliste pour ancrer les réflexes ;
- éviter les pièges qui font régresser ou abandonner ;
- où chercher pour aller vers le niveau avancé ;
- finaliser ton projet fil rouge par une session d'édition complète sans souris.

## Le chemin parcouru

Tu es parti de « comment quitter Vim ? ». Tu sais désormais :

- te déplacer sans souris ni flèches, du caractère au fichier entier (chapitres 2-3) ;
- éditer avec la grammaire **opérateur + mouvement** et les **text objects** (chapitre 4) ;
- copier/coller avec les registres et le presse-papier système (chapitre 5) ;
- chercher et remplacer avec des regex, y compris dans tout un fichier (chapitre 6) ;
- jongler entre plusieurs fichiers : buffers, splits, onglets (chapitre 7) ;
- façonner Vim avec ton `.vimrc` et tes mappings (chapitre 8) ;
- automatiser le répétitif avec macros et marks (chapitre 9) ;
- garder ces réflexes dans ton IDE (chapitre 10).

C'est exactement le bagage d'un utilisateur Vim **intermédiaire** : tu codes une session entière au
clavier, et tu sais chercher dans la doc ce que tu ne connais pas encore. L'objectif de la formation
est atteint. Reste à le **transformer en réflexe**.

## La vérité sur l'apprentissage de Vim

Vim ralentit avant d'accélérer. Les premiers jours, tu es **plus lent** qu'avec la souris : c'est
normal, ton cerveau traduit encore « je veux supprimer ce mot » en `diw` consciemment. Au bout d'une à
deux semaines d'usage quotidien, la traduction devient automatique, et là tu dépasses ta vitesse
d'avant.

> **À retenir** — La courbe d'apprentissage de Vim n'est pas une montagne infranchissable, c'est un
> **petit col** : quelques jours d'inconfort, puis ça roule. Le seul vrai échec, c'est d'abandonner
> pendant l'inconfort initial.

## Un plan d'entraînement réaliste

L'erreur classique : vouloir tout utiliser tout de suite, se décourager, revenir à la souris. La bonne
approche est **progressive**.

**Semaine 1 — Survie.** Utilise Vim (ou le mode Vim de ton IDE) pour **tout** ton code, même si c'est
lent. Autorise-toi les flèches au début. Objectif : ne plus jamais avoir peur de l'éditeur.

**Semaine 2 — Déplacements.** Force-toi à `h j k l` et aux mouvements de mots/ligne. Coupe les flèches
(les mappings `<Nop>` du chapitre 8). Pense « quelle est la plus grande portée vers ma cible ? ».

**Semaine 3 — Grammaire.** Remplace les sélections à rallonge par opérateur + mouvement et text
objects. Chaque fois que tu te surprends à sélectionner lettre par lettre, demande-toi : « quel `ci…`
/ `da…` ferait ça ? ».

**Semaine 4 et après — Automatiser.** Intègre `.` systématiquement, puis les macros pour les tâches
répétitives, les marks pour les gros fichiers. Enrichis ton `.vimrc` d'**un** réglage à la fois, quand
tu en ressens le manque.

> **Astuce** — La règle d'or de la progression : **quand tu fais la même chose trois fois à la main,
> arrête-toi et cherche la commande Vim qui le fait d'un coup.** C'est comme ça qu'on apprend les bons
> raccourcis — par besoin, pas par bachotage.

## Les pièges qui font régresser

- **Copier un `.vimrc` géant** trouvé en ligne : tu hérites de comportements que tu ne maîtrises pas.
  Construis le tien ligne par ligne.
- **Installer 20 plugins** avant de maîtriser le cœur : les plugins amplifient de bonnes habitudes,
  ils ne les remplacent pas.
- **Garder la souris « au cas où »** : tant que la main repart à la souris, le réflexe clavier ne se
  forme pas. Tiens bon la première semaine.
- **Bachoter une liste de raccourcis** sans les utiliser : un raccourci ne s'ancre que par la
  répétition sur du vrai code.

## Quelques commandes bonus utiles

Pour ta culture, sans en faire un nouveau chapitre :

| Commande | Effet |
| --- | --- |
| `:help <sujet>` | l'aide intégrée, exhaustive (ex. `:help text-objects`) |
| `gq` | reformate un paragraphe à la bonne largeur (opérateur) |
| `J` | fusionne la ligne suivante avec la courante |
| `~` | bascule la casse du caractère sous le curseur |
| `g~iw` `guiw` `gUiw` | bascule / minuscule / MAJUSCULE sur un mot |
| `Ctrl-a` / `Ctrl-x` | incrémente / décrémente le nombre sous le curseur |
| `>>` / `<<` | indente / désindente la ligne |
| `zz` | recentre l'écran sur le curseur |

`:help` est ta meilleure ressource : tout Vim y est documenté, et c'est consultable hors ligne. Tape
`:help` seul pour la page d'accueil, puis navigue avec les liens (curseur sur un mot entre `|...|` et
`Ctrl-]` pour le suivre, `Ctrl-o` pour revenir).

## Pour aller plus loin

- `vimtutor` (chapitre 1) : à refaire en entier, tu le verras différemment maintenant.
- **Vim Adventures** : un jeu en ligne pour ancrer les déplacements en s'amusant.
- La commande `:help` et le *user manual* intégré (`:help usr_toc`) : la référence officielle.
- Côté avancé, quand tu seras à l'aise : explorer **Neovim** et son écosystème de plugins (gestionnaire
  de plugins, *LSP* pour l'autocomplétion intelligente, *fuzzy finder* pour ouvrir des fichiers à la
  volée). C'est la suite naturelle, hors du périmètre « quotidien » de cette formation.

## Résumé

- Tu as le bagage d'un utilisateur intermédiaire : déplacements, grammaire d'édition, recherche,
  multi-fichiers, config, macros, IDE.
- Vim ralentit avant d'accélérer : tiens bon le petit col du début.
- Entraîne-toi **progressivement** (survie → déplacements → grammaire → automatisation), un réglage à
  la fois.
- Règle d'or : la **3ᵉ** fois que tu fais une tâche à la main, cherche la commande qui l'automatise.
- `:help` est la ressource ultime, consultable hors ligne.

## Exercices

### Exercice 1 — Audit de tes réflexes

Sans relire la formation, écris de mémoire les commandes que tu utilises pour : supprimer un mot,
changer l'intérieur de guillemets, renommer une variable partout dans un fichier, sauter à la ligne
42, et automatiser une transformation répétée. Compare ensuite avec ta cheat-sheet.

<details>
<summary>Voir le corrigé</summary>

Les réponses attendues : `diw` (supprimer un mot), `ci"` (changer l'intérieur des guillemets),
`:%s/\<ancien\>/nouveau/g` (renommer dans tout le fichier), `42G` (aller à la ligne 42), une **macro**
`q<lettre>` … `q` puis `@<lettre>` (automatiser).

Ce qui te vient sans réfléchir est ancré. Ce que tu as dû chercher est ta zone d'entraînement pour la
semaine.

</details>

### Exercice 2 — Refaire vimtutor en entier

Relance `vimtutor fr` et fais-le du début à la fin. Repère les commandes que tu avais oubliées.

<details>
<summary>Voir le corrigé</summary>

Pas de solution unique : l'intérêt est de revoir les bases avec ton niveau actuel. Tu iras beaucoup
plus vite qu'au chapitre 1, et tu remarqueras des détails qui t'avaient échappé. Note dans ta
cheat-sheet toute commande oubliée.

</details>

### Exercice 3 — Une journée sans souris

Choisis une vraie session de travail (une heure minimum) et code **sans toucher la souris** : Vim ou
mode Vim de ton IDE, déplacements au clavier, recherche, refactoring. À la fin, note les trois moments
où tu as failli reprendre la souris : ce sont tes prochains objectifs d'apprentissage.

<details>
<summary>Voir le corrigé</summary>

La démarche : se mettre en situation réelle et identifier ses points de friction.

Les « envies de souris » typiques et leur réponse Vim :
- changer de fichier → buffers (`:b nom`, `Ctrl-^`) ou explorateur (`:Explore`) ;
- sélectionner un bloc → mode Visuel + text objects (`vi{`, `vip`) ;
- aller à une définition → action de l'IDE mappée (`gd`) ;
- scroller → `Ctrl-d`/`Ctrl-u`, `gg`/`G`, recherche `/`.

Chaque friction résolue est un raccourci de plus dans tes réflexes.

</details>

## Quiz

**1.** Pourquoi se sent-on plus lent les premiers jours sous Vim ?
- A. Vim est intrinsèquement lent.
- B. Les commandes ne sont pas encore automatiques ; le cerveau les traduit consciemment.
- C. Il manque forcément des plugins.

**2.** Quelle est la « règle d'or » de progression proposée ?
- A. Apprendre toute la doc par cœur.
- B. À la 3ᵉ fois qu'on fait une tâche à la main, chercher la commande qui l'automatise.
- C. Installer le plus de plugins possible.

**3.** Quelle ressource est intégrée à Vim et consultable hors ligne ?
- A. `:help`
- B. un moteur de recherche
- C. Vim Adventures

**4.** Quel est le principal risque pour un débutant qui veut « faire comme les pros » tout de suite ?
- A. Aucun.
- B. Copier un `.vimrc` géant et installer plein de plugins qu'il ne maîtrise pas.
- C. Trop utiliser `:help`.

<details>
<summary>Voir les réponses</summary>

1. **B** — La lenteur initiale vient du manque d'automatisme, pas de l'outil. Elle disparaît avec la
   pratique.
2. **B** — Automatiser au moment où le besoin se manifeste (la 3ᵉ répétition) ancre durablement les
   commandes.
3. **A** — `:help` documente tout Vim, hors ligne.
4. **B** — Hériter d'une config et de plugins non maîtrisés mène à des comportements indéboguables et
   au découragement.

</details>

## Projet fil rouge — finalisation

Dernier jalon : la **session d'édition complète, chronométrée, sans souris**. Tu boucles le kit.

1. **Vérifie tes livrables.** Ton `kit-vim` doit contenir `panier.py` (édité tout du long),
   `cheatsheet.md` (complétée à chaque chapitre) et ta config de référence (`vimrc-de-reference`).
2. **Le défi chronométré.** Sur `panier.py`, lance un chrono et réalise sans souris, le plus vite
   possible :
   - renommer une variable partout (`:%s` ou refactoring de l'IDE) ;
   - changer le contenu de deux chaînes (`ci"`) ;
   - ajouter trois produits via une macro ;
   - réindenter le fichier (`gg=G`) ;
   - déplacer une ligne avec `dd`/`p` ;
   - enregistrer (`:w`).
   Refais l'exercice quelques jours plus tard : ton temps devrait nettement baisser.
3. **Fais vivre ta cheat-sheet.** Garde-la sous la main et raye au fur et à mesure les commandes
   devenues des réflexes — ajoute-en de nouvelles dès que tu en découvres.

Bravo : tu codes sans souris. À partir d'ici, c'est la pratique quotidienne qui fait le reste.

---

[← Chapitre précédent](10-vim-dans-l-ide.md) · [Sommaire](README.md)
