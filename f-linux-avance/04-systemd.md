# `systemd` : services, journaux et timers

[← Chapitre précédent](03-paquets.md) · [Sommaire](README.md) · [Chapitre suivant →](05-utilisateurs-et-ssh.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **service** (*daemon*) et le rôle de `systemd` au démarrage ;
- piloter un service avec `systemctl` : démarrer, arrêter, activer au boot, vérifier l'état ;
- lire les **journaux** d'un service avec `journalctl` pour diagnostiquer un problème ;
- écrire ta propre **unité de service** pour lancer un programme à toi en permanence ;
- planifier une tâche récurrente avec un **timer** `systemd` (l'alternative moderne à `cron`).

Au chapitre précédent, tu as **installé** des logiciels. Mais comment un serveur web reste-t-il
allumé jour et nuit, redémarre-t-il tout seul après un reboot, et écrit-il ses journaux quelque part ?
La réponse, sur quasiment toutes les distributions modernes, est **`systemd`**.

## Services et démarrage du système

Un **service** (ou *daemon*, prononcé « démon ») est un programme qui tourne **en arrière-plan**, en
permanence, sans interface : un serveur web, une base de données, le serveur SSH. Personne ne le lance
à la main à chaque démarrage — c'est le rôle du **système d'initialisation**.

`systemd` est ce système d'initialisation. C'est le **premier programme** que le noyau lance au
démarrage (il porte le numéro de processus 1, le **PID 1**). Ensuite, c'est lui qui démarre tous les
services dans le bon ordre, les surveille, les redémarre s'ils tombent, et collecte leurs journaux.

`systemd` décrit chaque chose à gérer comme une **unité** (*unit*). La plus courante est l'unité de
type **service** (fichiers `.service`). Tu pilotes tout ça avec une commande centrale : **`systemctl`**.

> **À retenir** — `systemd` est le chef d'orchestre du système : PID 1, il lance et surveille les
> services. `systemctl` est la télécommande pour lui parler.

## Piloter un service avec `systemctl`

Prenons un exemple concret. Le serveur SSH (qu'on étudie au chapitre 5) s'appelle `ssh` sur Ubuntu
(parfois `sshd`). Les commandes de pilotage sont toujours les mêmes, quel que soit le service.

```bash
$ systemctl status ssh           # quel est l'état du service ? (pas besoin de sudo)
$ sudo systemctl start ssh       # démarrer le service maintenant
$ sudo systemctl stop ssh        # arrêter le service maintenant
$ sudo systemctl restart ssh     # redémarrer (stop puis start)
$ sudo systemctl reload ssh      # recharger sa config sans couper le service
```

La commande la plus utile au quotidien est `status`. Sa sortie dit tout :

```text
$ systemctl status ssh
● ssh.service - OpenBSD Secure Shell server
     Loaded: loaded (/lib/systemd/system/ssh.service; enabled; preset: enabled)
     Active: active (running) since Mon 2026-06-15 09:12:03 UTC; 2h ago
   Main PID: 742 (sshd)
```

Deux mots y sont capitaux :

- `Active: active (running)` → le service **tourne** en ce moment.
- `enabled` → le service est **activé au démarrage** : il se relancera tout seul au prochain boot.

Ce sont deux choses **indépendantes**. Un service peut tourner maintenant mais ne pas être activé au
boot (il ne reviendra pas après un redémarrage), et inversement.

### Activer un service au démarrage

```bash
$ sudo systemctl enable ssh      # se lancera automatiquement à chaque démarrage
$ sudo systemctl disable ssh     # ne se lancera plus au démarrage
$ sudo systemctl enable --now ssh   # active au boot ET démarre tout de suite
```

> **À retenir** — `start`/`stop` agissent **maintenant** ; `enable`/`disable` agissent **au
> démarrage**. Le raccourci `enable --now` fait les deux. Oublier `enable` est l'erreur classique :
> ton service marche, tu redémarres le serveur, et il a disparu.

### Lister les services

```bash
$ systemctl list-units --type=service           # les services chargés
$ systemctl list-units --type=service --state=running   # ceux qui tournent
$ systemctl --failed                             # ceux qui ont échoué (à surveiller)
```

`systemctl --failed` est un excellent réflexe de diagnostic : il montre d'un coup d'œil ce qui ne va
pas sur la machine.

## Lire les journaux avec `journalctl`

Quand un service refuse de démarrer, **les journaux disent pourquoi**. `systemd` centralise les
journaux de tous les services dans un **journal** unique, qu'on consulte avec `journalctl`.

```bash
$ journalctl -u ssh              # tous les journaux du service ssh (-u = unit)
$ journalctl -u ssh -e           # saute directement à la fin (les plus récents)
$ journalctl -u ssh -f           # suit en direct (comme tail -f), Ctrl+C pour quitter
$ journalctl -u ssh --since "10 min ago"   # depuis 10 minutes
$ journalctl -p err -b           # seulement les erreurs, depuis ce démarrage (-b = boot)
```

Le scénario typique de diagnostic :

```bash
$ sudo systemctl restart monservice
$ systemctl status monservice    # état : failed ?
$ journalctl -u monservice -e    # on lit le message d'erreur exact dans les journaux
```

> **Astuce** — Devant un service qui ne démarre pas, l'ordre des réflexes est toujours : `status` pour
> l'état, puis `journalctl -u <service> -e` pour la **cause**. Le message d'erreur est presque toujours
> là, en clair.

`journalctl` peut grossir avec le temps ; on verra au [chapitre 12](12-supervision-maintenance.md)
comment limiter sa taille.

## Écrire ta propre unité de service

Tu peux faire gérer **ton propre programme** par `systemd` — par exemple un petit script ou une
application que tu héberges. Avantage : il démarre au boot, redémarre s'il plante, et ses journaux
arrivent dans `journalctl`, comme un vrai service.

Une unité est un fichier texte. Les tiennes vont dans `/etc/systemd/system/`. Créons un service qui
lance un programme imaginaire `/opt/mon-app/serveur.py`.

```ini
# /etc/systemd/system/mon-app.service
[Unit]
Description=Mon application maison
After=network.target

[Service]
ExecStart=/usr/bin/python3 /opt/mon-app/serveur.py
WorkingDirectory=/opt/mon-app
User=monapp
Restart=on-failure

[Install]
WantedBy=multi-user.target
```

Lis le fichier ligne par ligne, car cette structure revient partout :

- `[Unit]` — métadonnées. `Description` est le libellé affiché par `status`. `After=network.target`
  dit de démarrer **après** que le réseau soit prêt (logique pour un service réseau).
- `[Service]` — le cœur. `ExecStart` est la **commande à lancer** (toujours en chemin absolu).
  `WorkingDirectory` est le dossier de travail. `User` fait tourner le service sous un utilisateur
  **dédié et peu privilégié** (jamais `root` sans raison — on verra pourquoi au chapitre 5).
  `Restart=on-failure` demande à `systemd` de **relancer** le programme s'il se termine en erreur.
- `[Install]` — `WantedBy=multi-user.target` indique à quel moment du démarrage l'activer (en gros :
  « quand le système est prêt à servir »).

Après avoir créé ou modifié un fichier d'unité, il faut **recharger** `systemd` pour qu'il en prenne
connaissance :

```bash
$ sudo systemctl daemon-reload          # systemd relit les fichiers d'unités
$ sudo systemctl enable --now mon-app   # active au boot et démarre
$ systemctl status mon-app              # on vérifie
```

> **Attention** — Oublier `daemon-reload` après avoir édité une unité est l'erreur la plus fréquente :
> tu modifies le fichier, tu redémarres le service, et rien ne change parce que `systemd` lit encore
> l'ancienne version. Le réflexe : **modifier l'unité → `daemon-reload` → `restart`**.

## Planifier une tâche : les timers

Tu connais peut-être `cron` pour exécuter une tâche à intervalle régulier. `systemd` propose une
alternative : les **timers**. Un timer est une unité (`.timer`) qui **déclenche** un service
(`.service`) à un moment donné.

L'avantage par rapport à `cron` : les journaux passent par `journalctl`, le déclenchement se diagnostique
comme un service normal, et la tâche peut « rattraper » une exécution manquée (machine éteinte à
l'heure prévue).

Pour lancer un service tous les jours à 3 h du matin, on écrit **deux** fichiers : le service à exécuter
et le timer qui le déclenche.

```ini
# /etc/systemd/system/sauvegarde.service
[Unit]
Description=Sauvegarde quotidienne

[Service]
Type=oneshot
ExecStart=/opt/scripts/sauvegarde.sh
```

```ini
# /etc/systemd/system/sauvegarde.timer
[Unit]
Description=Déclenche la sauvegarde tous les jours

[Timer]
OnCalendar=*-*-* 03:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

- `Type=oneshot` indique un service qui s'exécute **une fois** puis se termine (une tâche, pas un
  serveur permanent).
- `OnCalendar=*-*-* 03:00:00` se lit « n'importe quelle année, n'importe quel mois, n'importe quel
  jour, à 03:00:00 ». La syntaxe est `année-mois-jour heure:minute:seconde`, avec `*` pour « tous ».
- `Persistent=true` rattrape une exécution manquée si la machine était éteinte à l'heure prévue.

On active **le timer** (pas le service, qui sera déclenché par lui) :

```bash
$ sudo systemctl daemon-reload
$ sudo systemctl enable --now sauvegarde.timer
$ systemctl list-timers            # voir les timers et leur prochaine échéance
```

> **À retenir** — Un timer `systemd` = un fichier `.timer` qui déclenche un fichier `.service`. On
> **active le timer**. C'est l'équivalent moderne d'une ligne de `crontab`, mieux intégré aux journaux.

## Résumé

- `systemd` est le **PID 1** : il démarre et surveille les **services** (daemons). On le pilote avec
  `systemctl`.
- `systemctl status/start/stop/restart` agit **maintenant** ; `enable`/`disable` agit **au démarrage**.
  `enable --now` fait les deux.
- `journalctl -u <service> -e` montre les **journaux** d'un service : le premier réflexe de diagnostic.
- Une **unité de service** (`/etc/systemd/system/*.service`) décrit comment lancer ton programme :
  `ExecStart`, `User`, `Restart`. Après édition : `daemon-reload` puis `restart`.
- Un **timer** (`.timer` + `.service`) planifie une tâche récurrente, en mieux intégré que `cron`.

## Exercices

### Exercice 1 — Maintenant ou au démarrage ?

Tu installes un nouveau serveur web. Tu le démarres avec `sudo systemctl start nginx` et tu vérifies
qu'il tourne. Le lendemain, après un redémarrage du serveur, il ne répond plus. Qu'as-tu oublié, et
quelle commande corrige le problème ?

<details>
<summary>Voir le corrigé</summary>

La démarche : distinguer « tourner maintenant » de « se lancer au démarrage ».

Tu as oublié de l'**activer au boot**. `start` ne fait que le lancer pour la session en cours ; après
un reboot, un service non `enabled` ne revient pas. La correction :

```bash
sudo systemctl enable nginx       # il se relancera désormais à chaque démarrage
sudo systemctl enable --now nginx # variante : active ET (re)démarre tout de suite
```

`systemctl status nginx` montre `enabled` ou `disabled` dans la ligne `Loaded:` — un bon moyen de
vérifier.

</details>

### Exercice 2 — Diagnostiquer un service qui ne démarre pas

Un service `mon-app` que tu viens de créer reste en échec. Décris la séquence de commandes pour
comprendre pourquoi.

<details>
<summary>Voir le corrigé</summary>

La démarche : état, puis journaux.

```bash
systemctl status mon-app          # confirme l'échec, montre les dernières lignes
journalctl -u mon-app -e          # affiche les journaux complets, fin en premier
```

Le message d'erreur exact (chemin introuvable, permission refusée, port déjà pris…) apparaît dans
`journalctl`. Erreurs fréquentes pour une unité maison : `ExecStart` n'est pas un **chemin absolu**, ou
on a oublié `sudo systemctl daemon-reload` après avoir modifié le fichier.

</details>

### Exercice 3 — Un timer hebdomadaire

Tu veux exécuter `/opt/scripts/menage.sh` **tous les dimanches à 4 h**. Écris la ligne `OnCalendar`
correspondante (tu peux t'aider de `systemd-analyze calendar "..."` pour vérifier).

<details>
<summary>Voir le corrigé</summary>

La démarche : `OnCalendar` accepte un jour de semaine devant la date/heure.

```ini
OnCalendar=Sun *-*-* 04:00:00
```

`Sun` cible le dimanche. On peut vérifier l'interprétation et la prochaine échéance avec :

```bash
systemd-analyze calendar "Sun *-*-* 04:00:00"
```

Cette commande affiche la prochaine date de déclenchement, ce qui évite les erreurs de syntaxe.

</details>

## Quiz

**1.** Quel processus `systemd` est-il sur un système Linux moderne ?
- A. Un service comme un autre
- B. Le PID 1, qui démarre et surveille tous les autres services
- C. Le gestionnaire de paquets

**2.** Quelle est la différence entre `systemctl start` et `systemctl enable` ?
- A. Aucune, ce sont des synonymes
- B. `start` lance maintenant ; `enable` fait démarrer au boot
- C. `start` est pour les services, `enable` pour les fichiers

**3.** Quelle commande montre pourquoi un service a échoué ?
- A. `apt show <service>`
- B. `journalctl -u <service> -e`
- C. `ls /etc/systemd/system/`

**4.** Après avoir modifié un fichier `.service`, que faut-il faire avant de redémarrer le service ?
- A. Rien de spécial
- B. `sudo systemctl daemon-reload`
- C. Réinstaller le paquet

<details>
<summary>Voir les réponses</summary>

1. **B** — `systemd` est le PID 1, chef d'orchestre des services.
2. **B** — `start` agit immédiatement, `enable` agit au démarrage (les deux sont indépendants).
3. **B** — `journalctl -u <service>` affiche les journaux, où se trouve la cause de l'échec.
4. **B** — `daemon-reload` fait relire les fichiers d'unités à `systemd` avant le redémarrage.

</details>

## Projet fil rouge

Quatrième jalon : **assure-toi que tes services essentiels survivent à un redémarrage**.

1. Vérifie l'état et l'activation de ton serveur SSH (vital : c'est par lui que tu administreras à
   distance) :

   ```bash
   systemctl status ssh
   sudo systemctl enable ssh      # garantis qu'il revient après un reboot
   ```

2. Liste les services qui tournent et repère ceux que tu reconnais :

   ```bash
   systemctl list-units --type=service --state=running
   ```

3. (Optionnel mais formateur) Écris une petite unité de service maison pour un script de ton choix
   (par exemple un `echo` daté dans un fichier toutes les minutes via un timer), active-la, puis
   observe ses journaux avec `journalctl`. Tu réutiliseras cette compétence pour faire tourner tes
   propres applications.

Tes services sont désormais robustes. Au chapitre suivant, on verrouille **qui** peut se connecter à
ce serveur et **comment**.

---

[← Chapitre précédent](03-paquets.md) · [Sommaire](README.md) · [Chapitre suivant →](05-utilisateurs-et-ssh.md)
