# De l'utilisateur à l'administrateur

[← Sommaire](README.md) · [Chapitre suivant →](02-distributions.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- distinguer **utiliser** Linux et **administrer** Linux, et ce que ça change concrètement ;
- comprendre ce qu'est un **serveur** et pourquoi on en héberge un soi-même ;
- mettre en place un **environnement de travail** sûr (machine virtuelle ou serveur loué) ;
- te connecter à ta machine et vérifier que tu es prêt pour la suite.

Tu sais déjà te servir d'un terminal. Jusqu'ici, tu étais un **utilisateur** : tu lançais des
commandes pour faire ton propre travail. Tu vas devenir **administrateur** : la personne qui installe
les logiciels, crée les comptes, fait tourner des services et décide qui a le droit de faire quoi.
C'est un autre métier, et il commence par comprendre ce qu'est un serveur.

## Utiliser contre administrer

Quand tu tapes `ls` ou que tu écris un script, tu agis **dans** ton espace personnel. Administrer,
c'est agir **sur la machine entière** : installer un logiciel pour tous les utilisateurs, lancer un
programme qui tourne en permanence, ouvrir un accès depuis le réseau. Ces actions touchent le système,
pas seulement ton dossier.

La différence visible, c'est `sudo` (*superuser do*, « faire en tant que super-utilisateur »). La
plupart des commandes d'administration exigent les droits du compte **`root`**, l'administrateur tout
puissant du système. Tu connais déjà `sudo` ; dans cette formation, tu vas l'utiliser **beaucoup**,
et surtout apprendre à t'en servir **prudemment**, car une erreur en `root` peut casser la machine.

> **À retenir** — Administrer, c'est agir sur tout le système, pas seulement sur tes fichiers. Le
> pouvoir de `root` est total : il faut comprendre une commande avant de la lancer en `sudo`.

## Qu'est-ce qu'un serveur ?

Un **serveur** n'est pas un type d'ordinateur particulier : c'est un **rôle**. Une machine est un
serveur dès qu'elle **rend un service à d'autres machines** à travers le réseau. Ton ordinateur
portable peut être un serveur ; un vieux Raspberry Pi aussi ; une machine louée dans un centre de
données également.

Le schéma est toujours le même : un **client** (ton navigateur, par exemple) envoie une **requête**,
le **serveur** la reçoit, la traite, et renvoie une **réponse**.

```text
   Client                          Serveur
  (navigateur)                   (ta machine)
       |                              |
       |  --- requête (GET /) ----->  |
       |                              |  (le serveur prépare la page)
       |  <---- réponse (HTML) -----  |
       |                              |
```

Un serveur a deux particularités par rapport à un poste de bureau :

- il tourne **en permanence**, sans personne devant l'écran (on dit *headless*, « sans tête », car il
  n'a souvent ni écran ni clavier) ;
- on le pilote **à distance**, par le réseau, presque toujours en ligne de commande via *SSH* (le
  protocole de connexion à distance, vu au [chapitre 5](05-utilisateurs-et-ssh.md)).

C'est pour ça que tout ce que tu as appris au terminal devient ici essentiel : sur un serveur, **il
n'y a que le terminal**.

### Pourquoi héberger soi-même ?

Tu pourrais payer un service en ligne pour stocker tes fichiers, héberger ton site, gérer tes mots de
passe. L'**auto-hébergement** consiste à faire tourner ces services sur **ta** machine. On le fait
pour trois raisons :

- **Apprendre** : rien n'apprend mieux le fonctionnement d'Internet que d'en exploiter un morceau.
- **Maîtriser** : tes données restent chez toi, tu décides des règles, rien ne ferme du jour au
  lendemain.
- **Le coût** : un seul petit serveur peut remplacer plusieurs abonnements.

## Mettre en place ton environnement de travail

Tu vas faire des manipulations qui peuvent casser un système : changer la configuration réseau,
modifier les règles d'accès, installer et désinstaller des paquets. **Ne fais jamais ça sur ta
machine principale.** Il te faut un terrain de jeu jetable. Deux options, et tu peux commencer par la
première puis passer à la seconde.

### Option A — Une machine virtuelle (pour apprendre sans rien risquer)

Une **machine virtuelle** (VM) est un ordinateur complet **simulé par un logiciel** à l'intérieur de
ton ordinateur. Elle a son propre système, son propre disque (un simple fichier), son propre réseau.
Si tu la casses, tu la supprimes et tu en recrées une en quelques minutes. C'est l'environnement idéal
pour les chapitres 1 à 6.

La marche à suivre, quel que soit l'outil :

1. Installe un **hyperviseur** (le logiciel qui fait tourner les VM). Gratuits et multiplateformes :
   *VirtualBox* ou *VMware Workstation Player*. Sur Linux, *GNOME Boxes* ou *virt-manager* (basés sur
   KVM) sont excellents.
2. Télécharge une **image ISO** de Debian ou d'Ubuntu Server (un fichier qui contient l'installateur).
3. Crée une nouvelle VM, donne-lui 2 Go de RAM et 20 Go de disque, et démarre-la sur l'ISO.
4. Suis l'installateur (on détaille le choix de la distribution au [chapitre 2](02-distributions.md)).

