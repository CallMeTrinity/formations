# Déployer avec Docker

[← Chapitre précédent](09-vpn-wireguard.md) · [Sommaire](README.md) · [Chapitre suivant →](11-reverse-proxy.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- expliquer ce qu'est un **conteneur** et en quoi il diffère d'une machine virtuelle ;
- distinguer **image**, **conteneur**, **registre** et **volume** ;
- lancer, lister, arrêter et inspecter des conteneurs avec la commande `docker` ;
- décrire un déploiement complet et reproductible avec **Docker Compose**.

Jusqu'ici, tu installais les services « à la main » : un paquet, une config, un service systemd. Ça
marche, mais c'est fastidieux et difficile à reproduire. **Docker** propose une autre approche :
empaqueter une application **avec tout ce dont elle a besoin** dans un **conteneur** qui tourne à
l'identique partout. C'est devenu la façon standard de déployer des services auto-hébergés.

## Conteneur contre machine virtuelle

Tu connais la **machine virtuelle** (chapitre 1) : un ordinateur entier simulé, avec son propre noyau
et son propre système. C'est puissant mais **lourd** : chaque VM embarque un système complet et prend
des giga-octets.

Un **conteneur** est plus léger. Il **n'embarque pas** de système d'exploitation complet : il
**partage le noyau** de la machine hôte et n'emporte que l'application et ses dépendances. Résultat :
un conteneur démarre en une fraction de seconde et pèse souvent quelques dizaines de méga-octets.

```text
   Machines virtuelles                  Conteneurs
   +----------+ +----------+            +--------+ +--------+ +--------+
   | appli    | | appli    |            | appli  | | appli  | | appli  |
   | + systè- | | + systè- |            | + dép. | | + dép. | | + dép. |
   |   me     | |   me     |            +--------+ +--------+ +--------+
   | complet  | | complet  |            |   moteur Docker             |
   +----------+ +----------+            +-----------------------------+
   |   hyperviseur         |            |   noyau de l'hôte (partagé) |
   +-----------------------+            +-----------------------------+
```

L'idée clé : un conteneur **isole** une application (ses fichiers, ses dépendances, son réseau) sans
le coût d'un système complet. Tu peux en faire tourner des dizaines sur une machine modeste.

> **À retenir** — Une VM simule une machine entière (lourd, noyau propre) ; un conteneur isole une
> application en partageant le noyau de l'hôte (léger, rapide). Pour déployer des services, le
> conteneur est presque toujours le bon choix.

## Le vocabulaire : image, conteneur, registre, volume

Quatre mots à bien distinguer, car ils reviennent sans cesse :

- une **image** est un **modèle** figé, en lecture seule : l'application + ses dépendances,
  empaquetées. C'est la « recette ».
- un **conteneur** est une **instance en cours d'exécution** d'une image. Une même image peut donner
  plusieurs conteneurs. C'est le « plat cuisiné à partir de la recette ».
- un **registre** (*registry*) est un dépôt d'images en ligne. Le plus connu est **Docker Hub**, où
  l'on trouve des images prêtes à l'emploi pour la plupart des logiciels (nginx, bases de données…).
  C'est l'équivalent des dépôts de paquets du chapitre 3, mais pour les images.
- un **volume** est un espace de stockage **persistant**, branché sur un conteneur. Crucial : un
  conteneur est **jetable**, ses données disparaissent quand on le supprime. Ce qui doit survivre (une
  base de données, des fichiers) se range dans un **volume**.

> **À retenir** — Image = modèle figé ; conteneur = instance qui tourne ; registre = bibliothèque
> d'images ; volume = stockage qui survit au conteneur. Confondre image et conteneur est l'erreur de
> débutant la plus fréquente.

## Installer Docker et lancer un premier conteneur

Docker s'installe via un dépôt officiel (méthode du chapitre 3). La façon la plus simple et recommandée
est le script officiel :

```bash
$ curl -fsSL https://get.docker.com | sudo sh     # installe Docker depuis le script officiel
$ sudo docker run hello-world                      # teste l'installation
```

`docker run hello-world` télécharge une petite image de test depuis Docker Hub, la lance, affiche un
message, et s'arrête. Si tu vois « Hello from Docker! », tout marche.

> **Astuce** — Par défaut, `docker` exige `sudo`. Pour t'éviter de le taper à chaque fois, ajoute ton
> utilisateur au groupe `docker` : `sudo usermod -aG docker $USER` (souviens-toi du `-aG` du chapitre
> 5), puis reconnecte-toi. Attention : ce groupe donne des droits équivalents à `root`, à n'accorder
> qu'à toi-même.

### Lancer un vrai service : nginx en conteneur

Reprenons nginx (chapitre 7), mais en conteneur cette fois :

```bash
$ docker run -d --name web -p 8080:80 nginx
```

Décortiquons cette commande, car ses options reviennent toujours :

- `-d` (*detached*) : lance le conteneur en arrière-plan (comme un service).
- `--name web` : donne un nom lisible au conteneur (sinon Docker en invente un).
- `-p 8080:80` : **publie** le port. Le port `80` **dans** le conteneur devient accessible sur le port
  `8080` de l'**hôte**. La forme est toujours `hôte:conteneur`.
- `nginx` : l'image à utiliser (téléchargée depuis Docker Hub si absente).

Teste : `curl http://localhost:8080` doit renvoyer la page d'accueil d'nginx. Tu viens de lancer un
serveur web **sans rien installer** sur le système hôte.

## Piloter les conteneurs

Les commandes de base, à connaître par cœur :

```bash
$ docker ps                    # les conteneurs en cours d'exécution
$ docker ps -a                 # tous, y compris arrêtés
$ docker stop web              # arrête le conteneur "web"
$ docker start web             # le redémarre
$ docker rm web                # le supprime (doit être arrêté)
$ docker logs web              # affiche ses journaux (comme journalctl pour un service)
$ docker logs -f web           # suit les journaux en direct
$ docker exec -it web bash     # ouvre un shell DANS le conteneur (pour inspecter)
$ docker images                # les images présentes localement
$ docker pull nginx            # télécharge/met à jour une image
```

`docker exec -it web bash` est précieux pour le diagnostic : il t'ouvre un terminal **à l'intérieur**
du conteneur, comme si tu te connectais à une petite machine. `-it` rend la session interactive.

> **Attention** — Supprimer un conteneur (`docker rm`) **détruit** tout ce qu'il contenait qui n'était
> pas dans un volume. C'est voulu : les conteneurs sont conçus pour être jetables et recréés à
> l'identique. Ne stocke jamais de données précieuses « dans » un conteneur — utilise un volume.

### Persister des données avec un volume

Pour qu'un fichier survive à la suppression du conteneur, on monte un dossier de l'hôte dans le
conteneur avec `-v hôte:conteneur` :

```bash
$ docker run -d --name web -p 8080:80 \
    -v /var/www/mon-site:/usr/share/nginx/html:ro \
    nginx
```

Ici, le dossier `/var/www/mon-site` de l'hôte est monté en lecture seule (`:ro`) là où nginx cherche
ses pages. Tu modifies tes fichiers sur l'hôte, le conteneur les sert ; tu peux détruire et recréer le
conteneur sans rien perdre.

## Docker Compose : décrire un déploiement complet

Lancer un service avec une longue commande `docker run`, c'est bien pour tester. Mais un vrai
déploiement a plusieurs conteneurs (une application + sa base de données), des volumes, des ports, des
variables… Tout retaper à la main n'est ni reproductible ni documenté.

**Docker Compose** résout ça : tu décris **toute** ton installation dans **un seul fichier** YAML,
`compose.yaml`, et tu la lances d'une commande. C'est l'aboutissement de la démarche : un déploiement
**déclaratif** et **versionnable**.

```yaml
# compose.yaml
services:
  web:
    image: nginx
    ports:
      - "8080:80"
    volumes:
      - ./site:/usr/share/nginx/html:ro
    restart: unless-stopped
```

Chaque clé correspond à une option `docker run` que tu connais déjà : `image`, `ports` (`hôte:conteneur`),
`volumes`, et `restart: unless-stopped` (redémarre le conteneur automatiquement, comme `Restart=` dans
systemd). On lance et on gère l'ensemble :

```bash
$ docker compose up -d        # démarre tous les services en arrière-plan
$ docker compose ps           # leur état
$ docker compose logs -f      # leurs journaux
$ docker compose down         # arrête et supprime les conteneurs (les volumes restent)
```

L'énorme avantage : ce fichier `compose.yaml`, versionné avec `git`, **est** la documentation exécutable
de ton déploiement. Sur une nouvelle machine, `docker compose up -d` recrée tout à l'identique. C'est
exactement la reproductibilité qu'on cherchait depuis le début.

> **À retenir** — Docker Compose décrit un déploiement entier (services, ports, volumes) dans un
> fichier `compose.yaml` versionnable. `docker compose up -d` le déploie, `docker compose down` le
> défait. C'est la base d'un auto-hébergement propre et reproductible.

## Résumé

- Un **conteneur** isole une application en **partageant le noyau** de l'hôte : bien plus léger qu'une
  VM.
- **Image** (modèle figé) ≠ **conteneur** (instance qui tourne) ; les images viennent d'un **registre**
  (Docker Hub) ; les données qui doivent survivre vont dans un **volume**.
- `docker run -d --name x -p hôte:conteneur image` lance un service ; `docker ps`, `logs`, `exec`,
  `stop`, `rm` le pilotent.
- Un conteneur est **jetable** : on ne stocke jamais de données précieuses dedans sans volume.
- **Docker Compose** (`compose.yaml` + `docker compose up -d`) décrit et déploie une installation
  complète de façon **reproductible et versionnable**.

## Exercices

### Exercice 1 — Image ou conteneur ?

Explique, avec tes mots, la différence entre l'image `nginx` et un conteneur lancé par `docker run
--name web nginx`. Peut-on avoir plusieurs conteneurs à partir d'une seule image ?

<details>
<summary>Voir le corrigé</summary>

La démarche : modèle figé contre instance qui tourne.

L'**image** `nginx` est le modèle en lecture seule (nginx + ses dépendances). Le **conteneur** `web`
est une **instance** lancée à partir de cette image, qui s'exécute réellement. Oui, on peut lancer
plusieurs conteneurs depuis la même image (`docker run --name web1 nginx`, `--name web2 nginx`…) :
chacun est indépendant, comme plusieurs plats cuisinés depuis une même recette.

</details>

### Exercice 2 — Publier le bon port

Tu lances une application qui écoute sur le port `3000` à l'intérieur de son conteneur, et tu veux y
accéder depuis l'hôte sur le port `9000`. Écris l'option `-p` correcte. Quelle URL testes-tu ensuite ?

<details>
<summary>Voir le corrigé</summary>

La démarche : la forme est toujours `hôte:conteneur`.

```bash
docker run -d -p 9000:3000 mon-appli
```

Le port `9000` de l'hôte est relié au port `3000` du conteneur. On teste ensuite avec
`curl http://localhost:9000`. Inverser l'ordre (`-p 3000:9000`) est l'erreur classique : on publierait
le mauvais port.

</details>

### Exercice 3 — Survivre à la suppression

Tu héberges une base de données en conteneur. Pourquoi est-il dangereux de stocker ses données « dans »
le conteneur, et que mets-tu en place pour les protéger ?

<details>
<summary>Voir le corrigé</summary>

La démarche : un conteneur est jetable, ses données internes aussi.

Si les données vivent dans le conteneur, un `docker rm` (volontaire ou lors d'une mise à jour de
l'image) les **détruit définitivement**. La protection est un **volume** : on monte un dossier de
l'hôte (ou un volume nommé Docker) là où la base écrit ses fichiers, par exemple
`-v db_data:/var/lib/mysql`. Les données vivent alors **hors** du conteneur et survivent à sa
suppression et à sa recréation.

</details>

## Quiz

**1.** Qu'est-ce qui distingue un conteneur d'une machine virtuelle ?
- A. Le conteneur partage le noyau de l'hôte ; il est donc bien plus léger
- B. Le conteneur est plus lourd
- C. Aucune différence

**2.** Dans `docker run -p 8080:80`, que signifie `8080:80` ?
- A. Le port 8080 du conteneur va vers le 80 de l'hôte
- B. Le port 8080 de l'hôte va vers le 80 du conteneur
- C. Deux conteneurs sur les ports 8080 et 80

**3.** Où stocker les données qui doivent survivre à la suppression d'un conteneur ?
- A. Dans le conteneur lui-même
- B. Dans un volume
- C. Dans l'image

**4.** À quoi sert un fichier `compose.yaml` ?
- A. À décrire un déploiement complet (services, ports, volumes) de façon reproductible
- B. À remplacer le noyau Linux
- C. À configurer le pare-feu

<details>
<summary>Voir les réponses</summary>

1. **A** — Le conteneur partage le noyau de l'hôte, d'où sa légèreté face à une VM.
2. **B** — La forme est `hôte:conteneur` : le 8080 de l'hôte mène au 80 du conteneur.
3. **B** — Les données persistantes vont dans un volume, hors du conteneur jetable.
4. **A** — `compose.yaml` décrit tout le déploiement de manière déclarative et versionnable.

</details>

## Projet fil rouge

Dixième jalon : **redéploie un service en conteneur avec Compose**.

1. Installe Docker sur ton serveur et lance un `hello-world` pour valider.
2. Écris un `compose.yaml` qui sert ton site (image `nginx`, ton dossier monté en volume en lecture
   seule, un port publié). Lance-le avec `docker compose up -d` et teste avec `curl`.
3. Versionne ce `compose.yaml` avec `git` (par exemple dans un dossier `homelab/`) : c'est désormais
   la **recette reproductible** de ton déploiement.
4. Note dans `notes-homelab.md` les services que tu passes en conteneur et les ports qu'ils publient.

Tu déploies maintenant des services proprement et de façon reproductible. Au chapitre suivant, on les
range tous derrière un **reverse proxy** pour les exposer sur un même domaine, en HTTPS.

---

[← Chapitre précédent](09-vpn-wireguard.md) · [Sommaire](README.md) · [Chapitre suivant →](11-reverse-proxy.md)
