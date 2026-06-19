# Déployer et exposer son premier serveur

[← Chapitre précédent](06-reseau.md) · [Sommaire](README.md) · [Chapitre suivant →](08-securite-exposition.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- installer un serveur web (**nginx**) et servir une page sur ta machine ;
- comprendre l'arborescence de configuration d'nginx et servir ton propre contenu ;
- rendre ton serveur accessible **depuis Internet**, selon que tu es sur un **VPS** ou **derrière une
  box** (port forwarding) ;
- associer un **nom de domaine** à ton serveur via un enregistrement DNS.

Tout converge ici. Tu as un système à jour, des services gérés par `systemd`, un accès SSH sécurisé et
les notions réseau. Il est temps de faire de ta machine un **vrai serveur web accessible au monde
entier**. On part d'une page locale, puis on ouvre les portes une à une.

## Installer nginx

**nginx** (prononcé « engine-x ») est un serveur web léger, rapide et omniprésent. On l'installe comme
n'importe quel paquet :

```bash
$ sudo apt update
$ sudo apt install nginx
$ systemctl status nginx        # vérifie qu'il tourne
```

nginx se lance et s'active au boot automatiquement à l'installation (Debian/Ubuntu font le `enable`
pour toi). Pour vérifier qu'il répond, **depuis la machine elle-même** :

```bash
$ curl -I http://localhost
HTTP/1.1 200 OK
Server: nginx
```

`localhost` (ou `127.0.0.1`) désigne **la machine elle-même** : c'est le test le plus local possible.
Un `200 OK` confirme qu'nginx fonctionne. Si tu es sur une VM ou un VPS avec un navigateur à portée,
tu peux aussi visiter `http://ADRESSE_DU_SERVEUR` et voir la page d'accueil « Welcome to nginx ».

> **À retenir** — On valide toujours **du plus proche au plus loin** : d'abord `curl localhost` sur la
> machine, puis depuis le réseau local, puis depuis Internet. Si ça marche en local mais pas de
> l'extérieur, le problème est le **pare-feu** ou le **NAT**, pas nginx.

## Servir ton propre contenu

nginx range sa configuration dans `/etc/nginx/`. Deux dossiers comptent :

- `/etc/nginx/sites-available/` : les fichiers de configuration de chaque site (disponibles).
- `/etc/nginx/sites-enabled/` : des **liens symboliques** vers ceux qui sont **activés**.

Cette séparation permet d'avoir plusieurs sites configurés et d'en activer ou désactiver à volonté.
Par défaut, un site `default` sert les fichiers du dossier `/var/www/html/`. Remplaçons sa page
d'accueil par la nôtre :

```bash
$ echo "<h1>Mon premier serveur</h1>" | sudo tee /var/www/html/index.html
$ curl http://localhost
<h1>Mon premier serveur</h1>
```

Pour servir un **vrai site** depuis ton propre dossier, on écrit un fichier de configuration. Voici un
**server block** (l'équivalent nginx d'un « site ») minimal :

```nginx
# /etc/nginx/sites-available/mon-site
server {
    listen 80;                          # écoute le port 80 (HTTP)
    server_name mon-serveur.fr;         # répond pour ce nom de domaine
    root /var/www/mon-site;             # sert les fichiers de ce dossier
    index index.html;                   # fichier par défaut
}
```

On l'active (en créant le lien symbolique), on teste la config, on recharge :

```bash
$ sudo mkdir -p /var/www/mon-site
$ echo "<h1>Bienvenue chez moi</h1>" | sudo tee /var/www/mon-site/index.html
$ sudo ln -s /etc/nginx/sites-available/mon-site /etc/nginx/sites-enabled/
$ sudo nginx -t                # teste la configuration (syntaxe)
$ sudo systemctl reload nginx  # recharge sans couper le service
```

> **Attention** — `sudo nginx -t` avant chaque rechargement est un réflexe vital : une config invalide
> empêcherait nginx de redémarrer. `nginx -t` te dit `syntax is ok` / `test is successful` avant que tu
> ne touches au service en production.

## Rendre le serveur accessible depuis Internet

C'est l'étape qui change tout. Le chemin dépend de **où** est ton serveur — c'est ici que la
distinction privé/public du chapitre 6 devient concrète.

### Cas A — Tu es sur un VPS (IP publique directe)

Un VPS a déjà une **adresse IP publique** : pas de NAT à traverser. Il ne reste qu'à s'assurer que
**rien ne bloque** le port 80. Deux verrous possibles :

1. Le **pare-feu de la machine** (chapitre 8). Pour l'instant, s'il est actif, autorise le web :

   ```bash
   sudo ufw allow 80/tcp        # on détaille ufw au chapitre 8
   ```

2. Le **pare-feu de l'hébergeur** : beaucoup d'hébergeurs (OVH, AWS, Scaleway…) ont un pare-feu réseau
   configurable dans leur interface web (*security group*). Vérifie que le port 80 (et bientôt 443) y
   est autorisé.

Ensuite, depuis **ton ordinateur** (pas le serveur), teste de l'extérieur :

```bash
$ curl -I http://IP_PUBLIQUE_DU_VPS
HTTP/1.1 200 OK
```

Si tu obtiens `200 OK` depuis chez toi, **ton serveur est en ligne sur Internet**.

### Cas B — Tu héberges derrière ta box (port forwarding)

Ton serveur a une adresse **privée** (`192.168.1.x`) et se cache derrière le **NAT** de ta box. Pour
qu'une connexion venue d'Internet l'atteigne, tu dois dire à la box : « les connexions entrantes sur
le port 80, **redirige-les** vers telle machine interne ». C'est la **redirection de port** (*port
forwarding*).

