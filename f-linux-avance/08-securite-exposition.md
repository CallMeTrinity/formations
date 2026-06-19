# Sécuriser l'exposition : pare-feu et HTTPS

[← Chapitre précédent](07-serveur-web.md) · [Sommaire](README.md) · [Chapitre suivant →](09-vpn-wireguard.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- comprendre ce qu'est un **pare-feu** et appliquer le principe « tout fermé sauf l'essentiel » ;
- configurer un pare-feu avec **`ufw`** (et savoir que `nftables` est dessous) ;
- protéger SSH des attaques répétées avec **`fail2ban`** ;
- passer ton site en **HTTPS** avec un certificat **Let's Encrypt** gratuit et automatique.

Ton serveur est en ligne. Dès la première minute, des robots scannent ses ports et tentent de se
connecter — c'est constant et automatique sur tout ce qui est exposé. Ce chapitre dresse les trois
remparts d'un serveur exposé : **filtrer** ce qui entre, **bloquer** les acharnés, et **chiffrer** le
trafic.

## Le pare-feu : tout fermé sauf l'essentiel

Un **pare-feu** (*firewall*) est un filtre qui décide, paquet par paquet, ce qui a le droit d'entrer
et de sortir de la machine, selon des **règles** (souvent par port et protocole). La bonne philosophie
est le **refus par défaut** : on **bloque tout**, puis on **ouvre uniquement** les ports des services
qu'on expose volontairement. Toute porte non ouverte explicitement reste fermée.

Sous Linux, le moteur de pare-feu du noyau s'appelle **nftables** (il succède à l'ancien `iptables`).
Sa syntaxe est puissante mais verbeuse. Sur Debian/Ubuntu, on utilise donc **`ufw`** (*Uncomplicated
Firewall*), une surcouche simple qui pilote nftables pour toi.

> **À retenir** — Principe de base d'un pare-feu : **refuser tout par défaut, n'ouvrir que le
> nécessaire**. Chaque port ouvert est une porte de plus à surveiller ; on n'en ouvre aucun « au cas
> où ».

## Configurer le pare-feu avec `ufw`

`ufw` était dans la trousse à outils installée au chapitre 3. Voici la séquence de mise en place. Lis
bien l'ordre : on autorise SSH **avant** d'activer le pare-feu, sous peine de se couper soi-même.

```bash
$ sudo ufw default deny incoming     # tout ce qui entre est refusé par défaut
$ sudo ufw default allow outgoing    # tout ce qui sort est autorisé
$ sudo ufw allow OpenSSH             # ON OUVRE SSH AVANT D'ACTIVER (sinon : déconnexion)
$ sudo ufw allow 80/tcp              # HTTP
$ sudo ufw allow 443/tcp             # HTTPS
$ sudo ufw enable                    # active le pare-feu
$ sudo ufw status verbose            # vérifie les règles
```

`ufw allow OpenSSH` utilise un **profil** nommé (ufw connaît les ports des services courants), mais on
peut aussi écrire `sudo ufw allow 22/tcp`. La sortie de `status` ressemble à :

```text
$ sudo ufw status verbose
Status: active
Default: deny (incoming), allow (outgoing)

To                         Action      From
--                         ------      ----
22/tcp (OpenSSH)           ALLOW IN    Anywhere
80/tcp                     ALLOW IN    Anywhere
443/tcp                    ALLOW IN    Anywhere
```

Pour fermer un port plus tard, on supprime la règle :

```bash
$ sudo ufw status numbered    # affiche les règles avec un numéro
$ sudo ufw delete 3           # supprime la règle numéro 3
```

> **Attention** — Activer un pare-feu en refus par défaut **sans avoir autorisé SSH** te déconnecte
> immédiatement et définitivement d'un serveur distant. Toujours `sudo ufw allow OpenSSH` **avant**
> `sudo ufw enable`. Sur un VPS, garde une console de secours (l'accès « KVM/rescue » de l'hébergeur)
> sous la main au cas où.

## Bloquer les acharnés avec `fail2ban`

Le pare-feu laisse passer le port SSH — il le faut bien pour t'y connecter. Mais des robots vont alors
**marteler** ce port avec des milliers de tentatives de connexion. `fail2ban` répond à ça : il
**surveille les journaux**, repère les échecs répétés depuis une même adresse IP, et **bannit**
temporairement cette IP au niveau du pare-feu.

```bash
$ sudo apt install fail2ban
$ systemctl status fail2ban     # il démarre et s'active automatiquement
```