> **Astuce** — Choisis une version **« Server »** (sans interface graphique) plutôt que « Desktop ».
> C'est plus léger, et surtout ça t'oblige à tout faire au terminal — exactement la compétence visée.

### Option B — Un serveur privé virtuel (pour exposer sur Internet)

Un **VPS** (*Virtual Private Server*) est une machine virtuelle, mais louée chez un hébergeur et
branchée en permanence sur Internet avec une **adresse IP publique** (on verra au
[chapitre 6](06-reseau.md) ce que ça signifie). C'est ce qu'il te faudra à partir du
[chapitre 7](07-serveur-web.md) pour rendre ton serveur **accessible depuis l'extérieur**.

Des hébergeurs comme OVH, Scaleway, Hetzner ou DigitalOcean en louent à partir de quelques euros par
mois. À la commande, tu choisis une distribution ; l'hébergeur te donne une adresse IP et un accès
initial (mot de passe `root` ou clé). Tu te connectes ensuite **en SSH**, comme on le verra.

> **À retenir** — Un VPS, c'est la même chose qu'une VM, mais hébergée ailleurs et joignable depuis
> Internet. Pour le projet fil rouge, tu peux apprendre sur une VM locale, puis louer un VPS quand tu
> veux exposer ton serveur au monde.

## Se connecter et prendre ses marques

Une fois ta machine démarrée, connecte-toi (directement dans la fenêtre de la VM, ou en SSH pour un
VPS — détaillé plus loin). Premier réflexe d'administrateur : **savoir où tu es**.

```bash
$ whoami           # quel utilisateur suis-je ?
debian
$ hostname         # quel est le nom de cette machine ?
mon-serveur
$ uname -a         # quel noyau (kernel) et quelle architecture ?
Linux mon-serveur 6.1.0-18-amd64 #1 SMP Debian ... x86_64 GNU/Linux
```

Le **noyau** (*kernel*) est le cœur de Linux : le programme qui parle au matériel et distribue le
temps de calcul entre les programmes. `uname -a` te dit quelle version tourne. Pour savoir **quelle
distribution** est installée :

```bash
$ cat /etc/os-release
PRETTY_NAME="Debian GNU/Linux 12 (bookworm)"
NAME="Debian GNU/Linux"
VERSION_ID="12"
...
```

Ce fichier `/etc/os-release` est ton premier réflexe sur une machine inconnue : il dit toujours quelle
distribution et quelle version tu as sous les doigts. On va voir au chapitre suivant ce que ces noms
veulent dire.

> **Attention** — Sur un serveur fraîchement loué, tu es souvent connecté directement en `root`. C'est
> pratique mais dangereux : la moindre faute de frappe agit avec les pleins pouvoirs. Un des tout
> premiers gestes (chapitre 5) sera de créer un utilisateur normal et d'arrêter de travailler en
> `root` au quotidien.

## Résumé

- **Administrer**, c'est agir sur tout le système (via `root`/`sudo`), pas seulement sur ses fichiers.
- Un **serveur** est un rôle : une machine qui rend un service à d'autres via le réseau, tourne en
  permanence et se pilote à distance en SSH.
