# Processus et automatisation

[← Chapitre précédent](10-logique-scripts.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre ce qu'est un **processus** et lister ceux qui tournent (`ps`, `top`) ;
- lancer un programme en arrière-plan (`&`, `jobs`, `fg`) et l'arrêter (`kill`) ;
- planifier l'exécution automatique d'un script avec `cron` ;
- lire et écrire une ligne de planification (la syntaxe `crontab`).

Jusqu'ici tu lançais des commandes et tu attendais qu'elles finissent. Ce chapitre t'apprend à gérer
plusieurs programmes en parallèle et, surtout, à faire travailler la machine **sans toi** — l'aboutis-
sement de l'automatisation.

## Qu'est-ce qu'un processus ?

Un **processus** est un programme **en cours d'exécution**. Quand tu lances `ls`, le système crée un
processus, lui donne un numéro unique appelé **PID** (*Process IDentifier*), l'exécute, puis le
termine. À tout instant, des dizaines de processus tournent sur ta machine.

### Lister les processus : `ps`

```bash
$ ps
    PID TTY          TIME CMD
   2841 pts/0    00:00:00 bash
   3012 pts/0    00:00:00 ps
```

Seul, `ps` ne montre que les processus de ton terminal. Pour voir **tous** ceux du système, l'usage
le plus courant est `ps aux`, souvent combiné à `grep` pour retrouver un programme précis :

```bash
$ ps aux | grep firefox        # retrouve les processus de Firefox
```

Chaque ligne donne notamment le PID, l'utilisateur propriétaire, et la consommation mémoire/CPU.

### Surveiller en direct : `top`

`ps` est une photo à un instant donné. `top` (ou son cousin plus lisible `htop`, à installer) affiche
un **tableau de bord vivant**, rafraîchi en continu, des processus qui consomment le plus :

```bash
$ top              # quitter avec q
```

C'est l'outil pour répondre à « pourquoi mon ordinateur rame ? » : tu repères en un coup d'œil le
processus qui dévore le processeur ou la mémoire. On quitte avec `q`, comme `man` et `less`.

## Arrière-plan et premier plan

Quand tu lances une commande longue, ton terminal est bloqué jusqu'à la fin. Tu peux la lancer en
**arrière-plan** en ajoutant `&`, ce qui libère immédiatement le prompt :

```bash
$ ./traitement-long.sh &
[1] 3245                       # [numéro de tâche] PID
$ 
```

Tu récupères la main tout de suite. Quelques commandes pour gérer ces tâches :

| Commande | Effet |
| --- | --- |
| `cmd &` | lancer `cmd` en arrière-plan |
| `jobs` | lister les tâches de ce terminal |
| `fg %1` | ramener la tâche 1 au premier plan |
| `bg %1` | reprendre en arrière-plan une tâche suspendue |
| `Ctrl` + `Z` | suspendre la tâche du premier plan |

```bash
$ jobs
[1]+  Running   ./traitement-long.sh &
$ fg %1                        # reprend la tâche au premier plan
```

## Arrêter un processus : `kill`

`kill` envoie un **signal** à un processus, le plus souvent pour lui demander de s'arrêter. On le
désigne par son PID :

```bash
$ kill 3245                    # demande poliment au processus 3245 de se terminer
```

Par défaut, `kill` envoie le signal `TERM` (*terminate*), qui demande au programme de s'arrêter
proprement. Si un processus est bloqué et ignore cette demande, on emploie le signal `KILL` (numéro
9), qui le termine de force et sans condition :

```bash
$ kill -9 3245                 # arrêt forcé, en dernier recours
```

> **Attention** — `kill -9` ne laisse pas au programme le temps de sauvegarder ou de nettoyer.
> Réserve-le aux processus vraiment bloqués ; essaie d'abord `kill` tout court. La commande
> `pkill firefox` permet de tuer par nom plutôt que par PID, pratique mais à manier avec soin.

> **À retenir** — `Ctrl` + `C` que tu utilises depuis le chapitre 1 envoie en fait le signal
> `INT` (interruption) au processus du premier plan. C'est la même mécanique de signaux que `kill`.

## Automatiser avec `cron`

Voici l'outil qui couronne la formation. **`cron`** est un service du système qui exécute des
commandes **automatiquement, à intervalles réguliers**, sans que tu sois présent. Sauvegarde
nocturne, nettoyage hebdomadaire, rapport quotidien : tout cela se planifie avec `cron`.

La liste de tes tâches planifiées s'appelle la **crontab** (*cron table*). On l'édite avec :

```bash
$ crontab -e          # éditer ta crontab
$ crontab -l          # afficher ta crontab
```

### La syntaxe d'une ligne cron

Chaque tâche tient sur une ligne : **cinq champs de temps**, puis la **commande** à exécuter.

```text
┌───────── minute (0-59)
│ ┌─────── heure (0-23)
│ │ ┌───── jour du mois (1-31)
│ │ │ ┌─── mois (1-12)
│ │ │ │ ┌─ jour de la semaine (0-7, 0 et 7 = dimanche)
│ │ │ │ │
* * * * *  commande à exécuter
```

Un `*` signifie « toutes les valeurs ». Quelques exemples qui valent mille explications :

```cron
0 3 * * *      /home/alex/sauvegarde.sh /home/alex/donnees     # tous les jours à 3h00
30 8 * * 1     /home/alex/rapport.sh                           # chaque lundi à 8h30
*/15 * * * *   /home/alex/check.sh                             # toutes les 15 minutes
0 0 1 * *      /home/alex/menage.sh                            # le 1er de chaque mois à minuit
```

Lis chaque ligne de gauche à droite : `0 3 * * *` = « à la minute 0 de l'heure 3, tous les jours, tous
les mois, tous les jours de la semaine ». La notation `*/15` signifie « tous les 15 ».

> **Astuce** — Le site *crontab.guru* traduit n'importe quelle ligne cron en phrase claire (et
> inversement). Garde-le sous la main : la syntaxe est facile à relire mais facile à mal écrire.