`fail2ban` protège SSH dès l'installation sur Debian/Ubuntu. Pour ajuster, on **ne modifie jamais**
le fichier fourni `jail.conf` (il serait écrasé aux mises à jour) : on crée un `jail.local` qui le
surcharge.

```ini
# /etc/fail2ban/jail.local
[sshd]
enabled = true
maxretry = 4          # nombre d'échecs tolérés
bantime = 1h          # durée du bannissement
findtime = 10m        # fenêtre pendant laquelle on compte les échecs
```

Ici : 4 échecs en moins de 10 minutes → l'IP est bannie pendant 1 heure. On recharge et on observe :

```bash
$ sudo systemctl restart fail2ban
$ sudo fail2ban-client status sshd    # voir les IP actuellement bannies
```

> **À retenir** — `fail2ban` transforme les journaux en action : il banni automatiquement les IP qui
> s'acharnent. Combiné à l'authentification par clé (chapitre 5), il rend les attaques par devinette
> de mot de passe à la fois inutiles et bloquées.

## Passer en HTTPS avec Let's Encrypt

Pour l'instant, ton site est en **HTTP** : tout le trafic circule **en clair**, lisible par quiconque
sur le chemin. Le **HTTPS** chiffre cet échange grâce à un **certificat** : un fichier signé par une
**autorité de certification** qui prouve l'identité de ton domaine et permet le chiffrement (le
fameux cadenas du navigateur).

Avant, ces certificats étaient payants et pénibles à renouveler. **Let's Encrypt** les délivre
**gratuitement** et **automatiquement**. L'outil officiel côté serveur s'appelle **certbot**.

Prérequis : ton **nom de domaine** doit déjà pointer vers ton serveur (chapitre 7), et les ports 80 et
443 doivent être ouverts (Let's Encrypt vérifie que tu contrôles bien le domaine en contactant ton
serveur sur le port 80).

```bash
$ sudo apt install certbot python3-certbot-nginx
$ sudo certbot --nginx -d mon-serveur.fr -d www.mon-serveur.fr
```

