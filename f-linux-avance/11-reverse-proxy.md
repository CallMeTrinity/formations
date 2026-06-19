# Reverse proxy et plusieurs services

[← Chapitre précédent](10-docker.md) · [Sommaire](README.md) · [Chapitre suivant →](12-supervision-maintenance.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **reverse proxy** et pourquoi il est indispensable pour héberger plusieurs
  services ;
- router plusieurs sites par leur **nom de domaine** vers différents services internes ;
- mettre en place un reverse proxy avec nginx, avec HTTPS pour chaque sous-domaine ;
- comprendre comment **un seul** point d'entrée (ports 80/443) dessert tout ton homelab.

Au chapitre précédent, tu as lancé un service nginx en conteneur sur le port 8080. Mais si tu héberges
trois services, ils ne peuvent pas tous écouter sur le port 443. Et personne ne veut taper
`mon-serveur.fr:8080`. Le **reverse proxy** résout ce problème : un seul gardien à l'entrée, qui
**aiguille** chaque visiteur vers le bon service.

## Le problème : un seul port 443 pour plusieurs services

Tu as plusieurs services sur ta machine : un site personnel, un cloud de fichiers, une interface
d'administration. Chacun écoute sur **son** port interne (8080, 8081, 3000…). Deux soucis :

- Internet attend le web sur les ports **80** (HTTP) et **443** (HTTPS). On ne peut pas demander aux
  visiteurs de connaître les numéros de port internes.
- **Un seul programme** peut écouter sur le port 443 à la fois. Tes trois services ne peuvent pas se le
  partager directement.

La solution est d'avoir **un seul programme** sur les ports 80/443 — le **reverse proxy** — qui reçoit
toutes les requêtes et les **redistribue** en interne selon le **nom de domaine** demandé.

## Qu'est-ce qu'un reverse proxy ?

Un **proxy** classique se place devant des **clients** et relaie leurs requêtes vers l'extérieur. Un
**reverse proxy** (proxy *inverse*) fait l'inverse : il se place devant tes **serveurs** et reçoit les
requêtes venues de l'extérieur pour les distribuer au bon service interne.

```text
   Internet                  Reverse proxy (nginx)            Services internes
                             ports 80 / 443
   site.fr ------\                                       ----> service web    (127.0.0.1:8080)
   cloud.fr -----+----> [ aiguille selon le nom ] -----+----> cloud           (127.0.0.1:8081)
   admin.fr ----/                                       ----> interface admin (127.0.0.1:3000)
```

Le reverse proxy regarde le **nom de domaine** (`site.fr`, `cloud.fr`…) de chaque requête et la
transmet au service interne correspondant. Pour le visiteur, tout passe par le même point d'entrée en
HTTPS ; en coulisses, le proxy répartit.

Il apporte d'autres bénéfices essentiels :

- **Le HTTPS centralisé** : le proxy gère **tous** les certificats Let's Encrypt à un seul endroit. Tes
  services internes peuvent rester en simple HTTP sur `127.0.0.1`, le proxy chiffre vers l'extérieur.
- **L'isolation** : tes services écoutent sur `127.0.0.1` (la machine elle-même) et **ne sont pas
  exposés** directement à Internet. Seul le proxy l'est.
- **Un point unique** pour les journaux, la limitation de débit, les en-têtes de sécurité.

> **À retenir** — Un reverse proxy est le **portier unique** de ton serveur : il occupe les ports 80 et
> 443, lit le nom de domaine demandé, et aiguille vers le bon service interne. C'est ce qui permet
> d'héberger plusieurs sites derrière une seule IP et un seul certificat par domaine.

## Mettre en place le reverse proxy avec nginx

nginx, que tu connais déjà comme serveur web, fait aussi très bien reverse proxy. Le principe : un
**server block** par nom de domaine, qui ne sert pas des fichiers mais **transmet** (`proxy_pass`) vers
un service interne.

Supposons deux services lancés via Docker (chapitre 10) : ton site sur `127.0.0.1:8080` et un cloud sur
`127.0.0.1:8081`. On crée un server block pour chacun.

```nginx
# /etc/nginx/sites-available/site
server {
    listen 80;
    server_name site.mon-domaine.fr;

    location / {
        proxy_pass http://127.0.0.1:8080;        # transmet au service interne
        proxy_set_header Host $host;             # conserve le nom demandé
        proxy_set_header X-Real-IP $remote_addr; # transmet l'IP réelle du visiteur
    }
}
```

```nginx
# /etc/nginx/sites-available/cloud
server {
    listen 80;
    server_name cloud.mon-domaine.fr;

    location / {
        proxy_pass http://127.0.0.1:8081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

- `server_name` distingue les deux : nginx choisit le bloc d'après le nom de domaine reçu.
- `proxy_pass http://127.0.0.1:8080` transmet la requête au service interne. `127.0.0.1` (la machine
  elle-même) garantit que ce service n'est joignable **que** par le proxy.
