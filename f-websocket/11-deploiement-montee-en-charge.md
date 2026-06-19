# Déploiement et montée en charge

[← Chapitre précédent](10-securite.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)

## Objectifs

À la fin de ce chapitre, tu sauras :

- placer un **reverse proxy** (nginx) devant ton serveur WebSocket et y gérer le TLS ;
- garder ton serveur Node en vie en production avec un gestionnaire de processus ;
- comprendre pourquoi un *broadcast* casse dès qu'on a **plusieurs instances** ;
- résoudre ce problème avec **Redis pub/sub**.

## Du « ça marche sur ma machine » à la production

En local, on lance `node server.js` et tout va bien. En production, il faut répondre à plusieurs
questions : comment servir le TLS (`wss://`) ? comment garder le serveur en vie s'il plante ? comment
encaisser plus d'utilisateurs qu'une seule instance ne peut en tenir ? Ce chapitre y répond.

## Le reverse proxy : nginx devant Node

Un **reverse proxy** est un serveur placé **devant** ton application : il reçoit les requêtes des
clients et les transmet à ton serveur Node. C'est lui qui, en pratique, gère :

- le **TLS** (les certificats `wss://`/HTTPS), pour ne pas le faire dans Node ;
- le **routage** : servir les fichiers statiques (la page du chat) et transmettre `/ws` au WebSocket ;
- la **robustesse** : limites, journaux, plusieurs instances derrière (voir plus bas).

```text
Client ──wss://chat.exemple.com──►  nginx (TLS, port 443)
                                      ├── /          → fichiers statiques (la page)
                                      └── /ws        → ws://localhost:8080 (Node)
```

Le point crucial : pour qu'une connexion WebSocket traverse nginx, il faut **explicitement** lui dire de
transmettre les en-têtes du *handshake* `Upgrade` (chapitre 2). Sans ça, nginx traite la requête comme
du HTTP normal et la connexion WebSocket échoue.

```nginx
server {
    listen 443 ssl;
    server_name chat.exemple.com;

    # Certificats TLS (par exemple obtenus via Let's Encrypt).
    ssl_certificate     /etc/letsencrypt/live/chat.exemple.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/chat.exemple.com/privkey.pem;

    # Les fichiers statiques (la page du chat).
    location / {
        root /var/www/chat/public;
    }

    # La connexion WebSocket : on transmet à Node sur le port 8080.
    location /ws {
        proxy_pass http://localhost:8080;

        # Ces trois lignes sont INDISPENSABLES pour le handshake WebSocket.
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        # Transmettre l'adresse réelle du client et garder la connexion ouverte longtemps.
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }
}
```

> **Attention** — Si ton WebSocket « ne se connecte pas en production » alors qu'il marche en local, le
> coupable numéro un est l'oubli des en-têtes `Upgrade`/`Connection` dans la config du proxy. Le
> deuxième est un `proxy_read_timeout` trop court : nginx ferme alors les connexions inactives au bout
> de quelques secondes. Mets un délai large et appuie-toi sur le *heartbeat* (chapitre 8).

## Garder le serveur en vie

`node server.js` lancé à la main s'arrête dès que tu fermes le terminal, et ne redémarre pas s'il plante.
En production, on confie ça à un **gestionnaire de processus** qui relance le serveur en cas de crash et
le démarre au boot de la machine. Deux options courantes :

- **systemd** (intégré à Linux) : on décrit le service dans un fichier `.service` ;
- **PM2** (`npm install -g pm2`) : un gestionnaire dédié à Node, simple à prendre en main.

```bash
# Avec PM2 :
pm2 start server.js --name chat
pm2 save            # sauvegarde la liste des processus
pm2 startup         # configure le démarrage automatique au boot
```

> **Astuce** — Quel que soit l'outil, l'essentiel est que le serveur **redémarre tout seul** après un
> crash ou un reboot. Et comme tes clients savent se reconnecter (chapitre 8) et rattraper les messages
> manqués (chapitre 9), un redémarrage serveur devient quasi invisible pour les utilisateurs.

## Le problème de la montée en charge

Une seule instance Node tient un certain nombre de connexions simultanées (quelques milliers à quelques
dizaines de milliers selon la machine et le travail par message). Au-delà, il faut **plusieurs
instances** — sur plusieurs cœurs ou plusieurs machines — avec un *load balancer* (répartiteur de
charge) qui distribue les clients entre elles.

Et là, un problème surgit. Souviens-toi : notre *broadcast* parcourt `wss.clients`, **les connexions de
cette instance**. Si Alice est connectée à l'instance A et Bob à l'instance B, le message d'Alice ne
sortira **jamais** de l'instance A. Bob ne le verra pas.