- L'**auto-hébergement** sert à apprendre, à maîtriser ses données et à réduire les coûts.
- Travaille sur un terrain jetable : une **VM** locale pour apprendre, un **VPS** quand il faut une
  IP publique pour exposer ton serveur.
- Premiers réflexes sur une machine : `whoami`, `hostname`, `uname -a`, et `cat /etc/os-release`.

## Exercices

### Exercice 1 — Reconnaître sa machine

Connecte-toi à ta VM ou ton VPS et réponds, commande à l'appui : quel utilisateur es-tu, quel est le
nom de la machine, quelle distribution et quelle version tournent ?

<details>
<summary>Voir le corrigé</summary>

La démarche : enchaîner les commandes de repérage vues dans le chapitre.

```bash
whoami                 # l'utilisateur courant
hostname               # le nom de la machine
cat /etc/os-release    # distribution et version (champ PRETTY_NAME)
uname -r               # version du noyau seule
```

`uname -r` donne juste la version du noyau (plus court que `uname -a`). Le champ `PRETTY_NAME` de
`/etc/os-release` est la réponse la plus lisible pour « quelle distribution ».

</details>

### Exercice 2 — Client ou serveur ?

Pour chacun de ces cas, dis qui joue le rôle de **client** et qui joue le rôle de **serveur** : (a)
tu consultes un site web ; (b) ton téléphone récupère tes mails ; (c) tu te connectes en SSH à ton
VPS.

<details>
<summary>Voir le corrigé</summary>

Le client est toujours celui qui **demande**, le serveur celui qui **répond** et reste à l'écoute.

- (a) Client = ton navigateur ; serveur = la machine qui héberge le site.
- (b) Client = l'application mail de ton téléphone ; serveur = le serveur de messagerie.
- (c) Client = ton ordinateur (le programme `ssh`) ; serveur = ton VPS (qui écoute les connexions
  SSH).

Une même machine peut être cliente pour un service et serveuse pour un autre : ton VPS est serveur
SSH pour toi, mais devient client quand il télécharge une mise à jour.

</details>

## Quiz

**1.** Qu'est-ce qui définit un « serveur » ?
- A. Un ordinateur très puissant rangé dans une armoire
- B. Le rôle d'une machine qui rend un service à d'autres via le réseau
- C. Un système d'exploitation spécial

**2.** Pourquoi travailler dans une VM ou un VPS plutôt que sur sa machine principale ?
- A. Parce que c'est plus rapide
- B. Pour pouvoir tout casser sans risque et recommencer
- C. Parce que `sudo` n'y fonctionne pas

**3.** Quel fichier indique quelle distribution est installée ?
- A. `/etc/os-release`
- B. `/etc/hostname`
- C. `~/.bashrc`

**4.** Que désigne le « noyau » (kernel) ?
- A. Le dossier personnel de l'administrateur
- B. Le programme central qui parle au matériel et distribue le temps de calcul
- C. L'interface graphique

<details>
<summary>Voir les réponses</summary>

1. **B** — Serveur est un rôle (rendre un service par le réseau), pas un type de matériel.
2. **B** — Un environnement jetable permet d'expérimenter sans risque pour ta vraie machine.
3. **A** — `/etc/os-release` contient le nom et la version de la distribution.
4. **B** — Le noyau est le cœur du système qui pilote le matériel et ordonnance les programmes.

</details>

## Projet fil rouge

Premier jalon : **mets en place ta machine de travail**.

1. Crée une **machine virtuelle** sous Debian ou Ubuntu Server (option A), ou loue un **VPS** si tu
   veux exposer ton serveur plus tard (option B). Garde l'autre option en tête pour la suite.
2. Connecte-toi, puis note dans un fichier `notes-homelab.md` (sur ta machine principale) les
   informations clés : nom de la machine, distribution et version, utilisateur de connexion, et —
   pour un VPS — son adresse IP.
3. Ce fichier de notes va t'accompagner toute la formation : tu y consigneras les adresses, les ports,
   les mots de passe (ou mieux, l'emplacement de tes clés) et les décisions de configuration. Un bon
   administrateur **documente son installation**.

Tu as maintenant un terrain de jeu. Au chapitre suivant, on choisit en connaissance de cause **quelle
distribution** y faire tourner.

---

[← Sommaire](README.md) · [Chapitre suivant →](02-distributions.md)