- Les `proxy_set_header` transmettent au service des informations utiles (le nom demandé, l'IP réelle
  du visiteur) qu'il perdrait sinon derrière le proxy.

On active et on recharge, comme au chapitre 7 :

```bash
$ sudo ln -s /etc/nginx/sites-available/site /etc/nginx/sites-enabled/
$ sudo ln -s /etc/nginx/sites-available/cloud /etc/nginx/sites-enabled/
$ sudo nginx -t && sudo systemctl reload nginx
```

### Les sous-domaines dans le DNS

Pour que `site.mon-domaine.fr` et `cloud.mon-domaine.fr` arrivent jusqu'à toi, il faut les déclarer
dans le DNS (chapitre 7). Le plus simple est un enregistrement **`A` joker** ou un enregistrement par
sous-domaine :

```text
Type   Nom                    Valeur
A      site.mon-domaine.fr     203.0.113.17
A      cloud.mon-domaine.fr    203.0.113.17
```

Les deux pointent vers la **même IP** (ton serveur) : c'est le reverse proxy, ensuite, qui les
distingue par leur nom. C'est toute l'astuce.

## HTTPS pour chaque service

Avec certbot (chapitre 8), tu sécurises chaque sous-domaine d'une commande. certbot détecte les server
blocks de reverse proxy et les bascule en HTTPS :

```bash
$ sudo certbot --nginx -d site.mon-domaine.fr -d cloud.mon-domaine.fr
```

Résultat : chaque service est accessible en `https://`, avec un certificat valide, **sans** que les
services internes n'aient à gérer le chiffrement. Le proxy déchiffre à l'entrée, parle en clair aux
services sur `127.0.0.1` (réseau local de la machine, donc sûr), et c'est tout.

> **À retenir** — Le reverse proxy + certbot, c'est le combo gagnant de l'auto-hébergement : un point
> d'entrée HTTPS unique, des services internes invisibles d'Internet, et l'ajout d'un nouveau service
> qui ne demande qu'un sous-domaine, un server block et une commande certbot.

## Ajouter un service : la routine

Une fois ce socle en place, héberger un nouveau service devient mécanique :

1. **Lancer** le service (souvent un conteneur Docker) sur un port interne libre, écoutant sur
   `127.0.0.1`.
2. **Créer** un enregistrement DNS pour son sous-domaine, pointant vers ton serveur.
3. **Écrire** un server block reverse proxy qui transmet ce sous-domaine vers le port interne.
4. **Activer** (`nginx -t && reload`) puis **chiffrer** (`certbot --nginx -d ...`).

C'est reproductible et rapide. Ton homelab devient une plateforme : tu y ajoutes des services au fil
de tes besoins, tous derrière le même portier sécurisé.

> **Astuce** — Pour l'**administration sensible** (interface d'un service, tableau de bord), combine
> ce chapitre avec le VPN du chapitre 9 : fais écouter le service sur l'adresse VPN du serveur plutôt
> que de créer un sous-domaine public. Il ne sera alors atteignable que par les pairs du tunnel —
> invisible pour le reste d'Internet.

## Résumé

- Un seul programme peut occuper le port 443 : c'est le rôle du **reverse proxy**, portier unique sur
  les ports 80/443.
- Le reverse proxy **aiguille** chaque requête vers le bon service interne d'après le **nom de
  domaine** (`server_name` + `proxy_pass`).
- Les services internes écoutent sur `127.0.0.1` : **invisibles d'Internet**, joignables seulement par
  le proxy.
- Le HTTPS est **centralisé** sur le proxy (certbot), une commande par domaine.
- Les sous-domaines pointent tous vers la **même IP** ; c'est le proxy qui les distingue.
- Ajouter un service = lancer le conteneur + DNS + server block + certbot. Pour l'admin sensible,
  passer plutôt par le **VPN**.

## Exercices

### Exercice 1 — Pourquoi un reverse proxy ?

Tu héberges deux sites web sur la même machine et tu veux qu'ils soient tous deux accessibles en HTTPS
sur le port 443. Pourquoi ne peux-tu pas simplement faire écouter les deux services sur le port 443, et
qu'est-ce qui résout le problème ?

<details>
<summary>Voir le corrigé</summary>

La démarche : un seul programme par port, d'où le besoin d'un répartiteur.

