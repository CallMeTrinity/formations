# Le réseau sous Linux

[← Chapitre précédent](05-utilisateurs-et-ssh.md) · [Sommaire](README.md) · [Chapitre suivant →](07-serveur-web.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est une **adresse IP**, un **port** et un **protocole** ;
- distinguer adresse **privée** et adresse **publique**, et comprendre le rôle du **NAT** ;
- comprendre à quoi sert le **DNS** (résolution des noms de domaine) ;
- inspecter le réseau de ta machine avec les outils modernes (`ip`, `ss`) et tester la connectivité
  (`ping`, `curl`).

Pour exposer un serveur, il faut comprendre comment les machines se parlent. Ce chapitre est le plus
théorique de la formation, mais c'est la **clé** des chapitres suivants : sans ces notions, le port
forwarding et le VPN restent de la magie. On va du paquet de données jusqu'au nom de domaine.

## Adresse IP : l'adresse postale des machines

Sur un réseau, chaque machine a une **adresse IP** (*Internet Protocol*) : un identifiant numérique qui
permet de l'atteindre, comme une adresse postale. La forme que tu croises le plus est l'**IPv4** :
quatre nombres de 0 à 255 séparés par des points.

```text
   192.168.1.42
   203.0.113.17
```

Il existe aussi l'**IPv6**, plus récente et plus longue (`2001:db8::1`), créée parce que les adresses
IPv4 sont en nombre limité et presque épuisées. Les concepts sont les mêmes ; dans cette formation, on
raisonne en IPv4 pour la clarté, mais tout se transpose en IPv6.

Pour voir les adresses de ta machine :

```bash
$ ip address           # ou la forme courte : ip a
2: eth0: <BROADCAST,MULTICAST,UP,LOWER_UP> ...
    inet 192.168.1.42/24 ...
```

`ip` est l'outil moderne (il remplace l'ancien `ifconfig`). La ligne `inet 192.168.1.42/24` donne
l'adresse IPv4 de l'interface réseau `eth0`. Le `/24` décrit la taille du réseau local — on n'a pas
besoin d'entrer dans le détail ici.

## Privé contre public : la grande distinction

C'est la notion la plus importante du chapitre. Toutes les adresses IP ne se valent pas :

- Une **adresse publique** est unique sur tout Internet. C'est par elle que le monde entier peut te
  joindre. Ton fournisseur d'accès (ou ton hébergeur de VPS) t'en attribue.
- Une **adresse privée** n'existe que **dans ton réseau local** (ta maison, ton entreprise). Plusieurs
  réseaux dans le monde réutilisent les mêmes adresses privées sans conflit, car elles ne sont **pas
  routables sur Internet**.

Les plages d'adresses **privées** sont réservées et faciles à reconnaître :

| Plage privée | Exemple typique |
| --- | --- |
| `10.0.0.0` à `10.255.255.255` | réseaux d'entreprise, VPN |
| `172.16.0.0` à `172.31.255.255` | Docker, par exemple |
| `192.168.0.0` à `192.168.255.255` | box internet domestiques |

Quand tu fais `ip a` chez toi et que tu vois `192.168.1.42`, c'est une adresse **privée** : elle te
désigne **dans** ton réseau, mais personne sur Internet ne peut t'atteindre avec.

> **À retenir** — Si l'adresse commence par `192.168.`, `10.` ou `172.16`–`172.31`, elle est
> **privée** : valable seulement dans ton réseau local. Tout le reste est potentiellement public.

### Connaître son adresse publique

Ta machine, derrière une box, ne « connaît » pas sa propre adresse publique : c'est la box qui la
détient. Pour la découvrir, on demande à un service extérieur :

```bash
$ curl ifconfig.me
203.0.113.17
```

Le serveur distant te renvoie l'adresse depuis laquelle il t'a vu arriver : ton adresse **publique**.

## Le NAT : comment tout ton réseau partage une seule adresse publique