```text
   Internet                Box (port forwarding)         Serveur
   visiteur:80  -->  203.0.113.17:80  ===>  192.168.1.42:80  (nginx)
                     (IP publique)          (IP privée fixe)
```

La marche à suivre, dans l'interface d'administration de ta box (l'URL est souvent `192.168.1.1`) :

1. **Fixe l'adresse privée** de ton serveur (réservation DHCP ou IP statique) pour qu'elle ne change
   pas. Une redirection vers `192.168.1.42` ne sert à rien si le serveur devient `192.168.1.50` demain.
2. Crée une **règle de redirection de port** : port externe `80` → IP interne `192.168.1.42`, port
   interne `80`, protocole TCP. Répète pour le `443` (HTTPS, chapitre 8).
3. Teste depuis l'extérieur (utilise les données mobiles de ton téléphone, ou demande à `curl`
   ifconfig.me ton IP publique, puis `curl http://CETTE_IP`).

> **Attention** — L'adresse publique d'une box est souvent **dynamique** : elle peut changer au gré du
> fournisseur d'accès. Ton serveur deviendrait alors injoignable par son ancienne IP. La solution est
> le **DNS dynamique** (DynDNS) : un service qui met à jour automatiquement ton enregistrement DNS
> quand ton IP change. On y revient avec le nom de domaine ci-dessous.

> **Attention** — Certains fournisseurs d'accès placent leurs clients derrière un **CGNAT** (un NAT
> partagé à grande échelle) : tu n'as alors **pas** d'IP publique à toi, et le port forwarding est
> impossible. Si `curl ifconfig.me` te donne une adresse différente de celle affichée dans ta box, tu
> es probablement dans ce cas. La parade : utiliser un VPS comme point d'entrée, ou un tunnel — sujets
> abordés au chapitre 9 avec le VPN.

## Associer un nom de domaine

Une adresse IP, c'est moche et fragile. Un **nom de domaine** (`mon-serveur.fr`) est lisible, stable, et
indispensable pour le HTTPS du chapitre 8. Tu en achètes un chez un **bureau d'enregistrement**
(*registrar* : OVH, Gandi, Namecheap…) pour quelques euros par an.

Une fois le domaine acheté, tu crées un **enregistrement DNS** de type `A` dans l'interface du
registrar, qui fait pointer ton nom vers l'IP de ton serveur :

```text
   Type   Nom            Valeur
   A      mon-serveur.fr  203.0.113.17     (l'IP publique de ton serveur)
   A      www             203.0.113.17     (pour www.mon-serveur.fr)
```

La propagation prend de quelques minutes à quelques heures. Tu vérifies que ça marche avec l'outil du
chapitre 6 :

```bash
$ dig +short mon-serveur.fr
203.0.113.17
$ curl -I http://mon-serveur.fr
HTTP/1.1 200 OK
```

Le `server_name mon-serveur.fr` de ta config nginx prend alors tout son sens : nginx sait répondre
pour ce nom.

> **Astuce** — Pour un serveur **à domicile** avec IP dynamique, beaucoup de registrars et de services
> (comme DuckDNS, gratuit) proposent le **DNS dynamique** : un petit client sur ton serveur met à jour
> l'enregistrement `A` automatiquement dès que ton IP publique change. Tu gardes ainsi un nom stable
> malgré une IP mouvante.

## Résumé

- **nginx** sert des pages web ; on le teste d'abord en local avec `curl http://localhost`.
- La config vit dans `/etc/nginx/` : un **server block** dans `sites-available/`, activé par un lien
  dans `sites-enabled/`. Toujours `sudo nginx -t` puis `reload`.
- **Sur un VPS** : IP publique directe, il suffit d'ouvrir le port (pare-feu machine + pare-feu
  hébergeur).
- **Derrière une box** : il faut une **redirection de port** (NAT), une IP interne fixe, et gérer
  l'IP publique **dynamique** (DNS dynamique). Le **CGNAT** peut rendre l'hébergement à domicile
  impossible sans tunnel.
- Un **nom de domaine** + un enregistrement DNS `A` rend ton serveur joignable par un nom stable.

## Exercices

### Exercice 1 — Valider en local avant d'exposer

