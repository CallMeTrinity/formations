# Les distributions Linux

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-paquets.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est une **distribution** et pourquoi il en existe des centaines ;
- situer les grandes **familles** (Debian/Ubuntu, Red Hat/Fedora, Arch) et ce qui les distingue ;
- comprendre les modèles de **publication** (versions figées contre *rolling release*) et le **cycle
  de vie** d'une version ;
- **choisir** une distribution adaptée à un usage serveur, et justifier ton choix.

On parle de « Linux » comme d'un seul système, mais ce n'est pas tout à fait vrai. Linux, au sens
strict, c'est seulement le **noyau**. Tout le reste — les commandes, l'installateur, le gestionnaire
de paquets — est assemblé par des projets différents. Chaque assemblage est une **distribution**.

## Une distribution, c'est quoi exactement ?

Le noyau Linux ne sert à rien tout seul : il pilote le matériel, mais il ne te donne ni terminal, ni
commande `ls`, ni navigateur. Une **distribution** (souvent abrégée *distro*) est un **système complet
et prêt à l'emploi** construit autour du noyau. Elle rassemble :

- le **noyau** Linux ;
- une collection de **logiciels** de base (le shell, les commandes Unix, les bibliothèques) ;
- un **gestionnaire de paquets** pour installer et mettre à jour ces logiciels (chapitre 3) ;
- un **système d'initialisation** qui démarre les services (`systemd`, chapitre 4) ;
- un **installateur**, une politique de mises à jour, une équipe qui maintient le tout.

C'est parce que ces choix peuvent être faits différemment qu'il existe des **centaines** de
distributions. Mais elles ne partent pas de zéro chacune : la plupart **dérivent** d'une poignée de
distributions « mères ». Connaître les familles suffit pour s'y retrouver.

> **À retenir** — « Linux » désigne le noyau ; une **distribution** est le système complet construit
> autour. Quand quelqu'un dit « j'utilise Linux », il utilise en réalité une distribution précise.

## Les grandes familles

### La famille Debian (et Ubuntu)

**Debian** est l'une des plus anciennes distributions, réputée pour sa **stabilité** et son immense
catalogue de logiciels. Elle utilise le gestionnaire de paquets `apt` et les paquets au format
`.deb`. C'est un projet communautaire, indépendant de toute entreprise.

**Ubuntu** est construite **sur** Debian par l'entreprise Canonical. Elle vise plus de convivialité,
des versions au calendrier régulier, et un support commercial. Comme elle dérive de Debian, elle
partage `apt` et les paquets `.deb` : ce que tu apprends sur l'une se transpose presque tel quel sur
l'autre.

Beaucoup d'autres en dérivent encore : Linux Mint, Raspberry Pi OS, Proxmox… C'est, de loin, la
famille **la plus répandue côté serveur**. **C'est celle qu'on utilisera dans cette formation.**

### La famille Red Hat (RHEL, Fedora, Rocky, AlmaLinux)

**Red Hat Enterprise Linux** (RHEL) est la distribution de référence en **entreprise** : payante, avec
un support de long terme. Elle utilise le gestionnaire `dnf` et les paquets au format `.rpm`.

Autour d'elle gravitent : **Fedora** (le laboratoire d'innovation, en amont de RHEL, qui reçoit les
nouveautés en premier), et des **clones gratuits** de RHEL comme **Rocky Linux** et **AlmaLinux**,
appréciés pour héberger en production sans payer la licence.

### La famille Arch

**Arch Linux** vise la **simplicité pour l'utilisateur avancé** et le contrôle total : tu construis ton
système toi-même, composant par composant. Elle utilise le gestionnaire `pacman` et une excellente
documentation (le *Arch Wiki*, utile même si tu n'utilises pas Arch). **Manjaro** en dérive avec une
installation plus simple.

Arch suit un modèle de publication différent, qu'on explique tout de suite.

### Les autres

Il en existe bien d'autres familles (openSUSE avec `zypper`, Alpine avec `apk` très utilisée dans les
conteneurs, Gentoo qui compile tout…). Tu n'as pas besoin de les connaître pour débuter ; sache juste
qu'elles existent et qu'elles se reconnaissent à leur gestionnaire de paquets.

## Comment les versions sortent : figé contre rolling