Chez toi, tu as peut-être dix appareils connectés (ordinateurs, téléphones, TV), chacun avec une
adresse **privée** (`192.168.1.x`). Pourtant, ta box n'a qu'**une seule** adresse **publique**. Comment
dix machines partagent-elles une seule adresse vers Internet ?

La réponse est le **NAT** (*Network Address Translation*, traduction d'adresses réseau). La box agit
comme un **standardiste** : quand une machine interne envoie une requête vers Internet, la box
**remplace** l'adresse privée par sa propre adresse publique, note la correspondance, et fait
l'inverse pour la réponse.

```text
   Réseau local (privé)              Box (NAT)            Internet
   192.168.1.20  --\                                          
   192.168.1.42  ---+--> traduit en --> 203.0.113.17 --> serveur web
   192.168.1.55  --/                                          
```

Conséquence **capitale** pour l'auto-hébergement : le NAT fonctionne bien pour les connexions qui
**partent** de chez toi. Mais une connexion qui **arrive** de l'extérieur ne sait pas à quelle machine
interne s'adresser — la box la rejette par défaut. C'est pourquoi exposer un serveur **derrière une
box** demande une configuration spéciale, le **port forwarding** (chapitre 7). Un VPS, lui, a
directement une adresse publique : pas de NAT à traverser.

> **À retenir** — Le NAT laisse sortir, mais bloque ce qui arrive sans instruction. Héberger un
> serveur chez soi = dire à la box « les connexions entrantes sur tel port, envoie-les à telle machine
> interne ». Un VPS évite ce problème en ayant directement une IP publique.

## Les ports : plusieurs services sur une même machine

Une adresse IP désigne une **machine**. Mais une machine fait tourner plusieurs services à la fois (un
serveur web, un serveur SSH, une base de données…). Comment distinguer auquel s'adresse une connexion ?
Grâce aux **ports**.

Un **port** est un numéro (de 0 à 65535) qui identifie un **service précis** sur une machine. L'image
classique : l'adresse IP est l'immeuble, le port est le numéro d'appartement.

Certains ports sont **standardisés** par convention :

| Port | Service |
| --- | --- |
| 22 | SSH |
| 80 | HTTP (web non chiffré) |
| 443 | HTTPS (web chiffré) |
| 53 | DNS |

Quand tu écris `https://exemple.fr` dans ton navigateur, il se connecte sous-entendu au **port 443**
de la machine. Quand tu fais `ssh ...`, c'est le **port 22**. On parle d'une adresse complète sous la
forme `adresse:port`, par exemple `203.0.113.17:443`.

### Protocoles : TCP et UDP

Les données voyagent selon un **protocole** de transport. Deux comptent :

- **TCP** (*Transmission Control Protocol*) : fiable, ordonné, avec accusé de réception. Utilisé par le
  web, SSH, le mail — tout ce qui ne doit rien perdre.
- **UDP** (*User Datagram Protocol*) : rapide, sans garantie de livraison. Utilisé par le DNS, la voix,
  la vidéo en direct, et… le VPN WireGuard du chapitre 9.

Tu n'as pas à choisir : chaque service utilise le protocole qui lui convient. Retiens juste que TCP et
UDP existent, et qu'un port « 51820/UDP » n'est pas le même que « 51820/TCP » du point de vue du
pare-feu.

## Le DNS : des noms plutôt que des numéros

Personne ne retient `142.250.179.78`. Le **DNS** (*Domain Name System*) est l'**annuaire** d'Internet :
il traduit un **nom de domaine** lisible (`exemple.fr`) en **adresse IP**. C'est ce qui se passe,
invisible, chaque fois que tu tapes une adresse dans ton navigateur.

```text
   "quelle est l'IP de exemple.fr ?"
   ton ordinateur  ---->  serveur DNS  ---->  "203.0.113.17"
   puis ton ordinateur contacte directement 203.0.113.17
```

Pour interroger le DNS toi-même :

```bash
$ dig +short exemple.fr        # demande l'IP associée au nom (paquet dnsutils)
203.0.113.17
$ host exemple.fr              # variante plus simple
```

Quand tu enregistreras ton propre nom de domaine (chapitre 7), tu créeras un **enregistrement DNS** de
type `A` qui dit « `mon-serveur.fr` pointe vers telle adresse IP ». C'est ce qui permettra au monde de
trouver ton serveur par son nom.

## Inspecter et tester le réseau

Deux outils de diagnostic à connaître par cœur.

### `ss` : quels services écoutent sur ma machine ?

```bash
$ sudo ss -tlnp
State   Recv-Q  Send-Q  Local Address:Port   Process
LISTEN  0       128     0.0.0.0:22           sshd
LISTEN  0       511     0.0.0.0:80           nginx
```

`ss -tlnp` se lit : `-t` TCP, `-l` les sockets en écoute (*listening*), `-n` en chiffres (pas de
résolution de noms), `-p` avec le processus. Ici, on voit que SSH écoute sur le port 22 et nginx sur le
80. C'est l'outil pour répondre à « mon serveur écoute-t-il bien là où je crois ? ». L'adresse
`0.0.0.0` signifie « sur toutes les interfaces » (donc joignable de l'extérieur, si le pare-feu laisse
passer).

### `ping` et `curl` : la machine répond-elle ?

```bash
$ ping -c 3 exemple.fr        # envoie 3 paquets, mesure le temps de réponse
$ curl -I http://exemple.fr   # récupère juste les en-têtes HTTP (teste un serveur web)
```

`ping` teste si une machine est **joignable** sur le réseau. `curl` va plus loin : il parle réellement
le protocole HTTP et te montre la réponse du serveur web. Tu les utiliseras sans cesse pour vérifier,
étape par étape, qu'un serveur répond.

> **Astuce** — Devant « mon serveur n'est pas joignable », procède par couches : `ping` (la machine
> répond-elle ?), puis `ss -tlnp` sur le serveur (le service écoute-t-il ?), puis `curl` depuis
> l'extérieur (le port passe-t-il le pare-feu et le NAT ?). On isole le problème au lieu de deviner.

