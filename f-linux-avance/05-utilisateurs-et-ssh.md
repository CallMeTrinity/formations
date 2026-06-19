# Utilisateurs, droits et accès SSH

[← Chapitre précédent](04-systemd.md) · [Sommaire](README.md) · [Chapitre suivant →](06-reseau.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- créer des **utilisateurs** et des **groupes**, et accorder des droits d'administration via `sudo` ;
- te connecter à un serveur distant avec **SSH** et comprendre comment ça marche ;
- mettre en place l'**authentification par clé** (paire de clés publique/privée) ;
- **durcir** la configuration SSH pour fermer les portes les plus visées par les attaques.

Ton serveur sera bientôt joignable depuis Internet. La toute première question de sécurité est :
**qui peut se connecter, et comment ?** Ce chapitre pose les fondations : des comptes bien gérés et un
accès SSH solide. C'est l'investissement de sécurité le plus rentable que tu feras.

## Utilisateurs et groupes

Linux est **multi-utilisateurs** : plusieurs comptes peuvent coexister, chacun avec ses fichiers et
ses droits. Tu connais déjà les permissions de fichiers (lecture/écriture/exécution). Ici, on regarde
les **comptes** eux-mêmes.

- Chaque utilisateur a un nom, un numéro (**UID**), un dossier personnel (`/home/nom`) et un shell.
- L'utilisateur **`root`** (UID 0) est l'administrateur tout-puissant : aucune permission ne le bloque.
- Un **groupe** rassemble des utilisateurs pour leur donner des droits en commun (par exemple le
  groupe `sudo` autorise ses membres à utiliser `sudo`).

### Créer et gérer un utilisateur

```bash
$ sudo adduser alice            # crée l'utilisateur alice (interactif : mot de passe, etc.)
$ sudo usermod -aG sudo alice   # ajoute alice au groupe sudo (-aG = append to Group)
$ groups alice                  # liste les groupes d'alice
$ sudo deluser alice            # supprime l'utilisateur
$ id alice                      # affiche UID, GID et groupes d'alice
```

`adduser` (script convivial de Debian/Ubuntu) crée le compte, son dossier personnel et demande un mot
de passe. `usermod -aG sudo alice` est la commande clé : elle rend alice **administratrice** en
l'ajoutant au groupe `sudo`. Le `-a` (*append*) est crucial : sans lui, `usermod -G` **remplacerait**
tous les groupes d'alice au lieu d'ajouter.

> **Attention** — Oublier le `-a` dans `usermod -aG` est un piège classique : `sudo usermod -G sudo
> alice` retire alice de **tous ses autres groupes** pour ne garder que `sudo`. Toujours `-aG`.

### Ne pas travailler en root

Sur un VPS fraîchement loué, tu es souvent connecté en `root`. **C'est dangereux** : tout s'exécute
avec les pleins pouvoirs, sans filet. La bonne pratique universelle :

1. créer un utilisateur normal pour toi ;
2. l'ajouter au groupe `sudo` ;
3. te connecter avec **lui** au quotidien, et n'invoquer `root` que ponctuellement via `sudo`.

```bash
# (connecté en root sur un VPS neuf)
adduser antonin
usermod -aG sudo antonin
# désormais on se reconnecte en tant qu'antonin, et on utilise sudo au besoin
```

> **À retenir** — `root` ne doit pas être ton compte de tous les jours. Un compte normal + `sudo`, ça
> te force à réfléchir avant chaque action privilégiée et limite les dégâts d'une fausse manœuvre.

## Se connecter à distance avec SSH

**SSH** (*Secure Shell*) est le protocole standard pour ouvrir un terminal sur une machine distante,
de façon **chiffrée**. C'est la porte d'entrée de tout serveur. Côté ton ordinateur, tu utilises la
commande `ssh` ; côté serveur, c'est le service `ssh`/`sshd` (vu au chapitre 4) qui écoute.

```bash
$ ssh antonin@203.0.113.42        # se connecter en tant qu'antonin sur cette IP
$ ssh antonin@mon-serveur.fr      # ou via un nom de domaine
$ ssh antonin@203.0.113.42 -p 2222   # si SSH écoute sur un autre port que 22
```

À la première connexion, SSH te montre l'**empreinte** de la machine et te demande de confirmer. C'est
normal : il enregistre l'identité du serveur pour détecter, plus tard, qu'on ne te redirige pas vers
un imposteur.

```text
The authenticity of host '203.0.113.42' can't be established.
ED25519 key fingerprint is SHA256:xxxxxxxx...
Are you sure you want to continue connecting (yes/no)?
```

Par défaut, SSH te demande ensuite le **mot de passe** du compte distant. Ça marche, mais c'est le
point faible : un mot de passe peut être deviné, et les serveurs exposés subissent des milliers de
tentatives par jour. La solution, bien plus sûre, est l'**authentification par clé**.

## L'authentification par clé

Le principe repose sur une **paire de clés** : une **clé privée** que tu gardes précieusement sur ton
ordinateur, et une **clé publique** que tu déposes sur le serveur. Ce qui est chiffré par l'une se
vérifie avec l'autre. Le serveur peut prouver que tu détiens la clé privée **sans qu'elle ne quitte
jamais ta machine**.

```text
   Ton ordinateur                     Le serveur
   +----------------+                 +------------------------+
   | clé PRIVÉE     |   on dépose -->  | clé PUBLIQUE           |
   | (jamais        |                 | (dans                  |
   |  partagée)     |                 |  ~/.ssh/authorized_keys)|
   +----------------+                 +------------------------+
```

### 1. Générer ta paire de clés (sur TON ordinateur)

```bash
$ ssh-keygen -t ed25519 -C "antonin@mon-portable"
```

- `-t ed25519` choisit un algorithme moderne, sûr et rapide (préféré à l'ancien RSA).
- `-C` ajoute un commentaire pour t'y retrouver (souvent ton e-mail ou le nom de la machine).

L'outil crée deux fichiers dans `~/.ssh/` : `id_ed25519` (la clé **privée**, à ne **jamais** partager)
et `id_ed25519.pub` (la clé **publique**, faite pour être copiée). Il te propose aussi une
*passphrase* : une phrase secrète qui chiffre ta clé privée sur le disque. **Mets-en une** : si ton
ordinateur est volé, la clé reste inutilisable.

### 2. Déposer ta clé publique sur le serveur

Le plus simple, depuis ton ordinateur :

```bash
$ ssh-copy-id antonin@203.0.113.42
```

Cette commande ajoute ta clé publique dans le fichier `~/.ssh/authorized_keys` du compte distant
(elle te demande ton mot de passe une dernière fois pour ça). Désormais :

```bash
$ ssh antonin@203.0.113.42        # connexion sans mot de passe, via ta clé
```

> **À retenir** — La clé **privée** ne quitte jamais ton ordinateur. Tu peux déposer ta clé **publique**
> sur autant de serveurs que tu veux, sans risque. C'est ce qui rend les clés à la fois plus sûres et
> plus pratiques que les mots de passe.

## Durcir la configuration SSH

Une fois que tu te connectes par clé, tu peux **fermer** les portes que les attaquants visent. La
configuration du serveur SSH est dans `/etc/ssh/sshd_config`. On l'édite avec `sudo`, puis on
redémarre le service.

Les trois réglages de durcissement essentiels :

```ini
# /etc/ssh/sshd_config (extraits)
PermitRootLogin no              # interdit la connexion directe en root
PasswordAuthentication no       # interdit les mots de passe : clés uniquement
PubkeyAuthentication yes        # autorise l'authentification par clé
```

- `PermitRootLogin no` : on ne se connecte plus jamais directement en `root` ; on passe par un compte
  normal puis `sudo`. Ça supprime la cible numéro un des attaques.
- `PasswordAuthentication no` : plus aucun mot de passe accepté. Les milliers de tentatives
  automatiques de devinette deviennent **inutiles**.
- Changer le port d'écoute (`Port 2222`) réduit le bruit des scans automatiques. Ce n'est pas une
  vraie protection (un scan trouve le nouveau port), mais ça allège les journaux.

Après modification, on **teste la config** puis on redémarre :

```bash
$ sudo sshd -t                  # vérifie la syntaxe du fichier (rien = OK)
$ sudo systemctl restart ssh
```

> **Attention** — Avant de couper `PasswordAuthentication`, **vérifie que ta connexion par clé
> fonctionne** dans un second terminal. Sinon, si la clé n'est pas en place, tu te verrouilles dehors.
> La règle d'or : garde toujours une session SSH ouverte pendant que tu modifies la config SSH, pour
> pouvoir annuler en cas de problème.

Ces réglages, combinés au pare-feu et à `fail2ban` (chapitre 8), constituent le socle de sécurité d'un
serveur exposé.

## Résumé

- Linux est multi-utilisateurs ; `root` (UID 0) est tout-puissant. On crée un compte normal et on lui
  donne `sudo` via `usermod -aG sudo <user>` (le `-a` est obligatoire).
- **Ne travaille pas en `root`** : compte normal + `sudo` au cas par cas.
- **SSH** ouvre un terminal distant chiffré : `ssh utilisateur@adresse`.
- L'**authentification par clé** (`ssh-keygen` puis `ssh-copy-id`) remplace le mot de passe : la clé
  privée reste chez toi, la publique va sur le serveur.
- **Durcir** SSH dans `/etc/ssh/sshd_config` : `PermitRootLogin no`, `PasswordAuthentication no`. On
  teste avec `sshd -t` et on garde une session ouverte par sécurité.

## Exercices

### Exercice 1 — Donner les droits d'admin sans tout casser

Tu viens de créer l'utilisateur `bob`. Tu veux qu'il puisse utiliser `sudo`, **sans** lui retirer ses
autres groupes. Quelle commande exacte, et quel piège évites-tu ?

<details>
<summary>Voir le corrigé</summary>

La démarche : ajouter au groupe `sudo` en mode « append ».

```bash
sudo usermod -aG sudo bob
```

Le piège évité : oublier le `-a`. `sudo usermod -G sudo bob` **remplacerait** tous les groupes de bob
par le seul groupe `sudo`, le retirant de tout le reste. On vérifie ensuite avec `groups bob`.

</details>

### Exercice 2 — Mettre en place l'accès par clé

Décris les étapes, dans l'ordre, pour te connecter à un nouveau VPS **sans mot de passe**, depuis ton
ordinateur où tu n'as encore aucune clé.

<details>
<summary>Voir le corrigé</summary>

La démarche : générer la paire, déposer la publique, tester.

```bash
# sur ton ordinateur
ssh-keygen -t ed25519 -C "moi@portable"   # génère la paire (avec une passphrase)
ssh-copy-id antonin@IP_DU_VPS             # dépose la clé publique (demande le mot de passe)
ssh antonin@IP_DU_VPS                     # se connecte désormais via la clé
```

La clé privée (`~/.ssh/id_ed25519`) reste sur ton ordinateur ; seule la publique
(`id_ed25519.pub`) est copiée sur le serveur, dans `~/.ssh/authorized_keys`.

</details>

### Exercice 3 — Verrouiller sans se verrouiller dehors

Tu veux interdire la connexion par mot de passe sur ton serveur. Quelle est la précaution
indispensable à prendre **avant** d'appliquer ce changement ?

<details>
<summary>Voir le corrigé</summary>

La démarche : ne jamais couper le mot de passe sans avoir validé une autre voie d'entrée.

La précaution : **vérifier que la connexion par clé fonctionne** (te connecter une fois sans mot de
passe), et **garder une session SSH ouverte** pendant la modification. Ensuite seulement, mettre
`PasswordAuthentication no`, tester la config avec `sudo sshd -t`, puis `sudo systemctl restart ssh`.
Si quelque chose tourne mal, la session restée ouverte permet de remettre le réglage. Sans clé
fonctionnelle, couper les mots de passe = se verrouiller dehors définitivement.

</details>

## Quiz

**1.** Pourquoi ne pas utiliser `root` comme compte de tous les jours ?
- A. Parce que `root` est plus lent
- B. Parce qu'une erreur en `root` agit sans filet, avec les pleins pouvoirs
- C. Parce que `root` ne peut pas utiliser SSH

**2.** Dans l'authentification par clé, que dépose-t-on sur le serveur ?
- A. La clé privée
- B. La clé publique
- C. Le mot de passe chiffré

**3.** Que fait `sudo usermod -aG sudo alice` ?
- A. Supprime alice du groupe sudo
- B. Ajoute alice au groupe sudo sans toucher à ses autres groupes
- C. Remplace tous les groupes d'alice par sudo

**4.** Quel réglage de `sshd_config` bloque les milliers de tentatives de devinette de mot de passe ?
- A. `PasswordAuthentication no`
- B. `Port 22`
- C. `PermitRootLogin yes`

<details>
<summary>Voir les réponses</summary>

1. **B** — En `root`, la moindre faute s'exécute avec tous les pouvoirs, sans garde-fou.
2. **B** — On dépose la clé **publique** ; la privée ne quitte jamais ton ordinateur.
3. **B** — `-aG` ajoute au groupe sans effacer les autres ; sans `-a`, on les remplacerait.
4. **A** — Interdire l'authentification par mot de passe rend les attaques par devinette inutiles.

</details>

## Projet fil rouge

Cinquième jalon : **sécurise l'accès à ton serveur**. C'est le jalon le plus important pour la suite,
car ton serveur va bientôt être exposé.

1. Crée ton compte administrateur normal et donne-lui `sudo` :

   ```bash
   sudo adduser antonin
   sudo usermod -aG sudo antonin
   ```

2. Depuis ton ordinateur, génère une paire de clés et dépose la publique sur le serveur :

   ```bash
   ssh-keygen -t ed25519 -C "antonin@portable"
   ssh-copy-id antonin@IP_DU_SERVEUR
   ```

3. Vérifie que tu te connectes **sans mot de passe**, puis durcis `/etc/ssh/sshd_config` :
   `PermitRootLogin no` et `PasswordAuthentication no`. Teste (`sudo sshd -t`), redémarre
   (`sudo systemctl restart ssh`), en gardant une session de secours ouverte.
4. Note dans `notes-homelab.md` : le nom de ton compte admin, le port SSH, et où se trouve ta clé.

Ton serveur n'accepte plus que toi, par clé. Au chapitre suivant, on plonge dans le **réseau** pour
comprendre comment il sera joignable depuis l'extérieur.

---

[← Chapitre précédent](04-systemd.md) · [Sommaire](README.md) · [Chapitre suivant →](06-reseau.md)
