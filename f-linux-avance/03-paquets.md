# Gestion des paquets et des dépôts

[← Chapitre précédent](02-distributions.md) · [Sommaire](README.md) · [Chapitre suivant →](04-systemd.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **paquet**, un **dépôt** (*repository*) et une **dépendance** ;
- installer, mettre à jour, rechercher et supprimer des logiciels avec `apt` ;
- comprendre la différence entre `apt update` et `apt upgrade`, et l'importance des mises à jour ;
- ajouter une **source de paquets** externe en connaissance de cause, et compiler un logiciel **depuis
  les sources** quand il le faut.

Sur ta machine personnelle, tu installes un logiciel en cliquant. Sur un serveur, tout passe par le
**gestionnaire de paquets** : un programme qui télécharge, installe, met à jour et supprime des
logiciels de façon propre et traçable. C'est l'outil que tu utiliseras le plus souvent.

## Paquets, dépôts, dépendances

Un **paquet** est un fichier qui contient un logiciel **prêt à installer** : son programme, ses
fichiers de configuration, et une fiche d'informations (version, description, et surtout ses
**dépendances**). Dans la famille Debian, un paquet est un fichier `.deb`.

Une **dépendance** est un autre paquet **nécessaire** pour que le premier fonctionne. Par exemple, un
serveur web a besoin de bibliothèques réseau. Le gestionnaire de paquets **résout les dépendances**
tout seul : tu demandes un logiciel, il installe aussi tout ce dont il a besoin. C'est le cœur de son
utilité — sinon tu passerais ta vie à chasser des fichiers manquants.

Les paquets ne traînent pas n'importe où : ils vivent dans des **dépôts** (*repositories*), des
serveurs officiels tenus par la distribution. Ta machine connaît une **liste de dépôts** et y pioche
les logiciels. C'est ce qui rend l'installation **sûre** : les paquets officiels sont vérifiés et
signés cryptographiquement.

```text
   Dépôts officiels (sur Internet)
   +--------------------------------+
   |  nginx   git   htop   python3  |
   +----------------+---------------+
                    |  apt télécharge le paquet + ses dépendances
                    v
              Ta machine
```

> **À retenir** — Tu n'installes pas un logiciel en téléchargeant un `.exe` au hasard sur le web. Tu
> demandes au gestionnaire de paquets, qui le prend dans un **dépôt de confiance** et gère ses
> dépendances. C'est plus sûr et plus simple.

## `apt` au quotidien (Debian / Ubuntu)

`apt` (*Advanced Package Tool*) est le gestionnaire de la famille Debian. Voici les commandes que tu
utiliseras tous les jours. Elles modifient le système : elles demandent donc `sudo`.

### Mettre à jour la liste des paquets : `apt update`

```bash
$ sudo apt update
```

Cette commande ne met **rien** à jour sur ta machine. Elle rafraîchit la **liste** des paquets
disponibles en interrogeant les dépôts : « quelles sont les dernières versions publiées ? ». C'est le
catalogue qui se met à jour, pas les logiciels. À lancer avant toute installation.

### Mettre à jour les logiciels installés : `apt upgrade`

```bash
$ sudo apt upgrade
```

Celle-ci installe réellement les **nouvelles versions** des logiciels déjà présents, d'après le
catalogue rafraîchi par `apt update`. C'est par là que passent les **correctifs de sécurité**.

> **À retenir** — Le couple à retenir : `sudo apt update` (rafraîchit le catalogue) **puis** `sudo apt
> upgrade` (applique les mises à jour). On les enchaîne souvent : `sudo apt update && sudo apt
> upgrade`.

### Installer, rechercher, supprimer

```bash
$ sudo apt install htop          # installe le paquet htop (et ses dépendances)
$ apt search "moniteur"          # cherche un paquet par mot-clé (pas besoin de sudo)
$ apt show htop                  # affiche la fiche détaillée d'un paquet
$ sudo apt remove htop           # supprime le paquet, garde ses fichiers de config
$ sudo apt purge htop            # supprime le paquet ET ses fichiers de config
$ sudo apt autoremove            # supprime les dépendances devenues inutiles
```

`apt install` peut prendre plusieurs paquets d'un coup : `sudo apt install git curl htop`. Pour
installer sans confirmation interactive (utile dans un script), ajoute `-y` :
`sudo apt install -y git`.

### Lister ce qui est installé

```bash
$ apt list --installed           # tous les paquets installés (souvent très long)
$ apt list --installed | grep nginx
```

## L'importance des mises à jour de sécurité

Sur un serveur exposé à Internet, les **mises à jour de sécurité ne sont pas optionnelles**. Une faille
connue dans un logiciel que tu exposes est une porte ouverte. La règle est simple : un serveur
**bien tenu est un serveur à jour**.

Tu peux automatiser les correctifs de sécurité avec le paquet `unattended-upgrades` (on y reviendra
au [chapitre 12](12-supervision-maintenance.md)). Pour l'instant, retiens le réflexe manuel :