## Résumé

- Une **adresse IP** identifie une machine ; un **port** identifie un service sur cette machine
  (`adresse:port`).
- Les adresses **privées** (`192.168.`, `10.`, `172.16`–`172.31`) ne valent que dans le réseau local ;
  une adresse **publique** est joignable depuis Internet.
- Le **NAT** permet à tout un réseau de partager une seule IP publique ; il laisse sortir mais bloque
  l'entrant non configuré (d'où le port forwarding pour héberger derrière une box).
- Le **DNS** traduit les noms de domaine en adresses IP ; un enregistrement `A` fait pointer ton nom
  vers ton serveur.
- **TCP** (fiable) et **UDP** (rapide) sont les deux protocoles de transport.
- Outils : `ip a` (adresses), `ss -tlnp` (services en écoute), `ping` et `curl` (joignabilité).

## Exercices

### Exercice 1 — Privée ou publique ?

Pour chaque adresse, dis si elle est privée ou publique : (a) `192.168.0.10` ; (b) `8.8.8.8` ;
(c) `10.4.1.7` ; (d) `203.0.113.5`.

<details>
<summary>Voir le corrigé</summary>

La règle : `192.168.`, `10.` et `172.16`–`172.31` sont privées ; le reste est public.

- (a) `192.168.0.10` → **privée**.
- (b) `8.8.8.8` → **publique** (c'est d'ailleurs un serveur DNS public de Google).
- (c) `10.4.1.7` → **privée**.
- (d) `203.0.113.5` → **publique** (`203.0.113.0/24` est même une plage réservée à la documentation,
  mais elle est de classe publique).

</details>

### Exercice 2 — Adresse et port