Deux philosophies s'opposent sur **quand** une distribution te livre de nouvelles versions de
logiciels.

- **Version figée** (*point release*) : la distribution fige un ensemble de logiciels à une date,
  puis ne livre plus que des **correctifs de sécurité** pendant des années. Tu as un système qui ne
  bouge presque pas — donc fiable et prévisible. Debian, Ubuntu LTS, RHEL fonctionnent ainsi. C'est
  ce qu'on veut **sur un serveur**.
- **Diffusion continue** (*rolling release*) : il n'y a pas de « versions » ; chaque logiciel est mis
  à jour en continu dès qu'une nouveauté sort. Tu as toujours le dernier cri, au prix d'un système qui
  change tout le temps (et peut casser plus souvent). Arch fonctionne ainsi.

> **À retenir** — Pour un serveur, on privilégie la **stabilité** : une version figée, mise à jour
> longtemps en sécurité. Le « toujours dernier » d'une rolling release est séduisant sur un poste de
> passionné, moins sur une machine qui doit tourner sans surprise.

## Le cycle de vie d'une version

Une version de distribution n'est pas maintenue éternellement. Deux dates comptent :

- la **date de sortie** ;
- la **fin de support** (souvent appelée *EOL*, *End Of Life*) : après quoi tu ne reçois **plus de
  correctifs de sécurité**. Continuer à utiliser une version en fin de vie, c'est laisser des failles
  ouvertes.

Certaines versions sont étiquetées **LTS** (*Long Term Support*, support de longue durée). Une Ubuntu
LTS est supportée **cinq ans**, contre neuf mois pour une version intermédiaire. Sur un serveur, on
choisit **toujours une LTS** : on ne veut pas réinstaller tous les neuf mois.

```text
   Ubuntu 24.04 LTS
   |-----------------------------------------|     ~5 ans de support
   sortie                                  fin de vie

   Ubuntu 24.10 (intermédiaire)
   |--------|                                       ~9 mois de support
```

Tu vérifies la version installée avec `cat /etc/os-release` (vu au chapitre 1), et la fin de support
sur le site de la distribution.

## Choisir sa distribution

Pour un usage serveur et auto-hébergement, la grille de décision est simple.

| Tu veux… | Choisis |
| --- | --- |
| La stabilité maximale, communautaire, gratuite | **Debian** (version *stable*) |
| Stabilité + documentation grand public + LTS | **Ubuntu Server LTS** |
| De la compatibilité avec le monde entreprise/RHEL | **Rocky Linux** ou **AlmaLinux** |
| Apprendre en profondeur, tout contrôler | **Arch** (plutôt pour plus tard) |

Pour cette formation, **prends Debian stable ou Ubuntu Server LTS**. Les deux utilisent `apt`, sont
ultra-documentées, et tournent sur la quasi-totalité des VPS du marché. Toutes les manipulations des
chapitres suivants sont écrites pour elles, avec un rappel des équivalents `dnf`/`pacman` quand c'est
utile.

> **Astuce** — Le choix de la distribution compte moins qu'on ne le croit : les concepts
> (`systemd`, réseau, SSH, pare-feu) sont **les mêmes partout**. Seules changent quelques commandes,
> surtout celles du gestionnaire de paquets. Apprends les concepts, pas par cœur une distribution.

## Résumé

- Linux est le **noyau** ; une **distribution** est le système complet construit autour (logiciels,
  gestionnaire de paquets, init, installateur).
- Trois grandes familles : **Debian/Ubuntu** (`apt`, `.deb`), **Red Hat/Fedora** (`dnf`, `.rpm`),
  **Arch** (`pacman`). On les reconnaît à leur gestionnaire de paquets.
- Deux modèles de publication : **version figée** (stable, pour les serveurs) et **rolling release**
  (toujours à jour, plus mouvant).
- Une version a une **fin de support** (EOL) ; sur un serveur, on choisit une **LTS** maintenue
  plusieurs années.
- Pour cette formation : **Debian stable** ou **Ubuntu Server LTS**.

## Exercices

### Exercice 1 — Reconnaître une famille

On te dit qu'une machine s'installe avec la commande `dnf install ...` et que ses paquets finissent en
`.rpm`. De quelle **famille** s'agit-il ? Et si c'était `pacman -S ...` ?