```bash
$ sudo apt update && sudo apt upgrade
```

> **Attention** — `apt upgrade` peut redémarrer des services pendant la mise à jour, voire demander un
> redémarrage du noyau (un fichier `/var/run/reboot-required` apparaît alors). Sur un serveur en
> production, on planifie ces opérations à une heure creuse plutôt que de les lancer n'importe quand.

## Ajouter une source de paquets externe

Parfois, le logiciel que tu veux n'est pas dans les dépôts officiels, ou seulement dans une version
trop ancienne. Tu peux alors ajouter un **dépôt tiers**. C'est puissant mais **engageant** : tu
accordes ta confiance à ce dépôt, qui pourra installer du code sur ta machine.

La méthode propre et moderne consiste à enregistrer la **clé de signature** du dépôt (pour vérifier
l'authenticité des paquets), puis à déclarer le dépôt. Voici le schéma, à adapter à la documentation
officielle du logiciel que tu installes :

```bash
# 1. récupérer la clé de signature du dépôt et la ranger
curl -fsSL https://exemple.com/cle.gpg | sudo gpg --dearmor -o /etc/apt/keyrings/exemple.gpg

# 2. déclarer le dépôt en précisant la clé qui le signe
echo "deb [signed-by=/etc/apt/keyrings/exemple.gpg] https://exemple.com/apt stable main" \
  | sudo tee /etc/apt/sources.list.d/exemple.list

# 3. rafraîchir le catalogue puis installer
sudo apt update
sudo apt install le-logiciel
```

> **Attention** — N'ajoute un dépôt tiers que depuis la **documentation officielle** de l'éditeur du
> logiciel, et jamais un dépôt trouvé sur un forum au hasard. Un dépôt malveillant peut installer ce
> qu'il veut, en `root`. En cas de doute, abstiens-toi.

## Compiler depuis les sources

Quand un logiciel n'existe dans **aucun** dépôt, ou que tu veux une version très précise, tu peux le
**compiler** : transformer son **code source** (le code écrit par les développeurs) en programme
exécutable, directement sur ta machine.

Le schéma classique d'un projet en C s'appelle souvent « les trois commandes » :

```bash
# Prérequis : les outils de compilation (compilateur, make, en-têtes…)
sudo apt install build-essential

# 1. récupérer et entrer dans le code source
git clone https://github.com/exemple/projet.git
cd projet

# 2. configurer (détecte ton système, prépare la compilation)
./configure

# 3. compiler, puis installer
make
sudo make install
```

- `./configure` examine ta machine et prépare la compilation (toutes les dépendances sont-elles là ?).
- `make` lance la compilation proprement dite : il lit le fichier `Makefile` du projet et produit
  l'exécutable.
- `sudo make install` copie le programme compilé aux bons endroits du système.

> **Attention** — Un logiciel compilé « à la main » **échappe au gestionnaire de paquets** : `apt` ne
> le connaît pas, ne le mettra pas à jour, et ne saura pas le désinstaller proprement. Réserve la
> compilation aux cas où il n'y a pas de paquet. Quand un paquet existe, **préfère toujours le
> paquet**.

### Les équivalents dans les autres familles

Les concepts sont identiques ; seules les commandes changent. Garde ce tableau sous la main si tu
tombes sur une machine d'une autre famille.

| Action | Debian/Ubuntu (`apt`) | Fedora/RHEL (`dnf`) | Arch (`pacman`) |
| --- | --- | --- | --- |
| Rafraîchir le catalogue | `apt update` | (automatique) | `pacman -Sy` |
| Mettre à jour le système | `apt upgrade` | `dnf upgrade` | `pacman -Su` |
| Installer un paquet | `apt install nom` | `dnf install nom` | `pacman -S nom` |
| Chercher un paquet | `apt search mot` | `dnf search mot` | `pacman -Ss mot` |
| Supprimer un paquet | `apt remove nom` | `dnf remove nom` | `pacman -R nom` |

## Résumé

- Un **paquet** est un logiciel prêt à installer avec sa fiche de **dépendances** ; le gestionnaire
  les **résout** automatiquement.
- Les paquets viennent de **dépôts** de confiance, signés cryptographiquement.
- `sudo apt update` rafraîchit le **catalogue** ; `sudo apt upgrade` installe les **mises à jour** : on
  enchaîne souvent les deux.
- Sur un serveur exposé, **rester à jour** est une mesure de sécurité fondamentale.
- Un **dépôt tiers** n'est ajouté que depuis une source officielle, avec sa clé de signature.
- **Compiler depuis les sources** (`./configure && make && sudo make install`) est un dernier recours :
  le logiciel échappe alors au gestionnaire de paquets.

## Exercices

### Exercice 1 — Le bon ordre des mises à jour

Tu veux installer la toute dernière version de `git` disponible pour ta distribution. Quelles
commandes lances-tu, et dans quel ordre ? Explique pourquoi.

<details>
<summary>Voir le corrigé</summary>

La démarche : toujours rafraîchir le catalogue avant d'installer, pour obtenir la version la plus
récente que la distribution propose.

```bash
sudo apt update          # rafraîchit la liste des paquets disponibles
sudo apt install git     # installe git d'après ce catalogue à jour
```

Sans le `apt update` préalable, `apt` pourrait installer une version d'après un catalogue périmé.
Note : « la dernière version disponible **pour ta distribution** » n'est pas forcément la dernière
publiée en amont par le projet git — une version figée fournit une version stable, pas la toute
dernière.

</details>

### Exercice 2 — Faire le ménage

Tu as installé `htop` pour tester, il ne te sert plus. Tu veux le supprimer **complètement**, y compris
sa configuration, et nettoyer les dépendances qu'il avait tirées et qui ne servent plus à rien.

<details>
<summary>Voir le corrigé</summary>

La démarche : `purge` enlève le paquet et sa config ; `autoremove` enlève les dépendances orphelines.

```bash
sudo apt purge htop      # supprime htop et ses fichiers de configuration
sudo apt autoremove      # supprime les dépendances devenues inutiles
```

`apt remove htop` aurait laissé les fichiers de configuration ; `purge` va plus loin. `autoremove` est
le geste de ménage à faire de temps en temps.

</details>

### Exercice 3 — Paquet ou compilation ?

Pour chacun de ces besoins, dis si tu installerais via `apt` ou si tu compilerais depuis les sources :
(a) installer `nginx` ; (b) utiliser un petit utilitaire publié seulement sur GitHub, absent des
dépôts ; (c) installer `python3`.

<details>
<summary>Voir le corrigé</summary>

La règle : un paquet existe → on prend le paquet ; aucun paquet n'existe → on compile.

- (a) `nginx` est dans les dépôts officiels : **`apt install nginx`**.
- (b) Absent des dépôts : c'est le cas légitime de la **compilation depuis les sources** (ou d'un
  dépôt tiers officiel s'il en existe un).
- (c) `python3` est dans les dépôts : **`apt install python3`**.

On ne compile que faute de paquet, car un logiciel compilé n'est plus suivi par `apt` (pas de mise à
jour ni de désinstallation propre).