Tu viens de configurer un server block pour servir `/var/www/mon-site`. Avant même de penser à
l'extérieur, quelles deux commandes confirment qu'nginx est correctement configuré et sert ta page ?

<details>
<summary>Voir le corrigé</summary>

La démarche : valider la syntaxe, puis tester le rendu en local.

```bash
sudo nginx -t            # la configuration est-elle valide ?
curl http://localhost    # nginx sert-il bien ma page ?
```

Si `nginx -t` échoue, on corrige avant de recharger (sinon nginx refuserait de redémarrer). Si `curl
localhost` renvoie ta page, le serveur fonctionne ; tout problème depuis l'extérieur viendra alors du
réseau (pare-feu/NAT), pas d'nginx.

</details>

### Exercice 2 — VPS ou box ?

Pour chacun, dis ce qu'il faut configurer pour exposer le port 80 : (a) un VPS loué chez Hetzner ;
(b) un mini-PC branché derrière la box de ta maison.

<details>
<summary>Voir le corrigé</summary>

La démarche : la présence ou non d'un NAT décide.

- (a) **VPS** : IP publique directe. Il suffit d'ouvrir le port 80 dans le **pare-feu de la machine**
  (`ufw allow 80/tcp`) et de vérifier le **pare-feu/security group de l'hébergeur**. Pas de NAT.
- (b) **Box** : adresse privée derrière le NAT. Il faut une **redirection de port** sur la box (port
  80 externe → IP interne fixe du mini-PC), fixer l'IP interne, et prévoir le **DNS dynamique** si
  l'IP publique change. Vérifier aussi l'absence de CGNAT.

</details>

### Exercice 3 — Du nom à l'IP

Tu as acheté `mon-labo.fr` et ton serveur a l'IP publique `203.0.113.50`. Quel enregistrement DNS
crées-tu, et comment vérifies-tu que la résolution fonctionne ?

<details>
<summary>Voir le corrigé</summary>

La démarche : un enregistrement `A` lie le nom à l'IP, puis on interroge le DNS.

Enregistrement à créer chez le registrar :

```text
Type   Nom        Valeur
A      mon-labo.fr  203.0.113.50
```

Vérification (après propagation) :

```bash
dig +short mon-labo.fr      # doit renvoyer 203.0.113.50
curl -I http://mon-labo.fr  # doit renvoyer une réponse d'nginx
```

`dig` confirme que le DNS résout bien le nom vers la bonne IP ; `curl` confirme que le serveur répond
pour ce nom.

</details>

## Quiz

**1.** Pourquoi tester `curl http://localhost` sur le serveur avant d'essayer depuis l'extérieur ?
- A. C'est plus rapide
- B. Pour isoler nginx du réseau : si ça marche en local mais pas dehors, le problème est le
  pare-feu/NAT
- C. Parce que `localhost` est plus sécurisé

**2.** Qu'est-ce que le port forwarding sur une box ?
- A. Chiffrer les connexions entrantes
- B. Rediriger les connexions arrivant sur un port vers une machine interne précise
- C. Accélérer le réseau local

**3.** À quoi sert un enregistrement DNS de type `A` ?
- A. À faire pointer un nom de domaine vers une adresse IP
- B. À chiffrer le site
- C. À ouvrir un port sur la box

**4.** Pourquoi un serveur à domicile a-t-il souvent besoin du DNS dynamique ?
- A. Parce que son IP privée change
- B. Parce que son IP publique attribuée par le fournisseur peut changer
- C. Parce que nginx l'exige

<details>
<summary>Voir les réponses</summary>

1. **B** — Tester en local isole nginx ; un échec uniquement depuis l'extérieur pointe vers le
   pare-feu ou le NAT.
2. **B** — Le port forwarding redirige l'entrant vers une machine interne, indispensable derrière un
   NAT.
3. **A** — L'enregistrement `A` associe un nom de domaine à une adresse IP.
4. **B** — L'IP publique d'une box est souvent dynamique ; le DNS dynamique met à jour le nom
   automatiquement.

</details>

## Projet fil rouge

Septième jalon : **ton homelab est en ligne**.

1. Installe nginx et sers **ta propre page** d'accueil depuis un server block dédié.
2. Rends-le accessible depuis Internet selon ta situation : ouverture de port sur un **VPS**, ou
   **redirection de port** sur ta **box**. Vérifie de l'extérieur avec `curl http://TON_IP`.
3. (Fortement recommandé) Achète un **nom de domaine** et crée l'enregistrement `A` vers ton serveur.
   Vérifie avec `dig +short ton-domaine` et `curl http://ton-domaine`.
4. Mets à jour `notes-homelab.md` : nom de domaine, IP publique, ports ouverts, et la procédure exacte
   de redirection si tu es derrière une box.

Ton serveur est joignable par tous… y compris les robots malveillants. Au chapitre suivant, on le
**sécurise** et on passe en **HTTPS**.

---

[← Chapitre précédent](06-reseau.md) · [Sommaire](README.md) · [Chapitre suivant →](08-securite-exposition.md)