```text
Instance A : Alice, Charlie       Alice écrit  ─┐
Instance B : Bob, Diane                          ├─► reste sur A : Bob et Diane ne voient rien
                                                 └─►
```

Chaque instance ne connaît que **ses** connexions. Le *broadcast* « à tous » devient « à tous ceux de ma
machine » — ce qui casse le chat dès qu'on dépasse une instance.

> **À retenir** — C'est le piège classique du temps réel à l'échelle : un état (la liste des clients)
> qui était global sur une instance devient **local à chaque instance** quand on en ajoute. Tout
> mécanisme reposant sur « parcourir mes connexions » doit être repensé pour franchir les frontières
> d'instance.

## La solution : Redis pub/sub

Il faut un moyen pour les instances de **se parler**. La solution standard est un système de
**publication/abonnement** (*pub/sub*), et l'outil de référence est **Redis** : une base de données en
mémoire, très rapide, qui propose justement un mécanisme pub/sub.

Le principe :

- chaque instance **s'abonne** à un canal Redis (par exemple `chat`) ;
- quand une instance reçoit un message d'un de ses clients, au lieu de le diffuser seulement en local,
  elle le **publie** sur le canal Redis ;
- **toutes** les instances (y compris celle qui a publié) reçoivent ce message via Redis, et le
  diffusent alors à **leurs** clients locaux.

```text
Alice (A) écrit ─► A publie sur Redis ─┬─► A reçoit ─► diffuse à Alice, Charlie
                                        └─► B reçoit ─► diffuse à Bob, Diane
```

Ainsi, peu importe à quelle instance chacun est connecté : tout le monde voit le message. Voici le
squelette, avec la bibliothèque `redis` :

```js
import { createClient } from "redis";

// Deux connexions Redis : une pour publier, une pour s'abonner (Redis l'impose).
const publieur = createClient();
const abonne = publieur.duplicate();
await publieur.connect();
await abonne.connect();

// On s'abonne au canal "chat" : on reçoit ce que TOUTES les instances publient.
await abonne.subscribe("chat", (charge) => {
  const objet = JSON.parse(charge);
  // On diffuse à NOS clients locaux du bon salon.
  diffuserDansSalonLocal(objet.salon, objet);
});

// Quand un de nos clients envoie un message de chat :
function publierChat(objet) {
  // Au lieu de diffuser seulement en local, on publie pour toutes les instances.
  publieur.publish("chat", JSON.stringify(objet));
}
```

`diffuserDansSalonLocal` est exactement notre `diffuserDansSalon` des chapitres précédents (il diffuse
aux connexions **de cette instance**). Le changement de logique : on ne l'appelle plus directement à la
réception d'un message client — on **publie sur Redis**, et c'est l'abonnement qui déclenche la
diffusion locale **sur chaque instance**.

> **À retenir** — Avec pub/sub, chaque instance reste responsable de **ses** connexions locales, mais
> les messages **circulent entre instances** via Redis. C'est le motif fondamental de la mise à
> l'échelle du temps réel — on le retrouve derrière Socket.IO, les solutions managées, etc.

## Et la présence, à plusieurs instances ?

La présence (chapitre 7) souffre du même problème : chaque instance ne voit que ses clients locaux. À
l'échelle, on stocke la présence dans Redis (par exemple un *Set* Redis par salon) plutôt que dans la
mémoire d'une instance, pour que toutes partagent la même vue. C'est une extension naturelle du même
principe : **l'état partagé entre instances vit dans Redis**, pas dans la mémoire d'un seul processus.

On ne détaille pas l'implémentation complète ici — l'essentiel est de **reconnaître le problème** et de
savoir que la réponse (un magasin partagé comme Redis) est la même pour le *broadcast* et pour la
présence.

## Résumé

- En production, un **reverse proxy** (nginx) gère le **TLS** (`wss://`) et le routage ; il faut lui
  faire **transmettre les en-têtes `Upgrade`/`Connection`** et un **timeout large**.
- Un **gestionnaire de processus** (systemd, PM2) garde le serveur en vie et le redémarre ; la
  reconnexion client (chapitre 8) rend les redémarrages indolores.
- Avec **plusieurs instances**, le *broadcast* local casse : chaque instance ne connaît que **ses**
  connexions.
- **Redis pub/sub** fait communiquer les instances : on **publie** les messages sur un canal, **toutes**
  les instances les reçoivent et les diffusent à leurs clients locaux.
- L'**état partagé entre instances** (présence, etc.) doit vivre dans un magasin commun comme Redis,
  pas dans la mémoire d'un seul processus.

## Exercices

### Exercice 1 — Diagnostiquer un WebSocket qui ne passe pas le proxy

Tu déploies ton chat derrière nginx. La page se charge, mais la console du navigateur affiche que la
connexion WebSocket échoue immédiatement. Quelles sont les deux causes les plus probables, et comment les
vérifier ?