</details>

## Quiz

**1.** Que fait `sudo apt update` ?
- A. Met à jour les logiciels installés
- B. Rafraîchit la liste des paquets disponibles, sans rien installer
- C. Supprime les paquets inutiles

**2.** Qu'est-ce qu'une « dépendance » ?
- A. Un paquet nécessaire au fonctionnement d'un autre paquet
- B. Un dépôt officiel
- C. Un fichier de configuration

**3.** Pourquoi les paquets viennent-ils de « dépôts » plutôt que d'un téléchargement libre ?
- A. Pour aller plus vite uniquement
- B. Parce qu'ils y sont vérifiés et signés : c'est plus sûr
- C. Parce que c'est obligatoire pour `sudo`

**4.** Quel est l'inconvénient principal d'un logiciel compilé depuis les sources ?
- A. Il est forcément plus lent
- B. Il échappe au gestionnaire de paquets (pas de mise à jour ni de désinstallation propre)
- C. Il ne fonctionne pas sur un serveur

<details>
<summary>Voir les réponses</summary>

1. **B** — `apt update` rafraîchit le catalogue ; c'est `apt upgrade` qui installe les mises à jour.
2. **A** — Une dépendance est un paquet requis par un autre ; le gestionnaire les résout pour toi.
3. **B** — Les dépôts fournissent des paquets vérifiés et signés, donc dignes de confiance.
4. **B** — Compilé à la main, le logiciel n'est plus suivi par `apt`.

</details>

## Projet fil rouge

Troisième jalon : **mets ton serveur à jour et installe ta trousse à outils**.

1. Rafraîchis le catalogue et applique toutes les mises à jour de sécurité :

   ```bash
   sudo apt update && sudo apt upgrade
   ```

2. Installe quelques outils d'administration qui te serviront tout au long de la formation :

   ```bash
   sudo apt install -y htop curl git ufw
   ```

   (`htop` pour surveiller, `curl` pour tester des serveurs web, `git` pour récupérer du code, `ufw`
   pour le pare-feu du chapitre 8.)
3. Dans `notes-homelab.md`, note la liste des logiciels que tu installes au fur et à mesure : c'est ta
   « recette » pour reconstruire le serveur à l'identique en cas de pépin.

Ton serveur est à jour et outillé. Au chapitre suivant, on apprend à faire **tourner ces logiciels
comme des services** durables, avec `systemd`.

---

[← Chapitre précédent](02-distributions.md) · [Sommaire](README.md) · [Chapitre suivant →](04-systemd.md)