<details>
<summary>Voir le corrigé</summary>

La démarche : le gestionnaire de paquets trahit la famille.

- `dnf` + `.rpm` → famille **Red Hat/Fedora** (RHEL, Fedora, Rocky, AlmaLinux).
- `pacman -S` → famille **Arch** (Arch, Manjaro).

Par contraste, `apt` + `.deb` aurait désigné la famille Debian/Ubuntu.

</details>

### Exercice 2 — Justifier un choix

Un ami veut héberger un petit site web sur un VPS et « ne plus y toucher pendant des années ».
Conseille-lui une distribution **et un type de version**, en une phrase d'argument.

<details>
<summary>Voir le corrigé</summary>

La démarche : « ne plus y toucher » = besoin de stabilité et de support long.

Réponse type : **Ubuntu Server LTS** (ou **Debian stable**), parce que ce sont des **versions figées
à support long** (cinq ans pour une LTS) : il ne reçoit que des correctifs de sécurité, son système
ne change pas sous ses pieds, et il n'aura pas à réinstaller avant longtemps. Une rolling release comme
Arch serait le mauvais choix ici, car elle évolue en continu.

</details>

### Exercice 3 — Vérifier la fin de vie

Sur ta machine, retrouve le nom de code et la version exacte de ta distribution, puis explique où tu
irais vérifier sa date de fin de support.

<details>
<summary>Voir le corrigé</summary>

```bash
cat /etc/os-release      # champs PRETTY_NAME, VERSION_ID et VERSION_CODENAME
```

Par exemple `bookworm` pour Debian 12, ou `noble` pour Ubuntu 24.04. La fin de support se vérifie sur
le site officiel de la distribution (page « releases » de Debian, page « Ubuntu release cycle » de
Canonical) ou sur le site de référence *endoflife.date*. Connaître la date de fin de vie évite de
laisser tourner un système qui ne reçoit plus de correctifs de sécurité.

</details>

## Quiz

**1.** Que désigne précisément le mot « Linux » au sens strict ?
- A. Le système complet avec ses logiciels
- B. Le noyau uniquement
- C. La marque déposée d'Ubuntu

**2.** Quel élément permet de reconnaître la famille d'une distribution ?
- A. La couleur du fond d'écran
- B. Son gestionnaire de paquets (`apt`, `dnf`, `pacman`…)
- C. Le nom de l'utilisateur par défaut

**3.** Pour un serveur qui doit tourner sans surprise, on préfère :
- A. Une rolling release pour avoir toujours le dernier logiciel
- B. Une version figée à support long (LTS)
- C. Réinstaller une nouvelle distribution chaque mois

**4.** Que signifie qu'une version est en « fin de vie » (EOL) ?
- A. Elle ne reçoit plus de correctifs de sécurité
- B. Elle s'efface automatiquement du disque
- C. Elle devient payante

<details>
<summary>Voir les réponses</summary>

1. **B** — Linux est le noyau ; le système complet est une distribution.
2. **B** — Le gestionnaire de paquets (et le format de paquet) identifie la famille.
3. **B** — Une version figée LTS offre la stabilité et un support long, ce qu'on veut sur un serveur.
4. **A** — En fin de vie, plus aucun correctif de sécurité n'est publié : la version devient risquée.

</details>

## Projet fil rouge

Deuxième jalon : **arrête ton choix de distribution et documente-le**.

1. Si ta VM ou ton VPS du chapitre 1 ne tourne pas déjà sous **Debian stable** ou **Ubuntu Server
   LTS**, recrée-la avec l'une des deux. C'est la base sur laquelle tout le reste se construira.
2. Dans ton fichier `notes-homelab.md`, ajoute une section « Système » : distribution, version exacte
   (avec le nom de code), date de fin de support estimée, et la raison de ton choix.
3. Vérifie d'une commande que ton système est bien celui que tu crois :

   ```bash
   cat /etc/os-release
   ```

Ton homelab repose maintenant sur une base claire et durable. Au chapitre suivant, on apprend à y
**installer des logiciels** proprement.

---

[← Chapitre précédent](01-introduction.md) · [Sommaire](README.md) · [Chapitre suivant →](03-paquets.md)