<details>
<summary>Voir le corrigé</summary>

**Démarche** : la page se charge (donc nginx et les fichiers statiques fonctionnent) mais le `/ws`
échoue : le problème est dans le *bloc* `location /ws`.

1. **En-têtes `Upgrade`/`Connection` manquants.** Sans
   `proxy_set_header Upgrade $http_upgrade;` et `proxy_set_header Connection "upgrade";` (plus
   `proxy_http_version 1.1;`), nginx ne transmet pas le *handshake* WebSocket. **Vérifier** : relire le
   bloc `location /ws`, recharger nginx (`nginx -s reload`).
2. **Mauvaise cible `proxy_pass` ou serveur Node éteint.** Si Node n'écoute pas sur le port indiqué
   (8080) ou n'est pas démarré, nginx ne peut rien transmettre. **Vérifier** : le processus tourne-t-il
   (`pm2 status` ou `systemctl status`) ? Le port correspond-il ?

Astuce de diagnostic : les **journaux de nginx** (`/var/log/nginx/error.log`) et la **console du
navigateur** indiquent en général lequel des deux est en cause.

</details>

### Exercice 2 — Tracer le trajet d'un message à deux instances

Alice est connectée à l'instance A, Bob à l'instance B, tous deux dans le salon `general`. Avec Redis
pub/sub en place, décris pas à pas ce qui se passe quand Alice envoie « bonjour », jusqu'à ce que Bob le
voie.

<details>
<summary>Voir le corrigé</summary>

**Démarche** : on suit le message d'Alice à travers l'instance A, Redis, puis l'instance B.

1. Le client d'Alice envoie `{ type: "chat", texte: "bonjour" }` à l'instance **A** (celle à laquelle il
   est connecté).
2. L'instance A valide, **persiste** le message (chapitre 9), puis **publie** sur le canal Redis `chat`
   l'objet complet (`{ type, salon: "general", pseudo: "Alice", texte, horodatage, id }`).
3. Redis transmet ce message à **toutes** les instances abonnées au canal : **A** et **B**.
4. L'instance A reçoit (via son abonnement) et diffuse à ses clients locaux du salon `general` : Alice
   (et les autres clients de A) voit son message.
5. L'instance B reçoit le même message et diffuse à ses clients locaux du salon `general` : **Bob le
   voit**.

Le point clé : A ne parle jamais directement à B. C'est **Redis** qui relaie, et chaque instance ne
s'occupe que de **ses** connexions. Ajouter une instance C ne change rien à ce schéma — elle s'abonne au
canal et reçoit tout comme les autres.

</details>

## Quiz

**1.** Quelles directives nginx sont indispensables pour relayer un WebSocket ?
- A. `gzip on` et `ssl_protocols`
- B. `proxy_http_version 1.1` et les en-têtes `Upgrade`/`Connection`
- C. `client_max_body_size`

**2.** Pourquoi un *broadcast* casse-t-il dès qu'on a plusieurs instances ?
- A. Parce que Redis est obligatoire pour tout WebSocket
- B. Parce que chaque instance ne connaît que ses propres connexions
- C. Parce que TLS bloque la diffusion

**3.** Quel rôle joue Redis pub/sub dans la montée en charge ?
- A. Il chiffre les connexions
- B. Il fait circuler les messages entre instances pour qu'elles diffusent toutes localement
- C. Il remplace le serveur Node

**4.** Où doit vivre la présence quand on a plusieurs instances ?
- A. Dans la mémoire de chaque instance, indépendamment
- B. Dans un magasin partagé comme Redis, vu par toutes les instances
- C. Dans le navigateur des clients

<details>
<summary>Voir les réponses</summary>

1. **B** — Sans `proxy_http_version 1.1` et les en-têtes `Upgrade`/`Connection`, le *handshake* ne passe
   pas.
2. **B** — Chaque instance ne diffuse qu'à ses connexions locales ; les clients des autres instances
   sont ignorés.
3. **B** — Redis relaie les messages entre instances, qui diffusent ensuite à leurs clients locaux.
4. **B** — L'état partagé entre instances doit vivre dans un magasin commun comme Redis.

</details>

## Projet fil rouge

Le chat est **déployable** : servi en `wss://` derrière nginx, maintenu en vie par un gestionnaire de
processus, et tu sais comment le faire **monter en charge** sur plusieurs instances grâce à Redis
pub/sub. Tu disposes d'une application temps réel complète, sécurisée et prête pour une mise en
production légère. Le dernier chapitre fait le bilan, dresse la liste des bonnes pratiques et t'indique
les pistes pour aller plus loin.

---

[← Chapitre précédent](10-securite.md) · [Sommaire](README.md) · [Chapitre suivant →](12-conclusion.md)
