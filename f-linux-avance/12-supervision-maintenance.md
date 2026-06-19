# Surveillance, sauvegardes et maintenance

[← Chapitre précédent](11-reverse-proxy.md) · [Sommaire](README.md) · [Chapitre suivant →](13-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- surveiller l'état d'un serveur : charge, mémoire, disque, services ;
- maîtriser les **journaux** avec `journalctl` et éviter qu'ils ne saturent le disque ;
- mettre en place des **sauvegardes** régulières et, surtout, en **vérifier la restauration** ;
- automatiser les **mises à jour de sécurité** et adopter une routine de maintenance.

Monter un serveur, c'est une chose ; le **garder en vie** sur la durée en est une autre. Un serveur
qu'on n'entretient pas finit par tomber : disque plein, service mort sans qu'on le sache, données
perdues, failles non corrigées. Ce chapitre te donne les réflexes pour que ton homelab tourne
durablement et que tu dormes tranquille.

## Surveiller l'état du serveur

Quelques commandes répondent à « est-ce que tout va bien ? ». Elles ne nécessitent pas `sudo`.

### Charge et mémoire

```bash
$ uptime
 14:32:01 up 12 days,  3:14,  1 user,  load average: 0.08, 0.12, 0.09
$ free -h          # mémoire utilisée / libre, en format lisible (-h)
$ htop             # tableau de bord interactif (installé au chapitre 3)
```

`uptime` donne depuis combien de temps la machine tourne et la **charge moyenne** (*load average*) sur
1, 5 et 15 minutes : en gros, le nombre de tâches qui attendent le processeur. Une règle simple : si
cette valeur dépasse durablement le **nombre de cœurs** de ta machine, elle est surchargée. `free -h`
montre la mémoire ; `htop` réunit tout dans une vue vivante.

### Espace disque : l'urgence silencieuse

Un disque plein est l'une des pannes les plus fréquentes et les plus sournoises : les services
s'arrêtent d'écrire, les bases de données se corrompent.

```bash
$ df -h             # espace par système de fichiers (-h = lisible)
Filesystem      Size  Used Avail Use% Mounted on
/dev/sda1        25G   18G  6.2G  75% /
$ du -sh /var/*    # ce qui pèse dans /var, dossier par dossier
```

`df -h` te dit le **pourcentage** d'occupation de chaque disque (la colonne `Use%`). `du -sh` te dit
**où** part la place. Le coupable est souvent dans `/var` : journaux volumineux, images Docker
inutilisées, anciennes sauvegardes.

> **À retenir** — Surveille trois choses en priorité : la **charge** (`uptime`), la **mémoire**
> (`free -h`) et surtout l'**espace disque** (`df -h`). Un disque plein casse les services en
> silence ; c'est la vérification à ne jamais négliger.

### Les services tournent-ils toujours ?

```bash
$ systemctl --failed              # les services en échec (chapitre 4)
$ docker compose ps               # l'état de tes conteneurs (chapitre 10)
```

`systemctl --failed` doit idéalement ne rien afficher. C'est un excellent réflexe quotidien.

## Maîtriser les journaux

Les **journaux** (*logs*) sont ta mémoire : ils racontent ce qui s'est passé. Tu connais déjà
`journalctl` (chapitre 4). Quelques usages de maintenance :

```bash
$ journalctl -p err -b            # les erreurs depuis le dernier démarrage
$ journalctl --since "today"      # tout ce qui s'est passé aujourd'hui
$ journalctl --disk-usage         # combien de place prennent les journaux
```

Le journal de `systemd` peut grossir sans fin. On le **borne** dans `/etc/systemd/journald.conf` :

```ini
# /etc/systemd/journald.conf
[Journal]
SystemMaxUse=500M        # le journal ne dépassera pas 500 Mo
```

Puis `sudo systemctl restart systemd-journald`. On peut aussi purger d'un coup l'ancien :

```bash
$ sudo journalctl --vacuum-time=30d     # ne garde que les 30 derniers jours
```

> **Astuce** — Pour Docker, pense aussi à `docker system prune` : il supprime les images, conteneurs
> arrêtés et caches inutilisés qui s'accumulent et grignotent le disque. À lancer de temps en temps,
> en sachant qu'il efface ce qui n'est pas en cours d'utilisation.

## Sauvegarder : la règle qui sauve

La question n'est pas **si** un disque lâchera ou si tu effaceras un fichier par erreur, mais **quand**.
Une sauvegarde est ta seule assurance. La règle de référence est la **règle 3-2-1** :

- **3** copies de tes données,
- sur **2** supports différents,
- dont **1** hors site (ailleurs que sur le serveur).

Concrètement, pour un homelab, tu sauvegardes : tes **données** (volumes Docker, fichiers servis), tes
**configurations** (`compose.yaml`, configs nginx, `wg0.conf` — idéalement versionnées avec `git`), et
au besoin des bases de données (avec leur outil d'export dédié).

### Un script de sauvegarde simple

En réutilisant `tar` et un timer systemd (chapitres 4), tu automatises une archive horodatée :

```bash
#!/bin/bash
# /opt/scripts/sauvegarde.sh
set -euo pipefail

destination="/sauvegardes"
date_jour="$(date +%Y-%m-%d)"
archive="$destination/homelab-$date_jour.tar.gz"

# archive les données et les configs importantes
tar -czf "$archive" /var/www /opt/homelab /etc/wireguard

# rotation : supprime les sauvegardes de plus de 30 jours
find "$destination" -name 'homelab-*.tar.gz' -mtime +30 -delete

echo "Sauvegarde terminée : $archive"
```

On le déclenche par un **timer systemd** (chapitre 4) chaque nuit. Mais une archive locale ne respecte
pas le « 1 hors site » : il faut **l'envoyer ailleurs**.

```bash
# copier la sauvegarde sur une autre machine via SSH (chapitre 5)
$ rsync -avz /sauvegardes/ antonin@autre-machine:/sauvegardes-distantes/
```

`rsync` copie efficacement (il ne transfère que les différences) vers une autre machine par SSH. C'est
ton « hors site ».

> **Attention** — Une sauvegarde **jamais testée** n'est pas une sauvegarde. Le jour du pépin, tu
> découvres trop tard qu'elle était vide, corrompue ou incomplète. **Restaure régulièrement** une
> archive sur une machine de test pour vérifier qu'elle fonctionne vraiment. C'est l'étape que tout le
> monde saute, et c'est celle qui compte le plus.

## Automatiser les mises à jour de sécurité

Au chapitre 3, tu mettais à jour à la main. Sur un serveur exposé qui tourne en continu, on
**automatise au moins les correctifs de sécurité** avec `unattended-upgrades` :

```bash
$ sudo apt install unattended-upgrades
$ sudo dpkg-reconfigure -plow unattended-upgrades    # répondre "Oui" pour activer
```

Une fois activé, le serveur installe **seul** les mises à jour de sécurité, sans rien casser des
versions majeures. Tu gardes la maîtrise des grosses montées de version, mais les failles connues sont
bouchées sans que tu y penses.

> **À retenir** — Sur un serveur exposé, les **correctifs de sécurité automatiques**
> (`unattended-upgrades`) ne sont pas un luxe. Un serveur à jour ferme la grande majorité des portes
> aux attaquants.

## Une routine de maintenance

Tu n'as pas à tout faire tous les jours. Une bonne hygiène tient en quelques habitudes :

| Fréquence | À faire |
| --- | --- |
| Quotidien (auto) | Sauvegarde, correctifs de sécurité (`unattended-upgrades`) |
| Hebdomadaire | Coup d'œil : `df -h`, `systemctl --failed`, `sudo wg`, `journalctl -p err -b` |
| Mensuel | Tester une **restauration** de sauvegarde, `docker system prune`, vérifier les certificats |
| À l'occasion | Montées de version majeures, relecture des règles de pare-feu |

L'essentiel est d'**automatiser ce qui doit l'être** (sauvegardes, sécurité) et de **regarder
régulièrement** ce que les automatismes ne disent pas (disque, erreurs, services).

## Résumé

- Surveille en priorité **charge** (`uptime`), **mémoire** (`free -h`) et **disque** (`df -h`/`du -sh`) ;
  un disque plein casse tout en silence.
- `systemctl --failed` et `docker compose ps` disent si tes services tournent toujours.
- **Borne les journaux** (`SystemMaxUse`, `journalctl --vacuum-time`) et nettoie Docker
  (`docker system prune`).
- Sauvegarde selon la **règle 3-2-1** (3 copies, 2 supports, 1 hors site) ; automatise avec un timer,
  envoie hors site avec `rsync`, et **teste la restauration**.
- Automatise les **correctifs de sécurité** (`unattended-upgrades`) et tiens une **routine** de
  maintenance régulière.

## Exercices

### Exercice 1 — Le disque se remplit

`df -h` indique que ta partition `/` est à 92 %. Quelles commandes utilises-tu pour trouver ce qui
prend la place, et quels coupables classiques vérifies-tu en premier ?

<details>
<summary>Voir le corrigé</summary>

La démarche : localiser les gros dossiers, viser les suspects habituels.

```bash
df -h                        # confirme quelle partition est pleine
sudo du -sh /var/* | sort -rh | head    # les plus gros dossiers de /var
journalctl --disk-usage      # taille des journaux systemd
docker system df             # place prise par Docker (images, volumes, cache)
```

Coupables classiques : journaux non bornés (`journalctl --vacuum-time=30d`), images et conteneurs
Docker inutilisés (`docker system prune`), anciennes sauvegardes (rotation avec `find ... -mtime +30
-delete`).

</details>

### Exercice 2 — Une sauvegarde digne de confiance

Ton script archive tes données chaque nuit dans `/sauvegardes`. Cite deux raisons pour lesquelles cela
ne suffit pas, et ce que tu ajoutes pour avoir une vraie stratégie.

<details>
<summary>Voir le corrigé</summary>

La démarche : appliquer la règle 3-2-1 et le test de restauration.

1. **Tout est sur la même machine.** Si le serveur ou son disque meurt, la sauvegarde meurt avec. Il
   faut une copie **hors site** : `rsync` vers une autre machine, un autre disque, ou un stockage
   distant (le « 1 » du 3-2-1).
2. **La sauvegarde n'est jamais testée.** Il faut **restaurer** régulièrement une archive sur une
   machine de test pour vérifier qu'elle est exploitable. Une sauvegarde non testée peut être vide ou
   corrompue sans qu'on le sache.

</details>

### Exercice 3 — Mises à jour sans y penser

Tu administres un VPS exposé que tu ne consultes qu'une fois par semaine. Comment garantis-tu que les
failles de sécurité connues sont corrigées entre tes visites ?

<details>
<summary>Voir le corrigé</summary>

La démarche : automatiser spécifiquement les correctifs de sécurité.

```bash
sudo apt install unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades   # activer
```

`unattended-upgrades` installe automatiquement les **mises à jour de sécurité** (sans toucher aux
montées de version majeures, qui restent sous ton contrôle). Ainsi, même sans connexion de ta part, les
failles connues sont bouchées rapidement, ce qui est vital sur une machine exposée en permanence.

</details>

## Quiz

**1.** Quelle vérification est la plus critique pour éviter une panne silencieuse ?
- A. La couleur du terminal
- B. L'espace disque (`df -h`)
- C. La version du noyau

**2.** Que dit la règle 3-2-1 des sauvegardes ?
- A. 3 copies, 2 supports différents, 1 hors site
- B. Sauvegarder 3 fois par jour
- C. Garder 21 jours d'historique

**3.** Pourquoi faut-il tester la restauration d'une sauvegarde ?
- A. Pour gagner du temps
- B. Parce qu'une sauvegarde non testée peut être corrompue ou vide à ton insu
- C. Ce n'est pas nécessaire

**4.** À quoi sert `unattended-upgrades` ?
- A. À installer automatiquement les correctifs de sécurité
- B. À surveiller le disque
- C. À configurer le VPN

<details>
<summary>Voir les réponses</summary>

1. **B** — Un disque plein casse les services en silence ; `df -h` est la vérification clé.
2. **A** — 3 copies, 2 supports, 1 hors site : la règle de référence des sauvegardes.
3. **B** — Une sauvegarde jamais testée peut s'avérer inutilisable le jour du pépin.
4. **A** — `unattended-upgrades` applique automatiquement les mises à jour de sécurité.

</details>

## Projet fil rouge

Douzième jalon : **rends ton homelab durable**.

1. Mets en place un **script de sauvegarde** de tes données et configurations, déclenché par un
   **timer systemd** chaque nuit, avec rotation des anciennes archives.
2. Ajoute une copie **hors site** avec `rsync` vers une autre machine, et **teste une restauration**
   pour de vrai.
3. Active les **correctifs de sécurité automatiques** (`unattended-upgrades`) et borne tes journaux.
4. Note dans `notes-homelab.md` ta **routine de maintenance** (quotidien/hebdo/mensuel) et
   l'emplacement de tes sauvegardes.

Ton serveur est désormais surveillé, sauvegardé et maintenu : prêt à durer. Au dernier chapitre, on
prend de la hauteur, on consolide, et on trace les pistes pour aller plus loin.

---

[← Chapitre précédent](11-reverse-proxy.md) · [Sommaire](README.md) · [Chapitre suivant →](13-conclusion.md)