Tu veux te connecter en SSH à un serveur d'adresse `203.0.113.17` qui écoute SSH sur le port 2222.
Écris la commande. Puis dis quelle adresse complète (`IP:port`) viserait un navigateur pour atteindre
le site HTTPS de ce même serveur.

<details>
<summary>Voir le corrigé</summary>

La démarche : SSH non standard se précise avec `-p` ; HTTPS utilise le port 443 par convention.

```bash
ssh utilisateur@203.0.113.17 -p 2222
```

Pour le HTTPS, l'adresse complète est `203.0.113.17:443` (le navigateur vise le port 443 par défaut
quand on tape `https://`).

</details>

### Exercice 3 — Diagnostiquer en couches

Tu as installé un serveur web mais `http://TON_IP` ne répond pas depuis ton ordinateur. Décris l'ordre
des vérifications que tu fais, et ce que chacune t'apprend.

<details>
<summary>Voir le corrigé</summary>

La démarche : isoler la couche défaillante, du plus bas au plus haut.

1. `ping TON_IP` depuis ton ordinateur : la machine est-elle **joignable** du tout ? Si non, problème
   réseau/NAT/pare-feu de base.
2. Sur le serveur, `sudo ss -tlnp | grep :80` : le service **écoute-t-il** bien sur le port 80, et sur
   `0.0.0.0` (pas seulement `127.0.0.1`) ?
3. `curl -I http://TON_IP` depuis l'extérieur : le port **passe-t-il** le pare-feu et le NAT ?

Si (2) montre que rien n'écoute, le problème est le service. Si (2) est bon mais (3) échoue, le
problème est le pare-feu ou le port forwarding (chapitres 7 et 8). On évite de deviner en testant
couche par couche.

</details>

## Quiz

**1.** Que distingue une adresse IP privée d'une publique ?
- A. La privée est plus rapide
- B. La privée n'est valable que dans le réseau local ; la publique est joignable depuis Internet
- C. La publique commence toujours par `192.168.`

**2.** À quoi sert le NAT sur une box internet ?
- A. À chiffrer les connexions
- B. À permettre à plusieurs machines privées de partager une seule IP publique
- C. À accélérer le DNS

**3.** Que fait le DNS ?
- A. Il chiffre le trafic web
- B. Il traduit un nom de domaine en adresse IP
- C. Il attribue les ports

**4.** Quelle commande montre les services en écoute sur la machine ?
- A. `ping`
- B. `ss -tlnp`
- C. `dig`

<details>
<summary>Voir les réponses</summary>

1. **B** — Une adresse privée ne vit que dans le réseau local ; la publique est routable sur Internet.
2. **B** — Le NAT fait partager une seule IP publique à tout un réseau privé.
3. **B** — Le DNS est l'annuaire qui associe un nom de domaine à une adresse IP.
4. **B** — `ss -tlnp` liste les sockets en écoute et les processus associés.

</details>

## Projet fil rouge

Sixième jalon : **cartographie le réseau de ton serveur**.

1. Relève les adresses de ta machine et son adresse publique :

   ```bash
   ip a                 # adresses privées des interfaces
   curl ifconfig.me     # adresse publique vue de l'extérieur
   ```

2. Liste les services déjà en écoute et identifie-les (tu devrais au moins voir SSH) :

   ```bash
   sudo ss -tlnp
   ```

3. Dans `notes-homelab.md`, dessine le schéma de ta situation : ton serveur est-il **derrière une
   box** (adresse privée + box NAT, donc port forwarding à prévoir) ou **directement sur Internet**
   (VPS avec IP publique) ? Note l'adresse publique et les ports déjà ouverts.

Tu comprends maintenant comment ton serveur est (ou n'est pas) joignable. Au chapitre suivant, on
installe un vrai serveur web et on le rend **accessible depuis Internet**.

---

[← Chapitre précédent](05-utilisateurs-et-ssh.md) · [Sommaire](README.md) · [Chapitre suivant →](07-serveur-web.md)
