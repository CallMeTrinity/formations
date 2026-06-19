# Son propre VPN avec WireGuard

[← Chapitre précédent](08-securite-exposition.md) · [Sommaire](README.md) · [Chapitre suivant →](10-docker.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **VPN**, à quoi il sert, et pourquoi en monter un soi-même ;
- comprendre le fonctionnement de **WireGuard** : clés, pairs (*peers*) et tunnel chiffré ;
- installer et configurer un **serveur WireGuard** sur ton serveur ;
- y connecter un **client** (ton ordinateur ou ton téléphone) pour rejoindre ton réseau « comme depuis
  chez toi ».

C'est le chapitre que tu attendais. Un **VPN** te permet de te brancher à distance sur ton réseau
privé comme si tu y étais physiquement, par un tunnel chiffré. Tu vas monter le tien avec
**WireGuard**, le VPN moderne, simple et rapide — et comprendre exactement ce que tu fais, sans magie.

## Qu'est-ce qu'un VPN, vraiment ?

Un **VPN** (*Virtual Private Network*, réseau privé virtuel) crée un **tunnel chiffré** entre deux
machines à travers Internet. Tout ce qui passe dans le tunnel est illisible pour les machines
intermédiaires, et les deux extrémités se retrouvent sur un **même réseau privé virtuel**, comme si un
câble les reliait directement.

Concrètement, ça résout deux besoins distincts :

1. **Accéder à ton réseau privé depuis l'extérieur.** Tu es au café, mais tu veux atteindre un service
   qui tourne chez toi (l'administration de ton serveur, un NAS, une caméra) sans l'exposer à tout
   Internet. Le VPN te place « virtuellement » sur ton réseau domestique. **C'est l'usage de ce
   chapitre et du projet fil rouge.**
2. **Faire sortir ton trafic par un autre endroit.** Tout ton trafic Internet passe par le serveur
   VPN, qui le renvoie vers le web. C'est l'usage « anonymat / contournement » des VPN commerciaux.
   WireGuard sait le faire aussi, mais ce n'est pas notre objectif principal.

```text
   Toi, au café (réseau inconnu)                      Ton serveur (chez toi / VPS)
   +-------------------+                              +----------------------+
   |  client WireGuard | ==== tunnel chiffré ====>    |  serveur WireGuard   |
   |  10.8.0.2         |     (à travers Internet)     |  10.8.0.1            |
   +-------------------+                              +----------------------+
                                                       et derrière lui : ton réseau privé
```

> **À retenir** — Un VPN n'est pas magique : c'est un **tunnel chiffré** qui relie deux machines sur
> un réseau privé virtuel. En te connectant, tu obtiens une adresse sur ce réseau (`10.8.0.2` par
> exemple) et tu peux parler aux autres machines du tunnel.

### Pourquoi héberger son propre VPN ?

Les VPN commerciaux te font confiance à un tiers et ne te donnent pas accès à **ton** réseau. Monter le
tien, c'est : un accès privé à tes propres services sans les exposer publiquement, aucun tiers à qui
faire confiance, et une compréhension réelle de la technologie. Pour l'administration de ton serveur,
c'est la meilleure des sécurités : **le service d'admin n'écoute que sur le tunnel**, invisible depuis
Internet.

## WireGuard : le modèle clé et pairs

**WireGuard** est intégré au noyau Linux moderne. Sa philosophie : faire **simple**. Là où les vieux
VPN demandaient des pages de configuration, WireGuard tient en quelques lignes.

Son modèle repose sur deux idées que tu connais déjà :

- **Des clés**, exactement comme SSH (chapitre 5). Chaque machine (chaque **pair**, *peer*) a une
  **clé privée** (secrète) et une **clé publique** (partagée). On s'authentifie par les clés, pas par
  mot de passe.
- **Des pairs.** Il n'y a pas vraiment de « client » et de « serveur » au sens strict : il y a des
  **pairs** qui s'autorisent mutuellement. Par convention, on appelle « serveur » le pair toujours
  allumé et joignable (ton serveur), et « clients » les pairs mobiles (ton portable, ton téléphone).

Chaque pair connaît : sa propre clé privée, son adresse sur le réseau VPN (`10.8.0.x`), et la **clé
publique** + l'adresse des pairs avec qui il a le droit de parler.

> **À retenir** — WireGuard = clés (comme SSH) + pairs qui se déclarent mutuellement. Pour autoriser
> une machine, on échange les **clés publiques**. La clé privée ne quitte jamais sa machine.

## Préparer le serveur WireGuard

On installe WireGuard et on génère la paire de clés du serveur.

```bash
$ sudo apt install wireguard
$ cd /etc/wireguard
$ umask 077                                    # les fichiers créés seront privés (lecture interdite aux autres)
$ wg genkey | sudo tee server_private.key | wg pubkey | sudo tee server_public.key
```

Cette ligne mérite d'être décomposée : `wg genkey` génère une clé privée, `tee server_private.key`
l'enregistre, le tube la passe à `wg pubkey` qui en dérive la clé publique, enregistrée dans
`server_public.key`. Tu as maintenant la paire de clés du serveur.

On écrit ensuite la configuration du tunnel, dans `/etc/wireguard/wg0.conf` (`wg0` est le nom de
l'interface VPN qu'on crée) :

```ini
# /etc/wireguard/wg0.conf  (côté serveur)
[Interface]
Address = 10.8.0.1/24                  # adresse du serveur sur le réseau VPN
ListenPort = 51820                     # port d'écoute (UDP)
PrivateKey = <CONTENU_DE_server_private.key>

# Un bloc [Peer] par client autorisé (on le complétera plus bas)
```

- `Address = 10.8.0.1/24` : le serveur prend l'adresse `10.8.0.1` sur un réseau privé virtuel
  `10.8.0.0/24` qu'on invente pour le VPN (n'importe quelle plage privée libre convient).
- `ListenPort = 51820` : le port **UDP** standard de WireGuard (souviens-toi, UDP, chapitre 6).
- `PrivateKey` : on y colle le **contenu** du fichier `server_private.key`.

## Connecter un client

Sur la machine cliente (ton ordinateur portable), installe WireGuard et génère **sa** paire de clés,
exactement de la même façon :

```bash
$ sudo apt install wireguard
$ wg genkey | tee client_private.key | wg pubkey | tee client_public.key
```

Maintenant, l'étape clé : **échanger les clés publiques**. Le serveur doit connaître la clé publique du
client, et le client celle du serveur.

### Déclarer le client sur le serveur

On ajoute un bloc `[Peer]` à la config du serveur (`wg0.conf`) :

```ini
# ... à la suite de [Interface] dans /etc/wireguard/wg0.conf
[Peer]
PublicKey = <CONTENU_DE_client_public.key>
AllowedIPs = 10.8.0.2/32               # l'adresse VPN qu'on attribue à ce client
```

`AllowedIPs = 10.8.0.2/32` dit : « ce pair est responsable de l'adresse `10.8.0.2` ». Le `/32` désigne
une seule adresse.

### Configurer le client

Sur le client, on crée `wg0.conf` avec sa clé privée et le serveur comme pair :

```ini
# /etc/wireguard/wg0.conf  (côté client)
[Interface]
Address = 10.8.0.2/24                   # l'adresse VPN de ce client
PrivateKey = <CONTENU_DE_client_private.key>

[Peer]
PublicKey = <CONTENU_DE_server_public.key>
Endpoint = mon-serveur.fr:51820         # où joindre le serveur (nom ou IP publique : port)
AllowedIPs = 10.8.0.0/24                # le trafic vers le réseau VPN passe par le tunnel
PersistentKeepalive = 25                # garde le tunnel ouvert à travers les NAT
```

- `Endpoint` : l'adresse **publique** du serveur et son port — c'est par là que le client initie le
  tunnel (ton nom de domaine du chapitre 7 est parfait ici).
- `AllowedIPs = 10.8.0.0/24` côté client signifie « envoie dans le tunnel tout ce qui va vers le réseau
  VPN ». Si tu mettais `0.0.0.0/0`, **tout** ton trafic Internet passerait par le serveur (l'usage
  numéro 2 vu plus haut).
- `PersistentKeepalive = 25` envoie un petit paquet toutes les 25 secondes pour maintenir le tunnel
  ouvert quand le client est derrière un NAT.

## Allumer le tunnel

WireGuard s'intègre à `systemd` (chapitre 4) via `wg-quick`. Sur le serveur **et** le client :

```bash
$ sudo systemctl enable --now wg-quick@wg0    # active au boot et démarre le tunnel wg0
$ sudo wg                                      # affiche l'état des tunnels et des pairs
```

`sudo wg` montre, pour chaque pair, la dernière poignée de main (*handshake*) et les octets échangés :
c'est ton outil de diagnostic. Une fois les deux côtés allumés, teste depuis le client :

```bash
$ ping 10.8.0.1        # le client joint le serveur par son adresse VPN
```

Si le `ping` répond, **ton VPN fonctionne** : ton client et ton serveur sont sur le même réseau privé
virtuel, où que tu sois physiquement.

### Ouvrir le port côté pare-feu et NAT

Pour que le client joigne le serveur de l'extérieur, le port `51820/UDP` doit être ouvert — c'est tout
ce que tu exposes :

```bash
$ sudo ufw allow 51820/udp     # sur le serveur (rappel du chapitre 8)
```

Si ton serveur WireGuard est **derrière une box**, ajoute aussi une **redirection de port** (chapitre
7) du port `51820/UDP` vers la machine du serveur. C'est le seul port à ouvrir : tous tes autres
services peuvent rester invisibles d'Internet et n'être joignables **que par le tunnel**.

> **À retenir** — Le VPN inverse la logique de sécurité : au lieu d'exposer chaque service, tu
> n'exposes **qu'un seul port UDP** (WireGuard), et tu accèdes au reste **à travers le tunnel**. Ton
> interface d'admin, ton NAS, etc. n'ont plus besoin d'être visibles d'Internet.

### Et depuis ton téléphone

WireGuard a des applications officielles pour Android et iOS. Le plus simple pour configurer un
téléphone est le **QR code** : sur le serveur, le paquet `qrencode` transforme une config client en
code à scanner.

```bash
$ sudo apt install qrencode
$ qrencode -t ansiutf8 < client_telephone.conf    # affiche un QR code dans le terminal
```

Tu scannes ce code depuis l'appli WireGuard du téléphone, et le tunnel est configuré en quelques
secondes — chaque téléphone étant un pair de plus, avec sa propre paire de clés et sa propre adresse
`10.8.0.x`.

> **Attention** — Chaque client doit avoir sa **propre paire de clés** et sa **propre adresse VPN**
> (`10.8.0.2`, `10.8.0.3`…). Ne partage jamais une même clé entre deux appareils : tu perdrais la
> traçabilité et la possibilité de révoquer un seul appareil (il suffit alors de supprimer son bloc
> `[Peer]` côté serveur).

## Résumé

- Un **VPN** est un **tunnel chiffré** qui place deux machines sur un même réseau privé virtuel à
  travers Internet.
- **WireGuard** repose sur des **clés** (comme SSH) et des **pairs** : on autorise une machine en
  échangeant les **clés publiques**.
- Côté serveur : `[Interface]` (adresse VPN, port UDP, clé privée) + un `[Peer]` par client. Côté
  client : sa clé, l'`Endpoint` public du serveur, les `AllowedIPs`.
- On allume le tunnel avec `wg-quick@wg0` (systemd), on diagnostique avec `sudo wg`.
- On n'expose qu'**un seul port UDP** (51820) ; tout le reste devient accessible **par le tunnel
  uniquement** — la meilleure protection pour les services d'administration.

## Exercices

### Exercice 1 — Qui connaît quelle clé ?

Dans une configuration WireGuard serveur + un client, dis précisément quelle clé chaque côté possède
et quelle clé chaque côté connaît de l'autre.

<details>
<summary>Voir le corrigé</summary>

La démarche : appliquer le modèle clé privée locale / clé publique partagée.

- Le **serveur** possède sa clé privée (`PrivateKey` dans son `[Interface]`) et connaît la **clé
  publique du client** (`PublicKey` dans le bloc `[Peer]` du client).
- Le **client** possède sa clé privée (`PrivateKey` dans son `[Interface]`) et connaît la **clé
  publique du serveur** (`PublicKey` dans son bloc `[Peer]`).

Aucune clé privée ne quitte sa machine ; seules les clés publiques sont échangées — exactement comme
avec SSH.

</details>

### Exercice 2 — Un seul port exposé

Ton serveur WireGuard tourne derrière ta box, et tu héberges aussi une interface d'administration que
tu **ne veux pas** exposer à Internet. Quels ports ouvres-tu sur la box, et comment accèdes-tu à
l'interface d'admin ?

<details>
<summary>Voir le corrigé</summary>

La démarche : exposer uniquement WireGuard, accéder au reste par le tunnel.

Sur la box, tu ne rediriges **que** le port `51820/UDP` vers le serveur WireGuard. L'interface d'admin
n'écoute que sur le réseau local (ou sur l'adresse VPN du serveur, `10.8.0.1`), et **n'est pas**
redirigée. Pour y accéder de l'extérieur : tu actives ton tunnel WireGuard, puis tu vises l'interface
par l'adresse VPN du serveur (par exemple `http://10.8.0.1:port-admin`). Elle reste totalement
invisible pour qui n'est pas sur le tunnel.