### Les pièges de cron

`cron` s'exécute dans un environnement **minimal**, très différent de ton terminal. Deux conséquences
qui causent 90 % des « ça marche à la main mais pas en cron » :

- **Utilise des chemins absolus.** Le `PATH` et le dossier courant ne sont pas ceux de ta session.
  Écris `/home/alex/sauvegarde.sh` et non `./sauvegarde.sh`, et donne des chemins absolus aux fichiers
  manipulés.
- **Redirige la sortie pour la garder.** En cron, il n'y a pas d'écran. Capture tout dans un fichier
  pour pouvoir diagnostiquer :

  ```cron
  0 3 * * *  /home/alex/sauvegarde.sh /home/alex/donnees >> /home/alex/cron.log 2>&1
  ```

  On reconnaît `>>` (ajouter au journal) et `2>&1` (y inclure aussi les erreurs), vus au chapitre 5.

## Résumé

- Un **processus** est un programme en exécution, identifié par un **PID** ; `ps aux` les liste, `top`
  les surveille en direct (quitter avec `q`).
- `&` lance en arrière-plan ; `jobs`, `fg`, `bg` et `Ctrl`+`Z` gèrent ces tâches.
- `kill PID` arrête un processus (signal `TERM`) ; `kill -9` force l'arrêt, en dernier recours.
- `cron` exécute des commandes automatiquement ; on édite la **crontab** avec `crontab -e`.
- Une ligne cron = **minute heure jour-du-mois mois jour-de-semaine** + commande ; `*` = toutes les
  valeurs, `*/n` = tous les n.
- En cron : **chemins absolus** et **redirection de la sortie**, sinon « ça ne marche pas ».

## Exercices

### Exercice 1 — Gérer une tâche en arrière-plan

1. Lance la commande `sleep 60` (qui ne fait qu'attendre 60 secondes) en arrière-plan.
2. Vérifie qu'elle apparaît dans la liste des tâches.
3. Arrête-la avant la fin sans attendre.

<details>
<summary>Voir le corrigé</summary>

La démarche : `&` pour l'arrière-plan, `jobs` pour lister, puis `kill` sur son numéro de tâche (ou
son PID).