certbot fait tout : il obtient le certificat, **modifie ta config nginx** pour servir en HTTPS, et met
en place la **redirection** automatique de HTTP vers HTTPS. Il te demande juste un e-mail (pour les
alertes d'expiration) et l'acceptation des conditions.

Vérifie ensuite :

```bash
$ curl -I https://mon-serveur.fr
HTTP/2 200
```

Le `https` et le `HTTP/2 200` confirment que le chiffrement est en place.

### Le renouvellement automatique

Un certificat Let's Encrypt n'est valable que **90 jours**. certbot installe automatiquement un
**timer systemd** (tu reconnais le mécanisme du chapitre 4) qui le renouvelle bien avant l'expiration.
Tu peux le vérifier et tester à blanc :

```bash
$ systemctl list-timers | grep certbot     # le timer de renouvellement existe
$ sudo certbot renew --dry-run             # simule un renouvellement sans rien changer
```

Si le `--dry-run` réussit, tu n'as plus jamais à t'en occuper : ton HTTPS se renouvelle tout seul.

> **À retenir** — Le HTTPS moderne est **gratuit et automatique** : `certbot --nginx`, puis le
> renouvellement se fait via un timer systemd. Un certificat dure 90 jours mais se renouvelle sans
> intervention. Il n'y a plus aucune raison de laisser un site en HTTP.

## Résumé

- Un **pare-feu** filtre le trafic ; la règle d'or est **refuser par défaut, n'ouvrir que le
  nécessaire**.
- **`ufw`** pilote `nftables` simplement. Ordre vital : autoriser **SSH avant** d'activer le pare-feu.
- **`fail2ban`** lit les journaux et **bannit** les IP qui multiplient les échecs de connexion.
- Le **HTTPS** chiffre le trafic via un **certificat** ; **Let's Encrypt** + **certbot** le rendent
  gratuit et automatique, avec **renouvellement** par timer systemd.
- Ces trois remparts (filtrer, bannir, chiffrer) sont le minimum pour un serveur exposé.

## Exercices

### Exercice 1 — L'ordre qui sauve

Tu administres un VPS uniquement par SSH. Tu veux activer `ufw` en refus par défaut. Dans quel ordre
exact lances-tu les commandes, et pourquoi cet ordre est-il critique ?

<details>
<summary>Voir le corrigé</summary>

La démarche : autoriser sa propre voie d'accès avant de tout fermer.

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH          # CRUCIAL : avant enable
sudo ufw enable
```

Si on activait le pare-feu (`enable`) avant d'autoriser SSH, la règle « deny incoming » couperait
immédiatement ta propre session SSH et tu ne pourrais plus te reconnecter. On ouvre toujours sa porte
d'entrée en premier.

</details>

### Exercice 2 — Régler fail2ban

Tu veux que `fail2ban` bannisse une IP pendant 24 heures après 3 échecs de connexion SSH en 5 minutes.
Quel fichier modifies-tu et avec quel contenu ?

<details>
<summary>Voir le corrigé</summary>

La démarche : surcharger la config dans `jail.local`, jamais dans `jail.conf`.

```ini
# /etc/fail2ban/jail.local
[sshd]
enabled = true
maxretry = 3
findtime = 5m
bantime = 24h
```

Puis `sudo systemctl restart fail2ban`. On modifie `jail.local` et non `jail.conf` car ce dernier est
fourni par le paquet et écrasé aux mises à jour. On vérifie avec `sudo fail2ban-client status sshd`.

</details>

### Exercice 3 — HTTPS de bout en bout

Liste les prérequis et la commande pour passer `mon-labo.fr` en HTTPS, puis explique comment tu
t'assures que le renouvellement automatique fonctionnera.

<details>
<summary>Voir le corrigé</summary>

La démarche : vérifier les prérequis, lancer certbot, tester le renouvellement.

Prérequis : `mon-labo.fr` pointe déjà vers le serveur (enregistrement DNS `A`), et les ports 80 et 443
sont ouverts dans `ufw` (et chez l'hébergeur). Commande :

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d mon-labo.fr
```

Vérification du renouvellement automatique :

```bash
sudo certbot renew --dry-run      # simule sans rien changer ; doit réussir
systemctl list-timers | grep certbot
```

Le `--dry-run` qui réussit garantit que le timer systemd renouvellera le certificat avant ses 90 jours
sans intervention.

</details>

## Quiz

**1.** Quelle est la bonne philosophie d'un pare-feu sur un serveur ?
- A. Tout ouvrir, puis fermer ce qui pose problème
- B. Refuser tout par défaut, n'ouvrir que le nécessaire
- C. Ouvrir tous les ports au-dessus de 1024

**2.** Pourquoi autoriser SSH avant d'activer `ufw` ?
- A. Pour aller plus vite
- B. Sinon le pare-feu coupe ta propre session SSH et tu te verrouilles dehors
- C. Parce que SSH refuse de démarrer sinon

**3.** Que fait `fail2ban` ?
- A. Il chiffre le trafic SSH
- B. Il bannit les IP qui multiplient les échecs de connexion, d'après les journaux
- C. Il remplace le pare-feu

**4.** Le certificat Let's Encrypt dure 90 jours. Comment se renouvelle-t-il ?
- A. À la main tous les 3 mois
- B. Automatiquement, via un timer systemd installé par certbot
- C. Il ne se renouvelle pas, on rachète

<details>
<summary>Voir les réponses</summary>

1. **B** — Refus par défaut, ouverture sélective : la base d'un pare-feu sûr.
2. **B** — Sans règle SSH préalable, activer le pare-feu coupe ta connexion.
3. **B** — `fail2ban` bannit les IP fautives en surveillant les journaux.
4. **B** — certbot installe un timer systemd qui renouvelle le certificat automatiquement.

</details>

## Projet fil rouge

Huitième jalon : **verrouille et chiffre ton serveur exposé**.

1. Mets en place `ufw` en refus par défaut, en ouvrant **OpenSSH, 80/tcp et 443/tcp** — dans le bon
   ordre, SSH avant `enable`. Vérifie avec `sudo ufw status verbose`.
2. Installe `fail2ban` et confirme qu'il protège SSH (`sudo fail2ban-client status sshd`).
3. Passe ton site en **HTTPS** avec `sudo certbot --nginx -d ton-domaine`, puis valide avec
   `curl -I https://ton-domaine` et `sudo certbot renew --dry-run`.
4. Note dans `notes-homelab.md` : les ports ouverts, les règles `fail2ban`, et la date d'émission du
   certificat.

Ton serveur est exposé **et** protégé, en HTTPS. Au chapitre suivant, on s'attaque à l'objectif phare :
monter **ton propre VPN** pour rejoindre ton réseau depuis l'extérieur.

---

[← Chapitre précédent](07-serveur-web.md) · [Sommaire](README.md) · [Chapitre suivant →](09-vpn-wireguard.md)