</details>

### Exercice 3 — Diagnostiquer un tunnel muet

Tu as tout configuré, mais `ping 10.8.0.1` depuis le client ne répond pas. Cite trois causes probables
et la commande qui t'aide à les diagnostiquer.

<details>
<summary>Voir le corrigé</summary>

La démarche : vérifier l'état du tunnel et les obstacles réseau.

`sudo wg` sur les deux machines est l'outil central : il indique s'il y a eu une **poignée de main**
(*latest handshake*). Pas de handshake = le tunnel ne s'établit pas. Causes probables :

1. Le **port 51820/UDP n'est pas ouvert** (pare-feu `ufw` du serveur ou redirection de port manquante
   sur la box).
2. Une **clé publique mal recopiée** dans un des fichiers (le client n'est pas reconnu).
3. Un **`Endpoint` erroné** côté client (mauvaise IP/nom ou mauvais port), ou les `AllowedIPs` qui ne
   couvrent pas l'adresse visée.

On corrige l'élément fautif, on relance `sudo systemctl restart wg-quick@wg0`, et on re-vérifie avec
`sudo wg`.

</details>

## Quiz

**1.** Qu'est-ce qu'un VPN, fondamentalement ?
- A. Un antivirus
- B. Un tunnel chiffré reliant deux machines sur un réseau privé virtuel
- C. Un serveur web sécurisé

**2.** Sur quoi repose l'authentification dans WireGuard ?
- A. Un mot de passe partagé
- B. Une paire de clés publique/privée, comme SSH
- C. L'adresse IP uniquement

**3.** Combien de ports faut-il exposer à Internet pour un VPN WireGuard donnant accès à plusieurs
services ?
- A. Un port par service
- B. Un seul port UDP (celui de WireGuard) ; le reste passe par le tunnel
- C. Tous les ports, ouverts

**4.** Pourquoi chaque client doit-il avoir sa propre paire de clés et sa propre adresse VPN ?
- A. Pour aller plus vite
- B. Pour la traçabilité et pour pouvoir révoquer un appareil individuellement
- C. C'est optionnel, on peut tout partager

<details>
<summary>Voir les réponses</summary>

1. **B** — Un VPN est un tunnel chiffré plaçant les machines sur un réseau privé virtuel.
2. **B** — WireGuard utilise des paires de clés, comme SSH ; pas de mot de passe.
3. **B** — On n'expose qu'un seul port UDP ; tous les services sont atteints via le tunnel.
4. **B** — Des clés et adresses distinctes permettent de tracer et de révoquer chaque appareil.

</details>

## Projet fil rouge

Neuvième jalon : **monte ton VPN et range l'administration derrière**.

1. Installe WireGuard sur ton serveur, génère sa paire de clés, et crée `wg0.conf` (`[Interface]` avec
   `10.8.0.1/24` et le port `51820`).
2. Configure un **client** (ton ordinateur) : génère sa paire de clés, échange les clés publiques,
   complète les deux `wg0.conf`, ouvre `51820/udp` sur le pare-feu (et redirige-le sur la box si
   besoin).
3. Allume les deux côtés (`wg-quick@wg0`), vérifie avec `sudo wg`, et confirme par `ping 10.8.0.1`
   depuis le client.
4. Ajoute un second client : ton **téléphone**, via un QR code (`qrencode`).
5. Décide qu'un service d'administration (par exemple une future interface) **n'écoutera que sur le
   tunnel**. Note dans `notes-homelab.md` les adresses VPN attribuées et la liste des pairs.

Tu peux désormais rejoindre ton réseau de n'importe où, comme depuis chez toi. Au chapitre suivant, on
modernise la façon de **déployer des services** avec Docker.

---

[← Chapitre précédent](08-securite-exposition.md) · [Sommaire](README.md) · [Chapitre suivant →](10-docker.md)