```bash
$ sleep 60 &
[1] 4501
$ jobs
[1]+  Running   sleep 60 &
$ kill %1            # %1 désigne la tâche numéro 1 ; kill 4501 marche aussi
```

`%1` est le numéro de **tâche** (entre crochets), `4501` le **PID**. `kill` accepte les deux.

</details>

### Exercice 2 — Traduire des besoins en lignes cron

Écris la ligne cron correspondant à chacun de ces besoins (la commande peut être
`/home/alex/script.sh`) :

1. Tous les jours à 22h00.
2. Toutes les heures pile.
3. Tous les dimanches à 6h30.

<details>
<summary>Voir le corrigé</summary>

La démarche : remplir les cinq champs *minute heure jour-mois mois jour-semaine*, en mettant `*` là
où « peu importe ».

```cron
0 22 * * *    /home/alex/script.sh      # tous les jours à 22h00
0 * * * *     /home/alex/script.sh      # toutes les heures (minute 0)
30 6 * * 0    /home/alex/script.sh      # dimanche (0) à 6h30
```

Pour le 2, la minute est fixée à `0` et l'heure à `*` : la tâche part au début de chaque heure. Pour
le 3, `0` dans le dernier champ désigne le dimanche.

</details>

## Quiz

**1.** Qu'est-ce qu'un PID ?
- A. Le nom d'un fichier
- B. Le numéro unique d'un processus en cours
- C. Une variable d'environnement

**2.** Que fait `./script.sh &` ?
- A. Supprime le script
- B. Lance le script en arrière-plan, libérant le prompt
- C. Lance le script deux fois

**3.** Dans la ligne cron `0 3 * * *`, quand la tâche s'exécute-t-elle ?
- A. Toutes les 3 minutes
- B. Tous les jours à 3h00
- C. Le 3 de chaque mois

**4.** Pourquoi un script qui marche à la main peut-il échouer en cron ?
- A. cron ne sait pas exécuter de scripts
- B. cron a un environnement minimal : chemins relatifs et `PATH` différents
- C. cron supprime les scripts après exécution

<details>
<summary>Voir les réponses</summary>

1. **B** — Le PID identifie de façon unique un processus en exécution.
2. **B** — Le `&` lance en arrière-plan et rend la main immédiatement.
3. **B** — `0 3 * * *` = minute 0, heure 3, tous les jours.
4. **B** — L'environnement de cron diffère ; d'où l'importance des chemins absolus et de la
   redirection de sortie.

</details>

## Projet fil rouge

Dernière étape : faire tourner `sauvegarde.sh` **tout seul**, chaque nuit. Ton outil deviendra alors
vraiment automatique.

1. Vérifie le chemin absolu de ton script :

   ```bash
   $ ls -l ~/sauvegarde.sh
   -rwxr--r-- 1 alex dev ... /home/alex/sauvegarde.sh
   ```

2. Ouvre ta crontab et ajoute une ligne pour une sauvegarde quotidienne à 2h00, en chemins absolus et
   avec journalisation :

   ```bash
   $ crontab -e
   ```

   ```cron
   0 2 * * *  /home/alex/sauvegarde.sh /home/alex/donnees >> /home/alex/sauvegardes/cron.log 2>&1
   ```

3. Vérifie que la tâche est bien enregistrée :

   ```bash
   $ crontab -l
   ```

4. Pour tester sans attendre 2h du matin, programme-la temporairement quelques minutes plus tard (par
   exemple `*/2 * * * *` pour toutes les 2 minutes), observe le journal grandir avec
   `tail -f ~/sauvegardes/cron.log`, puis remets l'horaire de production.

Félicitations : ton projet fil rouge est complet. `sauvegarde.sh` reçoit un dossier en argument, vérifie
ses entrées, copie vers une archive horodatée, journalise chaque opération et s'exécute
automatiquement grâce à `cron`. Tu as construit un outil réel, de bout en bout. Le dernier chapitre
fait le bilan et trace les pistes pour aller plus loin.

---

[← Chapitre précédent](10-logique-scripts.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)