Un seul programme peut écouter sur le port 443 à la fois : deux services ne peuvent pas se le partager.
La solution est un **reverse proxy** : lui seul occupe le port 443, et il aiguille chaque requête vers
le bon service interne (sur `127.0.0.1:8080`, `127.0.0.1:8081`…) d'après le **nom de domaine** demandé.
Les deux sites sont alors servis en HTTPS via un point d'entrée unique.

</details>

### Exercice 2 — Router un nouveau sous-domaine

Tu lances un service de notes sur `127.0.0.1:5000` et tu veux le rendre accessible en HTTPS sur
`notes.mon-domaine.fr`. Liste les étapes.

<details>
<summary>Voir le corrigé</summary>

La démarche : DNS, server block, activation, HTTPS.

1. **DNS** : créer un enregistrement `A` `notes.mon-domaine.fr` → IP du serveur.
2. **Server block** dans `/etc/nginx/sites-available/notes` :

   ```nginx
   server {
       listen 80;
       server_name notes.mon-domaine.fr;
       location / {
           proxy_pass http://127.0.0.1:5000;
           proxy_set_header Host $host;
       }
   }
   ```

3. **Activer** : `sudo ln -s .../notes /etc/nginx/sites-enabled/` puis `sudo nginx -t && sudo
   systemctl reload nginx`.
4. **HTTPS** : `sudo certbot --nginx -d notes.mon-domaine.fr`.

</details>

### Exercice 3 — Exposer ou pas ?

Tu installes l'interface d'administration d'un service. Faut-il lui créer un sous-domaine public via le
reverse proxy ? Quelle alternative plus sûre as-tu vue dans la formation ?

<details>
<summary>Voir le corrigé</summary>

La démarche : limiter la surface exposée pour ce qui est sensible.

Une interface d'administration n'a pas besoin d'être visible de tout Internet. Plutôt qu'un sous-domaine
public, l'alternative plus sûre est de la rendre accessible **uniquement par le VPN** (chapitre 9) :
faire écouter le service sur l'adresse VPN du serveur (`10.8.0.1`) ou ne pas la router dans le proxy
public. Seuls les pairs du tunnel WireGuard y accèdent ; pour le reste d'Internet, elle n'existe pas.

</details>

## Quiz

**1.** Quel est le rôle d'un reverse proxy ?
- A. Relayer les requêtes des clients vers l'extérieur
- B. Recevoir les requêtes externes et les aiguiller vers le bon service interne
- C. Remplacer le pare-feu

**2.** Comment le reverse proxy choisit-il vers quel service envoyer une requête ?
- A. Au hasard
- B. D'après le nom de domaine demandé (`server_name`)
- C. D'après l'heure

**3.** Pourquoi les services internes écoutent-ils sur `127.0.0.1` ?
- A. Pour être plus rapides
- B. Pour n'être joignables que par le proxy, pas directement depuis Internet
- C. Parce que c'est obligatoire pour Docker

**4.** Plusieurs sous-domaines (`site.fr`, `cloud.fr`) pointent vers la même IP. Qui les distingue ?
- A. Le DNS
- B. Le reverse proxy, d'après le nom demandé
- C. Le navigateur

<details>
<summary>Voir les réponses</summary>

1. **B** — Le reverse proxy reçoit l'externe et le distribue vers les services internes.
2. **B** — Il s'appuie sur le `server_name` (le nom de domaine) de la requête.
3. **B** — Écouter sur `127.0.0.1` rend les services invisibles d'Internet, seul le proxy y accède.
4. **B** — Les noms pointent vers la même IP ; c'est le proxy qui les aiguille.

</details>

## Projet fil rouge

Onzième jalon : **mets ton homelab derrière un reverse proxy**.

1. Configure nginx en **reverse proxy** devant tes services en conteneur : un server block par
   sous-domaine, `proxy_pass` vers le port interne (`127.0.0.1:...`).
2. Crée les enregistrements DNS des sous-domaines (tous vers ton IP) et chiffre-les avec
   `sudo certbot --nginx -d ...`.
3. Vérifie que chaque service répond en `https://son-sous-domaine` et que les ports internes ne sont
   **pas** accessibles directement de l'extérieur.
4. Pour ton interface d'administration, applique la bonne pratique : accès **par le VPN uniquement**.
5. Mets à jour `notes-homelab.md` avec la carte de tes services, sous-domaines et ports internes.

Ton homelab est une vraie plateforme multi-services, sécurisée et propre. Au dernier chapitre
technique, on apprend à la **garder en vie** : surveiller, sauvegarder, maintenir.

---

[← Chapitre précédent](10-docker.md) · [Sommaire](README.md) · [Chapitre suivant →](12-supervision-maintenance.md)
